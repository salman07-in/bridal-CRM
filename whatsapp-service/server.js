const express = require('express');
const { 
    default: makeWASocket, 
    useMultiFileAuthState, 
    DisconnectReason,
    Browsers,
    makeCacheableSignalKeyStore
} = require('@whiskeysockets/baileys');
const QRCode = require('qrcode');
const fs = require('fs');
const path = require('path');
require('dotenv').config();

const app = express();
app.use(express.json());

const PORT = process.env.PORT || 3000;
const AUTH_TOKEN = process.env.WHATSAPP_API_TOKEN || 'safiya_secret_token_123';
const AUTH_DIR = path.join(__dirname, 'auth_info_baileys');

let sock = null;
let currentQR = null;
let isConnected = false;
let connectedPhone = null;

// In-Memory message store to respond to WhatsApp E2EE retry requests
// Resolves "Waiting for this message. This may take a while."
const messageStore = new Map();

// Silent logger mock for Signal Key Store
const silentLogger = {
    trace: () => {},
    debug: () => {},
    info: () => {},
    warn: () => {},
    error: () => {},
    fatal: () => {},
    child: () => silentLogger
};

// Auth middleware
const verifyToken = (req, res, next) => {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];
    if (token !== AUTH_TOKEN) {
        return res.status(401).json({ sent: false, error: 'Unauthorized: Invalid token' });
    }
    next();
};

async function startWhatsApp() {
    try {
        const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);

        sock = makeWASocket({
            auth: {
                creds: state.creds,
                keys: makeCacheableSignalKeyStore(state.keys, silentLogger)
            },
            printQRInTerminal: false,
            browser: Browsers.macOS('Desktop'),
            syncFullHistory: false,
            generateHighQualityLinkPreview: true,
            // CRITICAL: getMessage handles decryption retry handshakes
            getMessage: async (key) => {
                if (key && key.id && messageStore.has(key.id)) {
                    return messageStore.get(key.id);
                }
                return {
                    conversation: "Safiya's Makeover Bridal CRM"
                };
            }
        });

        sock.ev.on('creds.update', saveCreds);

        // Store messages so decryption retries can be fulfilled
        sock.ev.on('messages.upsert', async ({ messages }) => {
            for (const m of messages) {
                if (m.key && m.key.id && m.message) {
                    messageStore.set(m.key.id, m.message);
                    if (messageStore.size > 2000) {
                        const oldestKey = messageStore.keys().next().value;
                        messageStore.delete(oldestKey);
                    }
                }
            }
        });

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                currentQR = qr;
            }

            if (connection === 'close') {
                isConnected = false;
                connectedPhone = null;
                const shouldReconnect = (lastDisconnect?.error)?.output?.statusCode !== DisconnectReason.loggedOut;
                console.log('Connection closed due to', lastDisconnect?.error, ', reconnecting:', shouldReconnect);
                if (shouldReconnect) {
                    setTimeout(startWhatsApp, 3000);
                }
            } else if (connection === 'open') {
                isConnected = true;
                currentQR = null;
                connectedPhone = sock.user?.id ? sock.user.id.split(':')[0] : 'Connected';
                console.log('✅ WhatsApp connection opened successfully as:', connectedPhone);
            }
        });
    } catch (err) {
        console.error('Fatal error initializing WhatsApp socket:', err);
        setTimeout(startWhatsApp, 5000);
    }
}

startWhatsApp();

// 1. Get Live QR Code as PNG image
app.get('/qr', async (req, res) => {
    if (isConnected) {
        return res.status(200).send('Already connected!');
    }
    if (!currentQR) {
        return res.status(503).send('QR is generating, please refresh in 2 seconds.');
    }
    try {
        const qrImage = await QRCode.toBuffer(currentQR);
        res.type('png').send(qrImage);
    } catch (err) {
        res.status(500).send('Error generating QR code');
    }
});

// 2. Status Check
app.get('/status', (req, res) => {
    res.json({
        connected: isConnected,
        phone: connectedPhone
    });
});

// 3. Reset Session Endpoint
app.post('/reset', verifyToken, async (req, res) => {
    try {
        if (sock) {
            try { await sock.logout(); } catch (e) {}
            sock = null;
        }
        isConnected = false;
        currentQR = null;
        connectedPhone = null;
        messageStore.clear();

        if (fs.existsSync(AUTH_DIR)) {
            fs.rmSync(AUTH_DIR, { recursive: true, force: true });
        }

        setTimeout(startWhatsApp, 1500);
        res.json({ success: true, message: 'Session reset successfully. Please refresh to scan new QR code.' });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// 4. Send Message Endpoint
app.post('/send', verifyToken, async (req, res) => {
    const { phone, text } = req.body;

    if (!isConnected || !sock) {
        return res.status(503).json({ sent: false, error: 'WhatsApp is not connected. Scan QR in Settings.' });
    }

    if (!phone || !text) {
        return res.status(400).json({ sent: false, error: 'Phone and text are required.' });
    }

    try {
        let cleanPhone = phone.replace(/[^\d]/g, '');
        if (cleanPhone.length === 10) cleanPhone = '91' + cleanPhone;
        const jid = `${cleanPhone}@s.whatsapp.net`;

        const sentResult = await sock.sendMessage(jid, { text: text });
        if (sentResult && sentResult.key && sentResult.key.id && sentResult.message) {
            messageStore.set(sentResult.key.id, sentResult.message);
        }

        res.json({ sent: true, phone: cleanPhone });
    } catch (err) {
        console.error('Error sending message:', err);
        res.status(500).json({ sent: false, error: err.message || 'Failed to send' });
    }
});

app.listen(PORT, () => {
    console.log(`WhatsApp Gateway listening on port ${PORT}`);
});

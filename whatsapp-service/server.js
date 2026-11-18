const express = require('express');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason } = require('@whiskeysockets/baileys');
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
    const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);

    sock = makeWASocket({
        auth: state,
        printQRInTerminal: true,
        browser: ["Safiya Makeover CRM", "Chrome", "1.0.0"]
    });

    sock.ev.on('creds.update', saveCreds);

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

// 3. Send Message Endpoint
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

        await sock.sendMessage(jid, { text: text });
        res.json({ sent: true, phone: cleanPhone });
    } catch (err) {
        console.error('Error sending message:', err);
        res.status(500).json({ sent: false, error: err.message || 'Failed to send' });
    }
});

app.listen(PORT, () => {
    console.log(`WhatsApp Gateway listening on port ${PORT}`);
});

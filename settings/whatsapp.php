<?php
/**
 * Safiya's Makeover — WhatsApp Web Connect & QR Scanner
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'WhatsApp Connect';
$apiUrl = getenv('WHATSAPP_API_URL') ?: 'https://safiyas-whatsapp.onrender.com';
$apiToken = getenv('WHATSAPP_API_TOKEN') ?: 'safiya_secret_token_123';

// Check current connection status via microservice
$statusData = ['connected' => false, 'phone' => null];
if ($apiUrl && $apiToken) {
    $ch = curl_init(rtrim($apiUrl, '/') . '/status');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiToken],
        CURLOPT_TIMEOUT => 4,
    ]);
    $res = curl_exec($ch);
    if (!curl_errno($ch)) {
        $json = json_decode($res, true);
        if ($json && isset($json['connected'])) {
            $statusData = $json;
        }
    }
    curl_close($ch);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card card-salon border-0 shadow-sm p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-stat-pink" style="background-color: #25D366; color: white;">
                        <i class="bi bi-whatsapp fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1">WhatsApp Web Connect</h4>
                        <p class="text-muted small mb-0">Link salon WhatsApp number to enable 1-click in-CRM message delivery.</p>
                    </div>
                </div>
                <div>
                    <?php if ($statusData['connected']): ?>
                        <span class="badge bg-success fs-6 px-3 py-2">
                            <i class="bi bi-check-circle-fill me-1"></i> Connected
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                            <i class="bi bi-exclamation-circle me-1"></i> Not Connected
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$apiUrl): ?>
                <div class="alert alert-warning border-0 mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Configuration Required:</strong> <code>WHATSAPP_API_URL</code> is not configured in <code>.env</code>. Please add your microservice URL to activate QR link.
                </div>
            <?php endif; ?>

            <div class="row align-items-center g-4">
                <div class="col-md-6 text-center border-end">
                    <div class="p-3 bg-light rounded-4 d-inline-block shadow-sm mb-3">
                        <?php if ($statusData['connected']): ?>
                            <div class="p-4 text-center">
                                <i class="bi bi-phone text-success" style="font-size: 4rem;"></i>
                                <h6 class="fw-bold mt-2 text-dark">Active Session</h6>
                                <p class="text-muted small font-monospace mb-0"><?= htmlspecialchars($statusData['phone'] ?: '+91 95195 06233') ?></p>
                            </div>
                        <?php elseif ($apiUrl): ?>
                            <img id="qrImage" src="<?= rtrim($apiUrl, '/') ?>/qr" alt="Scan WhatsApp QR" class="img-fluid rounded-3" style="max-width: 250px; min-height: 250px; background: white;" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=WhatsApp-Connecting-Please-Wait'">
                        <?php else: ?>
                            <div class="p-5 text-muted">
                                <i class="bi bi-qr-code fs-1"></i>
                                <div class="mt-2 small">QR Gateway Pending</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload();">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Status
                        </button>
                    </div>
                </div>

                <div class="col-md-6">
                    <h6 class="fw-bold mb-3"><i class="bi bi-phone me-1 text-salon"></i> How to link your phone:</h6>
                    
                    <div class="p-2 mb-3 bg-light rounded-2 small border">
                        <div class="fw-semibold text-dark mb-1">Registered Studio WhatsApp Numbers:</div>
                        <div><i class="bi bi-whatsapp text-success me-1"></i> <strong>Number 1 (Primary):</strong> +91 95195 06233</div>
                        <div><i class="bi bi-whatsapp text-success me-1"></i> <strong>Number 2 (Secondary):</strong> +91 86046 59368</div>
                    </div>

                    <ol class="small text-secondary ps-3 mb-4" style="line-height: 1.8;">
                        <li>Open WhatsApp on either salon phone (<strong>+91 95195 06233</strong> or <strong>+91 86046 59368</strong>).</li>
                        <li>Tap <strong>Menu (⋮)</strong> on Android or <strong>Settings</strong> on iPhone.</li>
                        <li>Select <strong>Linked Devices</strong>, then tap <strong>Link a Device</strong>.</li>
                        <li>Point your camera at the QR code on the left to scan.</li>
                        <li>Once scanned, the badge above will turn green (<span class="text-success fw-semibold">Connected</span>).</li>
                    </ol>

                    <div class="p-3 bg-light rounded-3 small text-muted">
                        <i class="bi bi-shield-lock-fill text-success me-1"></i>
                        <strong>Secure Session:</strong> Messages are sent directly from your linked device without 3rd-party access to personal chats.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

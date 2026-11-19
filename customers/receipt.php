<?php
/**
 * Safiya's Makeover — Luxury Digital Booking Receipt / Slip
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM customers WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash('error', 'Customer record not found.');
    header("Location: index.php");
    exit;
}

$balance = max(0, (float)$customer['amount'] - (float)$customer['advance_amount']);
$salon_name = get_setting('salon_name', "Safiya's Makeover");
$salon_tagline = get_setting('salon_tagline', "Luxury Bridal & Makeover Studio");
$salon_branch = get_setting('salon_branch', "Main Branch");
$salon_phone = get_setting('salon_phone', "+91 95195 06233");
$salon_phone_2 = get_setting('salon_phone_2', "+91 86046 59368");

// Format structured WhatsApp Receipt Message
$receipt_msg = "✨ *{$salon_name}* — Official Booking Receipt ✨\n\n";
$receipt_msg .= "📋 *Booking Code:* {$customer['customer_code']}\n";
if (!empty($customer['slip_number'])) {
    $receipt_msg .= "🏷️ *Slip #:* {$customer['slip_number']}\n";
}
$receipt_msg .= "👰 *Bride Name:* {$customer['full_name']}\n";
$receipt_msg .= "💄 *Package:* " . ($customer['service_package'] ?: 'Bridal Package') . "\n";
if (!empty($customer['makeup_date'])) {
    $receipt_msg .= "📅 *Makeup Date:* " . format_date_in($customer['makeup_date']) . "\n";
}
if (!empty($customer['wedding_date'])) {
    $receipt_msg .= "💍 *Wedding Date:* " . format_date_in($customer['wedding_date']) . "\n";
}
$receipt_msg .= "\n💰 *Total Amount:* " . format_inr($customer['amount']) . "\n";
$receipt_msg .= "✅ *Advance Paid:* " . format_inr($customer['advance_amount']) . "\n";
if ($balance > 0) {
    $receipt_msg .= "⏳ *Balance Due:* " . format_inr($balance) . "\n";
} else {
    $receipt_msg .= "🎉 *Payment Status:* Fully Paid\n";
}
$receipt_msg .= "\n📍 *Branch:* {$salon_branch}\n";
$receipt_msg .= "📞 *Contact:* {$salon_phone} | {$salon_phone_2}\n\n";
$receipt_msg .= "Thank you for choosing {$salon_name}! We look forward to creating your dream bridal look. 💖";

$wa_url = build_whatsapp_url($customer['mobile_number'], $receipt_msg);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Slip #<?= htmlspecialchars($customer['customer_code']) ?> — <?= htmlspecialchars($salon_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; color: #1E293B; padding: 30px 15px; }
        .receipt-card { max-width: 680px; margin: auto; background: white; border-radius: 16px; border: 1px solid #E2E8F0; box-shadow: 0 10px 30px rgba(0,0,0,0.06); padding: 40px; position: relative; }
        .receipt-header { border-bottom: 2px dashed #CBD5E1; padding-bottom: 24px; margin-bottom: 24px; }
        .brand-title { font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 700; color: #D4738C; letter-spacing: -0.5px; }
        .section-label { font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94A3B8; letter-spacing: 0.5px; }
        .receipt-table { width: 100%; margin-top: 15px; }
        .receipt-table th { background: #F8FAFC; padding: 10px 12px; font-size: 13px; color: #64748B; border-bottom: 1px solid #E2E8F0; }
        .receipt-table td { padding: 14px 12px; font-size: 14px; border-bottom: 1px solid #F1F5F9; }
        .total-box { background: #FDF5F7; border: 1px solid #F3D5DE; border-radius: 12px; padding: 20px; margin-top: 25px; }
        .btn-rose { background: #D4738C; color: white; border-radius: 8px; font-weight: 600; }
        .btn-rose:hover { background: #BF5771; color: white; }
        @media print {
            body { background: white; padding: 0; }
            .receipt-card { box-shadow: none; border: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Action Bar (Hidden in Print) -->
    <div class="no-print d-flex justify-content-between align-items-center mb-4 max-w-680 mx-auto" style="max-width: 680px;">
        <a href="view.php?id=<?= $customer['id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Profile
        </a>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm send-wa px-3" data-phone="<?= htmlspecialchars($customer['mobile_number']) ?>" data-msg="<?= htmlspecialchars($receipt_msg) ?>" data-customer-id="<?= $customer['id'] ?>">
                <i class="bi bi-send-fill me-1"></i> Send via Linked WhatsApp
            </button>
            <a href="<?= $wa_url ?>" target="_blank" class="btn btn-outline-success btn-sm px-3">
                <i class="bi bi-whatsapp me-1"></i> Open in WhatsApp App
            </a>
            <button onclick="window.print();" class="btn btn-dark btn-sm px-3">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
        </div>
    </div>

    <!-- Luxury Receipt Container -->
    <div class="receipt-card">
        <!-- Studio Brand Header -->
        <div class="receipt-header text-center">
            <div class="brand-title"><?= htmlspecialchars($salon_name) ?></div>
            <div class="text-muted small mt-1"><?= htmlspecialchars($salon_tagline) ?></div>
            <div class="small text-secondary mt-2">
                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($salon_branch) ?> &bull; 
                <i class="bi bi-telephone ms-2 me-1"></i><?= htmlspecialchars($salon_phone) ?>
                <?php if ($salon_phone_2): ?> &bull; <?= htmlspecialchars($salon_phone_2) ?><?php endif; ?>
            </div>
            <div class="badge bg-light text-dark border mt-3 px-3 py-1 font-monospace" style="font-size: 0.85rem;">
                OFFICIAL BOOKING CONFIRMATION
            </div>
        </div>

        <!-- Receipt Meta Information -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="section-label">Bride Full Name</div>
                <div class="h5 fw-bold mb-0 text-dark"><?= htmlspecialchars($customer['full_name']) ?></div>
                <div class="small text-muted font-monospace mt-1"><?= htmlspecialchars($customer['mobile_number']) ?></div>
            </div>
            <div class="col-6 text-end">
                <div class="section-label">Booking Reference</div>
                <div class="fw-bold font-monospace text-dark"><?= htmlspecialchars($customer['customer_code']) ?></div>
                <?php if ($customer['slip_number']): ?>
                    <span class="badge bg-secondary-subtle text-secondary font-monospace mt-1">Slip #<?= htmlspecialchars($customer['slip_number']) ?></span>
                <?php endif; ?>
                <div class="small text-muted mt-1">Date: <?= date('d M Y') ?></div>
            </div>
        </div>

        <!-- Event Dates Summary -->
        <div class="row g-2 mb-4 p-3 bg-light rounded-3">
            <div class="col-sm-6">
                <span class="small text-muted d-block">Makeup Appointment Date:</span>
                <strong class="text-dark"><i class="bi bi-calendar2-check text-success me-1"></i> <?= format_date_in($customer['makeup_date']) ?></strong>
            </div>
            <div class="col-sm-6">
                <span class="small text-muted d-block">Wedding Date:</span>
                <strong class="text-dark"><i class="bi bi-heart-fill text-danger me-1"></i> <?= format_date_in($customer['wedding_date']) ?></strong>
            </div>
        </div>

        <!-- Service & Payment Table -->
        <table class="receipt-table">
            <thead>
                <tr>
                    <th>Service Description</th>
                    <th>Booking Type</th>
                    <th class="text-end">Package Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong class="text-dark"><?= htmlspecialchars($customer['service_package'] ?: 'Bridal Package') ?></strong>
                        <?php if ($customer['notes']): ?>
                            <div class="small text-muted mt-1"><?= htmlspecialchars($customer['notes']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($customer['booking_type']) ?></span></td>
                    <td class="text-end fw-bold font-monospace"><?= format_inr($customer['amount']) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Totals & Balances -->
        <div class="total-box">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Total Package Cost:</span>
                <span class="fw-bold font-monospace"><?= format_inr($customer['amount']) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                <span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Advance Deposit Paid:</span>
                <span class="fw-bold font-monospace text-success"><?= format_inr($customer['advance_amount']) ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center pt-1">
                <div>
                    <span class="h6 fw-bold mb-0 text-dark">Remaining Balance Due:</span>
                    <div class="small text-muted">Payable on appointment date</div>
                </div>
                <div>
                    <?php if ($balance > 0): ?>
                        <span class="h5 fw-bold font-monospace text-danger mb-0"><?= format_inr($balance) ?></span>
                    <?php else: ?>
                        <span class="badge bg-success fs-6 px-3 py-2">✓ Fully Paid</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Terms & Thank You -->
        <div class="text-center mt-4 pt-3 text-muted small" style="font-size: 0.78rem;">
            <p class="mb-1">✨ Please arrive on time for your scheduled appointment. Advance deposits are non-refundable.</p>
            <p class="mb-0 fw-semibold text-secondary">Thank you for making Safiya's Makeover a part of your special day! 💖</p>
        </div>
    </div>

    <!-- Application JS for Instant WhatsApp Send -->
    <script>window.APP_ROOT = '../';</script>
    <script src="../assets/js/whatsapp_send.js"></script>
</body>
</html>

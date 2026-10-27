<?php
/**
 * Safiya's Makeover — Edit Bride Record
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM customers WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash('error', 'Customer not found.');
    header("Location: index.php");
    exit;
}

$page_title = 'Edit ' . $customer['full_name'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $mobile_number = clean_indian_phone(trim($_POST['mobile_number'] ?? ''));
    $wedding_date = !empty($_POST['wedding_date']) ? $_POST['wedding_date'] : null;
    $makeup_date = !empty($_POST['makeup_date']) ? $_POST['makeup_date'] : null;
    $service_package = trim($_POST['service_package'] ?? '');
    $amount = !empty($_POST['amount']) ? (float)$_POST['amount'] : null;
    $advance_amount = !empty($_POST['advance_amount']) ? (float)$_POST['advance_amount'] : 0.00;
    $slip_number = trim($_POST['slip_number'] ?? '');
    $booking_type = trim($_POST['booking_type'] ?? 'Bridal Makeup');
    $salon_branch = trim($_POST['salon_branch'] ?? 'Main Branch');
    $notes = trim($_POST['notes'] ?? '');
    $whatsapp_consent = isset($_POST['whatsapp_consent']) ? 1 : 0;
    $pre_makeup_reminder_enabled = isset($_POST['pre_makeup_reminder_enabled']) ? 1 : 0;

    if (empty($full_name)) {
        $error = 'Bride full name is required.';
    } elseif (empty($mobile_number)) {
        $error = 'Valid mobile number is required.';
    } else {
        $up_stmt = $db->prepare("UPDATE customers SET 
            full_name = :name, 
            mobile_number = :mobile, 
            wedding_date = :wed, 
            makeup_date = :mkp, 
            service_package = :pkg, 
            amount = :amt, 
            advance_amount = :adv, 
            slip_number = :slip, 
            booking_type = :btype, 
            salon_branch = :branch, 
            notes = :notes, 
            whatsapp_consent = :consent, 
            pre_makeup_reminder_enabled = :rem_pre 
            WHERE id = :id");

        try {
            $up_stmt->execute([
                'name' => $full_name,
                'mobile' => $mobile_number,
                'wed' => $wedding_date,
                'mkp' => $makeup_date,
                'pkg' => $service_package,
                'amt' => $amount,
                'adv' => $advance_amount,
                'slip' => $slip_number,
                'btype' => $booking_type,
                'branch' => $salon_branch,
                'notes' => $notes,
                'consent' => $whatsapp_consent,
                'rem_pre' => $pre_makeup_reminder_enabled,
                'id' => $id
            ]);

            log_audit('update', 'customer', $id, ['name' => $full_name]);
            set_flash('success', "Bride {$full_name} details updated successfully.");
            header("Location: view.php?id={$id}");
            exit;
        } catch (PDOException $e) {
            $error = 'Error updating record: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1">Edit Bride Details</h2>
        <p class="text-muted small mb-0">Updating profile for <strong><?= htmlspecialchars($customer['full_name']) ?></strong> (<?= htmlspecialchars($customer['customer_code']) ?>)</p>
    </div>
    <a href="view.php?id=<?= $customer['id'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Profile
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card card-salon border-0 shadow-sm col-lg-9 mx-auto">
    <div class="card-body p-4">
        <form method="POST" action="edit.php?id=<?= $customer['id'] ?>">
            <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">1. Personal &amp; Contact Information</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-7">
                    <label class="form-label small fw-semibold">Bride Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? $customer['full_name']) ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                    <input type="tel" name="mobile_number" class="form-control font-monospace" value="<?= htmlspecialchars($_POST['mobile_number'] ?? $customer['mobile_number']) ?>" required>
                </div>
            </div>

            <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">2. Critical Dates</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">
                        Wedding Date <span class="badge bg-danger-subtle text-danger ms-1">Sole Anniversary Trigger</span>
                    </label>
                    <input type="date" name="wedding_date" class="form-control" value="<?= htmlspecialchars($_POST['wedding_date'] ?? $customer['wedding_date'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">
                        Makeup Appointment Date <span class="badge bg-secondary-subtle text-secondary ms-1">Service Date</span>
                    </label>
                    <input type="date" name="makeup_date" class="form-control" value="<?= htmlspecialchars($_POST['makeup_date'] ?? $customer['makeup_date'] ?? '') ?>">
                </div>
            </div>

            <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">3. Package &amp; Billing</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Service Package</label>
                    <input type="text" name="service_package" class="form-control" value="<?= htmlspecialchars($_POST['service_package'] ?? $customer['service_package'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Total Package (₹)</label>
                    <input type="number" step="0.01" name="amount" class="form-control font-monospace" value="<?= htmlspecialchars($_POST['amount'] ?? $customer['amount'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Advance Received (₹)</label>
                    <input type="number" step="0.01" name="advance_amount" class="form-control font-monospace" value="<?= htmlspecialchars($_POST['advance_amount'] ?? $customer['advance_amount'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Slip / Register #</label>
                    <input type="text" name="slip_number" class="form-control font-monospace" value="<?= htmlspecialchars($_POST['slip_number'] ?? $customer['slip_number'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Booking Type</label>
                    <select name="booking_type" class="form-select">
                        <option value="Bridal Makeup" <?= ($customer['booking_type'] === 'Bridal Makeup') ? 'selected' : '' ?>>Bridal Makeup</option>
                        <option value="Engagement Makeup" <?= ($customer['booking_type'] === 'Engagement Makeup') ? 'selected' : '' ?>>Engagement Makeup</option>
                        <option value="Reception" <?= ($customer['booking_type'] === 'Reception') ? 'selected' : '' ?>>Reception</option>
                        <option value="Party Makeup" <?= ($customer['booking_type'] === 'Party Makeup') ? 'selected' : '' ?>>Party Makeup</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Salon Branch</label>
                    <input type="text" name="salon_branch" class="form-control" value="<?= htmlspecialchars($_POST['salon_branch'] ?? $customer['salon_branch'] ?? '') ?>">
                </div>
            </div>

            <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">4. Reminders &amp; Consent</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="whatsapp_consent" id="chkConsent" <?= $customer['whatsapp_consent'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="chkConsent">WhatsApp Wishes Consent</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="pre_makeup_reminder_enabled" id="chkPre" <?= $customer['pre_makeup_reminder_enabled'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="chkPre">1-Day Pre-Makeup Appointment Alert</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Notes &amp; Preferences</label>
                    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($_POST['notes'] ?? $customer['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <a href="view.php?id=<?= $customer['id'] ?>" class="btn btn-light border px-4">Cancel</a>
                <button type="submit" class="btn btn-salon-primary px-5">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

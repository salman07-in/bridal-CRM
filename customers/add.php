<?php
/**
 * Safiya's Makeover — Add New Bride
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Add New Bride';
$db = get_db();
$error = '';
$default_branch = get_setting('salon_branch', 'Main Branch');

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
    $salon_branch = trim($_POST['salon_branch'] ?? $default_branch);
    $notes = trim($_POST['notes'] ?? '');
    $whatsapp_consent = isset($_POST['whatsapp_consent']) ? 1 : 0;
    $pre_makeup_reminder_enabled = isset($_POST['pre_makeup_reminder_enabled']) ? 1 : 0;

    if (empty($full_name)) {
        $error = 'Bride full name is required.';
    } elseif (empty($mobile_number)) {
        $error = 'Valid mobile number is required.';
    } else {
        $customer_code = generate_customer_code();

        $stmt = $db->prepare("INSERT INTO customers (
            customer_code, full_name, mobile_number, wedding_date, makeup_date, 
            service_package, amount, advance_amount, slip_number, booking_type, 
            salon_branch, notes, whatsapp_consent, pre_makeup_reminder_enabled
        ) VALUES (
            :code, :name, :mobile, :wed, :mkp, 
            :pkg, :amt, :adv, :slip, :btype, 
            :branch, :notes, :consent, :rem_pre
        )");

        try {
            $stmt->execute([
                'code' => $customer_code,
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
                'rem_pre' => $pre_makeup_reminder_enabled
            ]);

            $new_id = (int)$db->lastInsertId();
            log_audit('create', 'customer', $new_id, ['customer_code' => $customer_code, 'name' => $full_name]);
            set_flash('success', "Bride {$full_name} ({$customer_code}) successfully added to register!");
            header("Location: view.php?id={$new_id}");
            exit;
        } catch (PDOException $e) {
            $error = 'Error saving customer: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1">Add New Bride</h2>
        <p class="text-muted small mb-0">Record a new bridal register entry with anniversary tracking</p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Register
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form Column -->
    <div class="col-lg-8">
        <div class="card card-salon border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="add.php" id="addBrideForm" autocomplete="off">
                    <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">1. Personal &amp; Contact Details</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold">Bride Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="inputFullName" class="form-control" placeholder="e.g. Sana Mirza" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required autofocus>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">+91</span>
                                <input type="tel" name="mobile_number" id="inputMobile" class="form-control" placeholder="9876543210" value="<?= htmlspecialchars($_POST['mobile_number'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">2. Critical Dates (Strict Separation)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">
                                Wedding Date <span class="badge bg-danger-subtle text-danger ms-1">Sole Anniversary Trigger</span>
                            </label>
                            <input type="date" name="wedding_date" id="inputWeddingDate" class="form-control" value="<?= htmlspecialchars($_POST['wedding_date'] ?? '') ?>">
                            <small class="text-muted d-block mt-1">Leaves empty if unknown. System will flag for review.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">
                                Makeup Date <span class="badge bg-secondary-subtle text-secondary ms-1">Operational Service Date</span>
                            </label>
                            <input type="date" name="makeup_date" id="inputMakeupDate" class="form-control" value="<?= htmlspecialchars($_POST['makeup_date'] ?? '') ?>">
                            <small class="text-muted d-block mt-1">Appointment date for bridal service at the salon.</small>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">3. Package, Financials &amp; Slip Details</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Service Package</label>
                            <input type="text" name="service_package" id="inputPackage" class="form-control" placeholder="e.g. Signature HD Bridal Glow" value="<?= htmlspecialchars($_POST['service_package'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Total Amount (₹)</label>
                            <input type="number" step="0.01" name="amount" id="inputAmount" class="form-control font-monospace" placeholder="25000" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Advance Paid (₹)</label>
                            <input type="number" step="0.01" name="advance_amount" id="inputAdvance" class="form-control font-monospace" placeholder="5000" value="<?= htmlspecialchars($_POST['advance_amount'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Slip / Register #</label>
                            <input type="text" name="slip_number" class="form-control font-monospace" placeholder="SLIP-2024-01" value="<?= htmlspecialchars($_POST['slip_number'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Booking Type</label>
                            <select name="booking_type" class="form-select">
                                <option value="Bridal Makeup">Bridal Makeup</option>
                                <option value="Engagement Makeup">Engagement Makeup</option>
                                <option value="Reception">Reception</option>
                                <option value="Party Makeup">Party Makeup</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Studio Branch</label>
                            <input type="text" name="salon_branch" class="form-control" value="<?= htmlspecialchars($_POST['salon_branch'] ?? $default_branch) ?>">
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">4. Reminders &amp; Consent</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="whatsapp_consent" id="chkConsent" checked>
                                <label class="form-check-label fw-semibold" for="chkConsent">
                                    <i class="bi bi-whatsapp text-success me-1"></i> WhatsApp Wishes Consent
                                </label>
                                <small class="text-muted d-block">Client consented to receive annual anniversary greetings.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="pre_makeup_reminder_enabled" id="chkPreMakeup" checked>
                                <label class="form-check-label fw-semibold" for="chkPreMakeup">
                                    <i class="bi bi-bell text-salon me-1"></i> 1-Day Pre-Makeup Appointment Alert
                                </label>
                                <small class="text-muted d-block">Notify client 1 day prior to their makeup appointment date.</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Client Preferences / Special Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Skin tones, hairstyle choices, floral preferences, groom name..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="index.php" class="btn btn-light border px-4">Cancel</a>
                        <button type="submit" class="btn btn-salon-primary px-5">
                            <i class="bi bi-check-lg me-1"></i> Save Bride Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Live Preview Card Column -->
    <div class="col-lg-4">
        <div class="sticky-top" style="top: 85px;">
            <div class="text-uppercase text-muted small fw-bold mb-2">Live Register Preview Card</div>
            <div class="card card-salon border-0 shadow-sm p-4 text-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 fw-bold text-white shadow-sm" id="previewAvatar" style="width: 68px; height: 68px; font-size: 1.5rem; background-color: #D4738C;">
                    B
                </div>
                <h4 class="fw-bold mb-1 text-dark" id="previewName">Bride Name</h4>
                <div class="text-muted small font-monospace mb-3" id="previewMobile">+91 ••••• •••••</div>

                <div class="border rounded-3 p-3 bg-light text-start mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="small text-muted">Wedding Date:</span>
                        <span class="small fw-bold text-dark" id="previewWedDate">—</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="small text-muted">Makeup Date:</span>
                        <span class="small fw-semibold text-dark" id="previewMkpDate">—</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="small text-muted">Package:</span>
                        <span class="small fw-semibold text-dark" id="previewPkg">—</span>
                    </div>
                    <div class="d-flex justify-content-between pt-2 border-top">
                        <span class="small text-muted">Balance Due:</span>
                        <span class="small fw-bold text-danger font-monospace" id="previewBalance">₹ 0.00</span>
                    </div>
                </div>

                <div class="p-2 border rounded-2" style="background-color: #FDF5F7; border-color: #F3D5DE !important;">
                    <i class="bi bi-gift text-salon me-1"></i>
                    <small class="text-muted" id="previewAnniversaryHint">Enter wedding date to see upcoming anniversary milestone.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const nameInput = document.getElementById("inputFullName");
    const mobileInput = document.getElementById("inputMobile");
    const wedInput = document.getElementById("inputWeddingDate");
    const mkpInput = document.getElementById("inputMakeupDate");
    const pkgInput = document.getElementById("inputPackage");
    const amtInput = document.getElementById("inputAmount");
    const advInput = document.getElementById("inputAdvance");

    const prevAvatar = document.getElementById("previewAvatar");
    const prevName = document.getElementById("previewName");
    const prevMobile = document.getElementById("previewMobile");
    const prevWedDate = document.getElementById("previewWedDate");
    const prevMkpDate = document.getElementById("previewMkpDate");
    const prevPkg = document.getElementById("previewPkg");
    const prevBalance = document.getElementById("previewBalance");
    const prevAnni = document.getElementById("previewAnniversaryHint");

    function updatePreview() {
        const name = nameInput.value.trim() || "Bride Name";
        prevName.textContent = name;
        prevAvatar.textContent = name.charAt(0).toUpperCase() || "B";

        const mobile = mobileInput.value.trim();
        prevMobile.textContent = mobile ? "+91 " + mobile : "+91 ••••• •••••";

        prevWedDate.textContent = wedInput.value ? wedInput.value : "—";
        prevMkpDate.textContent = mkpInput.value ? mkpInput.value : "—";
        prevPkg.textContent = pkgInput.value.trim() ? pkgInput.value.trim() : "—";

        const total = parseFloat(amtInput.value) || 0;
        const adv = parseFloat(advInput.value) || 0;
        const bal = Math.max(0, total - adv);
        prevBalance.textContent = "₹ " + bal.toLocaleString("en-IN", { minimumFractionDigits: 2 });

        if (wedInput.value) {
            const yr = new Date(wedInput.value).getFullYear();
            const currYr = new Date().getFullYear();
            const diff = currYr - yr;
            if (diff >= 0) {
                prevAnni.innerHTML = "Annual anniversary celebrated every <strong>" + wedInput.value.substring(5) + "</strong>";
            }
        } else {
            prevAnni.textContent = "Enter wedding date to see upcoming anniversary milestone.";
        }
    }

    [nameInput, mobileInput, wedInput, mkpInput, pkgInput, amtInput, advInput].forEach(el => {
        el.addEventListener("input", updatePreview);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

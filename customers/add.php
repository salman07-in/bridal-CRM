<?php
/**
 * Safiya's Makeover — Add New Bride (Fast & Streamlined)
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Quick Add Bride';
$db = get_db();
$error = '';
$default_branch = get_setting('salon_branch', 'Main Branch');

// Curated Package Catalog with preset prices
$packages = [
    'Bridal HD Makeup' => [
        'Bridal HD - Kryolan' => 8000,
        'Bridal HD - Forever 52' => 9500,
        'Bridal HD - Charmacy Milano' => 10000,
        'Bridal HD - P.A.C' => 11000,
        'Bridal HD - M.A.C' => 12000,
        'Bridal HD - Huda Beauty' => 13500,
        'Bridal HD - Makeup Studio' => 15000,
        'Bridal HD - NARS' => 17500,
        'Bridal HD - Bobbi Brown' => 20000,
    ],
    'Bridal Airbrush Makeup' => [
        'Bridal Airbrush - Kryolan' => 10000,
        'Bridal Airbrush - Forever 52' => 11000,
        'Bridal Airbrush - Charmacy Milano' => 12000,
        'Bridal Airbrush - P.A.C' => 12000,
        'Bridal Airbrush - M.A.C' => 13500,
        'Bridal Airbrush - Huda Beauty' => 15000,
        'Bridal Airbrush - Makeup Studio' => 16500,
        'Bridal Airbrush - NARS' => 20000,
        'Bridal Airbrush - Bobbi Brown' => 25000,
    ],
    'Engagement Makeup' => [
        'Engagement HD - Kryolan' => 5000,
        'Engagement HD - Forever 52' => 6000,
        'Engagement HD - M.A.C' => 6500,
        'Engagement HD - Huda Beauty' => 7000,
        'Engagement Airbrush - Kryolan' => 7000,
        'Engagement Airbrush - M.A.C' => 8500,
        'Engagement Airbrush - Huda Beauty' => 9000,
    ],
    'Party Makeup' => [
        'Party Normal' => 2000,
        'Party HD' => 3000,
        'Party Airbrush' => 4000,
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $mobile_number = clean_indian_phone(trim($_POST['mobile_number'] ?? ''));
    $wedding_date = !empty($_POST['wedding_date']) ? $_POST['wedding_date'] : null;
    $makeup_date = !empty($_POST['makeup_date']) ? $_POST['makeup_date'] : null;
    
    // Package can be from select or custom text
    $selected_pkg = trim($_POST['service_package_select'] ?? '');
    $custom_pkg = trim($_POST['service_package_custom'] ?? '');
    $service_package = ($selected_pkg === 'custom' || empty($selected_pkg)) ? $custom_pkg : $selected_pkg;

    $amount = !empty($_POST['amount']) ? (float)$_POST['amount'] : null;
    $advance_amount = !empty($_POST['advance_amount']) ? (float)$_POST['advance_amount'] : 0.00;
    $slip_number = trim($_POST['slip_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Auto-detect booking type based on package
    $booking_type = 'Bridal Makeup';
    if (stripos($service_package, 'Engagement') !== false) {
        $booking_type = 'Engagement Makeup';
    } elseif (stripos($service_package, 'Party') !== false) {
        $booking_type = 'Party Makeup';
    } elseif (stripos($service_package, 'Reception') !== false) {
        $booking_type = 'Reception';
    }

    $salon_branch = trim($_POST['salon_branch'] ?? $default_branch);
    if (empty($salon_branch)) $salon_branch = $default_branch;

    // Consent and reminder defaults (auto enabled)
    $whatsapp_consent = isset($_POST['whatsapp_consent']) ? (int)$_POST['whatsapp_consent'] : 1;
    $pre_makeup_reminder_enabled = isset($_POST['pre_makeup_reminder_enabled']) ? (int)$_POST['pre_makeup_reminder_enabled'] : 1;

    if (empty($full_name)) {
        $error = 'Bride full name is required.';
    } elseif (empty($mobile_number)) {
        $error = 'Valid 10-digit mobile number is required.';
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
            set_flash('success', "Bride {$full_name} ({$customer_code}) successfully registered!");
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
        <p class="text-muted small mb-0">Quick &amp; easy registration — automated calculations and reminder triggers</p>
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
    <!-- Streamlined Form Column -->
    <div class="col-lg-8">
        <div class="card card-salon border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="add.php" id="addBrideForm" autocomplete="off">
                    
                    <!-- 1. Bride Identity -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold">Bride Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="inputFullName" class="form-control form-control-lg" placeholder="e.g. Ayesha Khan" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required autofocus>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Mobile Number (WhatsApp) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light text-muted small">+91</span>
                                <input type="tel" name="mobile_number" id="inputMobile" class="form-control font-monospace" placeholder="9876543210" maxlength="15" value="<?= htmlspecialchars($_POST['mobile_number'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Dates (Wedding Date & Makeup Date) -->
                    <div class="card p-3 mb-4 border-0" style="background-color: #FDF9FA; border-radius: 10px;">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold d-flex justify-content-between align-items-center">
                                    <span>💍 Wedding Date <small class="text-danger fw-normal">(Anniversary Trigger)</small></span>
                                </label>
                                <input type="date" name="wedding_date" id="inputWeddingDate" class="form-control" value="<?= htmlspecialchars($_POST['wedding_date'] ?? '') ?>">
                                <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Annual anniversary wishes are scheduled from this date.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold d-flex justify-content-between align-items-center">
                                    <span>💄 Makeup / Appointment Date</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btnCopyDate" style="font-size: 0.75rem; color: #D4738C;">
                                        <i class="bi bi-copy me-1"></i>Same as Wedding Date
                                    </button>
                                </label>
                                <input type="date" name="makeup_date" id="inputMakeupDate" class="form-control" value="<?= htmlspecialchars($_POST['makeup_date'] ?? '') ?>">
                                <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Day of makeup appointment at studio.</small>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Package & Automatic Price Calculation -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Service Package</label>
                            <select name="service_package_select" id="selectPackage" class="form-select">
                                <option value="">-- Choose Package (Auto-Fills Price) --</option>
                                <?php foreach ($packages as $category => $items): ?>
                                    <optgroup label="<?= htmlspecialchars($category) ?>">
                                        <?php foreach ($items as $pkg_name => $price): ?>
                                            <option value="<?= htmlspecialchars($pkg_name) ?>" data-price="<?= $price ?>">
                                                <?= htmlspecialchars($pkg_name) ?> (₹<?= number_format($price) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                                <option value="custom">✏️ Other / Custom Package...</option>
                            </select>
                            
                            <!-- Custom package text box, shown only if 'custom' is picked -->
                            <div id="customPackageWrapper" class="mt-2" style="display: none;">
                                <input type="text" name="service_package_custom" id="inputCustomPackage" class="form-control" placeholder="Type custom service package name...">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Total Amount (₹)</label>
                            <input type="number" step="0.01" name="amount" id="inputAmount" class="form-control font-monospace" placeholder="0.00" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Advance Paid (₹)</label>
                            <input type="number" step="0.01" name="advance_amount" id="inputAdvance" class="form-control font-monospace" placeholder="0.00" value="<?= htmlspecialchars($_POST['advance_amount'] ?? '0') ?>">
                        </div>

                        <!-- Live Balance Due Alert -->
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center p-2 px-3 rounded-2 bg-light border">
                                <span class="small fw-semibold text-secondary">
                                    <i class="bi bi-calculator me-1"></i> Calculated Balance:
                                </span>
                                <span class="badge font-monospace fs-6 px-3 py-1" id="badgeBalanceStatus">
                                    ₹ 0.00 Due
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Optional Extra Fields (Cleanly Collapsible) -->
                    <div class="accordion mb-4" id="optionalAccordion">
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-2 px-3 bg-light rounded text-secondary small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOptional">
                                    <i class="bi bi-sliders me-2 text-salon"></i> Optional Details (Receipt #, Notes, Branch)
                                </button>
                            </h2>
                            <div id="collapseOptional" class="accordion-collapse collapse" data-bs-parent="#optionalAccordion">
                                <div class="accordion-body pt-3 pb-0 px-1">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Receipt / Slip #</label>
                                            <input type="text" name="slip_number" class="form-control font-monospace" placeholder="e.g. SLIP-1042" value="<?= htmlspecialchars($_POST['slip_number'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Studio Branch</label>
                                            <input type="text" name="salon_branch" class="form-control" value="<?= htmlspecialchars($_POST['salon_branch'] ?? $default_branch) ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold">Client Preferences / Special Notes</label>
                                            <textarea name="notes" class="form-control" rows="2" placeholder="Skin tones, hairstyle choices, floral preferences, groom name..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Actions -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="index.php" class="btn btn-light border px-4">Cancel</a>
                        <button type="submit" class="btn btn-salon-primary px-5 py-2 fw-semibold">
                            <i class="bi bi-check-lg me-1"></i> Register Bride
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
                        <span class="small fw-semibold text-dark text-truncate ms-2" id="previewPkg" style="max-width: 170px;">—</span>
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
    const btnCopyDate = document.getElementById("btnCopyDate");
    
    const selectPackage = document.getElementById("selectPackage");
    const customPackageWrapper = document.getElementById("customPackageWrapper");
    const inputCustomPackage = document.getElementById("inputCustomPackage");
    
    const amtInput = document.getElementById("inputAmount");
    const advInput = document.getElementById("inputAdvance");
    const badgeBalanceStatus = document.getElementById("badgeBalanceStatus");

    const prevAvatar = document.getElementById("previewAvatar");
    const prevName = document.getElementById("previewName");
    const prevMobile = document.getElementById("previewMobile");
    const prevWedDate = document.getElementById("previewWedDate");
    const prevMkpDate = document.getElementById("previewMkpDate");
    const prevPkg = document.getElementById("previewPkg");
    const prevBalance = document.getElementById("previewBalance");
    const prevAnni = document.getElementById("previewAnniversaryHint");

    // Copy wedding date to makeup date
    if (btnCopyDate) {
        btnCopyDate.addEventListener("click", function() {
            if (wedInput.value) {
                mkpInput.value = wedInput.value;
                updatePreview();
            }
        });
    }

    // Auto-fill price when package is selected
    selectPackage.addEventListener("change", function () {
        const val = this.value;
        if (val === "custom") {
            customPackageWrapper.style.display = "block";
            inputCustomPackage.focus();
        } else {
            customPackageWrapper.style.display = "none";
            const opt = this.options[this.selectedIndex];
            const price = opt.getAttribute("data-price");
            if (price) {
                amtInput.value = price;
            }
        }
        updatePreview();
    });

    if (inputCustomPackage) {
        inputCustomPackage.addEventListener("input", updatePreview);
    }

    function updatePreview() {
        const name = nameInput.value.trim() || "Bride Name";
        prevName.textContent = name;
        prevAvatar.textContent = name.charAt(0).toUpperCase() || "B";

        const mobile = mobileInput.value.trim();
        prevMobile.textContent = mobile ? "+91 " + mobile : "+91 ••••• •••••";

        prevWedDate.textContent = wedInput.value ? wedInput.value : "—";
        prevMkpDate.textContent = mkpInput.value ? mkpInput.value : "—";

        let pkgName = "—";
        if (selectPackage.value === "custom") {
            pkgName = inputCustomPackage.value.trim() || "Custom Package";
        } else if (selectPackage.value) {
            pkgName = selectPackage.value;
        }
        prevPkg.textContent = pkgName;

        const total = parseFloat(amtInput.value) || 0;
        const adv = parseFloat(advInput.value) || 0;
        const bal = Math.max(0, total - adv);

        if (total > 0 && bal === 0) {
            badgeBalanceStatus.className = "badge bg-success font-monospace fs-6 px-3 py-1";
            badgeBalanceStatus.textContent = "✓ Paid in Full";
            prevBalance.className = "small fw-bold text-success font-monospace";
            prevBalance.textContent = "✓ Fully Paid";
        } else if (bal > 0) {
            badgeBalanceStatus.className = "badge bg-danger font-monospace fs-6 px-3 py-1";
            badgeBalanceStatus.textContent = "₹ " + bal.toLocaleString("en-IN", { minimumFractionDigits: 2 }) + " Due";
            prevBalance.className = "small fw-bold text-danger font-monospace";
            prevBalance.textContent = "₹ " + bal.toLocaleString("en-IN", { minimumFractionDigits: 2 });
        } else {
            badgeBalanceStatus.className = "badge bg-secondary font-monospace fs-6 px-3 py-1";
            badgeBalanceStatus.textContent = "₹ 0.00";
            prevBalance.className = "small fw-bold text-muted font-monospace";
            prevBalance.textContent = "₹ 0.00";
        }

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

    [nameInput, mobileInput, wedInput, mkpInput, amtInput, advInput].forEach(el => {
        el.addEventListener("input", updatePreview);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Safiya's Makeover — Edit Bride Record (Fast & Streamlined)
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

// Check if customer's existing package is in predefined list
$is_preset_pkg = false;
foreach ($packages as $cat => $items) {
    if (isset($items[$customer['service_package']])) {
        $is_preset_pkg = true;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $mobile_number = clean_indian_phone(trim($_POST['mobile_number'] ?? ''));
    $wedding_date = !empty($_POST['wedding_date']) ? $_POST['wedding_date'] : null;
    $makeup_date = !empty($_POST['makeup_date']) ? $_POST['makeup_date'] : null;
    
    // Package from dropdown or custom
    $selected_pkg = trim($_POST['service_package_select'] ?? '');
    $custom_pkg = trim($_POST['service_package_custom'] ?? '');
    $service_package = ($selected_pkg === 'custom' || empty($selected_pkg)) ? $custom_pkg : $selected_pkg;

    $amount = !empty($_POST['amount']) ? (float)$_POST['amount'] : null;
    $advance_amount = !empty($_POST['advance_amount']) ? (float)$_POST['advance_amount'] : 0.00;
    $slip_number = trim($_POST['slip_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $salon_branch = trim($_POST['salon_branch'] ?? $customer['salon_branch'] ?? $default_branch);

    // Auto-detect booking type based on package
    $booking_type = $customer['booking_type'] ?: 'Bridal Makeup';
    if (stripos($service_package, 'Engagement') !== false) {
        $booking_type = 'Engagement Makeup';
    } elseif (stripos($service_package, 'Party') !== false) {
        $booking_type = 'Party Makeup';
    } elseif (stripos($service_package, 'Reception') !== false) {
        $booking_type = 'Reception';
    }

    if (empty($full_name)) {
        $error = 'Bride full name is required.';
    } elseif (empty($mobile_number)) {
        $error = 'Valid 10-digit mobile number is required.';
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
            notes = :notes
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

<div class="card card-salon border-0 shadow-sm col-lg-8 mx-auto">
    <div class="card-body p-4">
        <form method="POST" action="edit.php?id=<?= $customer['id'] ?>" id="editBrideForm" autocomplete="off">
            <!-- Keyboard Shortcuts Status Bar -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-2 px-3 rounded-2" style="background: #FDF4F6; border: 1px dashed #E8A8B8; font-size: 0.8rem;">
                <div class="text-dark">
                    <i class="bi bi-keyboard text-salon me-1"></i> <strong>Bina Mouse Ke Navigate Karein:</strong> 
                    <span class="badge bg-white text-dark border ms-1">Tab</span> ya <span class="badge bg-white text-dark border">Enter</span> = Agla Column &bull; 
                    <span class="badge bg-white text-dark border">← ↑ → ↓</span> = Ider / Uder / Uper / Nicha
                </div>
            </div>

            <!-- 1. Identity -->
            <div class="row g-3 mb-4">
                <div class="col-md-7">
                    <label class="form-label small fw-semibold">Bride Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="inputFullName" class="form-control form-control-lg" value="<?= htmlspecialchars($_POST['full_name'] ?? $customer['full_name']) ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light text-muted small">+91</span>
                        <input type="tel" name="mobile_number" id="inputMobile" class="form-control font-monospace" value="<?= htmlspecialchars($_POST['mobile_number'] ?? $customer['mobile_number']) ?>" required>
                    </div>
                </div>
            </div>

            <!-- 2. Dates -->
            <div class="card p-3 mb-4 border-0" style="background-color: #FDF9FA; border-radius: 10px;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">
                            💍 Wedding Date <small class="text-danger fw-normal">(Anniversary Trigger)</small>
                        </label>
                        <input type="date" name="wedding_date" id="inputWeddingDate" class="form-control" value="<?= htmlspecialchars($_POST['wedding_date'] ?? $customer['wedding_date'] ?? '') ?>">
                        <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Annual anniversary greetings are triggered by this date.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold d-flex justify-content-between align-items-center">
                            <span>💄 Makeup Appointment Date</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btnCopyDate" style="font-size: 0.75rem; color: #D4738C;">
                                <i class="bi bi-copy me-1"></i>Same as Wedding Date
                            </button>
                        </label>
                        <input type="date" name="makeup_date" id="inputMakeupDate" class="form-control" value="<?= htmlspecialchars($_POST['makeup_date'] ?? $customer['makeup_date'] ?? '') ?>">
                        <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Day of makeup appointment at studio.</small>
                    </div>
                </div>
            </div>

            <!-- 3. Package & Billing -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Service Package</label>
                    <select name="service_package_select" id="selectPackage" class="form-select">
                        <option value="">-- Choose Package (Auto-Fills Price) --</option>
                        <?php foreach ($packages as $category => $items): ?>
                            <optgroup label="<?= htmlspecialchars($category) ?>">
                                <?php foreach ($items as $pkg_name => $price): ?>
                                    <option value="<?= htmlspecialchars($pkg_name) ?>" data-price="<?= $price ?>" <?= ($customer['service_package'] === $pkg_name) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pkg_name) ?> (₹<?= number_format($price) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                        <option value="custom" <?= (!$is_preset_pkg && !empty($customer['service_package'])) ? 'selected' : '' ?>>✏️ Other / Custom Package...</option>
                    </select>
                    
                    <div id="customPackageWrapper" class="mt-2" style="<?= (!$is_preset_pkg && !empty($customer['service_package'])) ? '' : 'display: none;' ?>">
                        <input type="text" name="service_package_custom" id="inputCustomPackage" class="form-control" placeholder="Type custom service package name..." value="<?= htmlspecialchars($customer['service_package'] ?? '') ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Total Amount (₹)</label>
                    <input type="number" step="0.01" name="amount" id="inputAmount" class="form-control font-monospace" value="<?= htmlspecialchars($_POST['amount'] ?? $customer['amount'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Advance Received (₹)</label>
                    <input type="number" step="0.01" name="advance_amount" id="inputAdvance" class="form-control font-monospace" value="<?= htmlspecialchars($_POST['advance_amount'] ?? $customer['advance_amount'] ?? '0') ?>">
                </div>

                <!-- Live Balance Badge -->
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center p-2 px-3 rounded-2 bg-light border">
                        <span class="small fw-semibold text-secondary">
                            <i class="bi bi-calculator me-1"></i> Balance Due:
                        </span>
                        <span class="badge font-monospace fs-6 px-3 py-1" id="badgeBalanceStatus">
                            ₹ 0.00
                        </span>
                    </div>
                </div>
            </div>

            <!-- 4. Optional Extra Details -->
            <div class="accordion mb-4" id="optionalEditAccordion">
                <div class="accordion-item border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed py-2 px-3 bg-light rounded text-secondary small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEditOptional">
                            <i class="bi bi-sliders me-2 text-salon"></i> Optional Details (Receipt #, Notes, Branch)
                        </button>
                    </h2>
                    <div id="collapseEditOptional" class="accordion-collapse collapse" data-bs-parent="#optionalEditAccordion">
                        <div class="accordion-body pt-3 pb-0 px-1">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Receipt / Slip #</label>
                                    <input type="text" name="slip_number" class="form-control font-monospace" value="<?= htmlspecialchars($_POST['slip_number'] ?? $customer['slip_number'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Studio Branch</label>
                                    <input type="text" name="salon_branch" class="form-control" value="<?= htmlspecialchars($_POST['salon_branch'] ?? $customer['salon_branch'] ?? $default_branch) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Client Preferences / Special Notes</label>
                                    <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($_POST['notes'] ?? $customer['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <a href="view.php?id=<?= $customer['id'] ?>" class="btn btn-light border px-4">Cancel</a>
                <button type="submit" class="btn btn-salon-primary px-5 py-2 fw-semibold">
                    <i class="bi bi-check-lg me-1"></i> Save Changes
                </button>
            </div>
        </form>
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
    const form = document.getElementById("editBrideForm");

    function isCursorAtStart(input) {
        if (!input) return true;
        try {
            if ('selectionStart' in input && input.selectionStart !== null) {
                return input.selectionStart === 0 && input.selectionEnd === 0;
            }
        } catch (e) {}
        return true;
    }

    function isCursorAtEnd(input) {
        if (!input) return true;
        try {
            if ('selectionStart' in input && input.selectionStart !== null) {
                return input.selectionStart === input.value.length;
            }
        } catch (e) {}
        return true;
    }

    if (btnCopyDate) {
        btnCopyDate.addEventListener("click", function() {
            if (wedInput.value) {
                mkpInput.value = wedInput.value;
            }
        });
    }

    selectPackage.addEventListener("change", function () {
        const val = this.value;
        if (val === "custom") {
            customPackageWrapper.style.display = "block";
            inputCustomPackage.focus();
        } else {
            customPackageWrapper.style.display = "none";
            const opt = this.options[this.selectedIndex];
            const price = opt ? opt.getAttribute("data-price") : null;
            if (price) {
                amtInput.value = price;
            }
        }
        calcBalance();
    });

    function calcBalance() {
        const total = parseFloat(amtInput.value) || 0;
        const adv = parseFloat(advInput.value) || 0;
        const bal = Math.max(0, total - adv);

        if (total > 0 && bal === 0) {
            badgeBalanceStatus.className = "badge bg-success font-monospace fs-6 px-3 py-1";
            badgeBalanceStatus.textContent = "✓ Paid in Full";
        } else if (bal > 0) {
            badgeBalanceStatus.className = "badge bg-danger font-monospace fs-6 px-3 py-1";
            badgeBalanceStatus.textContent = "₹ " + bal.toLocaleString("en-IN", { minimumFractionDigits: 2 }) + " Due";
        } else {
            badgeBalanceStatus.className = "badge bg-secondary font-monospace fs-6 px-3 py-1";
            badgeBalanceStatus.textContent = "₹ 0.00";
        }
    }

    [amtInput, advInput].forEach(el => el.addEventListener("input", calcBalance));
    calcBalance();

    // --- KEYBOARD & ARROW NAVIGATION ---
    function getLinearSequence() {
        const seq = [nameInput, mobileInput, wedInput, mkpInput];
        if (selectPackage.value === 'custom' && customPackageWrapper && customPackageWrapper.style.display !== 'none') {
            seq.push(selectPackage, inputCustomPackage);
        } else {
            seq.push(selectPackage);
        }
        seq.push(amtInput, advInput);

        const slipEl = document.querySelector('input[name="slip_number"]');
        const branchEl = document.querySelector('input[name="salon_branch"]');
        const notesEl = document.querySelector('textarea[name="notes"]');
        if (slipEl && slipEl.offsetParent !== null) {
            seq.push(slipEl, branchEl, notesEl);
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) seq.push(submitBtn);

        return seq.filter(Boolean);
    }

    if (form) {
        form.addEventListener("keydown", function (e) {
            const el = e.target;
            if (!el) return;

            if (el.tagName.toLowerCase() === 'textarea' && e.key === 'Enter' && !e.ctrlKey) {
                return;
            }

            // Enter key advances to next field
            if (e.key === "Enter") {
                if (el.tagName.toLowerCase() === 'button' || el.type === 'submit' || el.tagName.toLowerCase() === 'a') {
                    return;
                }
                e.preventDefault();
                const seq = getLinearSequence();
                const idx = seq.indexOf(el);
                if (idx !== -1 && idx + 1 < seq.length) {
                    const nextEl = seq[idx + 1];
                    nextEl.focus();
                    if (nextEl.select && nextEl.type !== 'date') {
                        nextEl.select();
                    }
                }
                return;
            }

            const key = e.key;
            if (!["ArrowDown", "ArrowUp", "ArrowLeft", "ArrowRight"].includes(key)) {
                return;
            }

            if (el.tagName.toLowerCase() === 'select' && (key === 'ArrowUp' || key === 'ArrowDown') && !e.altKey && !e.ctrlKey) {
                return;
            }

            if (el.type === 'date' && (key === 'ArrowLeft' || key === 'ArrowRight') && !e.altKey && !e.ctrlKey) {
                return;
            }

            if ((el.type === 'text' || el.type === 'tel') && !e.altKey && !e.ctrlKey) {
                if (key === 'ArrowLeft' && !isCursorAtStart(el)) return;
                if (key === 'ArrowRight' && !isCursorAtEnd(el)) return;
            }

            let handled = false;

            if (el === nameInput) {
                if (key === 'ArrowRight') { mobileInput.focus(); mobileInput.select(); handled = true; }
                else if (key === 'ArrowDown') { wedInput.focus(); handled = true; }
            } else if (el === mobileInput) {
                if (key === 'ArrowLeft') { nameInput.focus(); nameInput.select(); handled = true; }
                else if (key === 'ArrowDown') { mkpInput.focus(); handled = true; }
            } else if (el === wedInput) {
                if (key === 'ArrowUp') { nameInput.focus(); handled = true; }
                else if (key === 'ArrowRight') { mkpInput.focus(); handled = true; }
                else if (key === 'ArrowDown') { selectPackage.focus(); handled = true; }
            } else if (el === mkpInput) {
                if (key === 'ArrowUp') { mobileInput.focus(); handled = true; }
                else if (key === 'ArrowLeft') { wedInput.focus(); handled = true; }
                else if (key === 'ArrowDown') { amtInput.focus(); handled = true; }
            } else if (el === selectPackage || el === inputCustomPackage) {
                if (key === 'ArrowUp' && (e.altKey || el === inputCustomPackage)) { wedInput.focus(); handled = true; }
                else if (key === 'ArrowRight') { amtInput.focus(); handled = true; }
                else if (key === 'ArrowDown' && (e.altKey || el === inputCustomPackage)) { amtInput.focus(); handled = true; }
                else if (key === 'ArrowLeft') { mkpInput.focus(); handled = true; }
            } else if (el === amtInput) {
                if (key === 'ArrowUp') { mkpInput.focus(); handled = true; }
                else if (key === 'ArrowLeft' && (isCursorAtStart(el) || e.altKey || el.value === '')) { selectPackage.focus(); handled = true; }
                else if (key === 'ArrowRight' && (isCursorAtEnd(el) || e.altKey || el.value === '')) { advInput.focus(); advInput.select(); handled = true; }
                else if (key === 'ArrowDown') { advInput.focus(); handled = true; }
            } else if (el === advInput) {
                if (key === 'ArrowUp') { mkpInput.focus(); handled = true; }
                else if (key === 'ArrowLeft' && (isCursorAtStart(el) || e.altKey || el.value === '')) { amtInput.focus(); amtInput.select(); handled = true; }
                else if (key === 'ArrowDown' || key === 'ArrowRight') {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.focus();
                    handled = true;
                }
            }

            if (handled) {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

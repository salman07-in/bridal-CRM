<?php
/**
 * Safiya's Makeover — Excel / CSV Import & Export Hub
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$db = get_db();

// 1. Export CSV Action
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $filename = "safiya_bridal_register_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fputs($output, "\xEF\xBB\xBF");

    // CSV Headers
    fputcsv($output, [
        'Customer Code', 'Bride Full Name', 'Mobile Number', 'Wedding Date', 
        'Makeup Date', 'Service Package', 'Total Amount (INR)', 'Advance Paid (INR)', 
        'Balance Due (INR)', 'Slip Number', 'Booking Type', 'Branch', 'WhatsApp Consent', 'Notes'
    ]);

    $stmt = $db->query("SELECT * FROM customers ORDER BY id ASC");
    while ($row = $stmt->fetch()) {
        $balance = max(0, (float)$row['amount'] - (float)$row['advance_amount']);
        fputcsv($output, [
            $row['customer_code'],
            $row['full_name'],
            $row['mobile_number'],
            $row['wedding_date'] ? date('d/m/Y', strtotime($row['wedding_date'])) : '',
            $row['makeup_date'] ? date('d/m/Y', strtotime($row['makeup_date'])) : '',
            $row['service_package'],
            $row['amount'],
            $row['advance_amount'],
            $balance,
            $row['slip_number'],
            $row['booking_type'],
            $row['salon_branch'],
            $row['whatsapp_consent'] ? 'Yes' : 'No',
            $row['notes']
        ]);
    }
    fclose($output);
    exit;
}

// 2. Download Sample Template CSV
if (isset($_GET['action']) && $_GET['action'] === 'sample_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sample_bridal_register_template.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, [
        'Bride Full Name', 'Mobile Number', 'Wedding Date (DD/MM/YYYY)', 'Makeup Date (DD/MM/YYYY)', 
        'Service Package', 'Total Amount', 'Advance Paid', 'Slip Number', 'Booking Type', 'Branch', 'Notes'
    ]);
    fputcsv($output, [
        'Sana Fatima', '9876543210', '25/11/2023', '24/11/2023', 
        'HD Bridal Makeup', '30000', '10000', 'SLIP-201', 'Bridal Makeup', 'Main Branch', 'Hairstyle preference: soft curls'
    ]);
    fputcsv($output, [
        'Zoya Khan', '9123456789', '14/02/2024', '13/02/2024', 
        'Airbrush Glow', '35000', '15000', 'SLIP-202', 'Bridal Makeup', 'Main Branch', 'Airbrush makeup requested'
    ]);
    fclose($output);
    exit;
}

// 3. Handle CSV File Upload & Parsing
$import_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'File upload error. Please select a valid CSV file.');
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            set_flash('error', 'Only .csv format files are supported. Please export your Excel register as CSV.');
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle !== false) {
                $imported = 0;
                $skipped = 0;
                $row_idx = 0;

                // Function to parse flexible dates
                $parse_date = function($d_str) {
                    $d_str = trim($d_str);
                    if (empty($d_str)) return null;
                    // Try DD/MM/YYYY or DD-MM-YYYY
                    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $d_str, $m)) {
                        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
                    }
                    // Try YYYY-MM-DD
                    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $d_str, $m)) {
                        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
                    }
                    return null;
                };

                while (($data = fgetcsv($handle, 4096, ',')) !== false) {
                    $row_idx++;
                    // Skip header row
                    if ($row_idx === 1) {
                        // Check if row contains text like "Name" or "Full Name"
                        if (stripos(implode(',', $data), 'Name') !== false) {
                            continue;
                        }
                    }

                    $name = trim($data[0] ?? '');
                    $mobile = clean_indian_phone(trim($data[1] ?? ''));

                    if (empty($name) || empty($mobile)) {
                        $skipped++;
                        continue;
                    }

                    $wedding_date = $parse_date($data[2] ?? '');
                    $makeup_date = $parse_date($data[3] ?? '');
                    $package = trim($data[4] ?? '');
                    $amount = !empty($data[5]) ? (float)$data[5] : null;
                    $advance = !empty($data[6]) ? (float)$data[6] : 0.00;
                    $slip = trim($data[7] ?? '');
                    $btype = !empty($data[8]) ? trim($data[8]) : 'Bridal Makeup';
                    $branch = !empty($data[9]) ? trim($data[9]) : 'Main Branch';
                    $notes = trim($data[10] ?? '');

                    // Check if customer already exists by mobile
                    $check = $db->prepare("SELECT id FROM customers WHERE mobile_number = :m LIMIT 1");
                    $check->execute(['m' => $mobile]);
                    if ($check->fetchColumn()) {
                        $skipped++;
                        continue;
                    }

                    $code = generate_customer_code();
                    $ins = $db->prepare("INSERT INTO customers (
                        customer_code, full_name, mobile_number, wedding_date, makeup_date,
                        service_package, amount, advance_amount, slip_number, booking_type,
                        salon_branch, notes, whatsapp_consent
                    ) VALUES (
                        :code, :name, :mobile, :wed, :mkp,
                        :pkg, :amt, :adv, :slip, :btype,
                        :branch, :notes, 1
                    )");

                    $ins->execute([
                        'code' => $code,
                        'name' => $name,
                        'mobile' => $mobile,
                        'wed' => $wedding_date,
                        'mkp' => $makeup_date,
                        'pkg' => $package,
                        'amt' => $amount,
                        'adv' => $advance,
                        'slip' => $slip,
                        'btype' => $btype,
                        'branch' => $branch,
                        'notes' => $notes
                    ]);
                    $imported++;
                }
                fclose($handle);

                log_audit('import', 'customer', null, ['imported' => $imported, 'skipped' => $skipped]);
                set_flash('success', "Import complete! Successfully added {$imported} new bride records ({$skipped} duplicate or invalid rows skipped).");
                header("Location: index.php");
                exit;
            }
        }
    }
}

$page_title = 'Import / Export Register';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold mb-1">Data Import &amp; Export</h2>
        <p class="text-muted small mb-0">Bulk upload your physical registers or export complete database backups in CSV/Excel format</p>
    </div>
</div>

<div class="row g-4">
    <!-- Export Box -->
    <div class="col-lg-6">
        <div class="card card-salon border-0 shadow-sm h-100 p-4">
            <div class="d-flex align-items-center mb-3">
                <div class="stat-icon bg-stat-green me-3">
                    <i class="bi bi-file-earmark-arrow-down"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Export Bridal Register</h5>
                    <div class="text-muted small">Download complete database with formatted INR balances</div>
                </div>
            </div>
            <p class="text-secondary small">
                Exports all registered brides including Customer Codes, Indian mobile numbers, Wedding Dates, Makeup Service Dates, Slip #, Package amount, and WhatsApp consent.
            </p>
            <div class="mt-auto pt-3 border-top">
                <a href="index.php?action=export_csv" class="btn btn-success px-4 py-2 fw-semibold">
                    <i class="bi bi-download me-1"></i> Download CSV Register (.csv)
                </a>
            </div>
        </div>
    </div>

    <!-- Import Box -->
    <div class="col-lg-6">
        <div class="card card-salon border-0 shadow-sm h-100 p-4">
            <div class="d-flex align-items-center mb-3">
                <div class="stat-icon bg-stat-pink me-3">
                    <i class="bi bi-file-earmark-arrow-up"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Upload Register CSV</h5>
                    <div class="text-muted small">Bulk import past brides (from 2020 onwards)</div>
                </div>
            </div>

            <form method="POST" action="index.php" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Select CSV Register File</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    <small class="text-muted d-block mt-1">Accepts dates formatted as <code>DD/MM/YYYY</code> or <code>YYYY-MM-DD</code>.</small>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="index.php?action=sample_csv" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-text me-1"></i> Download Sample Template
                    </a>
                    <button type="submit" class="btn btn-salon-primary px-4">
                        <i class="bi bi-upload me-1"></i> Upload &amp; Commit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

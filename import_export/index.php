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

// 3. Handle CSV File Upload & Parsing (Smart Adaptive Importer)
$import_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $update_existing = !empty($_POST['update_existing']);

    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'File upload error. Please select a valid CSV file.');
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            set_flash('error', 'Only .csv format files are supported. In Excel, click File ➔ Save As ➔ CSV (Comma delimited).');
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle !== false) {
                $imported = 0;
                $updated = 0;
                $skipped = 0;
                $skipped_reasons = [];
                $row_idx = 0;

                // Flexible multi-format Date Parser
                $parse_date = function($d_str) {
                    $d_str = trim($d_str ?? '');
                    if ($d_str === '' || $d_str === '-' || strtolower($d_str) === 'null' || strtolower($d_str) === 'n/a') {
                        return null;
                    }
                    // Excel numeric serial date (e.g. 44927)
                    if (is_numeric($d_str) && (int)$d_str >= 35000 && (int)$d_str <= 65000) {
                        return date('Y-m-d', ((int)$d_str - 25569) * 86400);
                    }
                    // DD/MM/YYYY or DD-MM-YYYY or DD.MM.YYYY
                    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $d_str, $m)) {
                        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
                    }
                    // DD/MM/YY or DD-MM-YY (2-digit year)
                    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2})$/', $d_str, $m)) {
                        $yr = (int)$m[3] < 50 ? 2000 + (int)$m[3] : 1900 + (int)$m[3];
                        return sprintf('%04d-%02d-%02d', $yr, (int)$m[2], (int)$m[1]);
                    }
                    // YYYY-MM-DD or YYYY/MM/DD
                    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $d_str, $m)) {
                        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
                    }
                    // Try strtotime for text dates e.g. "25 Nov 2023"
                    $ts = strtotime($d_str);
                    if ($ts && $ts > 0 && date('Y', $ts) > 1990) {
                        return date('Y-m-d', $ts);
                    }
                    return null;
                };

                // Default column mapping
                $col_map = [
                    'name' => 0,
                    'mobile' => 1,
                    'wedding_date' => 2,
                    'makeup_date' => 3,
                    'package' => 4,
                    'amount' => 5,
                    'advance' => 6,
                    'slip' => 7,
                    'btype' => 8,
                    'branch' => 9,
                    'notes' => 10
                ];
                $has_header_row = false;

                while (($data = fgetcsv($handle, 8192, ',')) !== false) {
                    $row_idx++;

                    // Check Header row on Row 1
                    if ($row_idx === 1) {
                        $detected_map = [];
                        $found_any = false;
                        foreach ($data as $col_i => $header_val) {
                            $clean_hdr = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $header_val)));
                            if (strpos($clean_hdr, 'name') !== false || strpos($clean_hdr, 'bride') !== false || strpos($clean_hdr, 'client') !== false || strpos($clean_hdr, 'customer') !== false) {
                                $detected_map['name'] = $col_i;
                                $found_any = true;
                            } elseif (strpos($clean_hdr, 'mobile') !== false || strpos($clean_hdr, 'phone') !== false || strpos($clean_hdr, 'contact') !== false || strpos($clean_hdr, 'number') !== false && strpos($clean_hdr, 'slip') === false) {
                                $detected_map['mobile'] = $col_i;
                                $found_any = true;
                            } elseif (strpos($clean_hdr, 'wedding') !== false || strpos($clean_hdr, 'marriage') !== false || strpos($clean_hdr, 'shaadi') !== false || strpos($clean_hdr, 'anni') !== false) {
                                $detected_map['wedding_date'] = $col_i;
                                $found_any = true;
                            } elseif (strpos($clean_hdr, 'makeup') !== false || strpos($clean_hdr, 'appointment') !== false || strpos($clean_hdr, 'service') !== false && strpos($clean_hdr, 'date') !== false) {
                                $detected_map['makeup_date'] = $col_i;
                                $found_any = true;
                            } elseif (strpos($clean_hdr, 'package') !== false || strpos($clean_hdr, 'service') !== false) {
                                $detected_map['package'] = $col_i;
                                $found_any = true;
                            } elseif (strpos($clean_hdr, 'advance') !== false || strpos($clean_hdr, 'deposit') !== false) {
                                $detected_map['advance'] = $col_i;
                                $found_any = true;
                            } elseif (strpos($clean_hdr, 'amount') !== false || strpos($clean_hdr, 'total') !== false || strpos($clean_hdr, 'cost') !== false || strpos($clean_hdr, 'price') !== false) {
                                $detected_map['amount'] = $col_i;
                                $found_any = true;
                            } elseif (strpos($clean_hdr, 'slip') !== false || strpos($clean_hdr, 'bill') !== false || strpos($clean_hdr, 'invoice') !== false) {
                                $detected_map['slip'] = $col_i;
                                $found_any = true;
                            } elseif (strpos($clean_hdr, 'branch') !== false || strpos($clean_hdr, 'city') !== false) {
                                $detected_map['branch'] = $col_i;
                                $found_any = true;
                            } elseif (strpos($clean_hdr, 'note') !== false || strpos($clean_hdr, 'remark') !== false || strpos($clean_hdr, 'address') !== false) {
                                $detected_map['notes'] = $col_i;
                                $found_any = true;
                            }
                        }

                        if ($found_any && isset($detected_map['name'])) {
                            $col_map = array_merge($col_map, $detected_map);
                            $has_header_row = true;
                            continue;
                        }
                    }

                    // Extract columns based on mapping
                    $raw_name = trim($data[$col_map['name']] ?? '');
                    $raw_mobile = trim($data[$col_map['mobile']] ?? '');

                    // Auto-scan fallback if name or phone missing in mapped column
                    if (empty($raw_name) || empty($raw_mobile)) {
                        foreach ($data as $cell) {
                            $cell_str = trim($cell);
                            $digits_only = preg_replace('/[^\d]/', '', $cell_str);
                            if (strlen($digits_only) >= 10 && empty($raw_mobile)) {
                                $raw_mobile = $cell_str;
                            } elseif (strlen($cell_str) >= 3 && !is_numeric($cell_str) && empty($raw_name)) {
                                $raw_name = $cell_str;
                            }
                        }
                    }

                    $name = $raw_name;
                    $mobile = clean_indian_phone($raw_mobile);

                    if (empty($name)) {
                        $skipped++;
                        if (count($skipped_reasons) < 5) $skipped_reasons[] = "Row {$row_idx}: Bride Name missing";
                        continue;
                    }

                    if (empty($mobile) || strlen(preg_replace('/[^\d]/', '', $mobile)) < 10) {
                        $skipped++;
                        if (count($skipped_reasons) < 5) $skipped_reasons[] = "Row {$row_idx}: Invalid mobile '{$raw_mobile}' for bride '{$name}'";
                        continue;
                    }

                    $wedding_date = $parse_date($data[$col_map['wedding_date']] ?? '');
                    $makeup_date = $parse_date($data[$col_map['makeup_date']] ?? '');
                    $package = trim($data[$col_map['package']] ?? '');
                    $amount = !empty($data[$col_map['amount']]) ? (float)preg_replace('/[^\d\.]/', '', $data[$col_map['amount']]) : null;
                    $advance = !empty($data[$col_map['advance']]) ? (float)preg_replace('/[^\d\.]/', '', $data[$col_map['advance']]) : 0.00;
                    $slip = trim($data[$col_map['slip']] ?? '');
                    $btype = !empty($data[$col_map['btype']]) ? trim($data[$col_map['btype']]) : 'Bridal Makeup';
                    $branch = !empty($data[$col_map['branch']]) ? trim($data[$col_map['branch']]) : 'Main Branch';
                    $notes = trim($data[$col_map['notes']] ?? '');

                    // Check if customer already exists by mobile
                    $check = $db->prepare("SELECT id FROM customers WHERE mobile_number = :m LIMIT 1");
                    $check->execute(['m' => $mobile]);
                    $existing_id = $check->fetchColumn();

                    if ($existing_id) {
                        if ($update_existing) {
                            // Update existing customer record with new dates & data
                            $upd = $db->prepare("UPDATE customers SET 
                                full_name = :name,
                                wedding_date = COALESCE(:wed, wedding_date),
                                makeup_date = COALESCE(:mkp, makeup_date),
                                service_package = CASE WHEN :pkg != '' THEN :pkg ELSE service_package END,
                                amount = COALESCE(:amt, amount),
                                advance_amount = CASE WHEN :adv > 0 THEN :adv ELSE advance_amount END,
                                slip_number = CASE WHEN :slip != '' THEN :slip ELSE slip_number END,
                                notes = CASE WHEN :notes != '' THEN :notes ELSE notes END
                                WHERE id = :id");
                            $upd->execute([
                                'name' => $name,
                                'wed' => $wedding_date,
                                'mkp' => $makeup_date,
                                'pkg' => $package,
                                'amt' => $amount,
                                'adv' => $advance,
                                'slip' => $slip,
                                'notes' => $notes,
                                'id' => $existing_id
                            ]);
                            $updated++;
                        } else {
                            $skipped++;
                            if (count($skipped_reasons) < 5) $skipped_reasons[] = "Row {$row_idx}: Mobile {$mobile} already exists ({$name})";
                        }
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

                log_audit('import', 'customer', null, ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped]);
                
                $msg = "Import completed! Successfully added <strong>{$imported}</strong> new brides";
                if ($updated > 0) {
                    $msg .= ", updated <strong>{$updated}</strong> existing records";
                }
                $msg .= ". ({$skipped} skipped)";
                if (!empty($skipped_reasons)) {
                    $msg .= "<br><small class='mt-1 d-block text-muted'>Sample skipped rows: " . implode(' &bull; ', $skipped_reasons) . "</small>";
                }

                set_flash($imported > 0 || $updated > 0 ? 'success' : 'warning', $msg);
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
                    <small class="text-muted d-block mt-1">Accepts any column order, and dates formatted as <code>DD/MM/YYYY</code> or <code>YYYY-MM-DD</code>.</small>
                </div>

                <div class="form-check mb-3 p-2 bg-light rounded-2">
                    <input class="form-check-input ms-1 me-2" type="checkbox" name="update_existing" value="1" id="updateExisting" checked>
                    <label class="form-check-label small fw-semibold" for="updateExisting">
                        Update existing brides if phone number matches (overwrites with new wedding date)
                    </label>
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

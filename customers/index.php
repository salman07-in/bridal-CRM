<?php
/**
 * Safiya's Makeover — Customer Register & Filters
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Customer Register';
$db = get_db();
$today_str = get_kolkata_today();
$tomorrow_str = (new DateTime($today_str))->modify('+1 day')->format('Y-m-d');

// Search & filter params
$search = trim($_GET['search'] ?? '');
$tab = $_GET['tab'] ?? 'all';
$missing_date = isset($_GET['missing_date']) ? (int)$_GET['missing_date'] : null;
$branch = trim($_GET['branch'] ?? '');
$booking_type = trim($_GET['booking_type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(full_name LIKE :s1 OR mobile_number LIKE :s2 OR customer_code LIKE :s3 OR slip_number LIKE :s4)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
    $params['s3'] = "%{$search}%";
    $params['s4'] = "%{$search}%";
}

if ($missing_date === 1 || $tab === 'missing_date') {
    $where[] = "wedding_date IS NULL";
}

if ($branch !== '') {
    $where[] = "salon_branch = :branch";
    $params['branch'] = $branch;
}

if ($booking_type !== '') {
    $where[] = "booking_type = :btype";
    $params['btype'] = $booking_type;
}

if ($tab === 'today') {
    $where[] = "makeup_date = :today";
    $params['today'] = $today_str;
} elseif ($tab === 'upcoming') {
    $where[] = "makeup_date = :tomorrow";
    $params['tomorrow'] = $tomorrow_str;
} elseif ($tab === 'pending_balance') {
    $where[] = "COALESCE(amount, 0) > COALESCE(advance_amount, 0)";
}

$where_clause = implode(" AND ", $where);

// Count total matching
$count_stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE {$where_clause}");
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_records / $per_page));

// Fetch paginated records
$sql = "SELECT * FROM customers WHERE {$where_clause} ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Distinct branches for filter dropdown
$branches = $db->query("SELECT DISTINCT salon_branch FROM customers WHERE salon_branch IS NOT NULL AND salon_branch != ''")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold mb-1">Customer Register</h2>
        <p class="text-muted small mb-0">Total <?= number_format($total_records) ?> registered bride(s) found</p>
    </div>
    <div class="d-flex gap-2">
        <a href="../import_export/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-arrow-down me-1"></i> Export / Import
        </a>
        <a href="add.php" class="btn btn-salon-primary btn-sm">
            <i class="bi bi-person-plus-fill me-1"></i> Add Bride
        </a>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-pills mb-3 border-bottom pb-2">
    <li class="nav-item">
        <a class="nav-link <?= ($tab === 'all' && !$missing_date) ? 'active' : '' ?>" href="index.php">
            All Brides
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($tab === 'today') ? 'active' : '' ?>" href="index.php?tab=today">
            <i class="bi bi-stars me-1 text-danger"></i> Today's Makeups
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($tab === 'upcoming') ? 'active' : '' ?>" href="index.php?tab=upcoming">
            <i class="bi bi-bell-fill me-1 text-salon"></i> Tomorrow's Makeups
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($tab === 'pending_balance') ? 'active' : '' ?>" href="index.php?tab=pending_balance">
            <i class="bi bi-cash-stack me-1 text-danger"></i> Pending Balance
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($tab === 'missing_date' || $missing_date === 1) ? 'active' : '' ?>" href="index.php?tab=missing_date">
            <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i> Missing Wedding Dates
        </a>
    </li>
</ul>

<!-- Filter & Search Bar -->
<div class="card card-salon border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <?php if ($tab !== 'all'): ?>
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <?php endif; ?>

            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, mobile, customer code, or slip #..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="branch" class="form-select">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= $branch === $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="booking_type" class="form-select">
                    <option value="">All Booking Types</option>
                    <option value="Bridal Makeup" <?= $booking_type === 'Bridal Makeup' ? 'selected' : '' ?>>Bridal Makeup</option>
                    <option value="Engagement Makeup" <?= $booking_type === 'Engagement Makeup' ? 'selected' : '' ?>>Engagement Makeup</option>
                    <option value="Reception" <?= $booking_type === 'Reception' ? 'selected' : '' ?>>Reception</option>
                    <option value="Party Makeup" <?= $booking_type === 'Party Makeup' ? 'selected' : '' ?>>Party Makeup</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-salon-primary w-100">Filter</button>
                <?php if ($search || $branch || $booking_type || $tab !== 'all'): ?>
                    <a href="index.php" class="btn btn-outline-secondary" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Customer Register Table -->
<div class="card card-salon border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-salon">
                <thead>
                    <tr>
                        <th class="ps-4">Code</th>
                        <th>Bride Name</th>
                        <th>Mobile</th>
                        <th>Wedding Date</th>
                        <th>Makeup Date</th>
                        <th>Package</th>
                        <th>Total Amount</th>
                        <th>Advance Paid</th>
                        <th>Balance Due</th>
                        <th>Branch</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-1 text-secondary"></i>
                                <p class="mt-2 mb-0">No matching records found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <?php 
                            $balance = max(0, (float)$c['amount'] - (float)$c['advance_amount']);
                            $clean_phone = preg_replace('/[^\d]/', '', $c['mobile_number']);
                            $wa_direct_url = "https://wa.me/{$clean_phone}";
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($c['customer_code']) ?></span>
                                    <?php if ($c['slip_number']): ?>
                                        <div class="small text-muted font-monospace">#<?= htmlspecialchars($c['slip_number']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view.php?id=<?= $c['id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                        <?= htmlspecialchars($c['full_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="font-monospace small"><?= htmlspecialchars($c['mobile_number']) ?></span>
                                    <?php if ($c['whatsapp_consent']): ?>
                                        <i class="bi bi-whatsapp text-success ms-1" title="WhatsApp Consent Given"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['wedding_date']): ?>
                                        <span class="badge bg-light text-dark border"><?= format_date_in($c['wedding_date']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= format_date_in($c['makeup_date']) ?></td>
                                <td class="small"><?= htmlspecialchars($c['service_package'] ?: '—') ?></td>
                                <td class="font-monospace small"><?= format_inr($c['amount']) ?></td>
                                <td class="font-monospace small text-success"><?= format_inr($c['advance_amount']) ?></td>
                                <td>
                                    <?php if ($balance > 0): ?>
                                        <span class="badge bg-danger-subtle text-danger font-monospace fw-bold"><?= format_inr($balance) ?> Due</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success">Paid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($c['salon_branch'] ?: '—') ?></td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= $wa_direct_url ?>" target="_blank" class="btn btn-outline-success" title="Open WhatsApp Chat"><i class="bi bi-whatsapp"></i></a>
                                        <a href="view.php?id=<?= $c['id'] ?>" class="btn btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="delete.php?id=<?= $c['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete bride <?= addslashes($c['full_name']) ?>?');" title="Delete"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center p-3 border-top">
                <span class="small text-muted">Showing page <?= $page ?> of <?= $total_pages ?></span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a></li>
                        <?php endif; ?>

                        <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

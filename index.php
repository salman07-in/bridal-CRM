<?php
/**
 * Safiya's Makeover — Bridal CRM Dashboard
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Studio Dashboard';
$db = get_db();
$today_str = get_kolkata_today();
$tomorrow_str = (new DateTime($today_str))->modify('+1 day')->format('Y-m-d');

// 1. Total brides count
$total_brides = (int)$db->query("SELECT COUNT(*) FROM customers")->fetchColumn();

// 2. Missing wedding dates
$missing_dates_count = (int)$db->query("SELECT COUNT(*) FROM customers WHERE wedding_date IS NULL")->fetchColumn();

// 3. Pending balances
$pending_stmt = $db->query("SELECT 
    COUNT(*) as pending_count,
    COALESCE(SUM(GREATEST(0, COALESCE(amount, 0) - COALESCE(advance_amount, 0))), 0) as total_pending
    FROM customers WHERE COALESCE(amount, 0) > COALESCE(advance_amount, 0)");
$pending_row = $pending_stmt->fetch();
$pending_clients_count = (int)$pending_row['pending_count'];
$total_pending_amount = (float)$pending_row['total_pending'];

// 4. Today's & Tomorrow's Makeups
$stmt_today_makeups = $db->prepare("SELECT * FROM customers WHERE makeup_date = :today ORDER BY full_name ASC");
$stmt_today_makeups->execute(['today' => $today_str]);
$todays_makeups = $stmt_today_makeups->fetchAll();
$todays_makeup_count = count($todays_makeups);

$stmt_tmrw_makeups = $db->prepare("SELECT * FROM customers WHERE makeup_date = :tomorrow ORDER BY full_name ASC");
$stmt_tmrw_makeups->execute(['tomorrow' => $tomorrow_str]);
$tomorrows_makeups = $stmt_tmrw_makeups->fetchAll();
$tomorrows_makeup_count = count($tomorrows_makeups);

// 5. Anniversaries (Today, Tomorrow Eve, 7-Day Forecast)
$todays_anniversaries = get_todays_anniversaries();
$tomorrows_anniversaries = get_tomorrows_anniversaries();
$upcoming_anniversaries = get_upcoming_anniversaries(7);

// 6. Recent Brides
$recent_stmt = $db->query("SELECT * FROM customers ORDER BY id DESC LIMIT 6");
$recent_brides = $recent_stmt->fetchAll();

// Salon templates
$wa_template = get_setting('whatsapp_anniversary_template', 'Dear {bride_name}, warm congratulations on your {years} Wedding Anniversary from {salon_name}!');
$wa_eve_template = get_setting('whatsapp_eve_template', 'Hello {bride_name}! Tomorrow is your special wedding anniversary! Warm wishes in advance from {salon_name}.');
$salon_name = get_setting('salon_name', "Safiya's Makeover");

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold mb-1">Bridal Studio Dashboard</h2>
        <p class="text-muted small mb-0">
            <i class="bi bi-calendar3 me-1"></i> Today is <strong><?= date('l, d F Y', strtotime($today_str)) ?></strong> (Asia/Kolkata IST)
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="reminders/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-bell me-1"></i> Reminders Center
        </a>
        <a href="customers/add.php" class="btn btn-salon-primary btn-sm">
            <i class="bi bi-person-plus-fill me-1"></i> Add New Bride
        </a>
    </div>
</div>

<!-- Missing Dates Alert Banner -->
<?php if ($missing_dates_count > 0): ?>
<div class="alert alert-warning border-0 shadow-sm d-flex align-items-center justify-content-between mb-4 p-3 rounded-3" style="background-color: #FFFBEB; border-left: 5px solid #F59E0B !important;">
    <div class="d-flex align-items-center">
        <div class="rounded-circle bg-warning bg-opacity-25 p-2 me-3 text-warning-emphasis">
            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
        </div>
        <div>
            <h6 class="mb-0 fw-bold text-warning-emphasis">Missing Wedding Dates Detected</h6>
            <span class="text-muted small">
                There are <strong><?= $missing_dates_count ?></strong> customer record(s) without a wedding date. Anniversaries cannot be tracked until wedding dates are recorded.
            </span>
        </div>
    </div>
    <a href="customers/index.php?missing_date=1" class="btn btn-warning btn-sm text-dark fw-semibold px-3 text-nowrap ms-3">
        Review Records <i class="bi bi-arrow-right ms-1"></i>
    </a>
</div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <!-- Total Brides -->
    <div class="col-sm-6 col-lg-4 col-xl">
        <div class="card card-salon h-100 p-3 card-stat" style="border-left-color: #D4738C;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Total Brides</div>
                    <h3 class="fw-bold mb-0 mt-1"><?= $total_brides ?></h3>
                    <div class="text-muted small mt-1"><i class="bi bi-check2-circle text-success me-1"></i>All Recorded Clients</div>
                </div>
                <div class="stat-icon bg-stat-pink">
                    <i class="bi bi-heart-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Balance Due -->
    <div class="col-sm-6 col-lg-4 col-xl">
        <a href="customers/index.php?tab=pending_balance" class="text-decoration-none">
            <div class="card card-salon h-100 p-3 card-stat" style="border-left-color: #EF4444; background: #FFFBFB;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-danger small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Pending Balance</div>
                        <h3 class="fw-bold mb-0 mt-1 text-danger font-monospace"><?= format_inr($total_pending_amount) ?></h3>
                        <div class="text-muted small mt-1"><i class="bi bi-exclamation-circle text-danger me-1"></i><?= $pending_clients_count ?> clients with due balance</div>
                    </div>
                    <div class="stat-icon bg-danger-subtle text-danger">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Today's Makeups -->
    <div class="col-sm-6 col-lg-4 col-xl">
        <a href="customers/index.php?tab=today" class="text-decoration-none">
            <div class="card card-salon h-100 p-3 card-stat" style="border-left-color: #BE123C; background: #FFF5F7;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-danger small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Today's Makeups</div>
                        <h3 class="fw-bold mb-0 mt-1 text-danger"><?= $todays_makeup_count ?></h3>
                        <div class="text-muted small mt-1"><i class="bi bi-clock-history text-danger me-1"></i>Scheduled Today</div>
                    </div>
                    <div class="stat-icon bg-danger-subtle text-danger">
                        <i class="bi bi-stars"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Today's Anniversaries -->
    <div class="col-sm-6 col-lg-4 col-xl">
        <div class="card card-salon h-100 p-3 card-stat" style="border-left-color: #10B981;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Today's Anniversaries</div>
                    <h3 class="fw-bold mb-0 mt-1 text-success"><?= count($todays_anniversaries) ?></h3>
                    <div class="text-muted small mt-1"><i class="bi bi-cake2 me-1"></i>Celebrated Today</div>
                </div>
                <div class="stat-icon bg-stat-green">
                    <i class="bi bi-gift-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tomorrow's Anniversaries (1-Day Eve) -->
    <div class="col-sm-6 col-lg-4 col-xl">
        <div class="card card-salon h-100 p-3 card-stat" style="border-left-color: #8B5CF6;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Anniversary (1-Day Eve)</div>
                    <h3 class="fw-bold mb-0 mt-1" style="color: #7C3AED;"><?= count($tomorrows_anniversaries) ?></h3>
                    <div class="text-muted small mt-1"><i class="bi bi-moon-stars me-1"></i>Advance Wishes</div>
                </div>
                <div class="stat-icon bg-stat-purple">
                    <i class="bi bi-moon-stars-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Today's Celebrations & Greetings -->
    <div class="col-lg-6">
        <div class="card card-salon h-100 border-0 shadow-sm">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="bi bi-gift text-success fs-5 me-2"></i>
                    <h5 class="mb-0 fw-bold">Today's Anniversaries</h5>
                </div>
                <span class="badge rounded-pill bg-success-subtle text-success px-3 py-1 font-monospace">
                    <?= count($todays_anniversaries) ?> Bride(s)
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($todays_anniversaries)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-emoji-smile fs-1 text-secondary"></i>
                        <p class="mt-2 mb-0 small">No wedding anniversaries scheduled for today.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody>
                                <?php foreach ($todays_anniversaries as $item): ?>
                                    <?php 
                                    $c = $item['customer'];
                                    $msg = str_replace(
                                        ['{bride_name}', '{years}', '{salon_name}'],
                                        [$c['full_name'], $item['years'] . (in_array($item['years'], [1]) ? 'st' : ($item['years'] == 2 ? 'nd' : ($item['years'] == 3 ? 'rd' : 'th'))), $salon_name],
                                        $wa_template
                                    );
                                    $wa_url = build_whatsapp_url($c['mobile_number'], $msg);
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($c['full_name']) ?></div>
                                            <div class="small text-muted font-monospace"><?= htmlspecialchars($c['mobile_number']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge badge-anniversary rounded-pill px-2 py-1">
                                                <?= $item['years'] ?><?= in_array($item['years'], [1]) ? 'st' : ($item['years'] == 2 ? 'nd' : ($item['years'] == 3 ? 'rd' : 'th')) ?> Year
                                            </span>
                                            <div class="small text-muted mt-1">Wed: <?= format_date_in($c['wedding_date']) ?></div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?= $wa_url ?>" target="_blank" class="btn btn-whatsapp-sm">
                                                <i class="bi bi-whatsapp"></i> Wish WhatsApp
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upcoming 7 Days Forecast -->
    <div class="col-lg-6">
        <div class="card card-salon h-100 border-0 shadow-sm">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="bi bi-calendar-event text-primary fs-5 me-2"></i>
                    <h5 class="mb-0 fw-bold">7-Day Anniversary Forecast</h5>
                </div>
                <a href="calendar/index.php" class="small text-decoration-none">View Calendar &rarr;</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcoming_anniversaries)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-calendar-check fs-1 text-secondary"></i>
                        <p class="mt-2 mb-0 small">No anniversaries in the next 7 days.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody>
                                <?php foreach (array_slice($upcoming_anniversaries, 0, 6) as $item): ?>
                                    <?php 
                                    $c = $item['customer'];
                                    $is_eve = ($item['days_away'] === 1);
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($c['full_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($c['salon_branch'] ?: 'Main Branch') ?></div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-dark"><?= date('D, d M', strtotime($item['anniversary_date'])) ?></div>
                                            <div class="small text-muted font-monospace"><?= $item['years'] ?> Year Milestone</div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ($item['days_away'] === 0): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Today</span>
                                            <?php elseif ($is_eve): ?>
                                                <span class="badge bg-purple-subtle text-purple border border-purple-subtle" style="color: #7C3AED; background-color: #FAF5FF;">Tomorrow (Eve)</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border">In <?= $item['days_away'] ?> days</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Brides Registered -->
<div class="card card-salon border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <i class="bi bi-people text-secondary fs-5 me-2"></i>
            <h5 class="mb-0 fw-bold">Recent Registered Brides</h5>
        </div>
        <a href="customers/index.php" class="btn btn-sm btn-outline-secondary">Open Full Register &rarr;</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Code</th>
                        <th>Bride Name</th>
                        <th>Mobile</th>
                        <th>Wedding Date</th>
                        <th>Makeup Date</th>
                        <th>Package</th>
                        <th>Balance Due</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_brides)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No brides registered yet. Click "Add New Bride" to start!</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_brides as $c): ?>
                            <?php 
                            $balance = max(0, (float)$c['amount'] - (float)$c['advance_amount']);
                            ?>
                            <tr>
                                <td class="ps-4"><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($c['customer_code']) ?></span></td>
                                <td class="fw-semibold"><a href="customers/view.php?id=<?= $c['id'] ?>" class="text-dark text-decoration-none"><?= htmlspecialchars($c['full_name']) ?></a></td>
                                <td class="font-monospace small"><?= htmlspecialchars($c['mobile_number']) ?></td>
                                <td><?= format_date_in($c['wedding_date']) ?></td>
                                <td><?= format_date_in($c['makeup_date']) ?></td>
                                <td class="small"><?= htmlspecialchars($c['service_package'] ?: '—') ?></td>
                                <td>
                                    <?php if ($balance > 0): ?>
                                        <span class="badge bg-danger-subtle text-danger font-monospace"><?= format_inr($balance) ?> Due</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success">Paid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="customers/view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-light border" title="View Profile"><i class="bi bi-eye"></i></a>
                                    <a href="customers/edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-light border text-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

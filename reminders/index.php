<?php
/**
 * Safiya's Makeover — Reminders Hub
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Anniversary & Appointment Reminders';
$db = get_db();
$today_str = get_kolkata_today();
$current_year = (int)date('Y', strtotime($today_str));
$salon_name = get_setting('salon_name', "Safiya's Makeover");

// Handle Mark As Sent Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_sent') {
    $cust_id = (int)$_POST['customer_id'];
    $anni_year = (int)$_POST['anniversary_year'];
    $channel = trim($_POST['channel'] ?? 'whatsapp');
    $rem_type = trim($_POST['reminder_type'] ?? 'today_greeting');

    $idempotency_key = build_idempotency_key($cust_id, $anni_year, $channel, $rem_type);

    $stmt = $db->prepare("INSERT INTO reminder_logs (
        idempotency_key, customer_id, anniversary_year, channel, reminder_type, 
        status, scheduled_for, sent_at
    ) VALUES (
        :key, :cid, :yr, :ch, :rtype, 
        'sent', :sched, NOW()
    ) ON DUPLICATE KEY UPDATE status = 'sent', sent_at = NOW()");

    $stmt->execute([
        'key' => $idempotency_key,
        'cid' => $cust_id,
        'yr' => $anni_year,
        'ch' => $channel,
        'rtype' => $rem_type,
        'sched' => $today_str
    ]);

    set_flash('success', 'Reminder successfully logged as sent.');
    header("Location: index.php");
    exit;
}

// Fetch reminders data
$todays_anniversaries = get_todays_anniversaries();
$tomorrows_anniversaries = get_tomorrows_anniversaries();
$pre_makeup = get_pre_makeup_appointments();
$upcoming_anniversaries = get_upcoming_anniversaries(7);

// Load message templates
$wa_template = get_setting('whatsapp_anniversary_template', 'Dear {bride_name}, warm congratulations on your {years} Wedding Anniversary from {salon_name}!');
$wa_eve_template = get_setting('whatsapp_eve_template', 'Hello {bride_name}! Tomorrow is your special wedding anniversary! Warm wishes in advance from {salon_name}.');
$wa_pre_template = get_setting('whatsapp_pre_makeup_template', 'Hello {bride_name}, gentle reminder from {salon_name} regarding your bridal appointment tomorrow ({makeup_date}).');

// Check sent statuses for today
$sent_keys_stmt = $db->prepare("SELECT idempotency_key FROM reminder_logs WHERE scheduled_for = :today AND status = 'sent'");
$sent_keys_stmt->execute(['today' => $today_str]);
$sent_keys = $sent_keys_stmt->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold mb-1">Reminders &amp; Wishes Center</h2>
        <p class="text-muted small mb-0">Double-send protected dispatch center for wedding anniversaries and makeup appointments</p>
    </div>
    <div class="d-flex gap-2">
        <a href="../settings/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-gear me-1"></i> Edit Message Templates
        </a>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-pills mb-4" id="reminderTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="today-tab" data-bs-toggle="pill" data-bs-target="#today-pane" type="button" role="tab">
            <i class="bi bi-gift-fill me-1 text-success"></i> Today's Anniversaries
            <span class="badge rounded-pill bg-success ms-1"><?= count($todays_anniversaries) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="eve-tab" data-bs-toggle="pill" data-bs-target="#eve-pane" type="button" role="tab">
            <i class="bi bi-moon-stars-fill me-1" style="color: #7C3AED;"></i> Tomorrow's Eve Alert
            <span class="badge rounded-pill bg-purple ms-1" style="background-color: #7C3AED;"><?= count($tomorrows_anniversaries) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="premakeup-tab" data-bs-toggle="pill" data-bs-target="#premakeup-pane" type="button" role="tab">
            <i class="bi bi-bell-fill me-1 text-danger"></i> 1-Day Pre-Makeup Alerts
            <span class="badge rounded-pill bg-danger ms-1"><?= count($pre_makeup) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="forecast-tab" data-bs-toggle="pill" data-bs-target="#forecast-pane" type="button" role="tab">
            <i class="bi bi-calendar7 me-1 text-primary"></i> 7-Day Forecast
            <span class="badge rounded-pill bg-primary ms-1"><?= count($upcoming_anniversaries) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content" id="reminderTabsContent">
    <!-- Tab 1: Today's Anniversaries -->
    <div class="tab-pane fade show active" id="today-pane" role="tabpanel">
        <div class="card card-salon border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($todays_anniversaries)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                        <h6 class="mt-3 fw-bold">All caught up!</h6>
                        <p class="small mb-0">No bridal anniversaries scheduled for today (<?= date('d M Y') ?>).</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Bride Name</th>
                                    <th>Mobile</th>
                                    <th>Milestone</th>
                                    <th>Wedding Date</th>
                                    <th>Consent</th>
                                    <th>Dispatch Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todays_anniversaries as $item): ?>
                                    <?php 
                                    $c = $item['customer'];
                                    $key = build_idempotency_key($c['id'], $item['years'], 'whatsapp', 'today_greeting');
                                    $is_sent = in_array($key, $sent_keys);

                                    $msg = str_replace(
                                        ['{bride_name}', '{years}', '{salon_name}'],
                                        [$c['full_name'], $item['years'] . (in_array($item['years'], [1]) ? 'st' : ($item['years'] == 2 ? 'nd' : ($item['years'] == 3 ? 'rd' : 'th'))), $salon_name],
                                        $wa_template
                                    );
                                    $wa_url = build_whatsapp_url($c['mobile_number'], $msg);
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <a href="../customers/view.php?id=<?= $c['id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                                <?= htmlspecialchars($c['full_name']) ?>
                                            </a>
                                            <div class="small text-muted font-monospace"><?= htmlspecialchars($c['customer_code']) ?></div>
                                        </td>
                                        <td class="font-monospace small"><?= htmlspecialchars($c['mobile_number']) ?></td>
                                        <td>
                                            <span class="badge badge-anniversary px-2 py-1">
                                                <?= $item['years'] ?> Year Milestone
                                            </span>
                                        </td>
                                        <td><?= format_date_in($c['wedding_date']) ?></td>
                                        <td>
                                            <?php if ($c['whatsapp_consent']): ?>
                                                <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Yes</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger"><i class="bi bi-x-circle me-1"></i>No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_sent): ?>
                                                <span class="badge bg-success-subtle text-success"><i class="bi bi-check-all me-1"></i>Sent</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="<?= $wa_url ?>" target="_blank" class="btn btn-whatsapp-sm">
                                                    <i class="bi bi-whatsapp"></i> Wish WhatsApp
                                                </a>
                                                <?php if (!$is_sent): ?>
                                                    <form method="POST" action="index.php" class="d-inline">
                                                        <input type="hidden" name="action" value="mark_sent">
                                                        <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                                        <input type="hidden" name="anniversary_year" value="<?= $item['years'] ?>">
                                                        <input type="hidden" name="channel" value="whatsapp">
                                                        <input type="hidden" name="reminder_type" value="today_greeting">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Mark as logged">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
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

    <!-- Tab 2: Tomorrow's Eve Alert -->
    <div class="tab-pane fade" id="eve-pane" role="tabpanel">
        <div class="card card-salon border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($tomorrows_anniversaries)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-moon-stars fs-1 text-secondary"></i>
                        <h6 class="mt-3 fw-bold">No anniversaries tomorrow</h6>
                        <p class="small mb-0">No brides are scheduled for 1-day advance Eve greetings tomorrow.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Bride Name</th>
                                    <th>Mobile</th>
                                    <th>Milestone</th>
                                    <th>Anniversary Date</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tomorrows_anniversaries as $item): ?>
                                    <?php 
                                    $c = $item['customer'];
                                    $msg = str_replace(
                                        ['{bride_name}', '{salon_name}'],
                                        [$c['full_name'], $salon_name],
                                        $wa_eve_template
                                    );
                                    $wa_url = build_whatsapp_url($c['mobile_number'], $msg);
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-semibold"><?= htmlspecialchars($c['full_name']) ?></td>
                                        <td class="font-monospace small"><?= htmlspecialchars($c['mobile_number']) ?></td>
                                        <td><span class="badge badge-eve px-2 py-1"><?= $item['years'] ?> Years</span></td>
                                        <td>Tomorrow (<?= format_date_in($item['anniversary_date']) ?>)</td>
                                        <td class="text-end pe-4">
                                            <a href="<?= $wa_url ?>" target="_blank" class="btn btn-whatsapp-eve">
                                                <i class="bi bi-whatsapp"></i> Advance Wish
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

    <!-- Tab 3: Pre-Makeup Appointments (Tomorrow) -->
    <div class="tab-pane fade" id="premakeup-pane" role="tabpanel">
        <div class="card card-salon border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($pre_makeup)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-calendar2-check fs-1 text-secondary"></i>
                        <h6 class="mt-3 fw-bold">No makeup appointments scheduled for tomorrow</h6>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Bride Name</th>
                                    <th>Mobile</th>
                                    <th>Package</th>
                                    <th>Balance Due</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pre_makeup as $c): ?>
                                    <?php 
                                    $balance = max(0, (float)$c['amount'] - (float)$c['advance_amount']);
                                    $msg = str_replace(
                                        ['{bride_name}', '{makeup_date}', '{salon_name}'],
                                        [$c['full_name'], format_date_in($c['makeup_date']), $salon_name],
                                        $wa_pre_template
                                    );
                                    $wa_url = build_whatsapp_url($c['mobile_number'], $msg);
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-semibold"><?= htmlspecialchars($c['full_name']) ?></td>
                                        <td class="font-monospace small"><?= htmlspecialchars($c['mobile_number']) ?></td>
                                        <td><?= htmlspecialchars($c['service_package'] ?: 'Bridal Package') ?></td>
                                        <td>
                                            <?php if ($balance > 0): ?>
                                                <span class="badge bg-danger-subtle text-danger font-monospace"><?= format_inr($balance) ?> Due</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success">Paid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?= $wa_url ?>" target="_blank" class="btn btn-whatsapp-sm">
                                                <i class="bi bi-whatsapp"></i> Send Appointment Alert
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

    <!-- Tab 4: 7-Day Forecast -->
    <div class="tab-pane fade" id="forecast-pane" role="tabpanel">
        <div class="card card-salon border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Bride Name</th>
                                <th>Mobile</th>
                                <th>Anniversary Date</th>
                                <th>Milestone</th>
                                <th>Days Away</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_anniversaries as $item): ?>
                                <?php $c = $item['customer']; ?>
                                <tr>
                                    <td class="ps-4 fw-semibold"><?= htmlspecialchars($c['full_name']) ?></td>
                                    <td class="font-monospace small"><?= htmlspecialchars($c['mobile_number']) ?></td>
                                    <td><?= format_date_in($item['anniversary_date']) ?></td>
                                    <td><span class="badge badge-anniversary px-2 py-1"><?= $item['years'] ?>y Milestone</span></td>
                                    <td>
                                        <?php if ($item['days_away'] === 0): ?>
                                            <span class="badge bg-success">Today</span>
                                        <?php elseif ($item['days_away'] === 1): ?>
                                            <span class="badge bg-purple text-white" style="background-color: #7C3AED;">Tomorrow</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border">In <?= $item['days_away'] ?> days</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

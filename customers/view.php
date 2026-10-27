<?php
/**
 * Safiya's Makeover — Bride Profile & History
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

$page_title = $customer['full_name'];
$balance = max(0, (float)$customer['amount'] - (float)$customer['advance_amount']);
$clean_phone = preg_replace('/[^\d]/', '', $customer['mobile_number']);
$salon_name = get_setting('salon_name', "Safiya's Makeover");

// Anniversary Calculation
$next_anniversary = null;
if ($customer['wedding_date']) {
    $today_str = get_kolkata_today();
    $current_year = (int)date('Y', strtotime($today_str));
    $target_date = get_anniversary_date_for_year($customer['wedding_date'], $current_year);
    
    // If today is past this year's date, compute next year
    if ($target_date < $today_str) {
        $target_year = $current_year + 1;
        $target_date = get_anniversary_date_for_year($customer['wedding_date'], $target_year);
    } else {
        $target_year = $current_year;
    }
    
    $wed_year = (int)date('Y', strtotime($customer['wedding_date']));
    $years_milestone = $target_year - $wed_year;
    $d1 = new DateTime($today_str);
    $d2 = new DateTime($target_date);
    $days_away = $d1->diff($d2)->days;

    $next_anniversary = [
        'date' => $target_date,
        'milestone_years' => $years_milestone,
        'days_away' => $days_away,
        'is_today' => ($target_date === $today_str),
        'is_eve' => ($days_away === 1)
    ];
}

// Fetch Reminder Logs for this client
$log_stmt = $db->prepare("SELECT * FROM reminder_logs WHERE customer_id = :id ORDER BY id DESC LIMIT 20");
$log_stmt->execute(['id' => $id]);
$reminder_logs = $log_stmt->fetchAll();

// Pre-fill WhatsApp greeting message
$wa_template = get_setting('whatsapp_anniversary_template', 'Dear {bride_name}, warm congratulations on your {years} Wedding Anniversary from {salon_name}!');
$years_str = $next_anniversary ? $next_anniversary['milestone_years'] . (in_array($next_anniversary['milestone_years'], [1]) ? 'st' : ($next_anniversary['milestone_years'] == 2 ? 'nd' : ($next_anniversary['milestone_years'] == 3 ? 'rd' : 'th'))) : '';
$wa_msg = str_replace(
    ['{bride_name}', '{years}', '{salon_name}'],
    [$customer['full_name'], $years_str, $salon_name],
    $wa_template
);
$wa_url = build_whatsapp_url($customer['mobile_number'], $wa_msg);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Back"><i class="bi bi-arrow-left"></i></a>
        <div>
            <div class="d-flex align-items-center gap-2">
                <h2 class="h3 fw-bold mb-0"><?= htmlspecialchars($customer['full_name']) ?></h2>
                <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($customer['customer_code']) ?></span>
                <?php if ($customer['slip_number']): ?>
                    <span class="badge bg-secondary-subtle text-secondary font-monospace">#<?= htmlspecialchars($customer['slip_number']) ?></span>
                <?php endif; ?>
            </div>
            <div class="text-muted small mt-1">
                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($customer['salon_branch'] ?: 'Main Branch') ?> &bull; 
                <i class="bi bi-telephone ms-2 me-1"></i><?= htmlspecialchars($customer['mobile_number']) ?>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $wa_url ?>" target="_blank" class="btn btn-whatsapp-sm px-3 py-2">
            <i class="bi bi-whatsapp"></i> Send WhatsApp Wish
        </a>
        <a href="edit.php?id=<?= $customer['id'] ?>" class="btn btn-outline-primary btn-sm px-3">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
        <a href="delete.php?id=<?= $customer['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this bride profile permanently?');">
            <i class="bi bi-trash"></i>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Profile & Details Column -->
    <div class="col-lg-8">
        <!-- Anniversary Status Card -->
        <?php if ($next_anniversary): ?>
            <div class="card card-salon border-0 shadow-sm p-3 mb-4" style="background: linear-gradient(135deg, #FDF5F7 0%, #FFFFFF 100%); border-left: 5px solid #D4738C !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-stat-pink me-3">
                            <i class="bi bi-heart-fill"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">
                                <?= $next_anniversary['milestone_years'] ?><?= in_array($next_anniversary['milestone_years'], [1]) ? 'st' : ($next_anniversary['milestone_years'] == 2 ? 'nd' : ($next_anniversary['milestone_years'] == 3 ? 'rd' : 'th')) ?> Wedding Anniversary Milestone
                            </h5>
                            <span class="text-muted small">
                                Date: <strong><?= date('l, d F Y', strtotime($next_anniversary['date'])) ?></strong>
                            </span>
                        </div>
                    </div>
                    <div>
                        <?php if ($next_anniversary['is_today']): ?>
                            <span class="badge bg-success fs-6 px-3 py-2">🎉 Celebrating Today!</span>
                        <?php elseif ($next_anniversary['is_eve']): ?>
                            <span class="badge bg-purple px-3 py-2 text-white" style="background-color: #7C3AED;">🌙 Tomorrow (1-Day Eve)</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark border px-3 py-2 font-monospace">In <?= $next_anniversary['days_away'] ?> days</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning border-0 shadow-sm mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>No Wedding Date on Record:</strong> Anniversary cannot be tracked. Please <a href="edit.php?id=<?= $customer['id'] ?>" class="alert-link">add her wedding date</a> to enable reminders.
            </div>
        <?php endif; ?>

        <!-- Full Customer Metadata -->
        <div class="card card-salon border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h5 class="mb-0 fw-bold">Service &amp; Booking Details</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="small text-muted d-block">Booking Type</label>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($customer['booking_type']) ?></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="small text-muted d-block">Service Package</label>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($customer['service_package'] ?: '—') ?></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="small text-muted d-block">Wedding Date</label>
                        <span class="fw-semibold text-dark"><?= format_date_in($customer['wedding_date']) ?></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="small text-muted d-block">Makeup Appointment Date</label>
                        <span class="fw-semibold text-dark"><?= format_date_in($customer['makeup_date']) ?></span>
                    </div>
                    <div class="col-sm-4">
                        <label class="small text-muted d-block">Total Package Amount</label>
                        <span class="fw-bold font-monospace text-dark"><?= format_inr($customer['amount']) ?></span>
                    </div>
                    <div class="col-sm-4">
                        <label class="small text-muted d-block">Advance Paid</label>
                        <span class="fw-bold font-monospace text-success"><?= format_inr($customer['advance_amount']) ?></span>
                    </div>
                    <div class="col-sm-4">
                        <label class="small text-muted d-block">Balance Due</label>
                        <?php if ($balance > 0): ?>
                            <span class="fw-bold font-monospace text-danger"><?= format_inr($balance) ?> Due</span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success">Fully Paid</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted d-block">Special Notes &amp; Styling Preferences</label>
                        <div class="p-3 bg-light rounded-2 text-secondary small">
                            <?= nl2br(htmlspecialchars($customer['notes'] ?: 'No notes recorded.')) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reminder Dispatch History -->
        <div class="card card-salon border-0 shadow-sm">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h5 class="mb-0 fw-bold">Anniversary Greeting &amp; Reminder Logs</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($reminder_logs)): ?>
                    <div class="p-4 text-center text-muted small">No past reminder dispatches recorded for this customer yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Year</th>
                                    <th>Channel</th>
                                    <th>Type</th>
                                    <th>Scheduled For</th>
                                    <th>Status</th>
                                    <th>Sent At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reminder_logs as $log): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace fw-bold"><?= $log['anniversary_year'] ?></td>
                                        <td><span class="text-capitalize"><?= htmlspecialchars($log['channel']) ?></span></td>
                                        <td><span class="text-capitalize"><?= str_replace('_', ' ', htmlspecialchars($log['reminder_type'])) ?></span></td>
                                        <td><?= format_date_in($log['scheduled_for']) ?></td>
                                        <td>
                                            <?php if ($log['status'] === 'sent'): ?>
                                                <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Sent</span>
                                            <?php elseif ($log['status'] === 'failed'): ?>
                                                <span class="badge bg-danger-subtle text-danger"><i class="bi bi-x-circle me-1"></i>Failed</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border"><?= htmlspecialchars($log['status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $log['sent_at'] ? date('d/m/Y H:i', strtotime($log['sent_at'])) : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Sidebar Quick Actions -->
    <div class="col-lg-4">
        <!-- Quick WhatsApp Composer -->
        <div class="card card-salon border-0 shadow-sm p-4 mb-4">
            <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-whatsapp text-success fs-5"></i> 1-Click WhatsApp Wish
            </h6>
            <div class="p-3 bg-light rounded-3 small text-secondary mb-3">
                <?= nl2br(htmlspecialchars($wa_msg)) ?>
            </div>
            <a href="<?= $wa_url ?>" target="_blank" class="btn btn-whatsapp-sm justify-content-center py-2 w-100">
                <i class="bi bi-send-fill me-1"></i> Open in WhatsApp
            </a>
            <small class="text-muted text-center d-block mt-2" style="font-size: 0.72rem;">Opens official WhatsApp web / mobile app with pre-filled message.</small>
        </div>

        <!-- Consent Status Card -->
        <div class="card card-salon border-0 shadow-sm p-4">
            <h6 class="fw-bold mb-3">Consent &amp; Notification Settings</h6>
            <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                <div>
                    <div class="fw-semibold small">WhatsApp Wishes Consent</div>
                    <div class="text-muted" style="font-size: 0.75rem;">Client allowed annual greetings</div>
                </div>
                <div>
                    <?php if ($customer['whatsapp_consent']): ?>
                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-lg"></i> Enabled</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger"><i class="bi bi-x-lg"></i> Disabled</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="fw-semibold small">Pre-Makeup 1-Day Alert</div>
                    <div class="text-muted" style="font-size: 0.75rem;">Reminder before service date</div>
                </div>
                <div>
                    <?php if ($customer['pre_makeup_reminder_enabled']): ?>
                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-lg"></i> Enabled</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-x-lg"></i> Disabled</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

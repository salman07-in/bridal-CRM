<?php
/**
 * Safiya's Makeover — Interactive Monthly Anniversary Calendar
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Anniversary Calendar';
$db = get_db();
$today_str = get_kolkata_today();
$today_dt = new DateTime($today_str);

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)$today_dt->format('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)$today_dt->format('n');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$first_day = new DateTime(sprintf('%04d-%02d-01', $year, $month));
$days_in_month = (int)$first_day->format('t');
$start_day_of_week = (int)$first_day->format('w'); // 0 (Sun) to 6 (Sat)
$month_name = $first_day->format('F');

// Fetch all customers with valid wedding dates
$stmt = $db->prepare("SELECT id, customer_code, full_name, mobile_number, wedding_date, salon_branch 
                      FROM customers 
                      WHERE wedding_date IS NOT NULL AND YEAR(wedding_date) < :target_year");
$stmt->execute(['target_year' => $year]);
$all_brides = $stmt->fetchAll();

// Map brides to days of this month
$events_by_day = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    $events_by_day[$d] = [];
}

foreach ($all_brides as $b) {
    $anni_date_str = get_anniversary_date_for_year($b['wedding_date'], $year);
    $a_year = (int)substr($anni_date_str, 0, 4);
    $a_month = (int)substr($anni_date_str, 5, 2);
    $a_day = (int)substr($anni_date_str, 8, 2);

    if ($a_year === $year && $a_month === $month) {
        $wed_year = (int)date('Y', strtotime($b['wedding_date']));
        $years_milestone = $year - $wed_year;
        if ($years_milestone >= 1) {
            $events_by_day[$a_day][] = [
                'id' => $b['id'],
                'name' => $b['full_name'],
                'years' => $years_milestone,
                'mobile' => $b['mobile_number']
            ];
        }
    }
}

// Previous & Next Month URLs
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold mb-1">Interactive Anniversary Calendar</h2>
        <p class="text-muted small mb-0">Monthly grid mapping bridal wedding anniversaries in Indian Standard Time</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php?year=<?= $prev_year ?>&month=<?= $prev_month ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i>
        </a>
        <span class="fw-bold px-2 fs-5"><?= $month_name ?> <?= $year ?></span>
        <a href="index.php?year=<?= $next_year ?>&month=<?= $next_month ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-right"></i>
        </a>
        <a href="index.php?year=<?= $today_dt->format('Y') ?>&month=<?= $today_dt->format('n') ?>" class="btn btn-salon-outline btn-sm ms-2">
            Today
        </a>
    </div>
</div>

<div class="card card-salon border-0 shadow-sm p-3 mb-4">
    <!-- Calendar Matrix Grid (Responsive horizontal scroll on mobile) -->
    <div class="table-responsive">
        <div class="cal-grid" style="min-width: 640px;">
            <!-- Headers -->
            <div class="cal-header-cell text-danger">Sun</div>
            <div class="cal-header-cell">Mon</div>
            <div class="cal-header-cell">Tue</div>
            <div class="cal-header-cell">Wed</div>
            <div class="cal-header-cell">Thu</div>
            <div class="cal-header-cell">Fri</div>
            <div class="cal-header-cell text-primary">Sat</div>

            <!-- Leading Empty Days -->
            <?php for ($i = 0; $i < $start_day_of_week; $i++): ?>
                <div class="cal-day-cell cal-other-month"></div>
            <?php endfor; ?>

            <!-- Days of Month -->
            <?php for ($d = 1; $d <= $days_in_month; $d++): ?>
                <?php 
                $cell_date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $is_today = ($cell_date_str === $today_str);
                $day_events = $events_by_day[$d] ?? [];
                ?>
                <div class="cal-day-cell <?= $is_today ? 'cal-today' : '' ?>">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="cal-day-number"><?= $d ?></span>
                        <?php if (!empty($day_events)): ?>
                            <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;"><?= count($day_events) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php foreach ($day_events as $ev): ?>
                        <a href="../customers/view.php?id=<?= $ev['id'] ?>" class="cal-event-pill" title="<?= htmlspecialchars($ev['name']) ?> (<?= $ev['years'] ?> Years)">
                            <i class="bi bi-heart-fill me-1" style="font-size: 0.65rem;"></i>
                            <strong><?= htmlspecialchars($ev['name']) ?></strong> 
                            <span class="badge badge-anniversary ms-1" style="font-size: 0.6rem;"><?= $ev['years'] ?>y</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>

            <!-- Trailing Empty Days to complete row -->
            <?php 
            $total_slots = $start_day_of_week + $days_in_month;
            $remaining = (7 - ($total_slots % 7)) % 7;
            for ($i = 0; $i < $remaining; $i++): ?>
                <div class="cal-day-cell cal-other-month"></div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

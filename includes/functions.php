<?php
/**
 * Safiya's Makeover — Bridal CRM & Anniversary Reminder System
 * Global Utility Functions & Anniversary Engine
 */

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns today's date in Asia/Kolkata timezone (YYYY-MM-DD).
 */
function get_kolkata_today(): string {
    $dt = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    return $dt->format('Y-m-d');
}

/**
 * Returns true if the given year is a leap year.
 */
function is_leap_year(int $year): bool {
    return (($year % 4 === 0) && ($year % 100 !== 0)) || ($year % 400 === 0);
}

/**
 * Compute the calendar date for an anniversary in target_year.
 * Handles February 29 leap years:
 * - If target_year is leap year -> Feb 29
 * - If target_year is not a leap year -> Feb 28
 */
function get_anniversary_date_for_year(string $wedding_date, int $target_year): string {
    $parts = explode('-', $wedding_date);
    $month = (int)$parts[1];
    $day = (int)$parts[2];

    if ($month === 2 && $day === 29) {
        if (is_leap_year($target_year)) {
            return sprintf('%04d-02-29', $target_year);
        } else {
            return sprintf('%04d-02-28', $target_year);
        }
    }

    return sprintf('%04d-%02d-%02d', $target_year, $month, $day);
}

/**
 * Get customers celebrating wedding anniversary today.
 */
function get_todays_anniversaries(?string $reference_date = null): array {
    $db = get_db();
    $today_str = $reference_date ?: get_kolkata_today();
    $today = new DateTime($today_str);
    $current_year = (int)$today->format('Y');
    $current_month = (int)$today->format('m');
    $current_day = (int)$today->format('d');
    $is_leap = is_leap_year($current_year);

    // Filter query: wedding_date is NOT NULL, marriage year <= current_year - 1
    if ($current_month === 2 && $current_day === 28 && !$is_leap) {
        $sql = "SELECT * FROM customers 
                WHERE wedding_date IS NOT NULL 
                AND MONTH(wedding_date) = 2 
                AND DAY(wedding_date) IN (28, 29)
                AND YEAR(wedding_date) < :current_year
                ORDER BY full_name ASC";
    } else {
        $sql = "SELECT * FROM customers 
                WHERE wedding_date IS NOT NULL 
                AND MONTH(wedding_date) = :month 
                AND DAY(wedding_date) = :day
                AND YEAR(wedding_date) < :current_year
                ORDER BY full_name ASC";
    }

    $stmt = $db->prepare($sql);
    if ($current_month === 2 && $current_day === 28 && !$is_leap) {
        $stmt->execute(['current_year' => $current_year]);
    } else {
        $stmt->execute([
            'month' => $current_month,
            'day' => $current_day,
            'current_year' => $current_year
        ]);
    }

    $customers = $stmt->fetchAll();
    $results = [];

    foreach ($customers as $c) {
        $wed_year = (int)date('Y', strtotime($c['wedding_date']));
        $years = $current_year - $wed_year;
        if ($years >= 1) {
            $results[] = [
                'customer' => $c,
                'anniversary_date' => $today_str,
                'years' => $years,
                'target_year' => $current_year,
                'is_today' => true,
                'days_away' => 0
            ];
        }
    }

    return $results;
}

/**
 * Get customers celebrating anniversary tomorrow (1-day Eve alert).
 */
function get_tomorrows_anniversaries(?string $reference_date = null): array {
    $today_str = $reference_date ?: get_kolkata_today();
    $tomorrow = (new DateTime($today_str))->modify('+1 day')->format('Y-m-d');
    $res = get_todays_anniversaries($tomorrow);
    foreach ($res as &$r) {
        $r['is_today'] = false;
        $r['days_away'] = 1;
        $r['is_eve'] = true;
    }
    return $res;
}

/**
 * Get upcoming anniversaries for the next N days.
 */
function get_upcoming_anniversaries(int $advance_days = 7, ?string $reference_date = null): array {
    $db = get_db();
    $today_str = $reference_date ?: get_kolkata_today();
    $today = new DateTime($today_str);
    $current_year = (int)$today->format('Y');

    // Fetch all customers with valid wedding dates from past years
    $stmt = $db->prepare("SELECT * FROM customers WHERE wedding_date IS NOT NULL AND YEAR(wedding_date) < :next_year ORDER BY full_name ASC");
    $stmt->execute(['next_year' => $current_year + 2]);
    $customers = $stmt->fetchAll();

    $results = [];

    for ($i = 0; $i <= $advance_days; $i++) {
        $check_date = (clone $today)->modify("+{$i} days");
        $check_str = $check_date->format('Y-m-d');
        $check_year = (int)$check_date->format('Y');

        foreach ($customers as $c) {
            $wed_year = (int)date('Y', strtotime($c['wedding_date']));
            $anni_date_str = get_anniversary_date_for_year($c['wedding_date'], $check_year);

            if ($anni_date_str === $check_str) {
                $years = $check_year - $wed_year;
                if ($years >= 1) {
                    $results[] = [
                        'customer' => $c,
                        'anniversary_date' => $anni_date_str,
                        'years' => $years,
                        'target_year' => $check_year,
                        'is_today' => ($i === 0),
                        'days_away' => $i,
                        'is_eve' => ($i === 1)
                    ];
                }
            }
        }
    }

    usort($results, function($a, $b) {
        if ($a['days_away'] === $b['days_away']) {
            return strcmp($a['customer']['full_name'], $b['customer']['full_name']);
        }
        return $a['days_away'] <=> $b['days_away'];
    });

    return $results;
}

/**
 * Pre-makeup appointment reminders (service is tomorrow).
 */
function get_pre_makeup_appointments(?string $reference_date = null): array {
    $db = get_db();
    $today_str = $reference_date ?: get_kolkata_today();
    $tomorrow_str = (new DateTime($today_str))->modify('+1 day')->format('Y-m-d');

    $stmt = $db->prepare("SELECT * FROM customers WHERE makeup_date = :tomorrow AND pre_makeup_reminder_enabled = 1 ORDER BY full_name ASC");
    $stmt->execute(['tomorrow' => $tomorrow_str]);
    return $stmt->fetchAll();
}

/**
 * Generate sequential customer code (e.g. SAF-2026-0001).
 */
function generate_customer_code(?int $year = null): string {
    $db = get_db();
    if (!$year) {
        $year = (int)date('Y');
    }
    $prefix = "SAF-{$year}-";

    $stmt = $db->prepare("SELECT customer_code FROM customers WHERE customer_code LIKE :prefix ORDER BY id DESC LIMIT 1");
    $stmt->execute(['prefix' => "{$prefix}%"]);
    $last_code = $stmt->fetchColumn();

    if ($last_code && strpos($last_code, $prefix) === 0) {
        $num_part = (int)substr($last_code, strlen($prefix));
        $next_seq = $num_part + 1;
    } else {
        $next_seq = 1;
    }

    return sprintf("%s%04d", $prefix, $next_seq);
}

/**
 * Clean & normalize Indian mobile number.
 */
function clean_indian_phone(?string $phone): string {
    if (!$phone) return '';
    $digits = preg_replace('/[^\d]/', '', $phone);
    if (strlen($digits) === 10) {
        return '+91' . $digits;
    }
    if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') {
        return '+' . $digits;
    }
    if (strlen($digits) === 11 && substr($digits, 0, 1) === '0') {
        return '+91' . substr($digits, 1);
    }
    return '+' . $digits;
}

/**
 * Format currency in Indian Rupees.
 */
function format_inr($amount): string {
    if ($amount === null || $amount === '') return '—';
    return '₹ ' . number_format((float)$amount, 2);
}

/**
 * Format Date to Indian format DD/MM/YYYY.
 */
function format_date_in(?string $date_str): string {
    if (!$date_str) return '—';
    $timestamp = strtotime($date_str);
    if (!$timestamp) return htmlspecialchars($date_str);
    return date('d/m/Y', $timestamp);
}

/**
 * Setting Helper: Get value.
 */
function get_setting(string $key, $default = ''): string {
    $db = get_db();
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (string)$val : (string)$default;
    } catch (Exception $e) {
        return (string)$default;
    }
}

/**
 * Setting Helper: Set value.
 */
function set_setting(string $key, $value, ?string $description = null): void {
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO settings (key_name, value, description) VALUES (:key, :val, :desc) ON DUPLICATE KEY UPDATE value = :val");
    $stmt->execute([
        'key' => $key,
        'val' => (string)$value,
        'desc' => $description
    ]);
}

/**
 * Audit Log Helper.
 */
function log_audit(string $action, string $entity_type, $entity_id = null, $details = null): void {
    $db = get_db();
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $details_str = is_array($details) ? json_encode($details) : (string)$details;

    try {
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (:uid, :act, :etype, :eid, :det, :ip)");
        $stmt->execute([
            'uid' => $user_id,
            'act' => $action,
            'etype' => $entity_type,
            'eid' => $entity_id ? (string)$entity_id : null,
            'det' => $details_str,
            'ip' => $ip
        ]);
    } catch (Exception $e) {
        // Silently prevent audit logging from breaking main application flow
    }
}

/**
 * Session Flash Messages.
 */
function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

/**
 * Build canonical Idempotency Key.
 */
function build_idempotency_key(int $customer_id, int $anniversary_year, string $channel, string $reminder_type): string {
    return sprintf("CUST_%d_Y%d_%s_%s", $customer_id, $anniversary_year, strtolower(trim($channel)), strtolower(trim($reminder_type)));
}

/**
 * Build WhatsApp click-to-chat URL (wa.me).
 */
function build_whatsapp_url(string $phone, string $message): string {
    $clean = preg_replace('/[^\d]/', '', $phone);
    return "https://wa.me/{$clean}?text=" . rawurlencode($message);
}

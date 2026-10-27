<?php
/**
 * Safiya's Makeover — Automated Daily Reminders Runner
 * Run daily at 09:00:00 AM IST via Hostinger Cron Jobs
 * 
 * Command in Hostinger hPanel Cron:
 * php /home/uXXXX/public_html/cron/run_reminders.php
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Allow running via Web only if CRON_TOKEN matches
if (php_sapi_name() !== 'cli') {
    $expected_token = getenv('CRON_TOKEN') ?: 'safiya_cron_secret_key_2026';
    $provided_token = $_GET['token'] ?? '';
    if ($provided_token !== $expected_token) {
        http_response_code(403);
        die("Access denied: Invalid cron token.");
    }
}

$today_str = get_kolkata_today();
$current_year = (int)date('Y', strtotime($today_str));
$db = get_db();

echo "[*] Safiya's Makeover Daily Anniversary Scan Starting: {$today_str} (Asia/Kolkata)\n";

// 1. Process Today's Anniversaries
$todays = get_todays_anniversaries($today_str);
$today_count = count($todays);
echo "[+] Today's Anniversaries: {$today_count}\n";

foreach ($todays as $item) {
    $c = $item['customer'];
    $key = build_idempotency_key($c['id'], $item['years'], 'whatsapp', 'today_greeting');
    
    // Insert into reminder_logs if not already logged
    $stmt = $db->prepare("INSERT IGNORE INTO reminder_logs (
        idempotency_key, customer_id, anniversary_year, channel, reminder_type, 
        status, scheduled_for, created_at
    ) VALUES (
        :key, :cid, :yr, 'whatsapp', 'today_greeting', 
        'pending', :sched, NOW()
    )");
    $stmt->execute([
        'key' => $key,
        'cid' => $c['id'],
        'yr' => $item['years'],
        'sched' => $today_str
    ]);
}

// 2. Process Tomorrow's Eve Anniversaries
$tomorrows = get_tomorrows_anniversaries($today_str);
$tomorrow_count = count($tomorrows);
echo "[+] Tomorrow's Anniversaries (1-Day Eve): {$tomorrow_count}\n";

foreach ($tomorrows as $item) {
    $c = $item['customer'];
    $key = build_idempotency_key($c['id'], $item['years'], 'whatsapp', 'advance_eve');
    
    $stmt = $db->prepare("INSERT IGNORE INTO reminder_logs (
        idempotency_key, customer_id, anniversary_year, channel, reminder_type, 
        status, scheduled_for, created_at
    ) VALUES (
        :key, :cid, :yr, 'whatsapp', 'advance_eve', 
        'pending', :sched, NOW()
    )");
    $stmt->execute([
        'key' => $key,
        'cid' => $c['id'],
        'yr' => $item['years'],
        'sched' => $today_str
    ]);
}

// 3. Process 1-Day Pre-Makeup Appointments
$pre_makeup = get_pre_makeup_appointments($today_str);
$pre_makeup_count = count($pre_makeup);
echo "[+] 1-Day Pre-Makeup Appointments: {$pre_makeup_count}\n";

// 4. Send Owner Daily Summary Digest Email
$owner_email = get_setting('salon_owner_email', '');
$send_digest = get_setting('send_owner_daily_digest', '1');
$email_sent = false;

if ($send_digest === '1' && !empty($owner_email)) {
    $salon_name = get_setting('salon_name', "Safiya's Makeover");
    $subject = "Daily Bridal Digest [{$today_str}] — {$salon_name}";
    
    $message = "Hello Salon Owner,\n\n";
    $message .= "Here is your daily anniversary & appointment report for today ({$today_str}):\n\n";
    $message .= "• Today's Anniversaries: {$today_count} Bride(s)\n";
    foreach ($todays as $t) {
        $message .= "   - {$t['customer']['full_name']} ({$t['years']} Year Milestone, Mobile: {$t['customer']['mobile_number']})\n";
    }
    $message .= "\n• Tomorrow's Anniversaries (Eve): {$tomorrow_count} Bride(s)\n";
    foreach ($tomorrows as $tm) {
        $message .= "   - {$tm['customer']['full_name']} ({$tm['years']} Year Milestone, Mobile: {$tm['customer']['mobile_number']})\n";
    }
    $message .= "\n• Tomorrow's Makeup Appointments: {$pre_makeup_count} Booking(s)\n";
    foreach ($pre_makeup as $pm) {
        $message .= "   - {$pm['full_name']} ({$pm['service_package']}, Mobile: {$pm['mobile_number']})\n";
    }
    $message .= "\nOpen your studio dashboard to send greetings in 1 click:\n";
    $message .= "https://" . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') . "/\n\n";
    $message .= "Safiya's Makeover CRM Engine\n";

    $headers = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'safiyasmakeover.com') . "\r\n" .
               "X-Mailer: PHP/" . phpversion();

    $email_sent = @mail($owner_email, $subject, $message, $headers);
    echo "[+] Owner Daily Digest Email: " . ($email_sent ? "Dispatched" : "Skipped/Failed") . "\n";
}

// Log execution in audit trail
log_audit('cron_reminders', 'reminder', null, [
    'today_count' => $today_count,
    'tomorrow_count' => $tomorrow_count,
    'pre_makeup_count' => $pre_makeup_count,
    'digest_sent' => $email_sent
]);

echo "[SUCCESS] Daily Reminder Routine Completed Successfully.\n";

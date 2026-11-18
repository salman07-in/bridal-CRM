<?php
/**
 * Safiya's Makeover — WhatsApp API Endpoint
 * Handles background WhatsApp sending via the linked QR gateway
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sent' => false, 'error' => 'Method not allowed. Only POST is accepted.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$phone = trim($input['phone'] ?? '');
$message = trim($input['msg'] ?? $input['message'] ?? '');
$customer_id = isset($input['customer_id']) ? (int)$input['customer_id'] : null;

if (empty($phone) || empty($message)) {
    http_response_code(400);
    echo json_encode(['sent' => false, 'error' => 'Phone number and message text are required.']);
    exit;
}

// Clean phone number (Indian format: 10 digits or 91 prefix)
$clean_phone = preg_replace('/[^\d]/', '', $phone);
if (strlen($clean_phone) === 10) {
    $clean_phone = '91' . $clean_phone;
}

// Call WhatsApp gateway service
$result = whatsapp_send_message($clean_phone, $message);

if ($result['sent']) {
    // Log dispatch if customer_id provided
    if ($customer_id) {
        try {
            $db = get_db();
            $stmt = $db->prepare("INSERT INTO reminder_logs (customer_id, channel, reminder_type, target_year, message_body, status, sent_at) VALUES (:cid, 'whatsapp', 'anniversary_greeting', :year, :body, 'SENT', NOW())");
            $stmt->execute([
                'cid' => $customer_id,
                'year' => (int)date('Y'),
                'body' => $message
            ]);
        } catch (Exception $e) {
            // Ignore DB log failures, message is already sent
        }
    }

    echo json_encode(['sent' => true, 'message' => 'WhatsApp message sent successfully!']);
} else {
    echo json_encode(['sent' => false, 'error' => $result['error'] ?? 'Failed to send WhatsApp message.']);
}

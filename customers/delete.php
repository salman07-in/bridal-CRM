<?php
/**
 * Safiya's Makeover — Delete Customer Action
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $db->prepare("SELECT full_name, customer_code FROM customers WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $cust = $stmt->fetch();

    if ($cust) {
        $del = $db->prepare("DELETE FROM customers WHERE id = :id");
        $del->execute(['id' => $id]);
        log_audit('delete', 'customer', $id, ['name' => $cust['full_name'], 'code' => $cust['customer_code']]);
        set_flash('success', "Customer record '{$cust['full_name']}' was successfully deleted.");
    }
}

header("Location: index.php");
exit;

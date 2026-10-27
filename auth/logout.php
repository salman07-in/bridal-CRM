<?php
/**
 * Safiya's Makeover — Bridal CRM Logout Action
 */

require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    log_audit('logout', 'user', $_SESSION['user_id']);
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: login.php");
exit;

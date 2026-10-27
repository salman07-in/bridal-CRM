<?php
/**
 * Safiya's Makeover — Bridal CRM Security Guard
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // Determine base url to reach login
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    // Normalize path to root
    $login_url = (strpos($_SERVER['REQUEST_URI'], '/auth/') !== false) ? 'login.php' : '/auth/login.php';
    
    // Fallback relative path calculation
    $depth = substr_count(trim($_SERVER['REQUEST_URI'], '/'), '/');
    $prefix = str_repeat('../', max(0, $depth - 1));
    if ($prefix === '') $prefix = './';
    
    header("Location: {$prefix}auth/login.php");
    exit;
}

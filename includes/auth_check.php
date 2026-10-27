<?php
/**
 * Safiya's Makeover — Bridal CRM Security Guard
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $depth = substr_count(trim($script_dir, '/\\'), '/');
    $root = str_repeat('../', $depth);
    if ($root === '') $root = './';
    
    header("Location: {$root}auth/login.php");
    echo "<script>window.location.href='{$root}auth/login.php';</script>";
    exit;
}

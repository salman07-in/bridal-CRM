<?php
/**
 * Safiya's Makeover — Bridal CRM Security Guard
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    $root = get_app_root();
    header("Location: {$root}auth/login.php");
    echo "<script>window.location.href='{$root}auth/login.php';</script>";
    exit;
}

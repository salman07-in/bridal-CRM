<?php
/**
 * Safiya's Makeover — Bridal CRM Header Component
 */

require_once __DIR__ . '/functions.php';

// Calculate relative path to project root
$root = get_app_root();

// Fetch salon branding settings
$salon_name = get_setting('salon_name', "Safiya's Makeover");
$salon_tagline = get_setting('salon_tagline', "Bridal & Makeover Studio");
$salon_branch = get_setting('salon_branch', "Main Branch");
$salon_phone = get_setting('salon_phone', "+91 98765 43210");

// Current active page detection
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_path = $_SERVER['PHP_SELF'];

// Current user details
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Admin';
$user_full_name = $_SESSION['user_full_name'] ?? 'Studio Administrator';
$user_role = $_SESSION['user_role'] ?? 'staff';
$is_admin = ($user_role === 'admin');

// Reminders count for badge
$today_anniversaries = get_todays_anniversaries();
$pending_reminders_count = count($today_anniversaries);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — ' : '' ?><?= htmlspecialchars($salon_name) ?> Bridal CRM</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & Playfair Display -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Core Responsive & Layout Safety CSS -->
    <style>
        :root {
            --salon-primary: #D4738C;
            --salon-primary-hover: #BF5771;
            --salon-primary-light: #FDF5F7;
            --salon-primary-border: #F3D5DE;
            --salon-sidebar-width: 260px;
            --salon-border: #E2E8F0;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #F8FAFC;
            color: #2D3748;
            margin: 0;
            min-height: 100vh;
        }
        #sidebar-wrapper {
            width: 260px;
            min-height: 100vh;
            background-color: #FFFFFF;
            border-right: 1px solid #E2E8F0;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            transition: margin 0.25s ease-in-out, transform 0.25s ease-in-out;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        #page-content-wrapper {
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin 0.25s ease-in-out, width 0.25s ease-in-out;
            background-color: #F8FAFC;
        }
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 1035;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }
        @media (max-width: 991.98px) {
            #sidebar-wrapper {
                margin-left: -260px;
                box-shadow: 4px 0 25px rgba(0, 0, 0, 0.15);
            }
            #page-content-wrapper {
                margin-left: 0 !important;
                width: 100% !important;
            }
            body.sidebar-toggled #sidebar-wrapper {
                margin-left: 0 !important;
            }
            body.sidebar-toggled .sidebar-backdrop {
                display: block !important;
            }
        }
        .text-salon {
            color: #D4738C !important;
        }
        .brand-logo-text {
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #D4738C;
        }
    </style>

    <!-- Custom Salon CRM Stylesheet -->
    <link href="<?= $root ?>assets/css/custom.css" rel="stylesheet">
    <?php if (isset($extra_css)): ?><?= $extra_css ?><?php endif; ?>
</head>
<body>

    <?php if ($user_id): ?>
    <!-- Sidebar Navigation -->
    <div id="sidebar-wrapper">
        <div class="sidebar-brand d-flex align-items-center justify-content-between">
            <div>
                <a href="<?= $root ?>index.php" class="text-decoration-none">
                    <span class="brand-logo-text fs-4"><?= htmlspecialchars($salon_name) ?></span>
                    <div class="text-muted small" style="font-size: 0.75rem;"><?= htmlspecialchars($salon_tagline) ?></div>
                </a>
            </div>
            <button class="btn btn-sm btn-light d-lg-none" id="sidebarCloseBtn" type="button" aria-label="Close Sidebar" onclick="document.body.classList.remove('sidebar-toggled');">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="nav-sidebar">
            <div class="text-uppercase text-muted px-3 py-1 mb-1 fw-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Main Menu</div>
            <a class="nav-link <?= ($current_page === 'index' && strpos($current_path, '/customers/') === false && strpos($current_path, '/calendar/') === false && strpos($current_path, '/reminders/') === false && strpos($current_path, '/import_export/') === false && strpos($current_path, '/settings/') === false) ? 'active' : '' ?>" href="<?= $root ?>index.php">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
            <a class="nav-link <?= ($current_page === 'add' && strpos($current_path, '/customers/') !== false) ? 'active' : '' ?>" href="<?= $root ?>customers/add.php">
                <i class="bi bi-person-plus-fill text-salon"></i> Add Bride
            </a>
            <a class="nav-link <?= (strpos($current_path, '/customers/') !== false && $current_page !== 'add') ? 'active' : '' ?>" href="<?= $root ?>customers/index.php">
                <i class="bi bi-people"></i> Customer Register
            </a>
            <a class="nav-link <?= (strpos($current_path, '/calendar/') !== false) ? 'active' : '' ?>" href="<?= $root ?>calendar/index.php">
                <i class="bi bi-calendar-heart"></i> Anniversary Calendar
            </a>

            <div class="text-uppercase text-muted px-3 py-1 mt-3 mb-1 fw-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Data &amp; Automation</div>
            <a class="nav-link <?= (strpos($current_path, '/import_export/') !== false) ? 'active' : '' ?>" href="<?= $root ?>import_export/index.php">
                <i class="bi bi-file-earmark-spreadsheet"></i> Import / Export
            </a>
            <a class="nav-link <?= (strpos($current_path, '/reminders/') !== false) ? 'active' : '' ?>" href="<?= $root ?>reminders/index.php">
                <i class="bi bi-bell"></i> Reminders
                <?php if ($pending_reminders_count > 0): ?>
                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;"><?= $pending_reminders_count ?></span>
                <?php endif; ?>
            </a>
            <?php if ($is_admin): ?>
            <a class="nav-link <?= (strpos($current_path, '/settings/') !== false) ? 'active' : '' ?>" href="<?= $root ?>settings/index.php">
                <i class="bi bi-gear"></i> Settings
            </a>
            <?php endif; ?>
        </div>

        <!-- Sidebar User Footer -->
        <div class="p-3 border-top bg-light mt-auto">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-stat-pink d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; font-weight: bold; background-color: #FDF5F7; color: #D4738C;">
                    <?= strtoupper(substr($user_full_name, 0, 1)) ?>
                </div>
                <div class="overflow-hidden me-auto">
                    <div class="fw-semibold text-truncate small" style="max-width: 130px;"><?= htmlspecialchars($user_full_name) ?></div>
                    <div class="text-muted text-capitalize" style="font-size: 0.72rem;"><?= htmlspecialchars($user_role) ?></div>
                </div>
                <a href="<?= $root ?>auth/logout.php" class="btn btn-sm btn-outline-danger" title="Sign Out" data-bs-toggle="tooltip">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile Backdrop Overlay -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="document.body.classList.remove('sidebar-toggled');"></div>
    <?php endif; ?>

    <!-- Page Content Wrapper -->
    <div id="page-content-wrapper" <?= !$user_id ? 'style="margin-left:0; width:100%;"' : '' ?>>
        <?php if ($user_id): ?>
        <!-- Top Navbar -->
        <nav class="navbar navbar-topbar d-flex justify-content-between align-items-center sticky-top">
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-secondary btn-sm me-2 d-lg-none" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <span class="d-lg-none fw-bold brand-logo-text fs-5 me-2"><?= htmlspecialchars($salon_name) ?></span>
                <div class="d-none d-md-block">
                    <span class="text-muted small">
                        <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($salon_branch) ?> &bull; 
                        <i class="bi bi-telephone ms-2 me-1"></i><?= htmlspecialchars($salon_phone) ?>
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="<?= $root ?>customers/add.php" class="btn btn-salon-primary btn-sm d-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Add Bride</span>
                </a>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($user_name) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><h6 class="dropdown-header">Signed in as <strong><?= htmlspecialchars($user_full_name) ?></strong></h6></li>
                        <li><a class="dropdown-item" href="<?= $root ?>settings/index.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
                        <?php if ($is_admin): ?>
                        <li><a class="dropdown-item" href="<?= $root ?>settings/index.php"><i class="bi bi-sliders me-2"></i>Salon Settings</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= $root ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <?php endif; ?>

        <!-- Flash Messages -->
        <div class="container-fluid px-4 pt-3">
            <?php foreach (get_flashes() as $fl): ?>
                <div class="alert alert-<?= $fl['type'] === 'error' ? 'danger' : htmlspecialchars($fl['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                    <?php if ($fl['type'] === 'success'): ?>
                        <i class="bi bi-check-circle-fill me-2"></i>
                    <?php elseif ($fl['type'] === 'error' || $fl['type'] === 'danger'): ?>
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php elseif ($fl['type'] === 'warning'): ?>
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?php else: ?>
                        <i class="bi bi-info-circle-fill me-2"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($fl['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Main Body -->
        <main class="flex-grow-1 px-4 py-3">

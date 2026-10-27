<?php
/**
 * Safiya's Makeover — Bridal CRM & Anniversary Reminder System
 * Database Configuration & PDO Connection Handler (Hostinger MySQL)
 */

// Timezone strictly set to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

// Load .env file if available
$env_file = dirname(__DIR__) . '/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// -----------------------------------------------------------------------------
// Hostinger MySQL Configuration
// You can edit these 4 variables with your Hostinger hPanel Database details:
// -----------------------------------------------------------------------------
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'u791573478_bridal_crm';
$DB_USER = getenv('DB_USER') ?: 'u791573478_admin';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'ENTER_YOUR_DB_PASSWORD_HERE';
$DB_PORT = getenv('DB_PORT') ?: '3306';

// Global PDO Instance
$pdo = null;

try {
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
    ];
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);

    // Auto-bootstrap schema if tables are not created yet
    try {
        $check_table = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if (!$check_table) {
            $schema_file = dirname(__DIR__) . '/database/schema.sql';
            if (file_exists($schema_file)) {
                $sql = file_get_contents($schema_file);
                $pdo->exec($sql);
            }
        }
    } catch (Exception $schema_err) {
        // Silently continue if tables already exist
    }
} catch (PDOException $e) {
    // If running via CLI (Cron), print error
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "[!] Database Connection Error: " . $e->getMessage() . "\n");
        exit(1);
    }

    // Friendly, beautiful setup error page for Hostinger Web Browser
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Setup Required — Safiya's Makeover CRM</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; color: #1E293B; }
            .card-setup { border: 1px solid #F3D5DE; border-radius: 16px; box-shadow: 0 10px 25px rgba(212,115,140,0.1); }
            .btn-rose { background-color: #D4738C; color: white; border-radius: 8px; }
            .btn-rose:hover { background-color: #BF5771; color: white; }
        </style>
    </head>
    <body class="d-flex align-items-center min-vh-100 py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card card-setup p-4 p-md-5 bg-white">
                        <div class="text-center mb-4">
                            <span class="badge px-3 py-2 rounded-pill" style="background-color: #FDF5F7; color: #D4738C; font-size: 0.9rem;">
                                ⚙️ Hostinger Setup Required
                            </span>
                            <h3 class="mt-3 fw-bold">Database Connection Setup</h3>
                            <p class="text-muted small">Safiya's Makeover Bridal CRM is uploaded, but needs your Hostinger MySQL database details to connect.</p>
                        </div>

                        <div class="alert alert-warning border-0 small">
                            <strong>Notice:</strong> <?= htmlspecialchars($e->getMessage()) ?>
                        </div>

                        <h6 class="fw-bold mt-4">Follow these 3 quick steps in Hostinger hPanel:</h6>
                        <ol class="small text-secondary ps-3">
                            <li class="mb-2">Go to <strong>Hostinger hPanel ➔ Databases</strong> and create a MySQL database.</li>
                            <li class="mb-2">Open <strong>phpMyAdmin</strong> and click <strong>Import</strong>, then select <code>database/schema.sql</code>.</li>
                            <li class="mb-2">Open <strong>File Manager</strong>, edit <code>config/db.php</code> (or <code>.env</code>), and enter your Database Name, Username, and Password.</li>
                        </ol>

                        <div class="mt-4 text-center">
                            <a href="index.php" class="btn btn-rose px-4 py-2 w-100">Refresh After Updating config/db.php</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Returns the active PDO database connection instance.
 */
function get_db(): PDO {
    global $pdo;
    return $pdo;
}

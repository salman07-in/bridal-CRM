<?php
/**
 * Comprehensive Database Diagnostic & Interactive Test Tool
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config_file = __DIR__ . '/config/db.php';
$content = file_exists($config_file) ? file_get_contents($config_file) : '';

preg_match('/\$DB_HOST\s*=\s*[\'"](.*?)[\'"]/', $content, $m_host);
preg_match('/\$DB_NAME\s*=\s*[\'"](.*?)[\'"]/', $content, $m_name);
preg_match('/\$DB_USER\s*=\s*[\'"](.*?)[\'"]/', $content, $m_user);
preg_match('/\$DB_PASS\s*=\s*[\'"](.*?)[\'"]/', $content, $m_pass);

$db_host = $m_host[1] ?? 'localhost';
$db_name = $m_name[1] ?? 'u791573478_mmcrm';
$db_user = $m_user[1] ?? 'u791573478_Salmancrm';
$db_pass = $m_pass[1] ?? '';

// Overrides from form
if (isset($_POST['test_user']) && trim($_POST['test_user']) !== '') {
    $db_user = trim($_POST['test_user']);
}
if (isset($_POST['test_pass']) && trim($_POST['test_pass']) !== '') {
    $db_pass = trim($_POST['test_pass']);
}
if (isset($_POST['test_name']) && trim($_POST['test_name']) !== '') {
    $db_name = trim($_POST['test_name']);
}

// Auto-save to config/db.php on success checkbox
$saved_msg = '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Diagnostic & Live Tester</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 30px; background: #f8fafc; color: #1e293b; max-width: 800px; margin: auto; }
        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .success { color: #15803d; background: #dcfce7; padding: 12px 18px; border-radius: 8px; font-weight: bold; border: 1px solid #86efac; margin: 10px 0; }
        .danger { color: #b91c1c; background: #fee2e2; padding: 12px 18px; border-radius: 8px; font-size: 14px; border: 1px solid #fca5a5; margin: 10px 0; }
        input[type=text], input[type=password] { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%; box-sizing: border-box; margin-bottom: 12px; font-size: 15px; }
        button { background: #d4738c; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; }
        button:hover { background: #bf5771; }
        label { font-weight: 600; font-size: 13px; color: #64748b; display: block; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🔍 Database Live Connection Tester</h2>
        <p style="color:#64748b; font-size: 14px;">Testing credentials directly with Hostinger MySQL server.</p>

        <?php if ($db_pass): ?>
            <h4 style="margin-bottom: 5px;">Testing Credentials:</h4>
            <div style="font-size: 14px; color: #475569; margin-bottom: 15px; background: #f1f5f9; padding: 10px 15px; border-radius: 6px;">
                User: <b><?= htmlspecialchars($db_user) ?></b> | DB: <b><?= htmlspecialchars($db_name) ?></b> | Password: <b><?= str_repeat('•', strlen($db_pass)) ?></b> (<?= strlen($db_pass) ?> chars)
            </div>

            <?php
            // Test Variation 1: Current user
            $hosts = ['localhost', '127.0.0.1'];
            $connected = false;

            foreach ($hosts as $h) {
                try {
                    $dsn = "mysql:host={$h};port=3306;dbname={$db_name};charset=utf8mb4";
                    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    echo "<div class='success'>🎉 SUCCESS! Connected to MySQL host '{$h}' with user '{$db_user}'!</div>";
                    $connected = true;

                    // If auto-save requested or connected
                    if (!empty($_POST['save_on_success'])) {
                        $new_cfg = "<?php\n" .
                                   "date_default_timezone_set('Asia/Kolkata');\n\n" .
                                   "\$DB_HOST = '{$h}';\n" .
                                   "\$DB_NAME = '{$db_name}';\n" .
                                   "\$DB_USER = '{$db_user}';\n" .
                                   "\$DB_PASS = '{$db_pass}';\n" .
                                   "\$DB_PORT = '3306';\n\n" .
                                   "\$pdo = null;\n" .
                                   "try {\n" .
                                   "    \$dsn = \"mysql:host={\$DB_HOST};port={\$DB_PORT};dbname={\$DB_NAME};charset=utf8mb4\";\n" .
                                   "    \$options = [\n" .
                                   "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n" .
                                   "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n" .
                                   "        PDO::ATTR_EMULATE_PREPARES => true,\n" .
                                   "        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'\n" .
                                   "    ];\n" .
                                   "    \$pdo = new PDO(\$dsn, \$DB_USER, \$DB_PASS, \$options);\n" .
                                   "} catch (Exception \$e) {}\n";
                        file_put_contents($config_file, $new_cfg);
                        echo "<div class='success'>💾 Saved working credentials to <code>config/db.php</code>! You can now <a href='index.php' style='color:#15803d; text-decoration:underline; font-weight:bold;'>Open Your CRM Dashboard</a>!</div>";
                    }
                    break;
                } catch (Exception $e) {
                    echo "<div class='danger'>✗ [{$h}] Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }

            // Test Variation 2: Try lowercase username if capital failed
            if (!$connected && strtolower($db_user) !== $db_user) {
                $lower_user = strtolower($db_user);
                echo "<p style='font-size:13px; color:#64748b; margin-top:10px;'>Retrying with lowercase username: <b>{$lower_user}</b>...</p>";
                try {
                    $dsn = "mysql:host=localhost;port=3306;dbname={$db_name};charset=utf8mb4";
                    $pdo = new PDO($dsn, $lower_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    echo "<div class='success'>🎉 SUCCESS! Connected with LOWERCASE username: '{$lower_user}'!</div>";
                    $connected = true;
                } catch (Exception $e) {
                    echo "<div class='danger'>✗ Lowercase attempt failed: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            ?>
        <?php endif; ?>

        <hr style="border:0; border-top:1px solid #e2e8f0; margin:25px 0;">

        <h3>Test Different Credentials:</h3>
        <form method="POST">
            <label>Database Name:</label>
            <input type="text" name="test_name" value="<?= htmlspecialchars($db_name) ?>" required>

            <label>Database Username:</label>
            <input type="text" name="test_user" value="<?= htmlspecialchars($db_user) ?>" required>

            <label>Database Password:</label>
            <input type="text" name="test_pass" value="<?= htmlspecialchars($db_pass) ?>" placeholder="Type your Hostinger MySQL password" required>

            <div style="margin-bottom:15px;">
                <label style="display:inline; font-weight:normal; cursor:pointer;">
                    <input type="checkbox" name="save_on_success" value="1" checked> 
                    <strong>Automatically save to <code>config/db.php</code> when connection succeeds!</strong>
                </label>
            </div>

            <button type="submit">🚀 Test Connection &amp; Save</button>
        </form>
    </div>
</body>
</html>

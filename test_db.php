<?php
/**
 * Quick Database Connection Diagnostic Tool
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Read raw config/db.php lines
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

// Check if user submitted form
if (!empty($_POST['test_pass'])) {
    $db_pass = $_POST['test_pass'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Diagnostic</title>
    <style>body { font-family: sans-serif; padding: 30px; background: #f4f6f8; }</style>
</head>
<body>
    <h2>🔍 Hostinger Database Connection Test</h2>
    <p><strong>DB Host:</strong> <?= htmlspecialchars($db_host) ?></p>
    <p><strong>DB Name:</strong> <?= htmlspecialchars($db_name) ?></p>
    <p><strong>DB User:</strong> <?= htmlspecialchars($db_user) ?></p>
    <p><strong>Current Password Length:</strong> <?= strlen($db_pass) ?> chars</p>

    <hr>
    <?php if ($db_pass): ?>
        <h3>1. Testing with 'localhost'</h3>
        <?php
        try {
            $p = new PDO("mysql:host=localhost;port=3306;dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo "<p style='color:green; font-size:18px;'><b>✓ CONNECTED SUCCESSFULLY via localhost!</b></p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>✗ FAILED: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>

        <h3>2. Testing with '127.0.0.1'</h3>
        <?php
        try {
            $p2 = new PDO("mysql:host=127.0.0.1;port=3306;dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo "<p style='color:green; font-size:18px;'><b>✓ CONNECTED SUCCESSFULLY via 127.0.0.1!</b></p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>✗ FAILED: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    <?php endif; ?>

    <hr>
    <h4>Test with different password:</h4>
    <form method="POST">
        <input type="text" name="test_pass" placeholder="Enter Hostinger DB Password" style="padding:8px; width:280px;" value="<?= htmlspecialchars($db_pass) ?>">
        <button type="submit" style="padding:8px 16px; background:#D4738C; color:white; border:none; border-radius:4px; cursor:pointer;">Test Password</button>
    </form>
</body>
</html>

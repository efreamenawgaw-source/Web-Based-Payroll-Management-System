<?php
// ============================================================
// BiT Payroll — Database Setup Script
// Run once: http://localhost/payroll/database/setup.php
// DELETE this file after setup is complete.
// ============================================================

// Only allow from localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Access denied. This script can only be run from localhost.');
}

define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

$errors   = [];
$messages = [];

try {
    // Connect without selecting a database first
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Read and execute the SQL file
    $sql_file = __DIR__ . '/payroll_db.sql';

    if (!file_exists($sql_file)) {
        throw new RuntimeException('SQL file not found: payroll_db.sql');
    }

    $sql = file_get_contents($sql_file);

    // Split into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !preg_match('/^--/', $s)
    );

    $count = 0;
    foreach ($statements as $statement) {
        if (trim($statement) === '') continue;
        try {
            $pdo->exec($statement);
            $count++;
        } catch (PDOException $e) {
            // Skip "already exists" errors gracefully
            if (strpos($e->getMessage(), 'already exists') !== false) {
                $messages[] = '⚠️  Skipped (already exists): ' . substr($statement, 0, 60) . '...';
            } else {
                $errors[] = $e->getMessage() . ' — Statement: ' . substr($statement, 0, 80);
            }
        }
    }

    $messages[] = "✅ Executed {$count} SQL statements successfully.";

} catch (PDOException $e) {
    $errors[] = 'Connection failed: ' . $e->getMessage();
} catch (RuntimeException $e) {
    $errors[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiT Payroll — Database Setup</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #E3F2FD; min-height: 100vh;
               display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #fff; border-radius: 14px; box-shadow: 0 8px 32px rgba(21,101,192,0.15);
                padding: 36px; max-width: 680px; width: 100%; }
        .logo { font-size: 2rem; font-weight: 900; color: #1565C0; letter-spacing: -1px; }
        h1 { font-size: 1.3rem; color: #263238; margin: 8px 0 24px; }
        .msg  { padding: 10px 14px; border-radius: 8px; margin-bottom: 8px; font-size: 0.88rem; }
        .ok   { background: #E8F5E9; color: #2E7D32; border-left: 4px solid #2E7D32; }
        .warn { background: #FFFDE7; color: #F57F17; border-left: 4px solid #F57F17; }
        .err  { background: #FFEBEE; color: #C62828; border-left: 4px solid #C62828; }
        .note { margin-top: 24px; padding: 14px; background: #FFF3E0; border-radius: 8px;
                font-size: 0.85rem; color: #E65100; border-left: 4px solid #E65100; }
        .note strong { display: block; margin-bottom: 4px; }
        a.btn { display: inline-block; margin-top: 20px; padding: 10px 24px;
                background: #1565C0; color: #fff; border-radius: 8px; text-decoration: none;
                font-weight: 600; font-size: 0.9rem; }
        a.btn:hover { background: #0D47A1; }
        hr { border: none; border-top: 1px solid #ECEFF1; margin: 20px 0; }
        .creds { background: #E3F2FD; border-radius: 8px; padding: 14px; font-size: 0.85rem; }
        .creds p { margin-bottom: 6px; color: #263238; }
        .creds code { background: #fff; padding: 2px 8px; border-radius: 4px;
                      font-family: monospace; color: #1565C0; font-weight: 700; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">BiT</div>
    <h1>Payroll Management System — Database Setup</h1>

    <?php foreach ($messages as $msg): ?>
        <div class="msg <?= str_starts_with($msg, '✅') ? 'ok' : 'warn' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $err): ?>
        <div class="msg err">❌ <?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <?php if (empty($errors)): ?>
    <hr>
    <div class="creds">
        <p><strong>Default Admin Login Credentials:</strong></p>
        <p>Username: <code>admin</code></p>
        <p>Password: <code>Admin@2025</code></p>
        <p style="margin-top:8px;color:#546E7A;font-size:0.8rem;">
            ⚠️ Change the admin password immediately after first login.
        </p>
    </div>

    <div class="note">
        <strong>⚠️ Security Notice</strong>
        Setup complete. <strong>Delete this file immediately</strong>
        (<code>payroll/database/setup.php</code>) to prevent unauthorized re-execution.
    </div>

    <a href="../pages/auth/login.php" class="btn">→ Go to Login Page</a>
    <?php endif; ?>
</div>
</body>
</html>

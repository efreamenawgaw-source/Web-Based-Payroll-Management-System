<?php
// Only allow from localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Access denied.');
}

require_once __DIR__ . '/db_connect.php';

$new_password = 'Admin@2025';
$hash         = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);

$pdo = getDB();

// Update password and ensure account is active
$stmt = $pdo->prepare("UPDATE users SET password = ?, is_active = 1 WHERE username = 'admin'");
$stmt->execute([$hash]);
$affected = $stmt->rowCount();

// Verify immediately
$check = $pdo->prepare("SELECT user_id, username, password, is_active FROM users WHERE username = 'admin'");
$check->execute();
$user = $check->fetch();

$verify_ok = $user && password_verify($new_password, $user['password']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset Admin</title>
    <style>
        body { font-family: Arial, sans-serif; background: #E3F2FD;
               display: flex; align-items: center; justify-content: center;
               min-height: 100vh; margin: 0; }
        .box { background: #fff; border-radius: 12px; padding: 32px;
               max-width: 500px; width: 100%;
               box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .ok  { background: #E8F5E9; color: #2E7D32; padding: 12px 16px;
               border-radius: 8px; margin: 8px 0; border-left: 4px solid #2E7D32; }
        .err { background: #FFEBEE; color: #C62828; padding: 12px 16px;
               border-radius: 8px; margin: 8px 0; border-left: 4px solid #C62828; }
        code { background: #E3F2FD; padding: 2px 8px; border-radius: 4px;
               font-weight: bold; color: #1565C0; }
        a { display: inline-block; margin-top: 16px; padding: 10px 24px;
            background: #1565C0; color: #fff; border-radius: 8px;
            text-decoration: none; font-weight: bold; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 6px;
              font-size: 11px; word-break: break-all; white-space: pre-wrap; }
    </style>
</head>
<body>
<div class="box">
    <h2 style="color:#1565C0;margin:0 0 20px;">BiT — Reset Admin Password</h2>

    <?php if ($affected > 0 && $verify_ok): ?>
    <div class="ok">✅ Password reset successfully!</div>
    <div class="ok">✅ <code>password_verify()</code> confirmed — hash works.</div>

    <p style="margin:16px 0 8px;"><strong>Login with:</strong></p>
    <p>Username: <code>admin</code></p>
    <p>Password: <code>Admin@2025</code></p>
    <p style="margin-top:12px;font-size:0.8rem;color:#666;">Hash stored:</p>
    <pre><?= htmlspecialchars($user['password']) ?></pre>

    <div style="background:#FFF3E0;padding:12px;border-radius:8px;margin-top:12px;font-size:0.85rem;color:#E65100;">
        ⚠️ Delete this file after use: <code>payroll/database/reset_admin.php</code>
    </div>
    <a href="../auth/login.php">→ Go to Login</a>

    <?php elseif ($affected === 0): ?>
    <div class="err">❌ No admin user found in database.</div>
    <p>The users table may be empty. Run setup first:</p>
    <a href="setup.php">→ Run Setup</a>

    <?php else: ?>
    <div class="err">❌ Password updated but verification failed.</div>
    <p>PHP version: <?= PHP_VERSION ?></p>
    <p>Hash generated: <pre><?= htmlspecialchars($hash) ?></pre></p>
    <?php endif; ?>
</div>
</body>
</html>

<?php
session_start();
require_once '../../database/db_connect.php';

$pdo   = getDB();
$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';

// ── Validate token ─────────────────────────────────────────
$reset = null;
$user  = null;

if (empty($token)) {
    $error = 'Invalid or missing reset token.';
} else {
    $stmt = $pdo->prepare("
        SELECT r.reset_id, r.user_id, r.expires_at, r.used,
               u.username, u.full_name, u.email
        FROM   password_resets r
        JOIN   users u ON r.user_id = u.user_id
        WHERE  r.token = ?
        LIMIT  1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        $error = 'This reset link is invalid or has already been used.';
    } elseif ($reset['used']) {
        $error = 'This reset link has already been used. Please request a new one.';
    } elseif (strtotime($reset['expires_at']) < time()) {
        $error = 'This reset link has expired (valid for 30 minutes). Please request a new one.';
    } else {
        $user = $reset;
    }
}

// ── Handle password reset form submission ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && $user) {
    $new_pass     = trim($_POST['new_password']     ?? '');
    $confirm_pass = trim($_POST['confirm_password'] ?? '');

    if (strlen($new_pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($new_pass !== $confirm_pass) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo->beginTransaction();

            // Update password
            $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 10]);
            $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")
                ->execute([$hash, $user['user_id']]);

            // Mark token as used
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE reset_id = ?")
                ->execute([$reset['reset_id']]);

            // Audit log
            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, action, details, ip_address)
                VALUES (?, ?, 'Password Reset', 'Password reset via email link', ?)
            ")->execute([
                $user['user_id'], $user['username'],
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $pdo->commit();
            $success = true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Reset Password — BiT Payroll System</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-page">

<div class="login-wrapper" style="max-width:500px;">
    <div class="login-form-panel" style="width:100%;padding:48px 40px;">

        <!-- Logo -->
        <div style="text-align:center;margin-bottom:28px;">
            <div style="display:inline-flex;align-items:center;gap:12px;">
                <div style="width:48px;height:48px;background:var(--primary);border-radius:12px;
                            display:flex;align-items:center;justify-content:center;
                            font-weight:900;font-size:1.1rem;color:white;">BiT</div>
                <div style="text-align:left;">
                    <div style="font-weight:700;color:var(--primary);font-size:1rem;">BiT Payroll</div>
                    <div style="font-size:0.72rem;color:var(--gray-400);">Management System</div>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
        <!-- ── Success ── -->
        <div style="text-align:center;padding:20px 0;">
            <div style="width:72px;height:72px;background:var(--success-light);border-radius:50%;
                        display:flex;align-items:center;justify-content:center;
                        margin:0 auto 20px;">
                <i class="fas fa-check-circle" style="font-size:2.2rem;color:var(--success);"></i>
            </div>
            <h2 style="color:var(--gray-800);margin:0 0 12px;">Password Reset!</h2>
            <p style="color:var(--gray-600);line-height:1.7;margin:0 0 24px;">
                Your password has been reset successfully.
                You can now login with your new password.
            </p>
            <a href="login.php" class="btn btn-primary w-100" style="justify-content:center;">
                <i class="fas fa-sign-in-alt"></i> Login Now
            </a>
        </div>

        <?php elseif ($error && !$user): ?>
        <!-- ── Invalid / Expired Token ── -->
        <div style="text-align:center;padding:20px 0;">
            <div style="width:72px;height:72px;background:var(--danger-light);border-radius:50%;
                        display:flex;align-items:center;justify-content:center;
                        margin:0 auto 20px;">
                <i class="fas fa-times-circle" style="font-size:2.2rem;color:var(--danger);"></i>
            </div>
            <h2 style="color:var(--gray-800);margin:0 0 12px;">Link Invalid</h2>
            <p style="color:var(--gray-600);line-height:1.7;margin:0 0 24px;">
                <?= htmlspecialchars($error) ?>
            </p>
            <a href="forgot_password.php" class="btn btn-primary w-100" style="justify-content:center;">
                <i class="fas fa-redo"></i> Request New Link
            </a>
            <div style="margin-top:12px;">
                <a href="login.php" style="color:var(--gray-400);font-size:0.85rem;">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- ── Reset Form ── -->
        <div class="login-form-header" style="text-align:center;">
            <h2>Set New Password</h2>
            <p style="color:var(--gray-600);font-size:0.88rem;">
                Hello <strong><?= htmlspecialchars($user['full_name']) ?></strong>,
                enter your new password below.
            </p>
        </div>

        <?php if ($error): ?>
        <div class="login-alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="?token=<?= urlencode($token) ?>" class="login-form" novalidate>

            <div class="form-group">
                <label class="form-label">New Password <span style="color:var(--danger)">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="new_password" id="new_pass"
                           class="form-control"
                           placeholder="Min. 6 characters"
                           oninput="checkStrength(this.value)"
                           required autofocus>
                    <button type="button" class="toggle-password"
                            onclick="togglePwd('new_pass','eye1')">
                        <i class="fas fa-eye" id="eye1"></i>
                    </button>
                </div>
                <!-- Strength bar -->
                <div style="margin-top:6px;">
                    <div style="height:4px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                        <div id="strengthBar" style="height:100%;width:0;border-radius:4px;transition:all 0.3s;"></div>
                    </div>
                    <p id="strengthText" style="font-size:0.72rem;margin:3px 0 0;color:var(--gray-400);"></p>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm Password <span style="color:var(--danger)">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="confirm_password" id="conf_pass"
                           class="form-control"
                           placeholder="Repeat new password"
                           oninput="checkMatch()"
                           required>
                    <button type="button" class="toggle-password"
                            onclick="togglePwd('conf_pass','eye2')">
                        <i class="fas fa-eye" id="eye2"></i>
                    </button>
                </div>
                <p id="matchText" style="font-size:0.72rem;margin:3px 0 0;"></p>
            </div>

            <!-- Token expiry info -->
            <div style="background:var(--bg-light);border-radius:var(--radius);padding:10px 14px;
                        font-size:0.8rem;color:var(--gray-600);margin-bottom:16px;">
                <i class="fas fa-clock" style="color:var(--warning);"></i>
                Link expires at: <strong><?= date('H:i', strtotime($reset['expires_at'])) ?></strong>
                (<?= date('M d, Y', strtotime($reset['expires_at'])) ?>)
            </div>

            <button type="submit" name="reset_password" class="btn btn-primary btn-login">
                <i class="fas fa-key"></i> Reset Password
            </button>
        </form>

        <div style="text-align:center;margin-top:16px;">
            <a href="login.php" style="color:var(--gray-400);font-size:0.85rem;">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function togglePwd(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

function checkStrength(val) {
    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    let score  = 0;
    if (val.length >= 6)           score++;
    if (val.length >= 10)          score++;
    if (/[A-Z]/.test(val))         score++;
    if (/[0-9]/.test(val))         score++;
    if (/[^A-Za-z0-9]/.test(val))  score++;
    const levels = [
        {pct:'20%',color:'#C62828',label:'Very Weak'},
        {pct:'40%',color:'#E65100',label:'Weak'},
        {pct:'60%',color:'#F57F17',label:'Fair'},
        {pct:'80%',color:'#2E7D32',label:'Strong'},
        {pct:'100%',color:'#1B5E20',label:'Very Strong'},
    ];
    const lvl = levels[Math.max(0, score - 1)] || levels[0];
    bar.style.width      = val.length ? lvl.pct : '0';
    bar.style.background = lvl.color;
    text.textContent     = val.length ? lvl.label : '';
    text.style.color     = lvl.color;
    checkMatch();
}

function checkMatch() {
    const p1   = document.getElementById('new_pass').value;
    const p2   = document.getElementById('conf_pass').value;
    const text = document.getElementById('matchText');
    if (!p2) { text.textContent = ''; return; }
    if (p1 === p2) {
        text.textContent = '✅ Passwords match';
        text.style.color = '#2E7D32';
    } else {
        text.textContent = '❌ Passwords do not match';
        text.style.color = '#C62828';
    }
}
</script>
</body>
</html>

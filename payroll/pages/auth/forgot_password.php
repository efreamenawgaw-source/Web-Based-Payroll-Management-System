<?php
session_start();
require_once '../../database/db_connect.php';
require_once '../../includes/mailer.php';

$pdo     = getDB();
$success = '';
$error   = '';
$step    = 'request'; // request | sent

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Find user by email
        $stmt = $pdo->prepare("SELECT user_id, full_name, username, email FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show success message (security — don't reveal if email exists)
        $step = 'sent';

        if ($user) {
            // Delete any existing unused tokens for this user
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND used = 0")
                ->execute([$user['user_id']]);

            // Generate secure token
            $token      = bin2hex(random_bytes(32)); // 64-char hex
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ")->execute([$user['user_id'], $token, $expires_at]);

            // Build reset URL
            $base_url  = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $script    = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
            $dir       = dirname($script); // /Web_based payroll system/payroll/pages/auth
            $reset_url = $base_url . $dir . '/reset_password.php?token=' . $token;

            // Build email
            $html = buildResetEmail($user['full_name'], $user['username'], $reset_url);

            $result = sendMail($user['email'], $user['full_name'],
                'BiT Payroll — Password Reset Request',
                $html);

            // Log the action
            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, details, ip_address)
                VALUES (?, ?, ?, 'Password Reset Request', 'Reset link sent to email', ?)
            ")->execute([
                $user['user_id'], $user['username'], $user['role'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        }
    }
}

// ── Build reset email HTML ─────────────────────────────────
function buildResetEmail(string $name, string $username, string $reset_url): string {
    $year = date('Y');
    return '
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Password Reset — BiT Payroll</title></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:\'Segoe UI\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <tr>
    <td style="background:linear-gradient(135deg,#0D47A1,#1565C0);
               border-radius:12px 12px 0 0;padding:32px 40px;text-align:center;">
      <div style="display:inline-block;background:white;border-radius:10px;
                  padding:8px 18px;margin-bottom:14px;">
        <span style="font-size:1.4rem;font-weight:900;color:#1565C0;">BiT</span>
      </div>
      <h1 style="color:white;margin:0;font-size:1.3rem;font-weight:700;">
        Password Reset Request
      </h1>
      <p style="color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:0.9rem;">
        BiT Payroll Management System
      </p>
    </td>
  </tr>

  <tr>
    <td style="background:white;padding:40px;">
      <p style="font-size:1rem;color:#263238;margin:0 0 16px;">
        Dear <strong>' . htmlspecialchars($name) . '</strong>,
      </p>
      <p style="color:#546E7A;line-height:1.7;margin:0 0 8px;">
        We received a request to reset the password for your BiT Payroll account.
      </p>
      <p style="color:#546E7A;line-height:1.7;margin:0 0 28px;">
        Username: <strong style="color:#1565C0;">' . htmlspecialchars($username) . '</strong>
      </p>

      <!-- Reset Button -->
      <div style="text-align:center;margin:0 0 28px;">
        <a href="' . htmlspecialchars($reset_url) . '"
           style="display:inline-block;background:linear-gradient(135deg,#1565C0,#1976D2);
                  color:white;text-decoration:none;padding:14px 40px;border-radius:8px;
                  font-weight:700;font-size:1rem;">
          🔐 Reset My Password
        </a>
      </div>

      <!-- Expiry warning -->
      <table width="100%" cellpadding="0" cellspacing="0"
             style="background:#FFF3E0;border-radius:8px;border-left:4px solid #F57F17;margin:0 0 22px;">
        <tr>
          <td style="padding:14px 18px;">
            <p style="color:#E65100;font-size:0.85rem;margin:0;">
              ⏰ <strong>This link expires in 30 minutes.</strong>
              If you did not request a password reset, you can safely ignore this email.
            </p>
          </td>
        </tr>
      </table>

      <!-- Link fallback -->
      <p style="color:#90A4AE;font-size:0.78rem;margin:0 0 6px;">
        If the button does not work, copy and paste this link into your browser:
      </p>
      <p style="word-break:break-all;font-size:0.78rem;color:#1565C0;margin:0;">
        ' . htmlspecialchars($reset_url) . '
      </p>
    </td>
  </tr>

  <tr>
    <td style="background:#0D47A1;border-radius:0 0 12px 12px;padding:20px 40px;text-align:center;">
      <p style="color:rgba(255,255,255,0.6);font-size:0.78rem;margin:0;">
        &copy; ' . $year . ' Bahir Dar Institute of Technology — BiT Payroll System
      </p>
      <p style="color:rgba(255,255,255,0.4);font-size:0.72rem;margin:6px 0 0;">
        This is an automated message. Do not reply.
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Forgot Password — BiT Payroll System</title>
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

        <?php if ($step === 'sent'): ?>
        <!-- ── Success State ── -->
        <div style="text-align:center;padding:20px 0;">
            <div style="width:72px;height:72px;background:var(--success-light);border-radius:50%;
                        display:flex;align-items:center;justify-content:center;
                        margin:0 auto 20px;">
                <i class="fas fa-envelope-open-text" style="font-size:2rem;color:var(--success);"></i>
            </div>
            <h2 style="color:var(--gray-800);margin:0 0 12px;">Check Your Email</h2>
            <p style="color:var(--gray-600);line-height:1.7;margin:0 0 24px;">
                If an account with that email exists, we've sent a password reset link.
                <br><strong>The link expires in 30 minutes.</strong>
            </p>
            <div style="background:var(--bg-light);border-radius:var(--radius);padding:14px;
                        font-size:0.85rem;color:var(--gray-600);margin-bottom:24px;text-align:left;">
                <i class="fas fa-info-circle" style="color:var(--info);"></i>
                Didn't receive it? Check your <strong>spam/junk folder</strong>, or
                <a href="forgot_password.php" style="color:var(--primary);font-weight:600;">try again</a>.
            </div>
            <a href="login.php" class="btn btn-primary w-100" style="justify-content:center;">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>

        <?php else: ?>
        <!-- ── Request Form ── -->
        <div class="login-form-header" style="text-align:center;">
            <h2>Forgot Password?</h2>
            <p style="color:var(--gray-600);font-size:0.88rem;">
                Enter your email address and we'll send you a link to reset your password.
            </p>
        </div>

        <?php if ($error): ?>
        <div class="login-alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="login-form">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control"
                           placeholder="your@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>
            </div>

            <button type="submit" name="request_reset" class="btn btn-primary btn-login">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>

        <div style="text-align:center;margin-top:20px;">
            <a href="login.php" style="color:var(--gray-400);font-size:0.85rem;">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>

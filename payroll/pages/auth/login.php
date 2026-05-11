<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../database/db_connect.php';
require_once '../../includes/notify.php';

$error   = '';
$MAX_ATTEMPTS = 5;          // lock after 5 failures
$LOCK_MINUTES = 15;         // lock duration

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';   // do NOT trim passwords

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (strlen($username) > 60 || strlen($password) > 200) {
        $error = 'Invalid credentials.';   // reject oversized inputs silently
    } else {
        $pdo = getDB();
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // ── Brute-force lockout check ─────────────────────
        $fail_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM audit_logs
            WHERE  status = 'failed'
            AND    action = 'Login'
            AND    ip_address = ?
            AND    logged_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $fail_stmt->execute([$ip, $LOCK_MINUTES]);
        $recent_fails = (int)$fail_stmt->fetchColumn();

        if ($recent_fails >= $MAX_ATTEMPTS) {
            $error = "Too many failed login attempts. Please wait {$LOCK_MINUTES} minutes before trying again.";
        } else {
            // Fetch user
            $stmt = $pdo->prepare("
                SELECT user_id, username, password, role, full_name, is_active, profile_photo
                FROM   users
                WHERE  username = ?
                LIMIT  1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
                // ── Successful login ──────────────────────
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id']       = $user['user_id'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['name']          = $user['full_name'];
                $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;
                $_SESSION['last_activity'] = time();

                // Update last_login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")
                    ->execute([$user['user_id']]);

                // Audit log — success
                $pdo->prepare("
                    INSERT INTO audit_logs
                        (user_id, username, role, action, details, ip_address, status)
                    VALUES (?, ?, ?, 'Login', 'Successful login', ?, 'success')
                ")->execute([$user['user_id'], $user['username'], $user['role'], $ip]);

                // Redirect by role
                switch ($user['role']) {
                    case 'admin':    header('Location: ../admin/dashboard.php');    break;
                    case 'hr':       header('Location: ../hr/dashboard.php');       break;
                    case 'finance':  header('Location: ../finance/dashboard.php');  break;
                    case 'employee': header('Location: ../employee/dashboard.php'); break;
                    default:         header('Location: ../auth/login.php');
                }
                exit();

            } else {
                // ── Failed login ──────────────────────────
                if ($user) {
                    $pdo->prepare("
                        INSERT INTO audit_logs
                            (user_id, username, role, action, details, ip_address, status)
                        VALUES (?, ?, ?, 'Login', 'Failed login attempt', ?, 'failed')
                    ")->execute([$user['user_id'], $user['username'], $user['role'], $ip]);
                } else {
                    $pdo->prepare("
                        INSERT INTO audit_logs
                            (username, action, details, ip_address, status)
                        VALUES (?, 'Login', 'Unknown username attempt', ?, 'failed')
                    ")->execute([$username, $ip]);
                }

                // Alert admins after 3 failures from same IP
                try {
                    $fail_count = $pdo->prepare("
                        SELECT COUNT(*) FROM audit_logs
                        WHERE status='failed' AND action='Login'
                        AND ip_address=? AND logged_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                    ");
                    $fail_count->execute([$ip]);
                    if ((int)$fail_count->fetchColumn() >= 3) {
                        notify_role($pdo, 'admin',
                            '⚠️ Multiple Failed Login Attempts',
                            "IP {$ip} has failed to login 3+ times in the last 10 minutes. Username tried: {$username}",
                            'danger');
                    }
                } catch (Exception $e) { /* ignore */ }

                $remaining = max(0, $MAX_ATTEMPTS - $recent_fails - 1);
                if ($user && !$user['is_active']) {
                    $error = 'Your account has been deactivated. Contact the administrator.';
                } elseif ($remaining > 0) {
                    $error = "Invalid username or password. {$remaining} attempt(s) remaining before lockout.";
                } else {
                    $error = 'Invalid credentials.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Secure Login — BiT Payroll Management System</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-page">

<div class="login-wrapper">

    <!-- ── Left Branding Panel ── -->
    <div class="login-brand-panel">
        <div class="brand-header">
            <div class="brand-logo-box">BiT</div>
            <div class="brand-header-text">
                <h1>Bahir Dar Institute<br>of Technology</h1>
                <p>Faculty of Computing</p>
            </div>
        </div>

        <div class="brand-content">
            <h2>Payroll Management<br>System</h2>
            <p>A secure, automated web-based solution for managing employee salaries, allowances, tax calculations, and payslip generation at BiT.</p>
            <div class="brand-features">
                <div class="brand-feature">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <span>Role-based access — Admin, HR, Finance & Staff</span>
                </div>
                <div class="brand-feature">
                    <div class="feature-icon"><i class="fas fa-calculator"></i></div>
                    <span>Automated Ethiopian tax & pension (2025 brackets)</span>
                </div>
                <div class="brand-feature">
                    <div class="feature-icon"><i class="fas fa-file-invoice"></i></div>
                    <span>Electronic payslip generation & download</span>
                </div>
                <div class="brand-feature">
                    <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
                    <span>Comprehensive payroll reports & analytics</span>
                </div>
            </div>
        </div>

        <div class="brand-footer">
            &copy; <?= date('Y') ?> Bahir Dar University — BiT &nbsp;|&nbsp; All rights reserved
        </div>
    </div>

    <!-- ── Right Form Panel ── -->
    <div class="login-form-panel">
        <div class="login-form-header">
            <h2>Secure Login</h2>
            <p>Enter your credentials to access the BiT Payroll System.</p>
        </div>

        <?php if ($error): ?>
        <div class="login-alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($_GET['timeout'])): ?>
        <div class="login-alert" style="background:#FFF3E0;color:#E65100;border-color:#E65100;">
            <i class="fas fa-clock"></i>
            Your session expired due to inactivity. Please log in again.
        </div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="" novalidate>
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username"
                           class="form-control"
                           placeholder="Enter your username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password"
                           class="form-control"
                           placeholder="Enter your password"
                           autocomplete="current-password" required>
                    <button type="button" class="toggle-password"
                            onclick="togglePassword()" title="Show/Hide password">
                        <i class="fas fa-eye" id="eye-icon"></i>
                    </button>
                </div>
            </div>

            <div class="login-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt"></i> Login to System
            </button>
        </form>

        <a href="../../home.php" class="btn-back-home">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <div class="login-footer">
            &copy; <?= date('Y') ?> Bahir Dar Institute of Technology
            &nbsp;&bull;&nbsp; Payroll System v1.0
        </div>
    </div>

</div>

<script>
function togglePassword() {
    const pwd  = document.getElementById('password');
    const icon = document.getElementById('eye-icon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>

<?php
session_start();
// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$error = '';
$success = '';

// Demo credentials for prototype
$demo_users = [
    'admin'    => ['password' => 'admin123',    'role' => 'admin',    'name' => 'System Admin'],
    'hr'       => ['password' => 'hr123',       'role' => 'hr',       'name' => 'HR Personnel'],
    'finance'  => ['password' => 'finance123',  'role' => 'finance',  'name' => 'Finance Officer'],
    'employee' => ['password' => 'emp123',      'role' => 'employee', 'name' => 'Staff Member'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (isset($demo_users[$username]) && $demo_users[$username]['password'] === $password) {
        $_SESSION['user_id']  = 1;
        $_SESSION['username'] = $username;
        $_SESSION['role']     = $demo_users[$username]['role'];
        $_SESSION['name']     = $demo_users[$username]['name'];

        $role = $_SESSION['role'];
        switch ($role) {
            case 'admin':    header('Location: ../admin/dashboard.php'); break;
            case 'hr':       header('Location: ../hr/dashboard.php'); break;
            case 'finance':  header('Location: ../finance/dashboard.php'); break;
            case 'employee': header('Location: ../employee/dashboard.php'); break;
        }
        exit();
    } else {
        $error = 'Invalid username or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login — BiT Payroll Management System</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/login.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-page">

<div class="login-wrapper">

    <!-- ===== Left Branding Panel ===== -->
    <div class="login-brand-panel">
        <div class="brand-header">
            <div class="brand-logo-box">BiT</div>
            <div class="brand-header-text">
                <h1>Bahir Dar Institute<br>of Technology</h1>
                
            </div>
        </div>

        <div class="brand-content">
            <h2>Payroll Management<br>System</h2>
            <p>A secure, automated web-based solution for managing employee salaries, allowances, tax calculations, and payslip generation at BiT.</p>

            <div class="brand-features">
                <div class="brand-feature">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <span>Role-based access control for Admin, HR, Finance & Staff</span>
                </div>
                <div class="brand-feature">
                    <div class="feature-icon"><i class="fas fa-calculator"></i></div>
                    <span>Automated Ethiopian tax & pension calculations</span>
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

    <!-- ===== Right Form Panel ===== -->
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

        <?php if ($success): ?>
        <div class="login-alert success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="Enter your username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        autocomplete="username"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="toggle-password" onclick="togglePassword()" title="Show/Hide password">
                        <i class="fas fa-eye" id="eye-icon"></i>
                    </button>
                </div>
            </div>

            <div class="login-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="#" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt"></i> Login to System
            </button>
        </form>

        <a href="../../home.php" class="btn-back-home">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <div class="login-footer">
            &copy; <?= date('Y') ?> Bahir Dar Institute of Technology &nbsp;&bull;&nbsp; Payroll System v1.0
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

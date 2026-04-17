<?php
session_start();

// Log the logout action before destroying session
if (isset($_SESSION['user_id'])) {
    require_once '../../database/db_connect.php';
    $pdo = getDB();
    $pdo->prepare("
        INSERT INTO audit_logs
            (user_id, username, role, action, details, ip_address, status)
        VALUES (?, ?, ?, 'Logout', 'User logged out', ?, 'success')
    ")->execute([
        $_SESSION['user_id'],
        $_SESSION['username'] ?? '',
        $_SESSION['role']     ?? '',
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

// Destroy session completely
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: login.php');
exit();
?>

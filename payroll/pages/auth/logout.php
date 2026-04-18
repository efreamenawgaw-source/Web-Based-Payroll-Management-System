<?php
session_start();

// Log the logout action — wrapped in try/catch so logout always works
if (isset($_SESSION['user_id'])) {
    try {
        require_once '../../database/db_connect.php';
        $pdo = getDB();

        // Verify user still exists before logging
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $chk->execute([$_SESSION['user_id']]);

        if ($chk->fetch()) {
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
    } catch (Exception $e) {
        // Silently ignore — logout must always succeed
    }
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

<?php
// ============================================================
// BiT Payroll — Logout Page (controller)
// All business logic lives in AuthService.
// ============================================================
session_start();

try {
    require_once '../../database/db_connect.php';
    require_once '../../includes/AuthService.php';

    $auth = new AuthService(getDB());
    $auth->logout();
} catch (Exception $e) {
    // Guarantee logout even if DB is unreachable —
    // manually destroy the session as a fallback.
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

header('Location: login.php');
exit();

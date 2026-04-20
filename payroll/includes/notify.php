<?php
// ============================================================
// BiT Payroll — Notification Helper
// Usage: require_once $depth . 'includes/notify.php';
//        notify($pdo, $user_id, 'Title', 'Message', 'success', '/link');
//        notify_role($pdo, 'admin', 'Title', 'Message', 'info');
//        notify_all_role($pdo, 'finance', 'Title', 'Message', 'warning');
// ============================================================

/**
 * Send notification to a specific user
 */
function notify(PDO $pdo, int $user_id, string $title, string $message,
                string $type = 'info', string $link = ''): void {
    try {
        $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$user_id, $title, $message, $type, $link ?: null]);
    } catch (Exception $e) { /* silently ignore */ }
}

/**
 * Send notification to ALL users with a specific role
 */
function notify_role(PDO $pdo, string $role, string $title, string $message,
                     string $type = 'info', string $link = ''): void {
    try {
        $users = $pdo->prepare("SELECT user_id FROM users WHERE role = ? AND is_active = 1");
        $users->execute([$role]);
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($users->fetchAll() as $u) {
            $stmt->execute([$u['user_id'], $title, $message, $type, $link ?: null]);
        }
    } catch (Exception $e) { /* silently ignore */ }
}

/**
 * Send notification to multiple roles at once
 */
function notify_roles(PDO $pdo, array $roles, string $title, string $message,
                      string $type = 'info', string $link = ''): void {
    foreach ($roles as $role) {
        notify_role($pdo, $role, $title, $message, $type, $link);
    }
}

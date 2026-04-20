<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit();
}

require_once '../database/db_connect.php';
$pdo     = getDB();
$user_id = (int)$_SESSION['user_id'];

// ── MARK AS READ ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'mark_read' && !empty($_POST['notif_id'])) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notif_id = ? AND user_id = ?")
            ->execute([(int)$_POST['notif_id'], $user_id]);
        echo json_encode(['status' => 'ok']);
        exit();
    }

    if ($action === 'mark_all_read') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
            ->execute([$user_id]);
        echo json_encode(['status' => 'ok']);
        exit();
    }
    exit();
}

// ── GET NOTIFICATIONS ──────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT notif_id, title, message, type, link, is_read, created_at,
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS minutes_ago
        FROM   notifications
        WHERE  user_id = ?
        ORDER  BY created_at DESC
        LIMIT  20
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();

    $uc = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $uc->execute([$user_id]);
    $unread = (int)$uc->fetchColumn();

    // Format time ago
    foreach ($notifications as &$n) {
        $mins = (int)$n['minutes_ago'];
        if ($mins < 1)        $n['time_ago'] = 'Just now';
        elseif ($mins < 60)   $n['time_ago'] = $mins . 'm ago';
        elseif ($mins < 1440) $n['time_ago'] = round($mins / 60) . 'h ago';
        else                  $n['time_ago'] = date('M d', strtotime($n['created_at']));
        unset($n['minutes_ago']);
    }

    echo json_encode([
        'count'         => $unread,
        'notifications' => $notifications,
    ]);

} catch (Exception $e) {
    echo json_encode(['count' => 0, 'notifications' => [], 'error' => $e->getMessage()]);
}

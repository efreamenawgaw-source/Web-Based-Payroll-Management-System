<?php
// ── Session ping — called by JS to extend the session ─────
// POST /api/ping_session.php
// Returns JSON { alive: true } and refreshes $_SESSION['last_activity']

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['alive' => false, 'reason' => 'not_logged_in']);
    exit;
}

// Refresh last activity
$_SESSION['last_activity'] = time();

echo json_encode(['alive' => true, 'last_activity' => $_SESSION['last_activity']]);

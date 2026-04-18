<?php
// Shared header — include at top of every protected page
// Requires: $page_title, $active_nav (optional)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/auth/login.php');
    exit();
}
$role       = $_SESSION['role']          ?? 'employee';
$user_name  = $_SESSION['name']          ?? 'User';
$username   = $_SESSION['username']      ?? '';
$initials   = strtoupper(substr($user_name, 0, 1));
$page_title = $page_title               ?? 'Dashboard';
$active_nav = $active_nav               ?? '';
$depth      = $depth                    ?? '../../';

// ── Dynamic web root (handles spaces in folder names) ─────
$_script    = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$_web_root  = rtrim(dirname(dirname(dirname($_script))), '/');
$_uploads_web = $_web_root . '/assets/uploads/profiles/';
$_photo_url = !empty($_SESSION['profile_photo'])
    ? $_uploads_web . rawurlencode($_SESSION['profile_photo'])
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — BiT Payroll System</title>
    <link rel="stylesheet" href="<?= $depth ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- ===== Sidebar Overlay (mobile backdrop) ===== -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="layout">

    <!-- ===== Sidebar ===== -->
    <aside class="sidebar" id="sidebar">

        <!-- Close button — mobile only -->
        <button class="sidebar-close" id="sidebarClose" onclick="closeSidebar()" aria-label="Close menu">
            <i class="fas fa-times"></i>
        </button>

        <!-- Brand -->
        <div class="sidebar-brand">
            <div class="brand-logo">BiT</div>
            <div class="brand-text">
                <h2>BiT Payroll</h2>
                <span>Management System</span>
            </div>
        </div>

        <!-- User Info -->
        <div class="sidebar-user">
            <?php if ($_photo_url): ?>
            <img src="<?= htmlspecialchars($_photo_url) ?>"
                 alt="Photo"
                 style="width:38px;height:38px;border-radius:50%;object-fit:cover;
                        flex-shrink:0;border:2px solid var(--accent-light);">
            <?php else: ?>
            <div class="user-avatar"><?= $initials ?></div>
            <?php endif; ?>
            <div class="user-info">
                <p><?= htmlspecialchars($user_name) ?></p>
                <span><?= ucfirst($role) ?></span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav">
            <?php include $depth . 'includes/nav_' . $role . '.php'; ?>
        </nav>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <a href="<?= $depth ?>pages/auth/logout.php">
                <i class="fas fa-sign-out-alt nav-icon"></i> Logout
            </a>
        </div>
    </aside>

    <!-- ===== Main Content ===== -->
    <div class="main-content">

        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <!-- Hamburger — visible on mobile -->
                <button class="hamburger-btn" id="hamburgerBtn"
                        onclick="openSidebar()" aria-label="Open menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="topbar-title">
                    <h3><?= htmlspecialchars($page_title) ?></h3>
                    <p class="topbar-sub">Bahir Dar Institute of Technology</p>
                </div>
            </div>
            <div class="topbar-right">
                <!-- Notifications Bell -->
                <div class="topbar-badge" style="position:relative;">
                    <button class="btn-icon" id="notifBtn" title="Notifications"
                            onclick="toggleNotifPanel()">
                        <i class="fas fa-bell"></i>
                    </button>
                    <span class="badge" id="notifCount" style="display:none;">0</span>
                </div>

                <!-- User chip → My Profile -->
                <a href="<?= $depth ?>pages/profile/my_profile.php"
                   class="topbar-user-chip" style="text-decoration:none;" title="My Profile">
                    <?php if ($_photo_url): ?>
                    <img src="<?= htmlspecialchars($_photo_url) ?>"
                         alt="Photo"
                         style="width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                    <?php else: ?>
                    <div class="topbar-avatar"><?= $initials ?></div>
                    <?php endif; ?>
                    <span class="topbar-username"><?= htmlspecialchars($user_name) ?></span>
                </a>
            </div>
        </header>

        <!-- Notification Panel -->
        <div id="notifPanel" style="display:none;position:fixed;top:62px;right:16px;
             width:340px;max-height:480px;background:var(--white);border-radius:var(--radius-lg);
             box-shadow:var(--shadow-lg);border:1px solid var(--gray-200);z-index:95;
             overflow:hidden;flex-direction:column;">
            <div style="padding:14px 16px;border-bottom:1px solid var(--gray-200);
                        display:flex;justify-content:space-between;align-items:center;">
                <h4 style="margin:0;font-size:0.95rem;">Notifications</h4>
                <button onclick="markAllRead()"
                        style="background:none;border:none;cursor:pointer;
                               font-size:0.78rem;color:var(--primary);font-weight:600;">
                    Mark all read
                </button>
            </div>
            <div id="notifList" style="overflow-y:auto;max-height:380px;"></div>
        </div>

        <!-- Page Content starts here -->
        <main class="page-content">

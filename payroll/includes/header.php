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
$role       = $_SESSION['role']     ?? 'employee';
$user_name  = $_SESSION['name']     ?? 'User';
$username   = $_SESSION['username'] ?? '';
$initials   = strtoupper(substr($user_name, 0, 1));
$page_title = $page_title ?? 'Dashboard';
$active_nav = $active_nav ?? '';
$depth      = $depth      ?? '../../';
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
            <div class="user-avatar"><?= $initials ?></div>
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
                <div class="topbar-badge">
                    <button class="btn-icon" title="Notifications">
                        <i class="fas fa-bell"></i>
                    </button>
                    <span class="badge">3</span>
                </div>
                <div class="topbar-user-chip">
                    <div class="topbar-avatar"><?= $initials ?></div>
                    <span class="topbar-username"><?= htmlspecialchars($user_name) ?></span>
                </div>
            </div>
        </header>

        <!-- Page Content starts here -->
        <main class="page-content">

<?php
// Entry point — redirect logged-in users to their dashboard, others to home page
session_start();
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':    header('Location: pages/admin/dashboard.php'); break;
        case 'hr':       header('Location: pages/hr/dashboard.php'); break;
        case 'finance':  header('Location: pages/finance/dashboard.php'); break;
        case 'employee': header('Location: pages/employee/dashboard.php'); break;
        default:         header('Location: home.php');
    }
    exit();
}
// Not logged in → show public home page
header('Location: home.php');
exit();
?>

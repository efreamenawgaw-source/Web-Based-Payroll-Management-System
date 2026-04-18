<?php $a = $active_nav ?? ''; ?>
<span class="nav-section-label">My Account</span>
<a href="<?= $depth ?>pages/employee/dashboard.php" class="<?= $a==='dashboard'?'active':'' ?>">
    <i class="fas fa-tachometer-alt nav-icon"></i> Dashboard
</a>
<a href="<?= $depth ?>pages/employee/payslips.php" class="<?= $a==='payslips'?'active':'' ?>">
    <i class="fas fa-file-invoice nav-icon"></i> My Payslips
</a>
<a href="<?= $depth ?>pages/employee/profile.php" class="<?= $a==='profile'?'active':'' ?>">
    <i class="fas fa-user-circle nav-icon"></i> My Profile
</a>

<span class="nav-section-label">Account</span>
<a href="<?= $depth ?>pages/profile/my_profile.php" class="<?= $a==='my_profile'?'active':'' ?>">
    <i class="fas fa-user-edit nav-icon"></i> My Profile
</a>

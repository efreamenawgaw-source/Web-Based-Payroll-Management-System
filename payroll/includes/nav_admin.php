<?php $a = $active_nav ?? ''; ?>
<span class="nav-section-label">Main</span>
<a href="<?= $depth ?>pages/admin/dashboard.php" class="<?= $a==='dashboard'?'active':'' ?>">
    <i class="fas fa-tachometer-alt nav-icon"></i> Dashboard
</a>

<span class="nav-section-label">User Management</span>
<a href="<?= $depth ?>pages/admin/users.php" class="<?= $a==='users'?'active':'' ?>">
    <i class="fas fa-users nav-icon"></i> Manage Users
</a>
<a href="<?= $depth ?>pages/admin/roles.php" class="<?= $a==='roles'?'active':'' ?>">
    <i class="fas fa-user-tag nav-icon"></i> Assign Roles
</a>

<span class="nav-section-label">System</span>
<a href="<?= $depth ?>pages/admin/audit.php" class="<?= $a==='audit'?'active':'' ?>">
    <i class="fas fa-history nav-icon"></i> Audit Log
</a>
<a href="<?= $depth ?>pages/admin/settings.php" class="<?= $a==='settings'?'active':'' ?>">
    <i class="fas fa-cog nav-icon"></i> Settings
</a>

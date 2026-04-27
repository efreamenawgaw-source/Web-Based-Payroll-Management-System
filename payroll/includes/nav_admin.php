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
<a href="<?= $depth ?>pages/admin/email_settings.php" class="<?= $a==='email_settings'?'active':'' ?>">
    <i class="fas fa-envelope nav-icon"></i> Email Settings
</a>
<a href="<?= $depth ?>pages/admin/contact_messages.php" class="<?= $a==='contact_messages'?'active':'' ?>"
   style="position:relative;">
    <i class="fas fa-inbox nav-icon"></i> Contact Messages
    <?php
    // Show unread badge
    try {
        $pdo_nav = getDB();
        $unread_nav = (int)$pdo_nav->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
        if ($unread_nav > 0): ?>
    <span style="position:absolute;right:14px;top:50%;transform:translateY(-50%);
                 background:var(--danger);color:white;border-radius:10px;
                 font-size:0.65rem;font-weight:700;padding:1px 6px;min-width:18px;text-align:center;">
        <?= $unread_nav ?>
    </span>
    <?php endif;
    } catch (Exception $e) { /* table may not exist yet */ }
    ?>
</a>

<span class="nav-section-label">Account</span>
<a href="<?= $depth ?>pages/profile/my_profile.php" class="<?= $a==='my_profile'?'active':'' ?>">
    <i class="fas fa-user-edit nav-icon"></i> My Profile
</a>

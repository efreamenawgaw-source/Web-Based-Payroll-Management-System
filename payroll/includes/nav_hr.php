<?php $a = $active_nav ?? ''; ?>
<span class="nav-section-label">Main</span>
<a href="<?= $depth ?>pages/hr/dashboard.php" class="<?= $a==='dashboard'?'active':'' ?>">
    <i class="fas fa-tachometer-alt nav-icon"></i> Dashboard
</a>

<span class="nav-section-label">Employee Management</span>
<a href="<?= $depth ?>pages/hr/employees.php" class="<?= $a==='employees'?'active':'' ?>">
    <i class="fas fa-id-card nav-icon"></i> All Employees
</a>
<a href="<?= $depth ?>pages/hr/register_employee.php" class="<?= $a==='register'?'active':'' ?>">
    <i class="fas fa-user-plus nav-icon"></i> Register Employee
</a>
<a href="<?= $depth ?>pages/hr/allowances.php" class="<?= $a==='allowances'?'active':'' ?>">
    <i class="fas fa-hand-holding-usd nav-icon"></i> Manage Allowances
</a>
<a href="<?= $depth ?>pages/hr/deductions.php" class="<?= $a==='deductions'?'active':'' ?>">
    <i class="fas fa-minus-circle nav-icon"></i> Manage Deductions
</a>
<a href="<?= $depth ?>pages/hr/working_days.php" class="<?= $a==='working_days'?'active':'' ?>">
    <i class="fas fa-calendar-check nav-icon"></i> Working Days
</a>
<a href="<?= $depth ?>pages/hr/status.php" class="<?= $a==='status'?'active':'' ?>">
    <i class="fas fa-toggle-on nav-icon"></i> Employee Status
</a>

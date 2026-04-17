<?php $a = $active_nav ?? ''; ?>
<span class="nav-section-label">Main</span>
<a href="<?= $depth ?>pages/finance/dashboard.php" class="<?= $a==='dashboard'?'active':'' ?>">
    <i class="fas fa-tachometer-alt nav-icon"></i> Dashboard
</a>

<span class="nav-section-label">Payroll</span>
<a href="<?= $depth ?>pages/finance/process_payroll.php" class="<?= $a==='process'?'active':'' ?>">
    <i class="fas fa-play-circle nav-icon"></i> Process Payroll
</a>
<a href="<?= $depth ?>pages/finance/verify_payroll.php" class="<?= $a==='verify'?'active':'' ?>">
    <i class="fas fa-check-double nav-icon"></i> Verify Payroll
</a>
<a href="<?= $depth ?>pages/finance/generate_payslip.php" class="<?= $a==='payslip'?'active':'' ?>">
    <i class="fas fa-file-invoice-dollar nav-icon"></i> Generate Payslips
</a>

<span class="nav-section-label">Reports</span>
<a href="<?= $depth ?>pages/finance/reports.php" class="<?= $a==='reports'?'active':'' ?>">
    <i class="fas fa-chart-bar nav-icon"></i> Payroll Reports
</a>

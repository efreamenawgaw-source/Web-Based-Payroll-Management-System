<?php
$page_title = 'Employee Dashboard';
$active_nav = 'dashboard';
$depth      = '../../';
require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <i class="fas fa-home"></i><span>/</span><span>Employee</span><span>/</span><span>Dashboard</span>
</div>

<div class="page-header">
    <h1>Employee Dashboard</h1>
    <p>View your payslips, salary details, and personal payroll information.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-wallet"></i></div>
        <div class="stat-info">
            <p>Total Annual Gross</p>
            <h2>ETB 78,500</h2>
            <span class="stat-change up"><i class="fas fa-calendar"></i> Year 2023</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-money-check-alt"></i></div>
        <div class="stat-info">
            <p>Current Month Net</p>
            <h2>ETB 5,210</h2>
            <span class="stat-change up"><i class="fas fa-check-circle"></i> June 2023</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-file-invoice"></i></div>
        <div class="stat-info">
            <p>Next Payslip</p>
            <h2>Jul 28</h2>
            <span class="stat-change up"><i class="fas fa-clock"></i> Available soon</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <p>Total Deductions</p>
            <h2>ETB 3,290</h2>
            <span class="stat-change down"><i class="fas fa-minus-circle"></i> Tax + Pension</span>
        </div>
    </div>
</div>

<!-- Main Grid -->
<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- Current Month Payslip Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-invoice-dollar" style="color:var(--primary);margin-right:8px"></i>June 2023 Payslip Breakdown</h3>
            <span class="badge badge-success">Verified</span>
        </div>
        <div class="card-body">
            <?php
            $breakdown = [
                ['label'=>'Basic Salary',          'value'=>'ETB 78,500', 'type'=>'income'],
                ['label'=>'Housing Allowance',     'value'=>'ETB 1,000',  'type'=>'income'],
                ['label'=>'Transport Allowance',   'value'=>'ETB 500',    'type'=>'income'],
                ['label'=>'Gross Salary',          'value'=>'ETB 80,000', 'type'=>'total'],
                ['label'=>'Income Tax',            'value'=>'- ETB 21,110','type'=>'deduction'],
                ['label'=>'Pension (7%)',          'value'=>'- ETB 7,200', 'type'=>'deduction'],
                ['label'=>'Employer Pension (11%)','value'=>'ETB 11,000', 'type'=>'info'],
                ['label'=>'Net Pay',               'value'=>'ETB 5,210',  'type'=>'net'],
            ];
            foreach ($breakdown as $b):
                $color = match($b['type']) {
                    'income'    => 'var(--gray-800)',
                    'total'     => 'var(--primary)',
                    'deduction' => 'var(--danger)',
                    'info'      => 'var(--info)',
                    'net'       => 'var(--success)',
                    default     => 'var(--gray-800)',
                };
                $weight = in_array($b['type'], ['total','net']) ? '700' : '500';
                $border = in_array($b['type'], ['total','net']) ? '2px solid var(--gray-200)' : '1px solid var(--gray-200)';
            ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:<?= $border ?>;">
                <span style="font-size:0.875rem;color:var(--gray-600);"><?= $b['label'] ?></span>
                <span style="font-weight:<?= $weight ?>;color:<?= $color ?>;"><?= $b['value'] ?></span>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:18px;display:flex;gap:10px;">
                <a href="payslips.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-eye"></i> View Payslip
                </a>
                <a href="payslips.php?download=1" class="btn btn-secondary btn-sm">
                    <i class="fas fa-download"></i> Download PDF
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Payslips Archive -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-archive" style="color:var(--primary);margin-right:8px"></i>Recent Payslips</h3>
            <a href="payslips.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Month</th><th>Year</th><th>Net Salary</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $payslips = [
                            ['June','2023','ETB 5,210'],
                            ['May', '2023','ETB 5,210'],
                            ['April','2023','ETB 5,210'],
                            ['March','2023','ETB 5,210'],
                            ['February','2023','ETB 5,210'],
                        ];
                        foreach ($payslips as $ps): ?>
                        <tr>
                            <td><strong><?= $ps[0] ?></strong></td>
                            <td><?= $ps[1] ?></td>
                            <td class="text-bold text-success"><?= $ps[2] ?></td>
                            <td>
                                <button class="btn btn-secondary btn-sm btn-icon-only" title="View"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-primary btn-sm btn-icon-only" title="Download PDF"><i class="fas fa-download"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Personal Info Summary -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-circle" style="color:var(--primary);margin-right:8px"></i>My Personal Information</h3>
        <a href="profile.php" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> View Full Profile</a>
    </div>
    <div class="card-body">
        <div class="grid-3" style="gap:20px;">
            <?php
            $info = [
                ['label'=>'Full Name',       'value'=>'Admasu Dejene',    'icon'=>'fas fa-user'],
                ['label'=>'Employee ID',     'value'=>'EMP-101',          'icon'=>'fas fa-id-badge'],
                ['label'=>'Department',      'value'=>'Faculty of Computing','icon'=>'fas fa-building'],
                ['label'=>'Position',        'value'=>'Lecturer',         'icon'=>'fas fa-briefcase'],
                ['label'=>'Basic Salary',    'value'=>'ETB 78,500',       'icon'=>'fas fa-money-bill'],
                ['label'=>'Employment Status','value'=>'Active',          'icon'=>'fas fa-check-circle'],
            ];
            foreach ($info as $i): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:14px;background:var(--bg-light);border-radius:var(--radius);">
                <div style="width:38px;height:38px;background:var(--white);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:1rem;flex-shrink:0;box-shadow:var(--shadow-sm);">
                    <i class="<?= $i['icon'] ?>"></i>
                </div>
                <div>
                    <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;letter-spacing:0.4px;"><?= $i['label'] ?></p>
                    <p style="font-size:0.9rem;font-weight:600;color:var(--gray-800);margin:0;"><?= $i['value'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

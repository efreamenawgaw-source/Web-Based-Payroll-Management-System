<?php
$page_title = 'Finance Dashboard';
$active_nav = 'dashboard';
$depth      = '../../';
require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <i class="fas fa-home"></i><span>/</span><span>Finance</span><span>/</span><span>Dashboard</span>
</div>

<div class="page-header">
    <h1>Finance Dashboard</h1>
    <p>Process payroll, verify calculations, and generate financial reports.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-info">
            <p>Total Monthly Payout</p>
            <h2>ETB 125,450</h2>
            <span class="stat-change up"><i class="fas fa-arrow-up"></i> June 2023</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <p>Payrolls Processed</p>
            <h2>132 / 135</h2>
            <span class="stat-change up"><i class="fas fa-check"></i> 97.8% complete</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <p>Verification Pending</p>
            <h2>3</h2>
            <span class="stat-change down"><i class="fas fa-exclamation-circle"></i> Needs review</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <p>Tax Deductions (ETB)</p>
            <h2>21,110</h2>
            <span class="stat-change up"><i class="fas fa-percent"></i> Income tax</span>
        </div>
    </div>
</div>

<!-- Summary Row -->
<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- Payroll Summary Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie" style="color:var(--primary);margin-right:8px"></i>June 2023 Payroll Summary</h3>
            <span class="badge badge-success">Processed</span>
        </div>
        <div class="card-body">
            <?php
            $summary = [
                ['label'=>'Gross Salary Total',       'value'=>'ETB 125,450', 'color'=>'var(--primary)'],
                ['label'=>'Pension Deductions (7%)',  'value'=>'ETB 7,200',   'color'=>'var(--warning)'],
                ['label'=>'Employer Pension (11%)',   'value'=>'ETB 11,000',  'color'=>'var(--info)'],
                ['label'=>'Income Tax Total',         'value'=>'ETB 21,110',  'color'=>'var(--danger)'],
                ['label'=>'Net Pay Total',            'value'=>'ETB 97,140',  'color'=>'var(--success)'],
            ];
            foreach ($summary as $s): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--gray-200);">
                <span style="font-size:0.875rem;color:var(--gray-600);"><?= $s['label'] ?></span>
                <span style="font-weight:700;color:<?= $s['color'] ?>;"><?= $s['value'] ?></span>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:18px;display:flex;gap:10px;">
                <a href="process_payroll.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-play"></i> Run Payroll
                </a>
                <a href="verify_payroll.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-check-double"></i> Verify
                </a>
                <a href="reports.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-download"></i> Report
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt" style="color:var(--primary);margin-right:8px"></i>Quick Actions</h3>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:12px;">
                <a href="process_payroll.php" class="btn btn-primary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-play-circle"></i> Run Monthly Payroll
                </a>
                <a href="verify_payroll.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-check-double"></i> Verify Payroll Data
                </a>
                <a href="generate_payslip.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-file-invoice-dollar"></i> Generate Payslips
                </a>
                <a href="reports.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-chart-bar"></i> Generate Reports
                </a>
            </div>

            <!-- Tax Info Box -->
            <div style="margin-top:20px;background:var(--bg-light);border-radius:var(--radius);padding:16px;">
                <h4 style="font-size:0.85rem;color:var(--primary);margin-bottom:10px;">
                    <i class="fas fa-info-circle"></i> Ethiopian Tax & Pension Rules
                </h4>
                <div style="font-size:0.8rem;color:var(--gray-600);line-height:1.8;">
                    <div>• Employee Pension: <strong>7%</strong> of basic salary</div>
                    <div>• Employer Pension: <strong>11%</strong> of basic salary</div>
                    <div>• Income Tax: Based on <strong>Ethiopian tax brackets</strong></div>
                    <div>• Taxable Income = Gross Salary − Pension Deduction</div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Recent Payroll Activity -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list-alt" style="color:var(--primary);margin-right:8px"></i>Recent Payroll Activity</h3>
        <a href="process_payroll.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Payroll</a>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Employee</th><th>Period</th>
                        <th>Gross (ETB)</th><th>Tax (ETB)</th><th>Net Pay (ETB)</th>
                        <th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $payrolls = [
                        [201,'Admasu Dejene','June 2023','12,500','1,875','9,375','Calculated'],
                        [202,'Bekele Abebe','June 2023','15,200','2,280','11,420','Verified'],
                        [203,'Chaltu Kebede','June 2023','9,800','1,470','7,330','Verified'],
                        [204,'Dawit Solomon','June 2023','11,000','1,650','8,250','Calculated'],
                        [205,'Eleni Tadesse','June 2023','13,500','2,025','10,125','Calculated'],
                    ];
                    foreach ($payrolls as $p): ?>
                    <tr>
                        <td><?= $p[0] ?></td>
                        <td><strong><?= $p[1] ?></strong></td>
                        <td><?= $p[2] ?></td>
                        <td><?= $p[3] ?></td>
                        <td class="text-danger"><?= $p[4] ?></td>
                        <td class="text-bold text-success"><?= $p[5] ?></td>
                        <td>
                            <span class="badge <?= $p[6]==='Verified'?'badge-success':'badge-warning' ?>">
                                <?= $p[6] ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-secondary btn-sm btn-icon-only" title="View"><i class="fas fa-eye"></i></button>
                            <?php if ($p[6]==='Calculated'): ?>
                            <button class="btn btn-success btn-sm btn-icon-only" title="Verify"><i class="fas fa-check"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

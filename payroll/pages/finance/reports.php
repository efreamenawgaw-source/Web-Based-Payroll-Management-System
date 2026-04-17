<?php
$page_title = 'Payroll Reports';
$active_nav = 'reports';
$depth      = '../../';
require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">Finance</a><span>/</span><span>Payroll Reports</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Payroll Reports</h1>
        <p>Generate and download financial reports for management, auditing, and CBE bank transfers.</p>
    </div>
    <button class="btn btn-primary" onclick="window.print()">
        <i class="fas fa-print"></i> Print Report
    </button>
</div>

<!-- Report Generator -->
<div class="card mb-3">
    <div class="card-header">
        <h3><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px"></i>Generate Report</h3>
    </div>
    <div class="card-body">
        <div class="filter-bar">
            <div class="form-group" style="margin:0;flex:1;">
                <select class="form-control">
                    <option>Monthly Payroll Summary</option>
                    <option>Annual Payroll Report</option>
                    <option>Tax Deduction Report</option>
                    <option>Pension Contribution Report</option>
                    <option>CBE Bank Transfer List</option>
                    <option>Department-wise Payroll</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <select class="form-control">
                    <option>June 2023</option>
                    <option>May 2023</option>
                    <option>April 2023</option>
                    <option>Q2 2023 (Apr–Jun)</option>
                    <option>Full Year 2023</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <select class="form-control">
                    <option>All Departments</option>
                    <option>Faculty of Computing</option>
                    <option>Faculty of Engineering</option>
                    <option>Administrative Office</option>
                </select>
            </div>
            <button class="btn btn-primary"><i class="fas fa-file-alt"></i> Generate</button>
            <button class="btn btn-secondary"><i class="fas fa-download"></i> Export Excel</button>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-info">
            <p>Total Gross (June)</p>
            <h2>ETB 1.69M</h2>
            <span class="stat-change up"><i class="fas fa-arrow-up"></i> +2.3% vs May</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="stat-info">
            <p>Total Net Pay (June)</p>
            <h2>ETB 1.28M</h2>
            <span class="stat-change up"><i class="fas fa-check-circle"></i> Disbursed</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-percent"></i></div>
        <div class="stat-info">
            <p>Total Tax Collected</p>
            <h2>ETB 285K</h2>
            <span class="stat-change up"><i class="fas fa-university"></i> To Revenue</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-piggy-bank"></i></div>
        <div class="stat-info">
            <p>Total Pension (Emp+Org)</p>
            <h2>ETB 304K</h2>
            <span class="stat-change up"><i class="fas fa-shield-alt"></i> 7% + 11%</span>
        </div>
    </div>
</div>

<!-- Monthly Payroll Summary Table -->
<div class="card mb-3">
    <div class="card-header">
        <h3><i class="fas fa-table" style="color:var(--primary);margin-right:8px"></i>Monthly Payroll Summary — June 2023</h3>
        <div class="d-flex gap-2">
            <button class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</button>
            <button class="btn btn-secondary btn-sm"><i class="fas fa-file-pdf"></i> PDF</button>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Basic (ETB)</th>
                        <th>Gross (ETB)</th>
                        <th>Pension Emp (ETB)</th>
                        <th>Pension Org (ETB)</th>
                        <th>Tax (ETB)</th>
                        <th>Net Pay (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $report_data = [
                        ['EMP-101','Admasu Dejene',   'Computing',   12500, 14000, 875,   1375,  1948.25, 11176.75],
                        ['EMP-102','Bekele Abebe',    'Engineering', 15200, 17900, 1064,  1672,  2837.10, 14998.90],
                        ['EMP-103','Chaltu Kebede',   'Admin',       9800,  11000, 686,   1078,  1357.90, 8956.10],
                        ['EMP-104','Dawit Solomon',   'Finance',     11000, 12700, 770,   1210,  1680.50, 10249.50],
                        ['EMP-105','Eleni Tadesse',   'Computing',   13500, 15600, 945,   1485,  2448.25, 12206.75],
                        ['EMP-106','Fatuma Ali',      'Science',     12000, 13500, 840,   1320,  1848.50, 10811.50],
                        ['EMP-107','Girma Haile',     'IT Support',  8500,  9400,  595,   935,   1040.50, 7764.50],
                        ['EMP-109','Ibrahim Yusuf',   'Engineering', 22000, 25000, 1540,  2420,  7175.00, 16285.00],
                        ['EMP-110','Kidist Mekonnen', 'Admin',       8800,  9700,  616,   968,   1100.40, 7983.60],
                    ];
                    $t_gross = $t_pension_e = $t_pension_o = $t_tax = $t_net = 0;
                    foreach ($report_data as $r):
                        $t_gross     += $r[4];
                        $t_pension_e += $r[5];
                        $t_pension_o += $r[6];
                        $t_tax       += $r[7];
                        $t_net       += $r[8];
                    ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= $r[0] ?></span></td>
                        <td><strong><?= $r[1] ?></strong></td>
                        <td><?= $r[2] ?></td>
                        <td><?= number_format($r[3], 2) ?></td>
                        <td><?= number_format($r[4], 2) ?></td>
                        <td class="text-warning"><?= number_format($r[5], 2) ?></td>
                        <td class="text-info"><?= number_format($r[6], 2) ?></td>
                        <td class="text-danger"><?= number_format($r[7], 2) ?></td>
                        <td class="text-bold text-success"><?= number_format($r[8], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--bg-light);font-weight:700;">
                        <td colspan="4" style="padding:12px 16px;color:var(--primary);">TOTALS (9 shown)</td>
                        <td style="padding:12px 16px;"><?= number_format($t_gross, 2) ?></td>
                        <td style="padding:12px 16px;color:var(--warning);"><?= number_format($t_pension_e, 2) ?></td>
                        <td style="padding:12px 16px;color:var(--info);"><?= number_format($t_pension_o, 2) ?></td>
                        <td style="padding:12px 16px;color:var(--danger);"><?= number_format($t_tax, 2) ?></td>
                        <td style="padding:12px 16px;color:var(--success);"><?= number_format($t_net, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Department Breakdown -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-building" style="color:var(--primary);margin-right:8px"></i>Department-wise Payroll Breakdown</h3>
    </div>
    <div class="card-body">
        <?php
        $depts = [
            ['Faculty of Computing',  72, 1050000, 'var(--primary)', 62],
            ['Faculty of Engineering',28, 420000,  'var(--success)', 25],
            ['Administrative Office', 20, 196000,  'var(--warning)', 12],
            ['Finance Office',        8,  88000,   'var(--info)',    5],
            ['HR Office',             7,  68600,   'var(--danger)',  4],
        ];
        foreach ($depts as $d): ?>
        <div style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <div>
                    <span style="font-weight:600;font-size:0.9rem;"><?= $d[0] ?></span>
                    <span class="badge badge-gray" style="margin-left:8px;"><?= $d[1] ?> staff</span>
                </div>
                <span style="font-weight:700;color:<?= $d[3] ?>;">ETB <?= number_format($d[2]) ?></span>
            </div>
            <div style="background:var(--gray-200);border-radius:20px;height:10px;">
                <div style="width:<?= $d[4] ?>%;background:<?= $d[3] ?>;height:10px;border-radius:20px;transition:width 0.5s;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

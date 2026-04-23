<?php
session_start();
$page_title = 'Finance Dashboard';
$active_nav = 'dashboard';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/header.php';

$pdo = getDB();

// â”€â”€ Current month / year â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$cur_month = (int)date('n');
$cur_year  = (int)date('Y');
$cur_label = date('F Y');

// â”€â”€ Latest processed period â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$latest_period = $pdo->query("
    SELECT pp.*,
           SUM(pr.gross_salary)     AS total_gross,
           SUM(pr.income_tax)       AS total_tax,
           SUM(pr.pension_employee) AS total_pension_emp,
           SUM(pr.pension_employer) AS total_pension_org,
           SUM(pr.other_deductions) AS total_other_ded,
           SUM(pr.net_pay)          AS total_net,
           COUNT(pr.record_id)      AS emp_count
    FROM   payroll_periods pp
    LEFT JOIN payroll_records pr ON pp.period_id = pr.period_id
    WHERE  pp.status IN ('processed','verified','finalized')
    GROUP  BY pp.period_id
    ORDER  BY pp.period_year DESC, pp.period_month DESC
    LIMIT  1
")->fetch();

// â”€â”€ Payroll period status counts â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$period_stats = $pdo->query("
    SELECT
        SUM(CASE WHEN status = 'processed'  THEN 1 ELSE 0 END) AS `processed`,
        SUM(CASE WHEN status = 'verified'   THEN 1 ELSE 0 END) AS `verified`,
        SUM(CASE WHEN status = 'finalized'  THEN 1 ELSE 0 END) AS `finalized`,
        SUM(CASE WHEN status = 'pending'    THEN 1 ELSE 0 END) AS `pending`,
        COUNT(*) AS `total`
    FROM payroll_periods
")->fetch();

// â”€â”€ Active employees count â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$active_emp = (int)$pdo->query("
    SELECT COUNT(*) FROM employees WHERE status = 'active'
")->fetchColumn();

// â”€â”€ Working days submitted for current month â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$wd_submitted = (int)$pdo->prepare("
    SELECT COUNT(*) FROM working_days
    WHERE period_month = ? AND period_year = ?
")->execute([$cur_month, $cur_year])
    ? (function() use ($pdo, $cur_month, $cur_year) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM working_days WHERE period_month=? AND period_year=?");
        $s->execute([$cur_month, $cur_year]);
        return (int)$s->fetchColumn();
    })()
    : 0;

// Simpler approach:
$wds = $pdo->prepare("SELECT COUNT(*) FROM working_days WHERE period_month=? AND period_year=?");
$wds->execute([$cur_month, $cur_year]);
$wd_submitted = (int)$wds->fetchColumn();

// â”€â”€ Payslips generated total â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$total_payslips = (int)$pdo->query("SELECT COUNT(*) FROM payslips")->fetchColumn();

// â”€â”€ Recent payroll records (last 8) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$recent_records = $pdo->query("
    SELECT pr.record_id, pr.emp_id, pr.gross_salary,
           pr.income_tax, pr.net_pay,
           e.full_name,
           pp.period_label, pp.status AS period_status
    FROM   payroll_records pr
    JOIN   employees e        ON pr.emp_id = e.emp_id
    JOIN   payroll_periods pp ON pr.period_id = pp.period_id
    ORDER  BY pr.calculated_at DESC
    LIMIT  8
")->fetchAll();

// â”€â”€ All periods list â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$all_periods = $pdo->query("
    SELECT pp.period_id, pp.period_label, pp.status,
           COUNT(pr.record_id)  AS emp_count,
           SUM(pr.net_pay)      AS total_net,
           SUM(pr.gross_salary) AS total_gross,
           COUNT(ps.payslip_id) AS payslips_count
    FROM   payroll_periods pp
    LEFT JOIN payroll_records pr ON pp.period_id = pr.period_id
    LEFT JOIN payslips ps        ON pp.period_id = ps.period_id
    GROUP  BY pp.period_id
    ORDER  BY pp.period_year DESC, pp.period_month DESC
    LIMIT  6
")->fetchAll();

// â”€â”€ Annual totals (current year) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$annual = $pdo->query("
    SELECT
        SUM(pr.gross_salary)     AS total_gross,
        SUM(pr.income_tax)       AS total_tax,
        SUM(pr.pension_employee) AS total_pension_emp,
        SUM(pr.pension_employer) AS total_pension_org,
        SUM(pr.net_pay)          AS total_net,
        COUNT(DISTINCT pp.period_id) AS months_processed
    FROM payroll_periods pp
    JOIN payroll_records pr ON pp.period_id = pr.period_id
    WHERE pp.period_year = " . $cur_year . "
    AND   pp.status IN ('verified','finalized')
")->fetch();

$status_badge = [
    'pending'    => 'badge-gray',
    'processed'  => 'badge-warning',
    'verified'   => 'badge-success',
    'finalized'  => 'badge-primary',
];
?>

<div class="breadcrumb">
    <i class="fas fa-home"></i><span>/</span>
    <span>Finance</span><span>/</span><span>Dashboard</span>
</div>

<div class="page-header">
    <h1>Finance Dashboard</h1>
    <p>Process payroll, verify calculations, and generate financial reports.</p>
</div>

<!-- â”€â”€ Stats â”€â”€ -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-info">
            <p>Latest Net Payout</p>
            <h2>
                <?= $latest_period
                    ? 'ETB ' . number_format($latest_period['total_net'], 0)
                    : 'â€”' ?>
            </h2>
            <span class="stat-change up">
                <?= $latest_period ? htmlspecialchars($latest_period['period_label']) : 'No data yet' ?>
            </span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <p>Active Employees</p>
            <h2><?= $active_emp ?></h2>
            <span class="stat-change up">
                <?= $wd_submitted ?>/<?= $active_emp ?> days submitted
                <?= $cur_label ?>
            </span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <p>Pending Verification</p>
            <h2><?= $period_stats['processed'] ?? 0 ?></h2>
            <span class="stat-change <?= ($period_stats['processed'] ?? 0) > 0 ? 'down' : 'up' ?>">
                <?= ($period_stats['processed'] ?? 0) > 0 ? 'Needs review' : 'All clear' ?>
            </span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-file-invoice"></i></div>
        <div class="stat-info">
            <p>Payslips Generated</p>
            <h2><?= $total_payslips ?></h2>
            <span class="stat-change up">Total all time</span>
        </div>
    </div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- â”€â”€ Latest Period Summary â”€â”€ -->
    <div class="card">
        <div class="card-header">
            <h3>
                <i class="fas fa-chart-pie" style="color:var(--primary);margin-right:8px"></i>
                <?= $latest_period
                    ? htmlspecialchars($latest_period['period_label']) . ' â€” Summary'
                    : 'No Payroll Processed Yet' ?>
            </h3>
            <?php if ($latest_period): ?>
            <span class="badge <?= $status_badge[$latest_period['status']] ?? 'badge-gray' ?>">
                <?= ucfirst($latest_period['status']) ?>
            </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($latest_period): ?>
            <?php
            $summary_rows = [
                ['Employees Processed',   $latest_period['emp_count'],                                    'var(--primary)'],
                ['Total Gross Earnings',  'ETB ' . number_format($latest_period['total_gross'], 2),       'var(--primary)'],
                ['Total Income Tax',      'ETB ' . number_format($latest_period['total_tax'], 2),         'var(--danger)'],
                ['Employee 11%)', 'ETB ' . number_format($latest_period['total_pension_emp'], 2), 'var(--warning)'],
                ['Employer 18%)','ETB ' . number_format($latest_period['total_pension_org'], 2), 'var(--info)'],
                ['Other Deductions',      'ETB ' . number_format($latest_period['total_other_ded'], 2),   'var(--gray-600)'],
                ['Total Net Pay',         'ETB ' . number_format($latest_period['total_net'], 2),         'var(--success)'],
            ];
            foreach ($summary_rows as $s): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:9px 0;border-bottom:1px solid var(--gray-200);">
                <span style="font-size:0.875rem;color:var(--gray-600);"><?= $s[0] ?></span>
                <span style="font-weight:700;color:<?= $s[2] ?>;"><?= $s[1] ?></span>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
                <?php if ($latest_period['status'] === 'processed'): ?>
                <a href="verify_payroll.php?period_id=<?= $latest_period['period_id'] ?>"
                   class="btn btn-success btn-sm">
                    <i class="fas fa-check-double"></i> Verify Now
                </a>
                <?php elseif ($latest_period['status'] === 'verified'): ?>
                <a href="generate_payslip.php?period_id=<?= $latest_period['period_id'] ?>"
                   class="btn btn-primary btn-sm">
                    <i class="fas fa-file-invoice-dollar"></i> Generate Payslips
                </a>
                <?php endif; ?>
                <a href="reports.php?period_id=<?= $latest_period['period_id'] ?>"
                   class="btn btn-secondary btn-sm">
                    <i class="fas fa-chart-bar"></i> View Report
                </a>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-play-circle"></i></div>
                <p>No payroll processed yet.</p>
                <a href="process_payroll.php" class="btn btn-primary btn-sm mt-2">
                    <i class="fas fa-play-circle"></i> Process First Payroll
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- â”€â”€ Quick Actions + Current Month Status â”€â”€ -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt" style="color:var(--primary);margin-right:8px"></i>
                Quick Actions
            </h3>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
                <a href="process_payroll.php" class="btn btn-primary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-play-circle"></i> Run Monthly Payroll
                </a>
                <a href="verify_payroll.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-check-double"></i> Verify Payroll
                    <?php if (($period_stats['processed'] ?? 0) > 0): ?>
                    <span class="badge badge-warning" style="margin-left:auto;">
                        <?= $period_stats['processed'] ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="generate_payslip.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-file-invoice-dollar"></i> Generate Payslips
                </a>
                <a href="reports.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-chart-bar"></i> Payroll Reports
                </a>
            </div>

            <!-- Current month readiness -->
            <div style="background:var(--bg-light);border-radius:var(--radius);padding:14px;">
                <h4 style="font-size:0.82rem;color:var(--primary);margin-bottom:10px;">
                    <i class="fas fa-calendar-check"></i>
                    <?= $cur_label ?> â€” Readiness
                </h4>
                <?php
                $readiness = [
                    ['HR Working Days Submitted',
                     $wd_submitted >= $active_emp && $active_emp > 0,
                     "{$wd_submitted}/{$active_emp} employees"],
                    ['Active Employees Available',
                     $active_emp > 0,
                     "{$active_emp} active"],
                    ['Previous Period Verified',
                     ($period_stats['verified'] ?? 0) > 0 || ($period_stats['finalized'] ?? 0) > 0,
                     ($period_stats['verified'] ?? 0) + ($period_stats['finalized'] ?? 0) . ' verified'],
                ];
                foreach ($readiness as $r): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:7px 0;
                            border-bottom:1px solid var(--gray-200);">
                    <i class="fas <?= $r[1] ? 'fa-check-circle' : 'fa-times-circle' ?>"
                       style="color:<?= $r[1] ? 'var(--success)' : 'var(--danger)' ?>;font-size:1rem;flex-shrink:0;"></i>
                    <div style="flex:1;">
                        <span style="font-size:0.82rem;"><?= $r[0] ?></span>
                    </div>
                    <span style="font-size:0.75rem;color:var(--gray-400);"><?= $r[2] ?></span>
                </div>
                <?php endforeach; ?>

                <!-- Tax rules reminder -->
                <div style="margin-top:12px;font-size:0.78rem;color:var(--gray-600);line-height:1.8;">
                    <div>â€¢ Employee 11%</strong> of basic salary</div>
                    <div>â€¢ Employer 18%</strong> of basic salary</div>
                    <div>â€¢ Credit Association: <strong>10%</strong> of basic salary</div>
                    <div>â€¢ Renaissance Dam: <strong>1%</strong> of basic salary</div>
                    <div>â€¢ Income Tax: <strong>2025 Brackets</strong> on gross earnings</div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- â”€â”€ Recent Payroll Records â”€â”€ -->
<div class="card mb-3">
    <div class="card-header">
        <h3>
            <i class="fas fa-list-alt" style="color:var(--primary);margin-right:8px"></i>
            Recent Payroll Records
        </h3>
        <a href="process_payroll.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> New Payroll
        </a>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Period</th>
                        <th>Gross (ETB)</th>
                        <th>Tax (ETB)</th>
                        <th>Net Pay (ETB)</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_records)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding:32px;">
                            <i class="fas fa-play-circle" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:8px;"></i>
                            No payroll records yet.
                            <a href="process_payroll.php">Process the first payroll.</a>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recent_records as $r): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($r['emp_id']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($r['period_label']) ?></td>
                        <td><?= number_format($r['gross_salary'], 2) ?></td>
                        <td class="text-danger"><?= number_format($r['income_tax'], 2) ?></td>
                        <td class="text-bold text-success"><?= number_format($r['net_pay'], 2) ?></td>
                        <td>
                            <span class="badge <?= $status_badge[$r['period_status']] ?? 'badge-gray' ?>">
                                <?= ucfirst($r['period_status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="verify_payroll.php" class="btn btn-secondary btn-sm btn-icon-only" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- â”€â”€ Payroll Periods Overview â”€â”€ -->
<div class="grid-2" style="gap:24px;">

    <!-- Periods Table -->
    <div class="card">
        <div class="card-header">
            <h3>
                <i class="fas fa-calendar-alt" style="color:var(--primary);margin-right:8px"></i>
                Payroll Periods
            </h3>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Employees</th>
                            <th>Net Pay (ETB)</th>
                            <th>Payslips</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_periods)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:24px;">
                                No periods yet.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($all_periods as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['period_label']) ?></strong></td>
                            <td><?= $p['emp_count'] ?></td>
                            <td class="text-success text-bold">
                                <?= $p['total_net'] ? number_format($p['total_net'], 2) : 'â€”' ?>
                            </td>
                            <td>
                                <?php if ($p['payslips_count'] > 0): ?>
                                <span class="badge badge-success"><?= $p['payslips_count'] ?></span>
                                <?php else: ?>
                                <span class="badge badge-gray">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $status_badge[$p['status']] ?? 'badge-gray' ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Annual Summary -->
    <div class="card">
        <div class="card-header">
            <h3>
                <i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px"></i>
                <?= $cur_year ?> Annual Summary
            </h3>
            <span class="badge badge-primary">
                <?= $annual['months_processed'] ?? 0 ?> months
            </span>
        </div>
        <div class="card-body">
            <?php if ($annual && $annual['total_gross'] > 0): ?>
            <?php
            $annual_rows = [
                ['Total Gross Paid',      'ETB ' . number_format($annual['total_gross'], 2),       'var(--primary)'],
                ['Total Income Tax',      'ETB ' . number_format($annual['total_tax'], 2),         'var(--danger)'],
                ['Employee 11%)', 'ETB ' . number_format($annual['total_pension_emp'], 2), 'var(--warning)'],
                ['Employer 18%)','ETB ' . number_format($annual['total_pension_org'], 2), 'var(--info)'],
                ['Total Net Disbursed',   'ETB ' . number_format($annual['total_net'], 2),         'var(--success)'],
            ];
            foreach ($annual_rows as $ar): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:9px 0;border-bottom:1px solid var(--gray-200);">
                <span style="font-size:0.875rem;color:var(--gray-600);"><?= $ar[0] ?></span>
                <span style="font-weight:700;color:<?= $ar[2] ?>;"><?= $ar[1] ?></span>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:14px;">
                <a href="reports.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-chart-bar"></i> View Full Annual Report
                </a>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-chart-bar"></i></div>
                <p>No finalized payrolls for <?= $cur_year ?> yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once $depth . 'includes/footer.php'; ?>


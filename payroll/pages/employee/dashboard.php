<?php
session_start();
$page_title = 'Employee Dashboard';
$active_nav = 'dashboard';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/header.php';

$pdo = getDB();

// ── Get employee record linked to this user ────────────────
$emp_stmt = $pdo->prepare("
    SELECT e.*,
           d.dept_name,
           u.username, u.email AS user_email
    FROM   employees e
    JOIN   departments d ON e.dept_id = d.dept_id
    JOIN   users u       ON e.user_id = u.user_id
    WHERE  e.user_id = ?
");
$emp_stmt->execute([$_SESSION['user_id']]);
$employee = $emp_stmt->fetch();

// ── If no employee record linked ───────────────────────────
if (!$employee) {
    require_once $depth . 'includes/footer.php';
    exit();
}

$emp_id = $employee['emp_id'];

// ── Latest payslip (most recent period) ───────────────────
$latest_ps = $pdo->prepare("
    SELECT ps.payslip_id, ps.generated_at,
           pp.period_label, pp.period_month, pp.period_year,
           pr.record_id, pr.basic_salary,
           pr.housing, pr.transport, pr.position_allowance, pr.teaching,
           pr.total_allowances, pr.gross_salary,
           pr.income_tax, pr.pension_employee, pr.pension_employer,
           pr.other_deductions, pr.net_pay, pr.tax_bracket,
           COALESCE(wd.working_days, 30) AS working_days
    FROM   payslips ps
    JOIN   payroll_periods pp  ON ps.period_id = pp.period_id
    JOIN   payroll_records pr  ON pr.period_id = ps.period_id AND pr.emp_id = ps.emp_id
    LEFT JOIN working_days wd  ON wd.emp_id = ps.emp_id
        AND wd.period_month = pp.period_month
        AND wd.period_year  = pp.period_year
    WHERE  ps.emp_id = ?
    ORDER  BY pp.period_year DESC, pp.period_month DESC
    LIMIT  1
");
$latest_ps->execute([$emp_id]);
$latest = $latest_ps->fetch();

// ── Latest deductions breakdown ────────────────────────────
$latest_ded = null;
if ($latest) {
    $dv = $pdo->prepare("
        SELECT * FROM deductions
        WHERE emp_id = ? AND effective_month = ? AND effective_year = ?
    ");
    $dv->execute([$emp_id, $latest['period_month'], $latest['period_year']]);
    $latest_ded = $dv->fetch();

    // Apply defaults if no HR record
    if (!$latest_ded) {
        $basic = (float)$employee['basic_salary'];
        $latest_ded = [
            'credit_association' => round($basic * 0.10, 2),
            'renaissance_dam'    => round($basic * 0.01, 2),
            'loan_repayment'     => 0,
            'penalty'            => 0,
            'other'              => 0,
        ];
    }
}

// ── Recent payslips (last 5) ───────────────────────────────
$recent_ps = $pdo->prepare("
    SELECT ps.payslip_id,
           pp.period_label, pp.period_month, pp.period_year,
           pr.record_id, pr.gross_salary, pr.net_pay,
           pr.income_tax, pr.pension_employee
    FROM   payslips ps
    JOIN   payroll_periods pp ON ps.period_id = pp.period_id
    JOIN   payroll_records pr ON pr.period_id = ps.period_id AND pr.emp_id = ps.emp_id
    WHERE  ps.emp_id = ?
    ORDER  BY pp.period_year DESC, pp.period_month DESC
    LIMIT  5
");
$recent_ps->execute([$emp_id]);
$recent_payslips = $recent_ps->fetchAll();

// ── Annual totals (current year) ───────────────────────────
$annual = $pdo->prepare("
    SELECT
        SUM(pr.gross_salary)     AS total_gross,
        SUM(pr.net_pay)          AS total_net,
        SUM(pr.income_tax)       AS total_tax,
        SUM(pr.pension_employee) AS total_pension,
        COUNT(ps.payslip_id)     AS months_paid
    FROM   payslips ps
    JOIN   payroll_periods pp ON ps.period_id = pp.period_id
    JOIN   payroll_records pr ON pr.period_id = ps.period_id AND pr.emp_id = ps.emp_id
    WHERE  ps.emp_id = ?
    AND    pp.period_year = ?
");
$annual->execute([$emp_id, date('Y')]);
$annual_data = $annual->fetch();

// ── Current allowances ─────────────────────────────────────
$allow_stmt = $pdo->prepare("
    SELECT * FROM allowances
    WHERE emp_id = ? AND effective_to IS NULL
    ORDER BY effective_from DESC LIMIT 1
");
$allow_stmt->execute([$emp_id]);
$allowances = $allow_stmt->fetch();

$status_badge = [
    'active'      => 'badge-success',
    'on_leave'    => 'badge-warning',
    'terminated'  => 'badge-danger',
    'transferred' => 'badge-info',
    'promoted'    => 'badge-primary',
];
?>

<div class="breadcrumb">
    <i class="fas fa-home"></i><span>/</span>
    <span>Employee</span><span>/</span><span>Dashboard</span>
</div>

<div class="page-header">
    <h1>Welcome, <?= htmlspecialchars(explode(' ', $employee['full_name'])[0]) ?>!</h1>
    <p>View your payslips, salary details, and personal payroll information.</p>
</div>

<?php if (!$employee['user_id']): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    Your account is not linked to an employee record. Please contact HR.
</div>
<?php else: ?>

<!-- ── Stats ── -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-wallet"></i></div>
        <div class="stat-info">
            <p>Annual Gross (<?= date('Y') ?>)</p>
            <h2>
                <?= $annual_data['total_gross']
                    ? 'ETB ' . number_format($annual_data['total_gross'], 0)
                    : '&mdash;' ?>
            </h2>
            <span class="stat-change up">
                <?= $annual_data['months_paid'] ?? 0 ?> months paid
            </span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-money-check-alt"></i></div>
        <div class="stat-info">
            <p>Latest Net Pay</p>
            <h2>
                <?= $latest
                    ? 'ETB ' . number_format($latest['net_pay'], 0)
                    : '&mdash;' ?>
            </h2>
            <span class="stat-change up">
                <?= $latest ? htmlspecialchars($latest['period_label']) : 'No payslip yet' ?>
            </span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-file-invoice"></i></div>
        <div class="stat-info">
            <p>Total Payslips</p>
            <h2><?= count($recent_payslips) > 0 ? (int)$annual_data['months_paid'] : 0 ?></h2>
            <span class="stat-change up">
                <?= count($recent_payslips) > 0 ? 'Available to download' : 'None yet' ?>
            </span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <p>Latest Total Deductions</p>
            <h2>
                <?php if ($latest):
                    $total_ded = $latest['income_tax'] + $latest['pension_employee'] + $latest['other_deductions'];
                    echo 'ETB ' . number_format($total_ded, 0);
                else: ?>&mdash;<?php endif; ?>
            </h2>
            <span class="stat-change down">Tax + Pension + Other</span>
        </div>
    </div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- ── Latest Payslip Breakdown ── -->
    <div class="card">
        <div class="card-header">
            <h3>
                <i class="fas fa-file-invoice-dollar" style="color:var(--primary);margin-right:8px"></i>
                <?= $latest ? htmlspecialchars($latest['period_label']) . ' &mdash; Breakdown' : 'No Payslip Yet' ?>
            </h3>
            <?php if ($latest): ?>
            <span class="badge badge-success">Available</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($latest): ?>
            <?php
            $rows = [
                ['Basic Salary',          number_format($latest['basic_salary'], 2),  'var(--gray-800)', false],
            ];
            if ($latest['housing'] > 0)
                $rows[] = ['Housing Allowance', number_format($latest['housing'], 2), 'var(--gray-800)', false];
            if ($latest['transport'] > 0)
                $rows[] = ['Transport Allowance', number_format($latest['transport'], 2), 'var(--gray-800)', false];
            if ($latest['position_allowance'] > 0)
                $rows[] = ['Position Allowance', number_format($latest['position_allowance'], 2), 'var(--gray-800)', false];
            if ($latest['teaching'] > 0)
                $rows[] = ['Teaching Allowance', number_format($latest['teaching'], 2), 'var(--gray-800)', false];
            $rows[] = ['Gross Earnings', number_format($latest['gross_salary'], 2), 'var(--primary)', true];
            $rows[] = ['Income Tax (' . ($latest['tax_bracket'] ?? '') . ')', '- ' . number_format($latest['income_tax'], 2), 'var(--danger)', false];
            $rows[] = ['Employee Pension (11%)', '- ' . number_format($latest['pension_employee'], 2), 'var(--warning)', false];

            // Other deductions
            if ($latest_ded) {
                if ((float)$latest_ded['credit_association'] > 0)
                    $rows[] = ['Credit Association (10%)', '- ' . number_format($latest_ded['credit_association'], 2), 'var(--info)', false];
                if ((float)$latest_ded['renaissance_dam'] > 0)
                    $rows[] = ['Renaissance Dam (1%)', '- ' . number_format($latest_ded['renaissance_dam'], 2), 'var(--info)', false];
                if ((float)$latest_ded['loan_repayment'] > 0)
                    $rows[] = ['Loan Repayment', '- ' . number_format($latest_ded['loan_repayment'], 2), 'var(--warning)', false];
                if (((float)$latest_ded['penalty'] + (float)$latest_ded['other']) > 0)
                    $rows[] = ['Penalty / Other', '- ' . number_format((float)$latest_ded['penalty'] + (float)$latest_ded['other'], 2), 'var(--danger)', false];
            }
            $rows[] = ['NET PAY', number_format($latest['net_pay'], 2), 'var(--success)', true];
            foreach ($rows as $row): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:<?= $row[3] ? '10px' : '8px' ?> 0;
                        border-bottom:<?= $row[3] ? '2px' : '1px' ?> solid var(--gray-200);">
                <span style="font-size:0.875rem;color:var(--gray-600);"><?= $row[0] ?></span>
                <span style="font-weight:<?= $row[3] ? '700' : '500' ?>;color:<?= $row[2] ?>;">
                    ETB <?= $row[1] ?>
                </span>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:14px;padding:10px 12px;background:var(--info-light);
                        border-radius:var(--radius);font-size:0.78rem;color:var(--info);text-align:center;">
                <i class="fas fa-shield-alt"></i>
                Employer Pension (18%): <strong>ETB <?= number_format($latest['pension_employer'], 2) ?></strong> &mdash; paid by BiT
            </div>

            <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
                <a href="payslips.php?view=<?= $latest['record_id'] ?>"
                   class="btn btn-primary btn-sm">
                    <i class="fas fa-eye"></i> View Full Payslip
                </a>
                <a href="payslips.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-history"></i> All Payslips
                </a>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-file-invoice"></i></div>
                <p>No payslips generated yet. Finance will generate your payslip after processing payroll.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Recent Payslips + Personal Info ── -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Recent Payslips -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-archive" style="color:var(--primary);margin-right:8px"></i>
                    Recent Payslips
                </h3>
                <a href="payslips.php" class="btn btn-secondary btn-sm">View All</a>
            </div>
            <div class="card-body" style="padding:0">
                <?php if (empty($recent_payslips)): ?>
                <div class="empty-state" style="padding:24px;">
                    <p>No payslips yet.</p>
                </div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Net Pay (ETB)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payslips as $ps): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ps['period_label']) ?></strong></td>
                                <td class="text-bold text-success">
                                    <?= number_format($ps['net_pay'], 2) ?>
                                </td>
                                <td>
                                    <a href="payslips.php?view=<?= $ps['record_id'] ?>"
                                       class="btn btn-secondary btn-sm btn-icon-only" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Info -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-circle" style="color:var(--primary);margin-right:8px"></i>
                    My Info
                </h3>
                <a href="profile.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-eye"></i> Full Profile
                </a>
            </div>
            <div class="card-body" style="padding:14px;">
                <?php
                $quick_info = [
                    ['fas fa-id-badge',   'Employee ID',  $employee['emp_id']],
                    ['fas fa-building',   'Department',   $employee['dept_name']],
                    ['fas fa-briefcase',  'Position',     $employee['position']],
                    ['fas fa-money-bill', 'Basic Salary', 'ETB ' . number_format($employee['basic_salary'], 2)],
                ];
                foreach ($quick_info as $qi): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:8px 0;
                            border-bottom:1px solid var(--gray-200);">
                    <i class="<?= $qi[0] ?>" style="color:var(--primary);width:18px;text-align:center;"></i>
                    <span style="font-size:0.8rem;color:var(--gray-400);width:90px;flex-shrink:0;"><?= $qi[1] ?></span>
                    <span style="font-weight:600;font-size:0.88rem;"><?= htmlspecialchars($qi[2]) ?></span>
                </div>
                <?php endforeach; ?>
                <div style="display:flex;align-items:center;gap:10px;padding:8px 0;">
                    <i class="fas fa-circle" style="color:var(--success);width:18px;text-align:center;font-size:0.6rem;"></i>
                    <span style="font-size:0.8rem;color:var(--gray-400);width:90px;flex-shrink:0;">Status</span>
                    <span class="badge <?= $status_badge[$employee['status']] ?? 'badge-gray' ?>">
                        <?= ucfirst(str_replace('_', ' ', $employee['status'])) ?>
                    </span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ── Annual Summary ── -->
<?php if ($annual_data && $annual_data['total_gross'] > 0): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px"></i>
            <?= date('Y') ?> Annual Summary
        </h3>
        <span class="badge badge-primary"><?= $annual_data['months_paid'] ?> months</span>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;">
            <?php
            $ann_cards = [
                ['Total Gross',     $annual_data['total_gross'],   'var(--primary)', 'fas fa-money-bill-wave'],
                ['Total Tax Paid',  $annual_data['total_tax'],     'var(--danger)',  'fas fa-percent'],
                ['Total Pension',   $annual_data['total_pension'], 'var(--warning)', 'fas fa-piggy-bank'],
                ['Total Net Pay',   $annual_data['total_net'],     'var(--success)', 'fas fa-hand-holding-usd'],
            ];
            foreach ($ann_cards as $ac): ?>
            <div style="text-align:center;padding:14px;background:var(--bg-light);
                        border-radius:var(--radius);border-top:3px solid <?= $ac[2] ?>;">
                <i class="<?= $ac[3] ?>" style="color:<?= $ac[2] ?>;font-size:1.4rem;margin-bottom:8px;display:block;"></i>
                <p style="font-size:0.7rem;color:var(--gray-400);margin:0;text-transform:uppercase;"><?= $ac[0] ?></p>
                <p style="font-size:1rem;font-weight:700;color:<?= $ac[2] ?>;margin:4px 0 0;">
                    ETB <?= number_format($ac[1], 2) ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once $depth . 'includes/footer.php'; ?>


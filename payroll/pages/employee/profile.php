<?php
session_start();
$page_title = 'My Profile';
$active_nav = 'profile';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/header.php';

$pdo = getDB();

// ── Get full employee record ───────────────────────────────
$emp_stmt = $pdo->prepare("
    SELECT e.*,
           d.dept_name,
           u.username, u.email AS user_email, u.last_login
    FROM   employees e
    JOIN   departments d ON e.dept_id = d.dept_id
    JOIN   users u       ON e.user_id = u.user_id
    WHERE  e.user_id = ?
");
$emp_stmt->execute([$_SESSION['user_id']]);
$employee = $emp_stmt->fetch();

if (!$employee) {
    echo '<div class="alert alert-warning" style="margin:20px;">
            <i class="fas fa-exclamation-triangle"></i>
            Your account is not linked to an employee record. Please contact HR.
          </div>';
    require_once $depth . 'includes/footer.php';
    exit();
}

$emp_id = $employee['emp_id'];

// ── Current allowances ─────────────────────────────────────
$allow_stmt = $pdo->prepare("
    SELECT * FROM allowances
    WHERE emp_id = ? AND effective_to IS NULL
    ORDER BY effective_from DESC LIMIT 1
");
$allow_stmt->execute([$emp_id]);
$allowances = $allow_stmt->fetch();

// ── Latest payroll record ──────────────────────────────────
$latest_pr = $pdo->prepare("
    SELECT pr.*,
           pp.period_label, pp.period_month, pp.period_year,
           COALESCE(wd.working_days, 30) AS working_days
    FROM   payroll_records pr
    JOIN   payroll_periods pp ON pr.period_id = pp.period_id
    LEFT JOIN working_days wd ON wd.emp_id = pr.emp_id
        AND wd.period_month = pp.period_month
        AND wd.period_year  = pp.period_year
    WHERE  pr.emp_id = ?
    ORDER  BY pp.period_year DESC, pp.period_month DESC
    LIMIT  1
");
$latest_pr->execute([$emp_id]);
$latest = $latest_pr->fetch();

// ── Latest deductions ──────────────────────────────────────
$latest_ded = null;
if ($latest) {
    $dv = $pdo->prepare("
        SELECT * FROM deductions
        WHERE emp_id = ? AND effective_month = ? AND effective_year = ?
    ");
    $dv->execute([$emp_id, $latest['period_month'], $latest['period_year']]);
    $latest_ded = $dv->fetch();
}

// Apply defaults if no HR record
$basic = (float)$employee['basic_salary'];
$credit_assoc = ($latest_ded && (float)$latest_ded['credit_association'] > 0)
    ? (float)$latest_ded['credit_association']
    : round($basic * 0.10, 2);
$gerd = ($latest_ded && (float)$latest_ded['renaissance_dam'] > 0)
    ? (float)$latest_ded['renaissance_dam']
    : round($basic * 0.01, 2);
$loan    = $latest_ded ? (float)$latest_ded['loan_repayment'] : 0;
$penalty = $latest_ded ? (float)($latest_ded['penalty'] + $latest_ded['other']) : 0;

// ── Status change history ──────────────────────────────────
$status_hist = $pdo->prepare("
    SELECT h.previous_status, h.new_status, h.effective_date, h.reason,
           u.full_name AS changed_by
    FROM   employee_status_history h
    LEFT JOIN users u ON h.changed_by = u.user_id
    WHERE  h.emp_id = ?
    ORDER  BY h.changed_at DESC
    LIMIT  5
");
$status_hist->execute([$emp_id]);
$status_history = $status_hist->fetchAll();

// ── Gross salary calculation ───────────────────────────────
$total_allowances = $allowances
    ? (float)$allowances['housing'] + (float)$allowances['transport']
      + (float)$allowances['position_allowance'] + (float)$allowances['teaching']
      + (float)$allowances['other']
    : 0;
$gross_salary = $basic + $total_allowances;

$status_badge = [
    'active'      => 'badge-success',
    'on_leave'    => 'badge-warning',
    'terminated'  => 'badge-danger',
    'transferred' => 'badge-info',
    'promoted'    => 'badge-primary',
];

$initials = strtoupper(substr($employee['full_name'], 0, 1));
?>

<div class="breadcrumb">
    <a href="dashboard.php">Employee</a><span>/</span><span>My Profile</span>
</div>

<div class="page-header">
    <h1>My Personal Information</h1>
    <p>View your profile, salary structure, allowances, and deduction details.</p>
</div>

<!-- ── Profile Header ── -->
<div class="card mb-3">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
            <!-- Avatar -->
            <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);
                        display:flex;align-items:center;justify-content:center;
                        color:white;font-size:2rem;font-weight:700;flex-shrink:0;">
                <?= $initials ?>
            </div>
            <!-- Info -->
            <div style="flex:1;min-width:0;">
                <h2 style="margin:0 0 4px;"><?= htmlspecialchars($employee['full_name']) ?></h2>
                <p style="color:var(--gray-600);margin:0 0 8px;">
                    <?= htmlspecialchars($employee['position']) ?> —
                    <?= htmlspecialchars($employee['dept_name']) ?>
                </p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <span class="badge <?= $status_badge[$employee['status']] ?? 'badge-gray' ?>">
                        <?= ucfirst(str_replace('_', ' ', $employee['status'])) ?>
                    </span>
                    <span class="badge badge-primary"><?= htmlspecialchars($emp_id) ?></span>
                    <span class="badge badge-gray">
                        <?= ucfirst(str_replace('_', '-', $employee['employment_type'])) ?>
                    </span>
                </div>
            </div>
            <!-- Contact -->
            <div style="text-align:right;flex-shrink:0;">
                <?php if ($employee['email']): ?>
                <p style="font-size:0.78rem;color:var(--gray-400);margin:0;">Email</p>
                <p style="font-weight:600;margin:0 0 6px;font-size:0.88rem;">
                    <?= htmlspecialchars($employee['email']) ?>
                </p>
                <?php endif; ?>
                <?php if ($employee['phone']): ?>
                <p style="font-size:0.78rem;color:var(--gray-400);margin:0;">Phone</p>
                <p style="font-weight:600;margin:0;font-size:0.88rem;">
                    <?= htmlspecialchars($employee['phone']) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- ── Personal Details ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user" style="color:var(--primary);margin-right:8px"></i>
                Personal Details
            </h3>
        </div>
        <div class="card-body">
            <?php
            $personal = [
                ['Full Name',       $employee['full_name'],                                    'fas fa-user'],
                ['Employee ID',     $employee['emp_id'],                                       'fas fa-id-badge'],
                ['Gender',          ucfirst($employee['gender'] ?? '—'),                       'fas fa-venus-mars'],
                ['Date of Birth',   $employee['date_of_birth']
                                    ? date('M d, Y', strtotime($employee['date_of_birth']))
                                    : '—',                                                     'fas fa-birthday-cake'],
                ['Email',           $employee['email'] ?? '—',                                 'fas fa-envelope'],
                ['Phone',           $employee['phone'] ?? '—',                                 'fas fa-phone'],
                ['Employment Date', $employee['employment_date']
                                    ? date('M d, Y', strtotime($employee['employment_date']))
                                    : '—',                                                     'fas fa-calendar-check'],
                ['Employment Type', ucfirst(str_replace('_', '-', $employee['employment_type'])), 'fas fa-briefcase'],
                ['Username',        $employee['username'],                                     'fas fa-user-circle'],
                ['Last Login',      $employee['last_login']
                                    ? date('M d, Y H:i', strtotime($employee['last_login']))
                                    : 'Never',                                                 'fas fa-clock'],
            ];
            foreach ($personal as $p): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:9px 0;
                        border-bottom:1px solid var(--gray-200);">
                <div style="width:30px;height:30px;background:var(--bg-light);border-radius:6px;
                            display:flex;align-items:center;justify-content:center;
                            color:var(--primary);font-size:0.85rem;flex-shrink:0;">
                    <i class="<?= $p[2] ?>"></i>
                </div>
                <div style="min-width:0;">
                    <p style="font-size:0.7rem;color:var(--gray-400);margin:0;text-transform:uppercase;"><?= $p[0] ?></p>
                    <p style="font-weight:600;margin:0;font-size:0.88rem;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($p[1]) ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Employment & Salary ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-briefcase" style="color:var(--primary);margin-right:8px"></i>
                Employment & Salary Structure
            </h3>
        </div>
        <div class="card-body">
            <?php
            $employment = [
                ['Department',          $employee['dept_name'],                          'fas fa-building'],
                ['Position',            $employee['position'],                           'fas fa-chalkboard-teacher'],
                ['Basic Salary',        'ETB ' . number_format($basic, 2),              'fas fa-money-bill'],
                ['Housing Allowance',   'ETB ' . number_format($allowances['housing'] ?? 0, 2),           'fas fa-home'],
                ['Transport Allowance', 'ETB ' . number_format($allowances['transport'] ?? 0, 2),         'fas fa-bus'],
                ['Position Allowance',  'ETB ' . number_format($allowances['position_allowance'] ?? 0, 2),'fas fa-star'],
                ['Teaching Allowance',  'ETB ' . number_format($allowances['teaching'] ?? 0, 2),          'fas fa-chalkboard'],
                ['Total Allowances',    'ETB ' . number_format($total_allowances, 2),   'fas fa-plus-circle'],
                ['Gross Salary',        'ETB ' . number_format($gross_salary, 2),       'fas fa-wallet'],
                ['Status',              ucfirst(str_replace('_', ' ', $employee['status'])), 'fas fa-check-circle'],
            ];
            foreach ($employment as $e): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:9px 0;
                        border-bottom:1px solid var(--gray-200);">
                <div style="width:30px;height:30px;background:var(--bg-light);border-radius:6px;
                            display:flex;align-items:center;justify-content:center;
                            color:var(--primary);font-size:0.85rem;flex-shrink:0;">
                    <i class="<?= $e[2] ?>"></i>
                </div>
                <div>
                    <p style="font-size:0.7rem;color:var(--gray-400);margin:0;text-transform:uppercase;"><?= $e[0] ?></p>
                    <p style="font-weight:600;margin:0;font-size:0.88rem;"><?= htmlspecialchars($e[1]) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- ── Deductions Breakdown ── -->
<div class="card mb-3">
    <div class="card-header">
        <h3><i class="fas fa-receipt" style="color:var(--danger);margin-right:8px"></i>
            Monthly Deductions Breakdown
        </h3>
        <span class="badge badge-info">
            <?= $latest ? htmlspecialchars($latest['period_label']) : 'Based on current salary' ?>
        </span>
    </div>
    <div class="card-body">
        <?php
        // Use latest payroll record if available, else calculate from current salary
        if ($latest) {
            $income_tax  = $latest['income_tax'];
            $pension_emp = $latest['pension_employee'];
            $pension_org = $latest['pension_employer'];
            $net_pay     = $latest['net_pay'];
            $gross_used  = $latest['gross_salary'];
        } else {
            // Estimate from current salary
            function estTax(float $g): float {
                if ($g <= 2000)  return 0;
                if ($g <= 4000)  return round(($g * 0.15) - 300, 2);
                if ($g <= 7000)  return round(($g * 0.20) - 500, 2);
                if ($g <= 10000) return round(($g * 0.25) - 850, 2);
                if ($g <= 14000) return round(($g * 0.30) - 1350, 2);
                return round(($g * 0.35) - 2050, 2);
            }
            $income_tax  = estTax($gross_salary);
            $pension_emp = round($basic * 0.07, 2);
            $pension_org = round($basic * 0.11, 2);
            $net_pay     = round($gross_salary - $income_tax - $pension_emp - $credit_assoc - $gerd - $loan - $penalty, 2);
            $gross_used  = $gross_salary;
        }
        ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px;">
            <?php
            $ded_cards = [
                ['Income Tax',           $income_tax,  'var(--danger)',  'fas fa-percent',         'Based on 2025 tax brackets'],
                ['Employee Pension (7%)',$pension_emp, 'var(--warning)', 'fas fa-piggy-bank',      '7% of basic salary'],
                ['Credit Association',   $credit_assoc,'var(--info)',    'fas fa-handshake',       '10% of basic salary'],
                ['Renaissance Dam',      $gerd,        'var(--primary)', 'fas fa-water',           '1% of basic salary'],
            ];
            if ($loan > 0)
                $ded_cards[] = ['Loan Repayment', $loan, 'var(--warning)', 'fas fa-hand-holding-usd', 'Monthly installment'];
            if ($penalty > 0)
                $ded_cards[] = ['Penalty / Other', $penalty, 'var(--danger)', 'fas fa-exclamation-triangle', 'As entered by HR'];

            foreach ($ded_cards as $dc): ?>
            <div style="padding:14px;background:var(--bg-light);border-radius:var(--radius);
                        border-left:4px solid <?= $dc[2] ?>;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <i class="<?= $dc[3] ?>" style="color:<?= $dc[2] ?>;font-size:1.1rem;"></i>
                    <span style="font-weight:700;font-size:0.82rem;"><?= $dc[0] ?></span>
                </div>
                <p style="font-size:1.2rem;font-weight:700;color:<?= $dc[2] ?>;margin:0 0 3px;">
                    ETB <?= number_format($dc[1], 2) ?>
                </p>
                <p style="font-size:0.72rem;color:var(--gray-400);margin:0;"><?= $dc[4] ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Net Pay Summary -->
        <div style="padding:18px;background:var(--success-light);border-radius:var(--radius);
                    border:2px solid var(--success);display:flex;justify-content:space-between;
                    align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <p style="font-size:0.82rem;color:var(--success);font-weight:700;margin:0;text-transform:uppercase;">
                    <?= $latest ? htmlspecialchars($latest['period_label']) : 'Estimated' ?> Net Pay
                </p>
                <p style="font-size:0.78rem;color:var(--gray-600);margin:4px 0 0;">
                    Gross (<?= number_format($gross_used, 2) ?>) − Tax − Pension − Credit − GERD
                    <?= $loan > 0 ? '− Loan' : '' ?>
                </p>
                <p style="font-size:0.7rem;color:var(--gray-400);margin:3px 0 0;">
                    <i class="fas fa-gavel"></i> Tax: Revised Monthly Employment Tax Brackets 2025
                </p>
            </div>
            <p style="font-size:2rem;font-weight:800;color:var(--success);margin:0;">
                ETB <?= number_format($net_pay, 2) ?>
            </p>
        </div>

        <!-- Employer pension note -->
        <div style="margin-top:12px;padding:10px 14px;background:var(--info-light);
                    border-radius:var(--radius);font-size:0.8rem;color:var(--info);
                    display:flex;align-items:center;gap:8px;">
            <i class="fas fa-shield-alt"></i>
            <span>
                Employer Pension (11% of basic): <strong>ETB <?= number_format($pension_org, 2) ?></strong>
                — paid by BiT on your behalf. Not deducted from your salary.
            </span>
        </div>
    </div>
</div>

<!-- ── Status History ── -->
<?php if (!empty($status_history)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>
            Employment Status History
        </h3>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th>Effective Date</th>
                        <th>Reason</th>
                        <th>Changed By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status_history as $h): ?>
                    <tr>
                        <td>
                            <span class="badge <?= $status_badge[$h['previous_status']] ?? 'badge-gray' ?>">
                                <?= ucfirst(str_replace('_', ' ', $h['previous_status'] ?? 'N/A')) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $status_badge[$h['new_status']] ?? 'badge-gray' ?>">
                                <?= ucfirst(str_replace('_', ' ', $h['new_status'])) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($h['effective_date']) ?></td>
                        <td style="font-size:0.82rem;"><?= htmlspecialchars($h['reason'] ?? '—') ?></td>
                        <td class="text-muted"><?= htmlspecialchars($h['changed_by'] ?? 'HR') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once $depth . 'includes/footer.php'; ?>

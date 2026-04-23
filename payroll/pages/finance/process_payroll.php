<?php

session_start();
$page_title = 'Process Payroll';
$active_nav = 'process';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/notify.php';

$pdo     = getDB();
$success = '';
$error   = '';
$results = [];

// ============================================================
// PAYROLL CALCULATION FUNCTIONS
// ============================================================

/**
 * Salary Expense = Basic Salary × (Working Days / 30)
 * Full month = 30 working days
 */
function calcSalaryExpense(float $basic, int $working_days): float {
    return round($basic * ($working_days / 30), 2);
}

/**
 * Gross Earnings = Salary Expense + Allowances
 */
function calcGross(float $salary_expense, float $allowances): float {
    return round($salary_expense + $allowances, 2);
}

/**
 * Ethiopian Income Tax — Revised Monthly Employment Tax 2025
 * Applied on Gross Earnings (not taxable income)
 *
 * Monthly Taxable Income (ETB) | Rate  | Deduction
 * 0        – 2,000             |  0%   |      0
 * 2,001    – 4,000             | 15%   |    300
 * 4,001    – 7,000             | 20%   |    500
 * 7,001    – 10,000            | 25%   |    850
 * 10,001   – 14,000            | 30%   |  1,350
 * Over 14,000                  | 35%   |  2,050
 */
function calcIncomeTax(float $gross): float {
    if ($gross <= 2000)   return 0.00;
    if ($gross <= 4000)   return round(($gross * 0.15) - 300.00,  2);
    if ($gross <= 7000)   return round(($gross * 0.20) - 500.00,  2);
    if ($gross <= 10000)  return round(($gross * 0.25) - 850.00,  2);
    if ($gross <= 14000)  return round(($gross * 0.30) - 1350.00, 2);
    return round(($gross * 0.35) - 2050.00, 2);
}

function getTaxBracket(float $gross): string {
    if ($gross <= 2000)  return '0%';
    if ($gross <= 4000)  return '15%';
    if ($gross <= 7000)  return '20%';
    if ($gross <= 10000) return '25%';
    if ($gross <= 14000) return '30%';
    return '35%';
}

/**
 * Employee Pension = 11% of Basic Salary  (updated rule)
 * Employer Pension = 18% of Basic Salary  (updated rule)
 */
function calcPensionEmployee(float $basic): float { return round($basic * 0.11, 2); }
function calcPensionEmployer(float $basic): float { return round($basic * 0.18, 2); }

// ============================================================
// GENERATE DYNAMIC PERIOD OPTIONS (last 12 months)
// ============================================================
$period_options = [];
for ($i = 0; $i < 12; $i++) {
    $ts = mktime(0, 0, 0, date('n') - $i, 1, date('Y'));
    $period_options[] = [
        'label' => date('F Y', $ts),
        'month' => (int)date('n', $ts),
        'year'  => (int)date('Y', $ts),
    ];
}

// ============================================================
// HANDLE POST — RUN PAYROLL
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_payroll'])) {
    $period_label = trim($_POST['period'] ?? '');
    $period_month = (int)($_POST['period_month'] ?? 0);
    $period_year  = (int)($_POST['period_year']  ?? 0);

    if (!$period_label || !$period_month || !$period_year) {
        $error = 'Please select a payroll period.';
    } else {
        // Fetch all active employees with their current allowances
        $emp_stmt = $pdo->query("
            SELECT
                e.emp_id,
                e.full_name,
                e.basic_salary,
                COALESCE(a.housing, 0)             AS housing,
                COALESCE(a.transport, 0)           AS transport,
                COALESCE(a.position_allowance, 0)  AS position_allowance,
                COALESCE(a.teaching, 0)            AS teaching,
                COALESCE(a.other, 0)               AS other_allowance,
                d.dept_name
            FROM employees e
            JOIN departments d ON e.dept_id = d.dept_id
            LEFT JOIN allowances a
                ON  a.emp_id = e.emp_id
                AND a.effective_to IS NULL
            WHERE e.status = 'active'
            ORDER BY e.full_name
        ");
        $active_employees = $emp_stmt->fetchAll();

        if (empty($active_employees)) {
            $error = 'No active employees found. Please register employees first.';
        } else {
            // Check if HR has submitted working days for this period
            $wd_check = $pdo->prepare("
                SELECT COUNT(*) FROM working_days
                WHERE period_month = ? AND period_year = ?
            ");
            $wd_check->execute([$period_month, $period_year]);
            $wd_submitted = (int)$wd_check->fetchColumn();

            if ($wd_submitted === 0) {
                $error = 'HR has not submitted working days for ' . htmlspecialchars($period_label) .
                         '. Please ask HR to submit working days first via <strong>HR → Working Days</strong>.';
            } else {
                foreach ($active_employees as $emp) {
                    $basic = (float)$emp['basic_salary'];

                    // Get working days from DB (HR submitted), default 30 if not found
                    $wd_stmt = $pdo->prepare("
                        SELECT working_days FROM working_days
                        WHERE emp_id = ? AND period_month = ? AND period_year = ?
                    ");
                    $wd_stmt->execute([$emp['emp_id'], $period_month, $period_year]);
                    $wd_row      = $wd_stmt->fetch();
                    $working_days = $wd_row ? (int)$wd_row['working_days'] : 30;

                // Total allowances
                $allowances = round(
                    $emp['housing'] + $emp['transport'] +
                    $emp['position_allowance'] + $emp['teaching'] +
                    $emp['other_allowance'], 2
                );

                // Core calculations
                $salary_expense  = calcSalaryExpense($basic, $working_days);
                $gross           = calcGross($salary_expense, $allowances);
                $income_tax      = calcIncomeTax($gross);
                $pension_emp     = calcPensionEmployee($basic);
                $pension_org     = calcPensionEmployer($basic);
                $tax_bracket     = getTaxBracket($gross);

                // Fetch other deductions for this period
                $ded_stmt = $pdo->prepare("
                    SELECT
                        credit_association,
                        renaissance_dam,
                        loan_repayment,
                        penalty,
                        other
                    FROM deductions
                    WHERE emp_id = ?
                    AND   effective_month = ?
                    AND   effective_year  = ?
                    AND   status = 'active'
                ");
                $ded_stmt->execute([$emp['emp_id'], $period_month, $period_year]);
                $ded = $ded_stmt->fetch();

                // Credit Association: 10% of basic (default if not set by HR)
                // Renaissance Dam:    1%  of basic (default if not set by HR)
                $credit_assoc = ($ded && $ded['credit_association'] > 0)
                    ? (float)$ded['credit_association']
                    : round($basic * 0.10, 2);

                $gerd         = ($ded && $ded['renaissance_dam'] > 0)
                    ? (float)$ded['renaissance_dam']
                    : round($basic * 0.01, 2);

                // Loan, penalty, other — only from HR entry, no default
                $loan      = $ded ? (float)$ded['loan_repayment'] : 0;
                $penalty   = $ded ? (float)$ded['penalty']        : 0;
                $other_ded = $ded ? (float)$ded['other']          : 0;

                // Other deductions total
                $other_deductions = round($credit_assoc + $gerd + $loan + $penalty + $other_ded, 2);

                // Total deductions = Income Tax + Pension Employee + Other Deductions
                $total_deductions = round($income_tax + $pension_emp + $other_deductions, 2);

                // Net Pay = Gross - Total Deductions
                $net_pay = round($gross - $total_deductions, 2);

                $results[] = [
                    'emp_id'           => $emp['emp_id'],
                    'full_name'        => $emp['full_name'],
                    'dept_name'        => $emp['dept_name'],
                    'basic_salary'     => $basic,
                    'working_days'     => $working_days,
                    'salary_expense'   => $salary_expense,
                    'allowances'       => $allowances,
                    'housing'          => (float)$emp['housing'],
                    'transport'        => (float)$emp['transport'],
                    'position_allow'   => (float)$emp['position_allowance'],
                    'teaching'         => (float)$emp['teaching'],
                    'gross'            => $gross,
                    'income_tax'       => $income_tax,
                    'tax_bracket'      => $tax_bracket,
                    'pension_emp'      => $pension_emp,
                    'pension_org'      => $pension_org,
                    'credit_assoc'     => $credit_assoc,
                    'gerd'             => $gerd,
                    'loan'             => $loan,
                    'penalty'          => $penalty,
                    'other_ded'        => $other_ded,
                    'other_deductions' => $other_deductions,
                    'total_deductions' => $total_deductions,
                    'net_pay'          => $net_pay,
                ];
            }

            $success = 'Payroll calculated for <strong>' . htmlspecialchars($period_label) . '</strong> — '
                     . count($results) . ' employees. Review and confirm below.';
            } // end else (wd_submitted > 0)
        } // end else (active_employees not empty)
    }
}

// ── HANDLE CONFIRM & SAVE to payroll_records ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payroll'])) {
    $period_label = trim($_POST['period']       ?? '');
    $period_month = (int)($_POST['period_month'] ?? 0);
    $period_year  = (int)($_POST['period_year']  ?? 0);

    if ($period_label && $period_month && $period_year) {
        try {
            $pdo->beginTransaction();

            // 1. Upsert payroll_periods row
            $pp = $pdo->prepare("
                SELECT period_id FROM payroll_periods
                WHERE period_month = ? AND period_year = ?
            ");
            $pp->execute([$period_month, $period_year]);
            $existing_period = $pp->fetch();

            if ($existing_period) {
                $period_id = $existing_period['period_id'];
                $pdo->prepare("
                    UPDATE payroll_periods
                    SET status = 'processed', processed_by = ?, processed_at = NOW()
                    WHERE period_id = ?
                ")->execute([$_SESSION['user_id'], $period_id]);
            } else {
                $pdo->prepare("
                    INSERT INTO payroll_periods
                        (period_label, period_month, period_year, status, processed_by, processed_at)
                    VALUES (?, ?, ?, 'processed', ?, NOW())
                ")->execute([$period_label, $period_month, $period_year, $_SESSION['user_id']]);
                $period_id = (int)$pdo->lastInsertId();
            }

            // 2. Re-run calculation and save each employee record
            $emp_stmt = $pdo->query("
                SELECT e.emp_id, e.full_name, e.basic_salary,
                       COALESCE(a.housing,0) AS housing,
                       COALESCE(a.transport,0) AS transport,
                       COALESCE(a.position_allowance,0) AS position_allowance,
                       COALESCE(a.teaching,0) AS teaching,
                       COALESCE(a.other,0) AS other_allowance,
                       d.dept_name
                FROM employees e
                JOIN departments d ON e.dept_id = d.dept_id
                LEFT JOIN allowances a ON a.emp_id = e.emp_id AND a.effective_to IS NULL
                WHERE e.status = 'active'
                ORDER BY e.full_name
            ");
            $active_employees = $emp_stmt->fetchAll();

            foreach ($active_employees as $emp) {
                $basic = (float)$emp['basic_salary'];

                $wd_s = $pdo->prepare("SELECT working_days FROM working_days WHERE emp_id=? AND period_month=? AND period_year=?");
                $wd_s->execute([$emp['emp_id'], $period_month, $period_year]);
                $wd_r = $wd_s->fetch();
                $working_days = $wd_r ? (int)$wd_r['working_days'] : 30;

                $allowances      = round($emp['housing'] + $emp['transport'] + $emp['position_allowance'] + $emp['teaching'] + $emp['other_allowance'], 2);
                $salary_expense  = calcSalaryExpense($basic, $working_days);
                $gross           = calcGross($salary_expense, $allowances);
                $income_tax      = calcIncomeTax($gross);
                $pension_emp     = calcPensionEmployee($basic);
                $pension_org     = calcPensionEmployer($basic);
                $tax_bracket     = getTaxBracket($gross);

                $ded_s = $pdo->prepare("SELECT * FROM deductions WHERE emp_id=? AND effective_month=? AND effective_year=? AND status='active'");
                $ded_s->execute([$emp['emp_id'], $period_month, $period_year]);
                $ded = $ded_s->fetch();

                // ── Apply defaults: Credit 10%, GERD 1% of basic ──
                $credit_assoc = ($ded && (float)$ded['credit_association'] > 0)
                    ? (float)$ded['credit_association']
                    : round($basic * 0.10, 2);   // DEFAULT 10%

                $gerd         = ($ded && (float)$ded['renaissance_dam'] > 0)
                    ? (float)$ded['renaissance_dam']
                    : round($basic * 0.01, 2);   // DEFAULT 1%

                $loan      = $ded ? (float)$ded['loan_repayment'] : 0;
                $penalty   = $ded ? (float)$ded['penalty']        : 0;
                $other_ded = $ded ? (float)$ded['other']          : 0;

                $other_deductions = round($credit_assoc + $gerd + $loan + $penalty + $other_ded, 2);
                $total_deductions = round($income_tax + $pension_emp + $other_deductions, 2);
                $net_pay          = round($gross - $total_deductions, 2);

                // Delete existing record for this period+employee then insert fresh
                $pdo->prepare("DELETE FROM payroll_records WHERE period_id=? AND emp_id=?")
                    ->execute([$period_id, $emp['emp_id']]);

                $pdo->prepare("
                    INSERT INTO payroll_records
                        (period_id, emp_id, basic_salary,
                         housing, transport, position_allowance, teaching, other_allowance,
                         total_allowances, gross_salary,
                         pension_employee, pension_employer,
                         taxable_income, income_tax, other_deductions, net_pay, tax_bracket)
                    VALUES (?,?,?, ?,?,?,?,?, ?,?, ?,?, ?,?,?,?,?)
                ")->execute([
                    $period_id, $emp['emp_id'], $basic,
                    $emp['housing'], $emp['transport'], $emp['position_allowance'], $emp['teaching'], $emp['other_allowance'],
                    $allowances, $gross,
                    $pension_emp, $pension_org,
                    $gross,        // taxable_income = gross (pension already in other_deductions)
                    $income_tax, $other_deductions, $net_pay, $tax_bracket
                ]);

                // Mark deductions as applied
                if ($ded) {
                    $pdo->prepare("UPDATE deductions SET status='applied' WHERE emp_id=? AND effective_month=? AND effective_year=?")
                        ->execute([$emp['emp_id'], $period_month, $period_year]);
                }
            }

            // Audit log
            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Process Payroll', ?, ?, ?)
            ")->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                $period_label,
                'Payroll confirmed and saved for ' . count($active_employees) . ' employees',
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $pdo->commit();
            $success = 'Payroll for <strong>' . htmlspecialchars($period_label) . '</strong> confirmed and saved. '
                     . '<a href="verify_payroll.php?period_id=' . $period_id . '" class="btn btn-success btn-sm" style="margin-left:10px;">'
                     . '<i class="fas fa-check-double"></i> Go to Verify Payroll</a>';

            // Notify admin
            notify_role($pdo, 'admin',
                'Payroll Processed — ' . $period_label,
                'Finance has processed payroll for ' . count($active_employees) . ' employees. Awaiting verification.',
                'info');

            // Notify HR
            notify_role($pdo, 'hr',
                'Payroll Processed — ' . $period_label,
                'Finance has processed payroll for ' . $period_label . '. Payslips will be available after verification.',
                'info');

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Save failed: ' . $e->getMessage();
        }
    }
}

require_once $depth . 'includes/header.php';

?>

<div class="breadcrumb">
    <a href="dashboard.php">Finance</a><span>/</span><span>Process Payroll</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Process Payroll</h1>
        <p>Calculate salary expenses, deductions, income tax, and pension for all active employees.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<!-- ── Period Selection ── -->
<div class="card mb-3">
    <div class="card-header">
        <h3><i class="fas fa-calendar-alt" style="color:var(--primary);margin-right:8px"></i>
            Select Payroll Period
        </h3>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="payrollForm">
            <div class="form-row" style="align-items:flex-end;">
                <div class="form-group">
                    <label class="form-label">Payroll Period <span style="color:var(--danger)">*</span></label>
                    <select name="period" id="periodSelect" class="form-control" required
                            onchange="syncPeriod(this)">
                        <option value="">— Select Period —</option>
                        <?php foreach ($period_options as $opt): ?>
                        <option value="<?= $opt['label'] ?>"
                                data-month="<?= $opt['month'] ?>"
                                data-year="<?= $opt['year'] ?>"
                            <?= (($_POST['period'] ?? '') === $opt['label']) ? 'selected' : '' ?>>
                            <?= $opt['label'] ?>
                            <?= ($opt['label'] === date('F Y')) ? ' (Current)' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="period_month" id="period_month"
                           value="<?= htmlspecialchars($_POST['period_month'] ?? '') ?>">
                    <input type="hidden" name="period_year"  id="period_year"
                           value="<?= htmlspecialchars($_POST['period_year']  ?? '') ?>">
                </div>
                <div class="form-group" style="padding-bottom:18px;">
                    <button type="submit" name="run_payroll" class="btn btn-primary">
                        <i class="fas fa-play-circle"></i> Calculate Payroll
                    </button>
                </div>
            </div>
        </form>

        <!-- Working days status check -->
        <?php
        $sel_month = (int)($_POST['period_month'] ?? $cur_month ?? date('n'));
        $sel_year  = (int)($_POST['period_year']  ?? $cur_year  ?? date('Y'));
        $wd_count  = 0;
        $emp_count = 0;
        if ($sel_month && $sel_year) {
            $wd_count  = (int)$pdo->prepare("SELECT COUNT(*) FROM working_days WHERE period_month=? AND period_year=?")
                              ->execute([$sel_month,$sel_year]) ?
                         $pdo->prepare("SELECT COUNT(*) FROM working_days WHERE period_month=? AND period_year=?")->execute([$sel_month,$sel_year]) : 0;
            // simpler:
            $wdc = $pdo->prepare("SELECT COUNT(*) FROM working_days WHERE period_month=? AND period_year=?");
            $wdc->execute([$sel_month, $sel_year]);
            $wd_count = (int)$wdc->fetchColumn();
            $emc = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='active'");
            $emp_count = (int)$emc->fetchColumn();
        }
        ?>
        <?php if ($wd_count > 0): ?>
        <div style="margin-bottom:12px;padding:10px 14px;background:var(--success-light);
                    border-radius:var(--radius);border-left:4px solid var(--success);
                    font-size:0.82rem;color:var(--success);display:flex;align-items:center;gap:8px;">
            <i class="fas fa-check-circle"></i>
            <span>
                HR has submitted working days for <strong><?= $wd_count ?></strong> of
                <strong><?= $emp_count ?></strong> active employees.
                <?= $wd_count < $emp_count
                    ? '<strong style="color:var(--warning);">⚠ ' . ($emp_count - $wd_count) . ' employees still pending.</strong>'
                    : 'All submitted — ready to process!' ?>
            </span>
        </div>
        <?php else: ?>
        <div style="margin-bottom:12px;padding:10px 14px;background:var(--warning-light);
                    border-radius:var(--radius);border-left:4px solid var(--warning);
                    font-size:0.82rem;color:var(--warning);display:flex;align-items:center;gap:8px;">
            <i class="fas fa-exclamation-triangle"></i>
            <span>
                HR has <strong>not yet submitted</strong> working days for this period.
                Ask HR to complete <strong>Working Days</strong> entry before processing.
            </span>
        </div>
        <?php endif; ?>

        <!-- Formula reference -->
        <div style="background:var(--bg-light);border-radius:var(--radius);padding:14px;margin-top:4px;">
            <h4 style="font-size:0.82rem;color:var(--primary);margin-bottom:10px;">
                <i class="fas fa-calculator"></i> Calculation Formula
            </h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;font-size:0.8rem;">
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--primary);">
                    <strong style="color:var(--primary);">Salary Expense</strong><br>
                    Basic × (Working Days ÷ 30)
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--success);">
                    <strong style="color:var(--success);">Gross Earnings</strong><br>
                    Salary Expense + Allowances
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--danger);">
                    <strong style="color:var(--danger);">Income Tax</strong><br>
                    2025 Brackets on Gross
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--warning);">
                    <strong style="color:var(--warning);">Pension (Emp 11%)</strong><br>
                    11% × Basic Salary
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--info);">
                    <strong style="color:var(--info);">Pension (Org 18%)</strong><br>
                    18% × Basic Salary (paid by BiT)
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--info);">
                    <strong style="color:var(--info);">Credit Association</strong><br>
                    10% × Basic (default, HR can override)
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--info);">
                    <strong style="color:var(--info);">Renaissance Dam (GERD)</strong><br>
                    1% × Basic (default, HR can override)
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--gray-600);">
                    <strong style="color:var(--gray-600);">Net Pay</strong><br>
                    Gross &minus; Tax &minus; Pension(11%) &minus; Credit(10%) &minus; GERD(1%) &minus; Other
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Tax Brackets Reference ── -->
<div class="card mb-3">
    <div class="card-header">
        <h3><i class="fas fa-percent" style="color:var(--warning);margin-right:8px"></i>
            Income Tax Brackets — Ethiopia 2025
        </h3>
        <span class="badge badge-warning"><i class="fas fa-gavel"></i> Updated 2025</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Bracket</th>
                        <th>Gross Earnings (ETB/month)</th>
                        <th>Tax Rate</th>
                        <th>Deduction (ETB)</th>
                        <th>Formula</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $brackets = [
                        [1,'0 — 2,000',       '0% (Exempt)','0',       'Tax = 0',                         'badge-success'],
                        [2,'2,001 — 4,000',   '15%',        '300',     'Tax = (Gross × 0.15) - 300',      'badge-info'],
                        [3,'4,001 — 7,000',   '20%',        '500',     'Tax = (Gross × 0.20) - 500',      'badge-primary'],
                        [4,'7,001 — 10,000',  '25%',        '850',     'Tax = (Gross × 0.25) - 850',      'badge-warning'],
                        [5,'10,001 — 14,000', '30%',        '1,350',   'Tax = (Gross × 0.30) - 1,350',    'badge-warning'],
                        [6,'Over 14,000',     '35%',        '2,050',   'Tax = (Gross × 0.35) - 2,050',    'badge-danger'],
                    ];
                    foreach ($brackets as $b): ?>
                    <tr>
                        <td><span class="badge <?= $b[5] ?>"><?= $b[0] ?></span></td>
                        <td><strong>ETB <?= $b[1] ?></strong></td>
                        <td><span class="badge <?= $b[5] ?>"><?= $b[2] ?></span></td>
                        <td>ETB <?= $b[3] ?></td>
                        <td style="font-family:monospace;font-size:0.8rem;color:var(--gray-600);"><?= $b[4] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:10px 16px;background:var(--success-light);border-top:1px solid var(--gray-200);
                    font-size:0.8rem;color:var(--success);">
            <i class="fas fa-arrow-up"></i>
            <strong>2025 Update:</strong> Exempt threshold raised to ETB 2,000/month. Minimum taxable rate is 15%.
        </div>
    </div>
</div>

<?php if (!empty($results)):
    // Calculate grand totals
    $gt = array_fill_keys([
        'basic','salary_expense','allowances','gross',
        'income_tax','pension_emp','pension_org',
        'credit_assoc','gerd','loan','penalty','other_ded',
        'other_deductions','total_deductions','net_pay'
    ], 0);
    foreach ($results as $r) {
        foreach (array_keys($gt) as $k) {
            $gt[$k] += $r[$k] ?? 0;
        }
    }
?>

<!-- ── Main Payroll Table (matches spreadsheet) ── -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-table" style="color:var(--success);margin-right:8px"></i>
            Payroll — <?= htmlspecialchars($_POST['period'] ?? '') ?>
        </h3>
        <div class="d-flex gap-2">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="period"       value="<?= htmlspecialchars($_POST['period']       ?? '') ?>">
                <input type="hidden" name="period_month" value="<?= htmlspecialchars($_POST['period_month'] ?? '') ?>">
                <input type="hidden" name="period_year"  value="<?= htmlspecialchars($_POST['period_year']  ?? '') ?>">
                <button type="submit" name="confirm_payroll" class="btn btn-success btn-sm">
                    <i class="fas fa-check"></i> Confirm & Save
                </button>
            </form>
            <button class="btn btn-secondary btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align:middle;">#</th>
                        <th rowspan="2" style="vertical-align:middle;">Employee Name</th>
                        <th rowspan="2" style="vertical-align:middle;">Basic Salary</th>
                        <th rowspan="2" style="vertical-align:middle;">Working Days</th>
                        <th rowspan="2" style="vertical-align:middle;">Salary Expense</th>
                        <th rowspan="2" style="vertical-align:middle;">Allowance</th>
                        <th rowspan="2" style="vertical-align:middle;background:var(--success-light);color:var(--success);">
                            Gross Earnings
                        </th>
                        <!-- Deductions group header -->
                        <th colspan="6" style="text-align:center;background:var(--danger-light);color:var(--danger);">
                            Deductions
                        </th>
                        <th rowspan="2" style="vertical-align:middle;background:var(--danger-light);color:var(--danger);">
                            Total Deductions
                        </th>
                        <th rowspan="2" style="vertical-align:middle;background:var(--success-light);color:var(--success);">
                            Net Pay
                        </th>
                    </tr>
                    <tr>
                        <th style="background:var(--danger-light);color:var(--danger);">Income Tax</th>
                        <th style="background:var(--warning-light);color:var(--warning);">Pension 11%
                            <br><span style="font-weight:400;font-size:0.65rem;">(Employee)</span>
                        </th>
                        <th style="background:var(--info-light);color:var(--info);">Credit Assoc.
                            <br><span style="font-weight:400;font-size:0.65rem;">(10% default)</span>
                        </th>
                        <th style="background:var(--info-light);color:var(--info);">GERD
                            <br><span style="font-weight:400;font-size:0.65rem;">(1% default)</span>
                        </th>
                        <th style="background:var(--warning-light);color:var(--warning);">Loan</th>
                        <th style="background:var(--danger-light);color:var(--danger);">Penalty/Other</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($results as $r): ?>
                    <tr>
                        <td class="text-muted"><?= $i++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($r['emp_id']) ?></small>
                        </td>
                        <td><?= number_format($r['basic_salary'], 2) ?></td>
                        <td style="text-align:center;">
                            <span class="badge badge-gray"><?= $r['working_days'] ?></span>
                        </td>
                        <td><?= number_format($r['salary_expense'], 2) ?></td>
                        <td>
                            <?= $r['allowances'] > 0
                                ? number_format($r['allowances'], 2)
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td class="text-bold" style="color:var(--success);">
                            <?= number_format($r['gross'], 2) ?>
                            <br><small class="badge <?= $r['tax_bracket'] === '0%' ? 'badge-success' : 'badge-warning' ?>"
                                       style="font-size:0.65rem;"><?= $r['tax_bracket'] ?></small>
                        </td>
                        <!-- Deductions -->
                        <td style="color:var(--danger);"><?= number_format($r['income_tax'], 2) ?></td>
                        <td style="color:var(--warning);"><?= number_format($r['pension_emp'], 2) ?></td>
                        <td style="color:var(--info);">
                            <?= number_format($r['credit_assoc'], 2) ?>
                        </td>
                        <td style="color:var(--info);">
                            <?= number_format($r['gerd'], 2) ?>
                        </td>
                        <td style="color:var(--warning);">
                            <?= $r['loan'] > 0
                                ? number_format($r['loan'], 2)
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td style="color:var(--danger);">
                            <?= ($r['penalty'] + $r['other_ded']) > 0
                                ? number_format($r['penalty'] + $r['other_ded'], 2)
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td class="text-bold" style="color:var(--danger);">
                            <?= number_format($r['total_deductions'], 2) ?>
                        </td>
                        <td class="text-bold" style="color:var(--success);font-size:1rem;">
                            <?= number_format($r['net_pay'], 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <!-- Grand Totals Row -->
                <tfoot>
                    <tr style="background:var(--bg-light);font-weight:700;font-size:0.88rem;">
                        <td colspan="2" style="padding:12px 16px;color:var(--primary);">
                            TOTALS (<?= count($results) ?> employees)
                        </td>
                        <td style="padding:12px 16px;"><?= number_format($gt['basic'], 2) ?></td>
                        <td style="padding:12px 16px;"></td>
                        <td style="padding:12px 16px;"><?= number_format($gt['salary_expense'], 2) ?></td>
                        <td style="padding:12px 16px;"><?= number_format($gt['allowances'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--success);"><?= number_format($gt['gross'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--danger);"><?= number_format($gt['income_tax'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--warning);"><?= number_format($gt['pension_emp'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--info);"><?= number_format($gt['credit_assoc'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--info);"><?= number_format($gt['gerd'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--warning);"><?= number_format($gt['loan'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--danger);"><?= number_format($gt['penalty'] + $gt['other_ded'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--danger);"><?= number_format($gt['total_deductions'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--success);font-size:1rem;"><?= number_format($gt['net_pay'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="card-footer">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:12px;">
            <?php
            $summary_cards = [
                ['Total Gross',               $gt['gross'],            'var(--success)'],
                ['Total Income Tax',          $gt['income_tax'],       'var(--danger)'],
                ['Employee Pension (11%)',    $gt['pension_emp'],      'var(--warning)'],
                ['Employer Pension (18%)',    $gt['pension_org'],      'var(--info)'],
                ['Credit Association',        $gt['credit_assoc'],     'var(--info)'],
                ['Renaissance Dam',           $gt['gerd'],             'var(--primary)'],
                ['Total Deductions',          $gt['total_deductions'], 'var(--danger)'],
                ['Total Net Pay',             $gt['net_pay'],          'var(--success)'],
            ];
            foreach ($summary_cards as [$label, $val, $color]): ?>
            <div style="text-align:center;padding:10px;background:var(--gray-100);border-radius:var(--radius);border-top:3px solid <?= $color ?>;">
                <p style="font-size:0.68rem;color:var(--gray-400);margin:0;text-transform:uppercase;"><?= $label ?></p>
                <p style="font-size:0.95rem;font-weight:700;color:<?= $color ?>;margin:2px 0 0;">
                    ETB <?= number_format($val, 2) ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="padding:10px 14px;background:var(--info-light);border-radius:var(--radius);
                    font-size:0.8rem;color:var(--info);display:flex;align-items:center;gap:8px;">
            <i class="fas fa-gavel"></i>
            <span>
                Income tax per <strong>Revised Monthly Employment Tax Brackets — Ethiopia 2025</strong>.
                Pension: Employee <strong>11%</strong> + Employer <strong>18%</strong> of basic salary.
                Net Pay = Gross - Income Tax - Pension (18%) - Other Deductions.
            </span>
        </div>
    </div>
</div>

<!-- ── Employer Pension Summary ── -->
<div class="card mt-3">
    <div class="card-header">
        <h3><i class="fas fa-piggy-bank" style="color:var(--info);margin-right:8px"></i>
            Employer Pension Contribution (18%) &mdash; Paid by BiT
        </h3>
        <span class="badge badge-info">Not deducted from employee</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Basic Salary (ETB)</th>
                        <th>Employer Pension 18% (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($results as $r): ?>
                    <tr>
                        <td class="text-muted"><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
                        <td><?= number_format($r['basic_salary'], 2) ?></td>
                        <td class="text-bold" style="color:var(--info);">
                            <?= number_format($r['pension_org'], 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--bg-light);font-weight:700;">
                        <td colspan="3" style="padding:12px 16px;color:var(--primary);">TOTAL</td>
                        <td style="padding:12px 16px;color:var(--info);">
                            ETB <?= number_format($gt['pension_org'], 2) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
function syncPeriod(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('period_month').value = opt.dataset.month || '';
    document.getElementById('period_year').value  = opt.dataset.year  || '';
}
// Sync on page load if period already selected
window.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('periodSelect');
    if (sel) syncPeriod(sel);
});
</script>

<?php require_once $depth . 'includes/footer.php'; ?>


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
$saved_period_id    = null;
$saved_period_label = '';

// ============================================================
// PAYROLL CALCULATION FUNCTIONS
// ============================================================

/**
 * Salary Expense = Basic Salary x (Working Days / 30)
 * A full month is treated as 30 working days.
 */
function calcSalaryExpense(float $basic, int $working_days): float {
    return round($basic * ($working_days / 30), 2);
}

/**
 * Gross Earnings = Salary Expense + Total Allowances
 */
function calcGross(float $salary_expense, float $allowances): float {
    return round($salary_expense + $allowances, 2);
}

/**
 * Ethiopian Income Tax — Revised Monthly Employment Tax Brackets 2025
 * Applied on Gross Earnings.
 *
 * Gross (ETB/month)   | Rate | Deduction
 * 0       - 2,000     |  0%  |      0
 * 2,001   - 4,000     | 15%  |    300
 * 4,001   - 7,000     | 20%  |    500
 * 7,001   - 10,000    | 25%  |    850
 * 10,001  - 14,000    | 30%  |  1,350
 * Over 14,000         | 35%  |  2,050
 */
function calcIncomeTax(float $gross): float {
    if ($gross <= 2000)  return 0.00;
    if ($gross <= 4000)  return round(($gross * 0.15) - 300.00,  2);
    if ($gross <= 7000)  return round(($gross * 0.20) - 500.00,  2);
    if ($gross <= 10000) return round(($gross * 0.25) - 850.00,  2);
    if ($gross <= 14000) return round(($gross * 0.30) - 1350.00, 2);
    return round(($gross * 0.35) - 2050.00, 2);
}

/** Returns the tax bracket label for a given gross amount. */
function getTaxBracket(float $gross): string {
    if ($gross <= 2000)  return '0%';
    if ($gross <= 4000)  return '15%';
    if ($gross <= 7000)  return '20%';
    if ($gross <= 10000) return '25%';
    if ($gross <= 14000) return '30%';
    return '35%';
}

/** Employee pension = 11% of basic salary (set by Ethiopian law). */
function calcPensionEmployee(float $basic): float { return round($basic * 0.11, 2); }

/** Employer pension = 18% of basic salary — paid by BiT, not deducted from employee. */
function calcPensionEmployer(float $basic): float { return round($basic * 0.18, 2); }

// ============================================================
// BUILD PERIOD OPTIONS — last 12 months
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
// HANDLE POST — CALCULATE PAYROLL (preview, not saved yet)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_payroll'])) {
    $period_label = trim($_POST['period']       ?? '');
    $period_month = (int)($_POST['period_month'] ?? 0);
    $period_year  = (int)($_POST['period_year']  ?? 0);

    if (!$period_label || !$period_month || !$period_year) {
        $error = 'Please select a payroll period.';
    } else {
        // Load all active employees with their current allowances
        $emp_stmt = $pdo->query("
            SELECT
                e.emp_id,
                e.full_name,
                e.cbe_account_number,
                e.basic_salary,
                COALESCE(a.housing, 0)            AS housing,
                COALESCE(a.transport, 0)          AS transport,
                COALESCE(a.position_allowance, 0) AS position_allowance,
                COALESCE(a.teaching, 0)           AS teaching,
                COALESCE(a.other, 0)              AS other_allowance,
                d.dept_name
            FROM employees e
            JOIN departments d ON e.dept_id = d.dept_id
            LEFT JOIN allowances a
                ON  a.emp_id = e.emp_id
                AND a.effective_to IS NULL
            WHERE e.status = 'active'
            ORDER BY d.dept_name, e.full_name
        ");
        $active_employees = $emp_stmt->fetchAll();

        if (empty($active_employees)) {
            $error = 'No active employees found. Please register employees first.';
        } else {
            // Verify HR has submitted working days for this period
            $wd_check = $pdo->prepare("
                SELECT COUNT(*) FROM working_days
                WHERE period_month = ? AND period_year = ?
            ");
            $wd_check->execute([$period_month, $period_year]);
            $wd_submitted = (int)$wd_check->fetchColumn();

            if ($wd_submitted === 0) {
                $error = 'HR has not submitted working days for <strong>' . htmlspecialchars($period_label) . '</strong>. '
                       . 'Ask HR to complete <strong>HR &rarr; Working Days</strong> first.';
            } else {
                foreach ($active_employees as $emp) {
                    $basic = (float)$emp['basic_salary'];

                    // Get working days submitted by HR; default to 30 if not found
                    $wd_stmt = $pdo->prepare("
                        SELECT working_days FROM working_days
                        WHERE emp_id = ? AND period_month = ? AND period_year = ?
                    ");
                    $wd_stmt->execute([$emp['emp_id'], $period_month, $period_year]);
                    $wd_row       = $wd_stmt->fetch();
                    $working_days = $wd_row ? (int)$wd_row['working_days'] : 30;

                    // Sum all allowances
                    $allowances = round(
                        $emp['housing'] + $emp['transport'] +
                        $emp['position_allowance'] + $emp['teaching'] +
                        $emp['other_allowance'], 2
                    );

                    // Core salary calculations
                    $salary_expense = calcSalaryExpense($basic, $working_days);
                    $gross          = calcGross($salary_expense, $allowances);
                    $income_tax     = calcIncomeTax($gross);
                    $pension_emp    = calcPensionEmployee($basic);
                    $pension_org    = calcPensionEmployer($basic);
                    $tax_bracket    = getTaxBracket($gross);

                    // Load HR-entered deductions for this period
                    $ded_stmt = $pdo->prepare("
                        SELECT credit_association, renaissance_dam,
                               loan_repayment, penalty, other
                        FROM   deductions
                        WHERE  emp_id = ? AND effective_month = ?
                        AND    effective_year = ? AND status = 'active'
                    ");
                    $ded_stmt->execute([$emp['emp_id'], $period_month, $period_year]);
                    $ded = $ded_stmt->fetch();

                    // Credit Association: use HR value if set, otherwise default 10% of basic
                    $credit_assoc = ($ded && $ded['credit_association'] > 0)
                        ? (float)$ded['credit_association']
                        : round($basic * 0.10, 2);

                    // Renaissance Dam (GERD): use HR value if set, otherwise default 1% of basic
                    $gerd = ($ded && $ded['renaissance_dam'] > 0)
                        ? (float)$ded['renaissance_dam']
                        : round($basic * 0.01, 2);

                    // Loan, penalty, other — only from HR entry, no automatic default
                    $loan      = $ded ? (float)$ded['loan_repayment'] : 0;
                    $penalty   = $ded ? (float)$ded['penalty']        : 0;
                    $other_ded = $ded ? (float)$ded['other']          : 0;

                    $other_deductions = round($credit_assoc + $gerd + $loan + $penalty + $other_ded, 2);
                    $total_deductions = round($income_tax + $pension_emp + $other_deductions, 2);
                    $net_pay          = round($gross - $total_deductions, 2);

                    $results[] = [
                        'emp_id'             => $emp['emp_id'],
                        'full_name'          => $emp['full_name'],
                        'dept_name'          => $emp['dept_name'],
                        'cbe_account_number' => $emp['cbe_account_number'] ?? null,
                        'basic_salary'       => $basic,
                        'working_days'       => $working_days,
                        'salary_expense'     => $salary_expense,
                        'housing'            => (float)$emp['housing'],
                        'transport'          => (float)$emp['transport'],
                        'position_allow'     => (float)$emp['position_allowance'],
                        'teaching'           => (float)$emp['teaching'],
                        'allowances'         => $allowances,
                        'gross'              => $gross,
                        'tax_bracket'        => $tax_bracket,
                        'income_tax'         => $income_tax,
                        'pension_emp'        => $pension_emp,
                        'pension_org'        => $pension_org,
                        'credit_assoc'       => $credit_assoc,
                        'gerd'               => $gerd,
                        'loan'               => $loan,
                        'penalty'            => $penalty,
                        'other_ded'          => $other_ded,
                        'other_deductions'   => $other_deductions,
                        'total_deductions'   => $total_deductions,
                        'net_pay'            => $net_pay,
                    ];
                }

                $success = 'Payroll calculated for <strong>' . htmlspecialchars($period_label) . '</strong> — '
                         . count($results) . ' employees. Review the table below and confirm to save.';
            }
        }
    }
}

// ============================================================
// HANDLE POST — CONFIRM & SAVE to payroll_records
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payroll'])) {
    $period_label = trim($_POST['period']        ?? '');
    $period_month = (int)($_POST['period_month'] ?? 0);
    $period_year  = (int)($_POST['period_year']  ?? 0);

    if ($period_label && $period_month && $period_year) {
        try {
            $pdo->beginTransaction();

            // Upsert payroll_periods row
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

            // Re-run full calculation for every active employee and persist records
            $emp_stmt = $pdo->query("
                SELECT e.emp_id, e.full_name, e.cbe_account_number, e.basic_salary,
                       COALESCE(a.housing, 0)            AS housing,
                       COALESCE(a.transport, 0)          AS transport,
                       COALESCE(a.position_allowance, 0) AS position_allowance,
                       COALESCE(a.teaching, 0)           AS teaching,
                       COALESCE(a.other, 0)              AS other_allowance,
                       d.dept_name
                FROM employees e
                JOIN departments d ON e.dept_id = d.dept_id
                LEFT JOIN allowances a ON a.emp_id = e.emp_id AND a.effective_to IS NULL
                WHERE e.status = 'active'
                ORDER BY d.dept_name, e.full_name
            ");
            $active_employees = $emp_stmt->fetchAll();

            foreach ($active_employees as $emp) {
                $basic = (float)$emp['basic_salary'];

                $wd_s = $pdo->prepare("
                    SELECT working_days FROM working_days
                    WHERE emp_id = ? AND period_month = ? AND period_year = ?
                ");
                $wd_s->execute([$emp['emp_id'], $period_month, $period_year]);
                $wd_r         = $wd_s->fetch();
                $working_days = $wd_r ? (int)$wd_r['working_days'] : 30;

                $allowances     = round($emp['housing'] + $emp['transport'] + $emp['position_allowance'] + $emp['teaching'] + $emp['other_allowance'], 2);
                $salary_expense = calcSalaryExpense($basic, $working_days);
                $gross          = calcGross($salary_expense, $allowances);
                $income_tax     = calcIncomeTax($gross);
                $pension_emp    = calcPensionEmployee($basic);
                $pension_org    = calcPensionEmployer($basic);
                $tax_bracket    = getTaxBracket($gross);

                $ded_s = $pdo->prepare("
                    SELECT * FROM deductions
                    WHERE emp_id = ? AND effective_month = ? AND effective_year = ? AND status = 'active'
                ");
                $ded_s->execute([$emp['emp_id'], $period_month, $period_year]);
                $ded = $ded_s->fetch();

                $credit_assoc = ($ded && (float)$ded['credit_association'] > 0)
                    ? (float)$ded['credit_association']
                    : round($basic * 0.10, 2);

                $gerd = ($ded && (float)$ded['renaissance_dam'] > 0)
                    ? (float)$ded['renaissance_dam']
                    : round($basic * 0.01, 2);

                $loan      = $ded ? (float)$ded['loan_repayment'] : 0;
                $penalty   = $ded ? (float)$ded['penalty']        : 0;
                $other_ded = $ded ? (float)$ded['other']          : 0;

                $other_deductions = round($credit_assoc + $gerd + $loan + $penalty + $other_ded, 2);
                $total_deductions = round($income_tax + $pension_emp + $other_deductions, 2);
                $net_pay          = round($gross - $total_deductions, 2);

                // Replace any existing record for this period + employee
                $pdo->prepare("DELETE FROM payroll_records WHERE period_id = ? AND emp_id = ?")
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
                    $emp['housing'], $emp['transport'], $emp['position_allowance'],
                    $emp['teaching'], $emp['other_allowance'],
                    $allowances, $gross,
                    $pension_emp, $pension_org,
                    $gross,   // taxable_income stored as gross (pension is in other_deductions)
                    $income_tax, $other_deductions, $net_pay, $tax_bracket
                ]);

                // Mark HR deductions as applied so they are not double-counted
                if ($ded) {
                    $pdo->prepare("
                        UPDATE deductions SET status = 'applied'
                        WHERE emp_id = ? AND effective_month = ? AND effective_year = ?
                    ")->execute([$emp['emp_id'], $period_month, $period_year]);
                }
            }

            // Write audit log
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
                     . '<a href="verify_payroll.php?period_id=' . $period_id
                     . '" class="btn btn-success btn-sm" style="margin-left:10px;">'
                     . '<i class="fas fa-check-double"></i> Go to Verify Payroll</a>';

            notify_role($pdo, 'admin',
                'Payroll Processed — ' . $period_label,
                'Finance has processed payroll for ' . count($active_employees) . ' employees. Awaiting verification.',
                'info');

            notify_role($pdo, 'hr',
                'Payroll Processed — ' . $period_label,
                'Finance has processed payroll for ' . $period_label . '. Payslips will be available after verification.',
                'info');

            // ── Reload $results from saved payroll_records so the table renders ──
            $reload = $pdo->prepare("
                SELECT
                    pr.emp_id,
                    e.full_name,
                    d.dept_name,
                    e.cbe_account_number,
                    pr.basic_salary,
                    COALESCE(wd.working_days, 30)   AS working_days,
                    pr.basic_salary                  AS salary_expense,
                    pr.housing,
                    pr.transport,
                    pr.position_allowance            AS position_allow,
                    pr.teaching,
                    pr.total_allowances              AS allowances,
                    pr.gross_salary                  AS gross,
                    pr.tax_bracket,
                    pr.income_tax,
                    pr.pension_employee              AS pension_emp,
                    pr.pension_employer              AS pension_org,
                    pr.other_deductions,
                    pr.net_pay
                FROM payroll_records pr
                JOIN employees e   ON pr.emp_id = e.emp_id
                JOIN departments d ON e.dept_id  = d.dept_id
                LEFT JOIN working_days wd
                    ON wd.emp_id = pr.emp_id
                    AND wd.period_month = ?
                    AND wd.period_year  = ?
                WHERE pr.period_id = ?
                ORDER BY d.dept_name, e.full_name
            ");
            $reload->execute([$period_month, $period_year, $period_id]);
            foreach ($reload->fetchAll() as $row) {
                // Approximate deduction breakdown from stored other_deductions
                $basic_r       = (float)$row['basic_salary'];
                $credit_r      = round($basic_r * 0.10, 2);
                $gerd_r        = round($basic_r * 0.01, 2);
                $salary_exp_r  = calcSalaryExpense($basic_r, (int)$row['working_days']);
                $results[] = [
                    'emp_id'             => $row['emp_id'],
                    'full_name'          => $row['full_name'],
                    'dept_name'          => $row['dept_name'],
                    'cbe_account_number' => $row['cbe_account_number'],
                    'basic_salary'       => $basic_r,
                    'working_days'       => (int)$row['working_days'],
                    'salary_expense'     => $salary_exp_r,
                    'housing'            => (float)$row['housing'],
                    'transport'          => (float)$row['transport'],
                    'position_allow'     => (float)$row['position_allow'],
                    'teaching'           => (float)$row['teaching'],
                    'allowances'         => (float)$row['allowances'],
                    'gross'              => (float)$row['gross'],
                    'tax_bracket'        => $row['tax_bracket'],
                    'income_tax'         => (float)$row['income_tax'],
                    'pension_emp'        => (float)$row['pension_emp'],
                    'pension_org'        => (float)$row['pension_org'],
                    'credit_assoc'       => $credit_r,
                    'gerd'               => $gerd_r,
                    'loan'               => 0,
                    'penalty'            => 0,
                    'other_ded'          => max(0, (float)$row['other_deductions'] - $credit_r - $gerd_r),
                    'other_deductions'   => (float)$row['other_deductions'],
                    'total_deductions'   => round((float)$row['income_tax'] + (float)$row['pension_emp'] + (float)$row['other_deductions'], 2),
                    'net_pay'            => (float)$row['net_pay'],
                ];
            }
            // Store period_id so the export button can reference it
            $saved_period_id    = $period_id;
            $saved_period_label = $period_label;

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
        <p>Calculate salary, deductions, income tax, and pension for all active employees.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= $error ?></span></div>
<?php endif; ?>

<!-- Period Selection Card -->
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
                    <input type="hidden" name="period_year" id="period_year"
                           value="<?= htmlspecialchars($_POST['period_year'] ?? '') ?>">
                </div>
                <div class="form-group" style="padding-bottom:18px;">
                    <button type="submit" name="run_payroll" class="btn btn-primary">
                        <i class="fas fa-play-circle"></i> Calculate Payroll
                    </button>
                </div>
            </div>
        </form>

        <?php
        // Working days readiness check for the selected period
        $sel_month = (int)($_POST['period_month'] ?? date('n'));
        $sel_year  = (int)($_POST['period_year']  ?? date('Y'));
        $wdc = $pdo->prepare("SELECT COUNT(*) FROM working_days WHERE period_month=? AND period_year=?");
        $wdc->execute([$sel_month, $sel_year]);
        $wd_count  = (int)$wdc->fetchColumn();
        $emp_count = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
        ?>
        <?php if ($wd_count > 0): ?>
        <div style="padding:10px 14px;background:var(--success-light);border-radius:var(--radius);
                    border-left:4px solid var(--success);font-size:0.82rem;color:var(--success);
                    display:flex;align-items:center;gap:8px;margin-bottom:12px;">
            <i class="fas fa-check-circle"></i>
            <span>
                HR submitted working days for <strong><?= $wd_count ?></strong> of
                <strong><?= $emp_count ?></strong> active employees.
                <?= $wd_count < $emp_count
                    ? '<strong style="color:var(--warning);">&#9888; ' . ($emp_count - $wd_count) . ' employees still pending.</strong>'
                    : 'All submitted &mdash; ready to process!' ?>
            </span>
        </div>
        <?php else: ?>
        <div style="padding:10px 14px;background:var(--warning-light);border-radius:var(--radius);
                    border-left:4px solid var(--warning);font-size:0.82rem;color:var(--warning);
                    display:flex;align-items:center;gap:8px;margin-bottom:12px;">
            <i class="fas fa-exclamation-triangle"></i>
            <span>HR has <strong>not yet submitted</strong> working days for this period.
                Ask HR to complete <strong>HR &rarr; Working Days</strong> first.</span>
        </div>
        <?php endif; ?>

        <!-- Calculation formula reference -->
        <div style="background:var(--bg-light);border-radius:var(--radius);padding:14px;">
            <h4 style="font-size:0.82rem;color:var(--primary);margin-bottom:10px;">
                <i class="fas fa-calculator"></i> Calculation Formula
            </h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px;font-size:0.78rem;">
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--primary);">
                    <strong style="color:var(--primary);">Salary Expense</strong><br>Basic &times; (Working Days &divide; 30)
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--success);">
                    <strong style="color:var(--success);">Total Payment (Gross)</strong><br>Salary Expense + Allowances
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--warning);">
                    <strong style="color:var(--warning);">Pension 11%</strong><br>11% &times; Basic Salary
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--danger);">
                    <strong style="color:var(--danger);">Income Tax</strong><br>2025 Brackets on Gross
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--info);">
                    <strong style="color:var(--info);">Pension 18%</strong><br>18% &times; Basic (paid by BiT)
                </div>
                <div style="padding:8px 12px;background:var(--white);border-radius:6px;border-left:3px solid var(--gray-600);">
                    <strong style="color:var(--gray-600);">Net Pay</strong><br>Gross &minus; Tax &minus; Pension 11% &minus; Other Ded.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tax Brackets Reference -->
<div class="card mb-3">
    <div class="card-header">
        <h3><i class="fas fa-percent" style="color:var(--warning);margin-right:8px"></i>
            Income Tax Brackets &mdash; Ethiopia 2025
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
                        [1, '0 &mdash; 2,000',       '0% (Exempt)', '0',     'Tax = 0',                          'badge-success'],
                        [2, '2,001 &mdash; 4,000',   '15%',         '300',   'Tax = (Gross &times; 0.15) - 300', 'badge-info'],
                        [3, '4,001 &mdash; 7,000',   '20%',         '500',   'Tax = (Gross &times; 0.20) - 500', 'badge-primary'],
                        [4, '7,001 &mdash; 10,000',  '25%',         '850',   'Tax = (Gross &times; 0.25) - 850', 'badge-warning'],
                        [5, '10,001 &mdash; 14,000', '30%',         '1,350', 'Tax = (Gross &times; 0.30) - 1,350','badge-warning'],
                        [6, 'Over 14,000',           '35%',         '2,050', 'Tax = (Gross &times; 0.35) - 2,050','badge-danger'],
                    ];
                    foreach ($brackets as $b): ?>
                    <tr>
                        <td><span class="badge <?= $b[5] ?>"><?= $b[0] ?></span></td>
                        <td><strong>ETB <?= $b[1] ?></strong></td>
                        <td><span class="badge <?= $b[5] ?>"><?= $b[2] ?></span></td>
                        <td>ETB <?= $b[3] ?></td>
                        <td style="font-family:monospace;font-size:0.78rem;color:var(--gray-600);"><?= $b[4] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:10px 16px;background:var(--success-light);border-top:1px solid var(--gray-200);font-size:0.8rem;color:var(--success);">
            <i class="fas fa-arrow-up"></i>
            <strong>2025 Update:</strong> Exempt threshold raised to ETB 2,000/month. Minimum taxable rate is 15%.
        </div>
    </div>
</div>

<?php if (!empty($results)):
    // Grand totals across all employees
    $gt = array_fill_keys([
        'basic','salary_expense','allowances','gross',
        'pension_emp','income_tax','pension_org',
        'credit_assoc','gerd','loan','penalty','other_ded',
        'other_deductions','total_deductions','net_pay'
    ], 0.0);
    foreach ($results as $r) {
        foreach (array_keys($gt) as $k) {
            $gt[$k] += $r[$k] ?? 0;
        }
    }
?>

<!-- ============================================================
     MAIN PAYROLL TABLE
     Column order matches the official BiT payroll spreadsheet:
     S.No | Emp ID | Department | Working Days | Basic Salary |
     Allowances | Pension 11% | Total Payment | Income Tax |
     Pension 18% | Other Deductions | Total Deductions | Net Pay | CBE Account
     ============================================================ -->
<div class="card print-target">
    <div class="card-header">
        <h3><i class="fas fa-table" style="color:var(--success);margin-right:8px"></i>
            Payroll &mdash;
            <span class="payroll-period-label">
                <?= htmlspecialchars($saved_period_label ?: ($_POST['period'] ?? '')) ?>
            </span>
        </h3>
        <div class="d-flex gap-2">
            <!-- Confirm & Save button — only shown before saving -->
            <?php if (!$saved_period_id): ?>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="period"       value="<?= htmlspecialchars($_POST['period']       ?? '') ?>">
                <input type="hidden" name="period_month" value="<?= htmlspecialchars($_POST['period_month'] ?? '') ?>">
                <input type="hidden" name="period_year"  value="<?= htmlspecialchars($_POST['period_year']  ?? '') ?>">
                <button type="submit" name="confirm_payroll" class="btn btn-success btn-sm">
                    <i class="fas fa-check"></i> Confirm &amp; Save
                </button>
            </form>
            <?php else: ?>
            <span class="badge badge-success" style="padding:8px 14px;font-size:0.82rem;">
                <i class="fas fa-check-circle"></i> Saved
            </span>
            <a href="verify_payroll.php?period_id=<?= $saved_period_id ?>"
               class="btn btn-success btn-sm">
                <i class="fas fa-check-double"></i> Go to Verify
            </a>
            <?php endif; ?>
            <!-- Export to Excel -->
            <button class="btn btn-primary btn-sm" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <!-- Print -->
            <button class="btn btn-secondary btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="card-body" style="padding:0">
        <!-- Print header — hidden on screen, visible when printing -->
        <h2 class="print-title" style="padding:12px 16px 0;">
            Bahir Dar Institute of Technology (BiT) &mdash; Payroll Report
        </h2>
        <p class="print-subtitle" style="padding:0 16px 8px;">
            Period: <?= htmlspecialchars($saved_period_label ?: ($_POST['period'] ?? '')) ?>
            &nbsp;|&nbsp; Generated: <?= date('M d, Y H:i') ?>
            &nbsp;|&nbsp; Employees: <?= count($results) ?>
        </p>
        <div class="table-wrapper">
            <table id="payrollTable" style="font-size:0.8rem;">
                <thead>
                    <!-- Row 1: grouped headers -->
                    <tr>
                        <th rowspan="2" style="vertical-align:middle;text-align:center;">#</th>
                        <th rowspan="2" style="vertical-align:middle;">Emp ID</th>
                        <th rowspan="2" style="vertical-align:middle;">Department</th>
                        <th rowspan="2" style="vertical-align:middle;text-align:center;">Working<br>Days</th>
                        <th rowspan="2" style="vertical-align:middle;text-align:right;">Basic<br>Salary</th>
                        <th rowspan="2" style="vertical-align:middle;text-align:right;">Allowances</th>
                        <th rowspan="2" style="vertical-align:middle;text-align:right;background:var(--warning-light);color:var(--warning);">
                            Pension<br>11%
                        </th>
                        <th rowspan="2" style="vertical-align:middle;text-align:right;background:var(--success-light);color:var(--success);">
                            Total Payment<br><span style="font-weight:400;font-size:0.7rem;">(Gross)</span>
                        </th>
                        <!-- Deductions group -->
                        <th colspan="4" style="text-align:center;background:var(--danger-light);color:var(--danger);">
                            Deductions
                        </th>
                        <th rowspan="2" style="vertical-align:middle;text-align:right;background:var(--danger-light);color:var(--danger);">
                            Total<br>Deduction
                        </th>
                        <th rowspan="2" style="vertical-align:middle;text-align:right;background:var(--success-light);color:var(--success);">
                            Net<br>Payment
                        </th>
                        <th rowspan="2" style="vertical-align:middle;text-align:center;background:var(--info-light);color:var(--info);">
                            CBE Account No.<br><span style="font-weight:400;font-size:0.7rem;">(Bank)</span>
                        </th>
                    </tr>
                    <!-- Row 2: deduction sub-headers -->
                    <tr>
                        <th style="background:var(--danger-light);color:var(--danger);text-align:right;">
                            Income Tax
                        </th>
                        <th style="background:var(--info-light);color:var(--info);text-align:right;">
                            Pension 18%<br><span style="font-weight:400;font-size:0.65rem;">(Employer)</span>
                        </th>
                        <th style="background:var(--danger-light);color:var(--danger);text-align:right;">
                            Other<br>Deduction
                        </th>
                        <th style="background:var(--warning-light);color:var(--warning);text-align:right;font-size:0.68rem;">
                            Credit(10%)<br>GERD(1%)<br>Loan/Penalty
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($results as $r): ?>
                    <tr>
                        <!-- S.No -->
                        <td style="text-align:center;color:var(--gray-400);"><?= $i++ ?></td>

                        <!-- Emp ID + Name -->
                        <td>
                            <strong style="font-size:0.78rem;"><?= htmlspecialchars($r['full_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($r['emp_id']) ?></small>
                        </td>

                        <!-- Department -->
                        <td style="font-size:0.78rem;color:var(--gray-600);">
                            <?= htmlspecialchars($r['dept_name']) ?>
                        </td>

                        <!-- Working Days -->
                        <td style="text-align:center;">
                            <span class="badge <?= $r['working_days'] == 30 ? 'badge-success' : 'badge-warning' ?>">
                                <?= $r['working_days'] ?>
                            </span>
                        </td>

                        <!-- Basic Salary -->
                        <td style="text-align:right;"><?= number_format($r['basic_salary'], 2) ?></td>

                        <!-- Allowances (total) with tooltip breakdown -->
                        <td style="text-align:right;"
                            title="Housing: <?= number_format($r['housing'],2) ?> | Transport: <?= number_format($r['transport'],2) ?> | Position: <?= number_format($r['position_allow'],2) ?> | Teaching: <?= number_format($r['teaching'],2) ?>">
                            <?= $r['allowances'] > 0
                                ? number_format($r['allowances'], 2)
                                : '<span class="text-muted">&mdash;</span>' ?>
                        </td>

                        <!-- Pension 11% (employee — deducted from salary) -->
                        <td style="text-align:right;color:var(--warning);background:var(--warning-light);">
                            <?= number_format($r['pension_emp'], 2) ?>
                        </td>

                        <!-- Total Payment (Gross) with tax bracket badge -->
                        <td style="text-align:right;font-weight:700;color:var(--success);background:var(--success-light);">
                            <?= number_format($r['gross'], 2) ?>
                            <br><span class="badge <?= $r['tax_bracket'] === '0%' ? 'badge-success' : 'badge-warning' ?>"
                                      style="font-size:0.6rem;"><?= $r['tax_bracket'] ?></span>
                        </td>

                        <!-- Income Tax -->
                        <td style="text-align:right;color:var(--danger);">
                            <?= number_format($r['income_tax'], 2) ?>
                        </td>

                        <!-- Pension 18% (employer — paid by BiT, shown for reference) -->
                        <td style="text-align:right;color:var(--info);">
                            <?= number_format($r['pension_org'], 2) ?>
                        </td>

                        <!-- Other Deductions: Credit Assoc + GERD combined -->
                        <td style="text-align:right;color:var(--danger);"
                            title="Credit Assoc: <?= number_format($r['credit_assoc'],2) ?> | GERD: <?= number_format($r['gerd'],2) ?>">
                            <?= number_format($r['credit_assoc'] + $r['gerd'], 2) ?>
                        </td>

                        <!-- Loan + Penalty + Other -->
                        <td style="text-align:right;color:var(--warning);">
                            <?= ($r['loan'] + $r['penalty'] + $r['other_ded']) > 0
                                ? number_format($r['loan'] + $r['penalty'] + $r['other_ded'], 2)
                                : '<span class="text-muted">&mdash;</span>' ?>
                        </td>

                        <!-- Total Deductions -->
                        <td style="text-align:right;font-weight:700;color:var(--danger);">
                            <?= number_format($r['total_deductions'], 2) ?>
                        </td>

                        <!-- Net Pay -->
                        <td style="text-align:right;font-weight:700;color:var(--success);font-size:0.9rem;">
                            <?= number_format($r['net_pay'], 2) ?>
                        </td>

                        <!-- CBE Account Number -->
                        <td style="text-align:center;font-family:monospace;font-size:0.78rem;color:var(--info);">
                            <?= $r['cbe_account_number']
                                ? htmlspecialchars($r['cbe_account_number'])
                                : '<span class="text-muted" style="font-family:sans-serif;">&mdash;</span>' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>

                <!-- Grand Totals Footer Row -->
                <tfoot>
                    <tr style="background:var(--bg-light);font-weight:700;font-size:0.82rem;">
                        <td colspan="3" style="padding:12px 16px;color:var(--primary);">
                            TOTALS &mdash; <?= count($results) ?> employees
                        </td>
                        <td style="padding:12px 16px;text-align:center;"></td>
                        <td style="padding:12px 16px;text-align:right;"><?= number_format($gt['basic'], 2) ?></td>
                        <td style="padding:12px 16px;text-align:right;"><?= number_format($gt['allowances'], 2) ?></td>
                        <td style="padding:12px 16px;text-align:right;color:var(--warning);"><?= number_format($gt['pension_emp'], 2) ?></td>
                        <td style="padding:12px 16px;text-align:right;color:var(--success);"><?= number_format($gt['gross'], 2) ?></td>
                        <td style="padding:12px 16px;text-align:right;color:var(--danger);"><?= number_format($gt['income_tax'], 2) ?></td>
                        <td style="padding:12px 16px;text-align:right;color:var(--info);"><?= number_format($gt['pension_org'], 2) ?></td>
                        <td style="padding:12px 16px;text-align:right;color:var(--danger);"><?= number_format($gt['credit_assoc'] + $gt['gerd'], 2) ?></td>
                        <td style="padding:12px 16px;text-align:right;color:var(--warning);"><?= number_format($gt['loan'] + $gt['penalty'] + $gt['other_ded'], 2) ?></td>
                        <td style="padding:12px 16px;text-align:right;color:var(--danger);"><?= number_format($gt['total_deductions'], 2) ?></td>
                        <td style="padding:12px 16px;text-align:right;color:var(--success);font-size:0.95rem;"><?= number_format($gt['net_pay'], 2) ?></td>
                        <td style="padding:12px 16px;"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Summary cards + legal note -->
    <div class="card-footer">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-bottom:12px;">
            <?php
            $summary_cards = [
                ['Total Gross',            $gt['gross'],            'var(--success)'],
                ['Income Tax',             $gt['income_tax'],       'var(--danger)'],
                ['Pension 11% (Employee)', $gt['pension_emp'],      'var(--warning)'],
                ['Pension 18% (Employer)', $gt['pension_org'],      'var(--info)'],
                ['Credit + GERD',          $gt['credit_assoc'] + $gt['gerd'], 'var(--primary)'],
                ['Loan / Penalty / Other', $gt['loan'] + $gt['penalty'] + $gt['other_ded'], 'var(--gray-600)'],
                ['Total Deductions',       $gt['total_deductions'], 'var(--danger)'],
                ['Total Net Pay',          $gt['net_pay'],          'var(--success)'],
            ];
            foreach ($summary_cards as [$label, $val, $color]): ?>
            <div style="text-align:center;padding:10px;background:var(--gray-100);
                        border-radius:var(--radius);border-top:3px solid <?= $color ?>;">
                <p style="font-size:0.65rem;color:var(--gray-400);margin:0;text-transform:uppercase;"><?= $label ?></p>
                <p style="font-size:0.88rem;font-weight:700;color:<?= $color ?>;margin:2px 0 0;">
                    ETB <?= number_format($val, 2) ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="padding:10px 14px;background:var(--info-light);border-radius:var(--radius);
                    font-size:0.78rem;color:var(--info);display:flex;align-items:center;gap:8px;">
            <i class="fas fa-gavel"></i>
            <span>
                Income tax per <strong>Revised Monthly Employment Tax Brackets &mdash; Ethiopia 2025</strong>.
                Pension: Employee <strong>11%</strong> + Employer <strong>18%</strong> of basic salary.
                Employer pension is paid by BiT and is <em>not</em> deducted from the employee.
            </span>
        </div>
    </div>
</div>

<?php endif; ?>

<style>
@media print {
    /* Hide everything except the payroll table card */
    .breadcrumb, .page-header, .card:not(.print-target),
    .topbar, .sidebar, .sidebar-overlay,
    #sessionWarnBox, .alert, .card-footer,
    .btn, form, nav { display: none !important; }

    .print-target { display: block !important; box-shadow: none !important; border: none !important; }
    .print-target .card-header .d-flex { display: none !important; }

    body, .layout, .main-content, .page-content {
        margin: 0 !important; padding: 0 !important;
        background: white !important;
    }

    table { border-collapse: collapse; width: 100%; font-size: 9pt; }
    th, td { border: 1px solid #999; padding: 4px 6px; }
    th { background: #1565C0 !important; color: white !important; -webkit-print-color-adjust: exact; }
    .badge { border: 1px solid #999; padding: 1px 4px; font-size: 8pt; }

    h2.print-title { display: block !important; font-size: 13pt; margin-bottom: 4px; }
    p.print-subtitle { display: block !important; font-size: 10pt; margin-bottom: 10px; }
}

/* Hidden on screen, shown only when printing */
.print-title, .print-subtitle { display: none; }
</style>

<script>
// Sync hidden period_month / period_year fields when the dropdown changes
function syncPeriod(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('period_month').value = opt.dataset.month || '';
    document.getElementById('period_year').value  = opt.dataset.year  || '';
}
// Run on page load in case the period is already selected (after form submit)
window.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('periodSelect');
    if (sel) syncPeriod(sel);
});

// ── Export payroll table to Excel (.xls) ──────────────────
// Uses the HTML table export trick: wraps the table in an XLS-compatible
// HTML document and triggers a download. Opens directly in Excel/LibreOffice.
function exportToExcel() {
    const table = document.getElementById('payrollTable');
    if (!table) { alert('No payroll data to export.'); return; }

    const period = document.querySelector('.payroll-period-label')?.textContent?.trim()
                || 'Payroll';

    // Build a minimal HTML document that Excel recognises as XLS
    const html = `
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
  <meta charset="UTF-8">
  <!--[if gte mso 9]>
  <xml><x:ExcelWorkbook><x:ExcelWorksheets>
    <x:ExcelWorksheet>
      <x:Name>${period}</x:Name>
      <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
    </x:ExcelWorksheet>
  </x:ExcelWorksheets></x:ExcelWorkbook></xml>
  <![endif]-->
  <style>
    table { border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 6px 10px; font-size: 11pt; }
    th { background: #1565C0; color: white; font-weight: bold; }
    .totals-row { background: #E3F2FD; font-weight: bold; }
  </style>
</head>
<body>
  <h2>Bahir Dar Institute of Technology &mdash; BiT Payroll</h2>
  <h3>${period}</h3>
  ${table.outerHTML}
</body>
</html>`;

    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'BiT_Payroll_' + period.replace(/\s+/g, '_') + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

<?php require_once $depth . 'includes/footer.php'; ?>

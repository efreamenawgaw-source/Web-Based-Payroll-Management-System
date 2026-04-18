<?php
session_start();
$page_title = 'Manage Deductions';
$active_nav = 'deductions';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';

$pdo     = getDB();
$success = '';
$error   = '';

$cur_month = (int)date('n');
$cur_year  = (int)date('Y');

// ── Load deduction rates from system_settings ──────────────
$rates = $pdo->query("
    SELECT setting_key, setting_value FROM system_settings
    WHERE  setting_key IN ('credit_association_rate','renaissance_dam_rate')
")->fetchAll(PDO::FETCH_KEY_PAIR);

$CREDIT_RATE = (float)($rates['credit_association_rate'] ?? 0.10); // default 10%
$GERD_RATE   = (float)($rates['renaissance_dam_rate']    ?? 0.01); // default 1%

$selected_emp_id = trim($_GET['emp'] ?? '');
$f_month = (int)($_GET['month'] ?? $cur_month);
$f_year  = (int)($_GET['year']  ?? $cur_year);

// ── SAVE / UPDATE deduction row ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_deductions'])) {
    $emp_id             = trim($_POST['emp_id']             ?? '');
    $credit_association = (float)($_POST['credit_association'] ?? 0);
    $renaissance_dam    = (float)($_POST['renaissance_dam']    ?? 0);
    $loan_repayment     = (float)($_POST['loan_repayment']     ?? 0);
    $penalty            = (float)($_POST['penalty']            ?? 0);
    $other              = (float)($_POST['other']              ?? 0);
    $description        = trim($_POST['description']           ?? '');
    $eff_month          = (int)($_POST['effective_month']      ?? $cur_month);
    $eff_year           = (int)($_POST['effective_year']       ?? $cur_year);

    if (!$emp_id) {
        $error = 'Please select an employee.';
    } else {
        try {
            // Upsert — update if exists for this period, insert if not
            $check = $pdo->prepare("
                SELECT deduction_id FROM deductions
                WHERE emp_id = ? AND effective_month = ? AND effective_year = ?
            ");
            $check->execute([$emp_id, $eff_month, $eff_year]);
            $existing = $check->fetch();

            if ($existing) {
                $pdo->prepare("
                    UPDATE deductions
                    SET    credit_association = ?,
                           renaissance_dam    = ?,
                           loan_repayment     = ?,
                           penalty            = ?,
                           other              = ?,
                           description        = ?,
                           status             = 'active',
                           created_by         = ?
                    WHERE  deduction_id = ?
                ")->execute([
                    $credit_association, $renaissance_dam,
                    $loan_repayment, $penalty, $other,
                    $description ?: null,
                    $_SESSION['user_id'],
                    $existing['deduction_id']
                ]);
            } else {
                $pdo->prepare("
                    INSERT INTO deductions
                        (emp_id, credit_association, renaissance_dam,
                         loan_repayment, penalty, other,
                         description, effective_month, effective_year,
                         status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
                ")->execute([
                    $emp_id, $credit_association, $renaissance_dam,
                    $loan_repayment, $penalty, $other,
                    $description ?: null,
                    $eff_month, $eff_year,
                    $_SESSION['user_id']
                ]);
            }

            $total = $credit_association + $renaissance_dam + $loan_repayment + $penalty + $other;

            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Update Deductions', ?, ?, ?)
            ")->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                $emp_id,
                "Period:{$eff_month}/{$eff_year} | Credit:{$credit_association} GERD:{$renaissance_dam} Loan:{$loan_repayment} Penalty:{$penalty} Other:{$other} Total:{$total}",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $selected_emp_id = $emp_id;
            $f_month = $eff_month;
            $f_year  = $eff_year;
            $success = "Deductions saved for <strong>{$emp_id}</strong> — " .
                       date('F', mktime(0,0,0,$eff_month,1)) . " {$eff_year}. " .
                       "Total: <strong>ETB " . number_format($total, 2) . "</strong>";
        } catch (PDOException $e) {
            $error = 'Save failed: ' . $e->getMessage();
        }
    }
}

// ── Load active employees ──────────────────────────────────
$employees = $pdo->query("
    SELECT e.emp_id, e.full_name, e.basic_salary, d.dept_name
    FROM   employees e
    JOIN   departments d ON e.dept_id = d.dept_id
    WHERE  e.status = 'active'
    ORDER  BY e.full_name
")->fetchAll();

// ── Selected employee info ─────────────────────────────────
$sel_emp = null;
$sel_ded = null;
if ($selected_emp_id) {
    $s = $pdo->prepare("
        SELECT e.emp_id, e.full_name, e.basic_salary, d.dept_name, e.position
        FROM   employees e
        JOIN   departments d ON e.dept_id = d.dept_id
        WHERE  e.emp_id = ?
    ");
    $s->execute([$selected_emp_id]);
    $sel_emp = $s->fetch();

    // Load existing deduction for selected period
    $ds = $pdo->prepare("
        SELECT * FROM deductions
        WHERE emp_id = ? AND effective_month = ? AND effective_year = ?
    ");
    $ds->execute([$selected_emp_id, $f_month, $f_year]);
    $sel_ded = $ds->fetch();
}

// ── Monthly deductions overview (all employees) ────────────
$overview = $pdo->query("
    SELECT
        e.emp_id,
        e.full_name,
        e.basic_salary,
        COALESCE(d.credit_association, 0) AS credit_association,
        COALESCE(d.renaissance_dam,    0) AS renaissance_dam,
        COALESCE(d.loan_repayment,     0) AS loan_repayment,
        COALESCE(d.penalty,            0) AS penalty,
        COALESCE(d.other,              0) AS other,
        COALESCE(
            d.credit_association + d.renaissance_dam +
            d.loan_repayment + d.penalty + d.other, 0
        ) AS total_other_deductions,
        d.status
    FROM employees e
    LEFT JOIN deductions d
        ON  e.emp_id = d.emp_id
        AND d.effective_month = {$f_month}
        AND d.effective_year  = {$f_year}
    WHERE e.status = 'active'
    ORDER BY e.full_name
")->fetchAll();

// ── Totals for the month ───────────────────────────────────
$totals = [
    'credit_association' => 0,
    'renaissance_dam'    => 0,
    'loan_repayment'     => 0,
    'penalty'            => 0,
    'other'              => 0,
    'total'              => 0,
];
foreach ($overview as $row) {
    $totals['credit_association'] += $row['credit_association'];
    $totals['renaissance_dam']    += $row['renaissance_dam'];
    $totals['loan_repayment']     += $row['loan_repayment'];
    $totals['penalty']            += $row['penalty'];
    $totals['other']              += $row['other'];
    $totals['total']              += $row['total_other_deductions'];
}

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">HR</a><span>/</span><span>Manage Deductions</span>
</div>

<div class="page-header">
    <h1>Manage Employee Deductions</h1>
    <p>Set Credit Association, Renaissance Dam (GERD), loan repayments, and other deductions per employee.</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<!-- ── Info Box ── -->
<div class="alert alert-info mb-3">
    <i class="fas fa-info-circle"></i>
    <div style="font-size:0.88rem;">
        <strong>Deduction Structure:</strong>
        Income Tax and Pension (7%) are <em>calculated automatically</em> during payroll.
        The fields below are <em>additional</em> deductions entered by HR:
        <strong>Credit Association</strong>, <strong>Renaissance Dam (GERD)</strong>,
        <strong>Loan Repayment</strong>, <strong>Penalty</strong>, and <strong>Other</strong>.
        These are summed as <em>Total Other Deductions</em> and subtracted from net pay.
    </div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- ── Entry Form ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-edit" style="color:var(--danger);margin-right:8px"></i>
                Enter Deductions
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">

                <!-- Period selector -->
                <div class="form-row" style="margin-bottom:4px;">
                    <div class="form-group">
                        <label class="form-label">Month</label>
                        <select name="effective_month" class="form-control"
                                onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === $f_month ? 'selected' : '' ?>>
                                <?= date('F', mktime(0,0,0,$m,1)) ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Year</label>
                        <select name="effective_year" class="form-control"
                                onchange="this.form.submit()">
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?= $y ?>" <?= $y === $f_year ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- Employee selector -->
                <div class="form-group">
                    <label class="form-label">Select Employee <span style="color:var(--danger)">*</span></label>
                    <select name="emp_id" class="form-control" required
                            onchange="this.form.action='deductions.php?emp='+this.value+'&month=<?= $f_month ?>&year=<?= $f_year ?>'; this.form.submit();">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= htmlspecialchars($e['emp_id']) ?>"
                            <?= $selected_emp_id === $e['emp_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['emp_id']) ?> — <?= htmlspecialchars($e['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($sel_emp): ?>

                <!-- Employee info bar -->
                <div style="background:var(--bg-light);border-radius:var(--radius);
                     padding:12px 14px;margin-bottom:16px;
                     display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                    <div>
                        <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;">Employee</p>
                        <p style="font-weight:700;margin:0;"><?= htmlspecialchars($sel_emp['full_name']) ?></p>
                        <p style="font-size:0.8rem;color:var(--gray-600);margin:0;">
                            <?= htmlspecialchars($sel_emp['position']) ?> — <?= htmlspecialchars($sel_emp['dept_name']) ?>
                        </p>
                    </div>
                    <div style="text-align:right;">
                        <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;">Basic Salary</p>
                        <p style="font-size:1.1rem;font-weight:700;color:var(--primary);margin:0;">
                            ETB <?= number_format($sel_emp['basic_salary'], 2) ?>
                        </p>
                    </div>
                </div>

                <!-- Deduction fields — matching spreadsheet columns -->
                <div style="background:var(--gray-100);border-radius:var(--radius);padding:14px;margin-bottom:16px;">
                    <p style="font-size:0.78rem;font-weight:700;color:var(--gray-600);
                               text-transform:uppercase;margin:0 0 12px;">
                        Other Deductions — <?= date('F', mktime(0,0,0,$f_month,1)) ?> <?= $f_year ?>
                    </p>

                    <?php
                    // Auto-calculate defaults from basic salary
                    $basic = (float)$sel_emp['basic_salary'];
                    $default_credit = round($basic * $CREDIT_RATE, 2);  // 10% of basic
                    $default_gerd   = round($basic * $GERD_RATE,   2);  // 1% of basic

                    $ded_fields = [
                        ['credit_association', 'Credit Association (10% of basic)',    'fas fa-handshake',         'var(--info)',     'Default: 10% of basic salary',   $default_credit],
                        ['renaissance_dam',    'Renaissance Dam — GERD (1% of basic)', 'fas fa-water',             'var(--primary)', 'Default: 1% of basic salary',    $default_gerd],
                        ['loan_repayment',     'Loan Repayment',                       'fas fa-hand-holding-usd',  'var(--warning)', 'Monthly loan installment',       0],
                        ['penalty',            'Penalty / Absence',                    'fas fa-exclamation-triangle','var(--danger)', 'Penalty or absence deduction',  0],
                        ['other',              'Other Deduction',                      'fas fa-minus-circle',      'var(--gray-600)','Any other deduction',            0],
                    ];
                    foreach ($ded_fields as [$fname, $flabel, $ficon, $fcolor, $fhint, $fdefault]):
                        // Use saved value if record exists, otherwise use default
                        $cur_val = isset($sel_ded[$fname]) ? (float)$sel_ded[$fname] : $fdefault;
                    ?>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label" style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="display:flex;align-items:center;gap:6px;">
                                <i class="<?= $ficon ?>" style="color:<?= $fcolor ?>;width:16px;"></i>
                                <?= $flabel ?> (ETB)
                            </span>
                            <?php if ($fdefault > 0): ?>
                            <span style="font-size:0.72rem;color:<?= $fcolor ?>;font-weight:600;
                                         background:<?= $fcolor ?>18;padding:2px 8px;border-radius:10px;cursor:pointer;"
                                  onclick="document.getElementById('<?= $fname ?>').value='<?= number_format($fdefault,2,'.','') ?>'; updateTotal();"
                                  title="Click to reset to default">
                                Default: ETB <?= number_format($fdefault, 2) ?>
                            </span>
                            <?php endif; ?>
                        </label>
                        <input type="number" name="<?= $fname ?>" id="<?= $fname ?>"
                               class="form-control ded-input"
                               value="<?= number_format($cur_val, 2, '.', '') ?>"
                               min="0" step="0.01" placeholder="0.00">
                        <span class="form-hint"><?= $fhint ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Live total preview -->
                <div style="background:var(--danger-light);border-radius:var(--radius);
                     padding:14px;border-left:4px solid var(--danger);margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                        <div>
                            <p style="font-size:0.78rem;color:var(--danger);margin:0;font-weight:700;text-transform:uppercase;">
                                Total Other Deductions
                            </p>
                            <p style="font-size:0.75rem;color:var(--gray-600);margin:2px 0 0;">
                                (Pension 7% + Income Tax calculated separately during payroll)
                            </p>
                        </div>
                        <?php
                        // Show current total (saved or defaults)
                        $display_total = isset($sel_ded)
                            ? (($sel_ded['credit_association'] ?? 0) +
                               ($sel_ded['renaissance_dam']    ?? 0) +
                               ($sel_ded['loan_repayment']     ?? 0) +
                               ($sel_ded['penalty']            ?? 0) +
                               ($sel_ded['other']              ?? 0))
                            : ($default_credit + $default_gerd);
                        ?>
                        <p id="totalDed" style="font-size:1.4rem;font-weight:800;color:var(--danger);margin:0;">
                            ETB <?= number_format($display_total, 2) ?>
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes (optional)</label>
                    <textarea name="description" class="form-control" rows="2"
                              placeholder="e.g. Loan installment #3 of 12..."><?= htmlspecialchars($sel_ded['description'] ?? '') ?></textarea>
                </div>

                <button type="submit" name="save_deductions" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i>
                    <?= $sel_ded ? 'Update Deductions' : 'Save Deductions' ?>
                </button>

                <?php if ($sel_ded): ?>
                <p style="text-align:center;font-size:0.78rem;color:var(--success);margin-top:8px;">
                    <i class="fas fa-check-circle"></i>
                    Record exists for <?= date('F', mktime(0,0,0,$f_month,1)) ?> <?= $f_year ?> — will be updated.
                </p>
                <?php endif; ?>

                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-minus-circle"></i></div>
                    <p>Select an employee above to enter their deductions.</p>
                </div>
                <?php endif; ?>

            </form>
        </div>
    </div>

    <!-- ── Deduction Summary for selected employee ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>
                <?= $sel_emp
                    ? htmlspecialchars($sel_emp['full_name']) . ' — History'
                    : 'Deduction History' ?>
            </h3>
        </div>
        <div class="card-body" style="padding:0">
            <?php if ($sel_emp): ?>
            <?php
            // Load last 6 months for this employee
            $hist = $pdo->prepare("
                SELECT effective_month, effective_year,
                       credit_association, renaissance_dam,
                       loan_repayment, penalty, other,
                       (credit_association + renaissance_dam + loan_repayment + penalty + other) AS total,
                       status
                FROM   deductions
                WHERE  emp_id = ?
                ORDER  BY effective_year DESC, effective_month DESC
                LIMIT  6
            ");
            $hist->execute([$selected_emp_id]);
            $history = $hist->fetchAll();
            ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Credit Assoc.</th>
                            <th>GERD</th>
                            <th>Loan</th>
                            <th>Penalty</th>
                            <th>Other</th>
                            <th>Total (ETB)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted" style="padding:24px;">
                                No deduction records yet.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td style="white-space:nowrap;">
                                <strong><?= date('M', mktime(0,0,0,$h['effective_month'],1)) ?> <?= $h['effective_year'] ?></strong>
                            </td>
                            <td><?= number_format($h['credit_association'], 2) ?></td>
                            <td><?= number_format($h['renaissance_dam'],    2) ?></td>
                            <td><?= number_format($h['loan_repayment'],     2) ?></td>
                            <td><?= number_format($h['penalty'],            2) ?></td>
                            <td><?= number_format($h['other'],              2) ?></td>
                            <td class="text-bold text-danger">
                                <?= number_format($h['total'], 2) ?>
                            </td>
                            <td>
                                <span class="badge <?= $h['status'] === 'active' ? 'badge-warning' : ($h['status'] === 'applied' ? 'badge-success' : 'badge-danger') ?>">
                                    <?= ucfirst($h['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-history"></i></div>
                <p>Select an employee to view their deduction history.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ── Monthly Overview Table (matches spreadsheet layout) ── -->
<div class="card">
    <div class="card-header">
        <h3>
            <i class="fas fa-table" style="color:var(--primary);margin-right:8px"></i>
            All Employees — Deductions for
            <?= date('F', mktime(0,0,0,$f_month,1)) ?> <?= $f_year ?>
        </h3>
        <!-- Period switcher -->
        <form method="GET" action="" style="display:flex;gap:8px;align-items:center;">
            <select name="month" class="form-control" style="width:auto;font-size:0.82rem;">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $f_month ? 'selected' : '' ?>>
                    <?= date('F', mktime(0,0,0,$m,1)) ?>
                </option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-control" style="width:auto;font-size:0.82rem;">
                <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $f_year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-filter"></i> View
            </button>
        </form>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Basic Salary</th>
                        <th style="color:var(--info);">Credit Association</th>
                        <th style="color:var(--primary);">Renaissance Dam</th>
                        <th style="color:var(--warning);">Loan Repayment</th>
                        <th style="color:var(--danger);">Penalty</th>
                        <th>Other</th>
                        <th style="background:var(--danger-light);color:var(--danger);">Total Deductions</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($overview as $row): ?>
                    <tr>
                        <td class="text-muted"><?= $i++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['full_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($row['emp_id']) ?></small>
                        </td>
                        <td><?= number_format($row['basic_salary'], 2) ?></td>
                        <td style="color:var(--info);">
                            <?= $row['credit_association'] > 0
                                ? number_format($row['credit_association'], 2)
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td style="color:var(--primary);">
                            <?= $row['renaissance_dam'] > 0
                                ? number_format($row['renaissance_dam'], 2)
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td style="color:var(--warning);">
                            <?= $row['loan_repayment'] > 0
                                ? number_format($row['loan_repayment'], 2)
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td style="color:var(--danger);">
                            <?= $row['penalty'] > 0
                                ? number_format($row['penalty'], 2)
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td>
                            <?= $row['other'] > 0
                                ? number_format($row['other'], 2)
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td style="font-weight:700;color:var(--danger);">
                            <?= $row['total_other_deductions'] > 0
                                ? 'ETB ' . number_format($row['total_other_deductions'], 2)
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td>
                            <a href="deductions.php?emp=<?= urlencode($row['emp_id']) ?>&month=<?= $f_month ?>&year=<?= $f_year ?>"
                               class="btn btn-secondary btn-sm btn-icon-only" title="Edit Deductions">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <!-- Totals row -->
                <tfoot>
                    <tr style="background:var(--bg-light);font-weight:700;">
                        <td colspan="3" style="padding:12px 16px;color:var(--primary);">
                            TOTALS (<?= count($overview) ?> employees)
                        </td>
                        <td style="padding:12px 16px;color:var(--info);">
                            <?= number_format($totals['credit_association'], 2) ?>
                        </td>
                        <td style="padding:12px 16px;color:var(--primary);">
                            <?= number_format($totals['renaissance_dam'], 2) ?>
                        </td>
                        <td style="padding:12px 16px;color:var(--warning);">
                            <?= number_format($totals['loan_repayment'], 2) ?>
                        </td>
                        <td style="padding:12px 16px;color:var(--danger);">
                            <?= number_format($totals['penalty'], 2) ?>
                        </td>
                        <td style="padding:12px 16px;">
                            <?= number_format($totals['other'], 2) ?>
                        </td>
                        <td style="padding:12px 16px;color:var(--danger);font-size:1rem;">
                            ETB <?= number_format($totals['total'], 2) ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="card-footer" style="font-size:0.8rem;color:var(--gray-600);">
        <i class="fas fa-info-circle" style="color:var(--info);"></i>
        Click <i class="fas fa-edit"></i> to enter or update deductions for any employee.
        These will be included in payroll processing as <strong>other_deductions</strong>.
    </div>
</div>

<script>
// Live total calculation
function updateTotal() {
    const fields = ['credit_association','renaissance_dam','loan_repayment','penalty','other'];
    let total = 0;
    fields.forEach(f => {
        const el = document.getElementById(f);
        if (el) total += parseFloat(el.value) || 0;
    });
    const el = document.getElementById('totalDed');
    if (el) {
        el.textContent = 'ETB ' + total.toLocaleString('en-US', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }
}
document.querySelectorAll('.ded-input').forEach(i => i.addEventListener('input', updateTotal));
</script>

<?php require_once $depth . 'includes/footer.php'; ?>

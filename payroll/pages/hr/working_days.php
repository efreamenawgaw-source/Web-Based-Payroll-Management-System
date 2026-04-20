<?php
session_start();
$page_title = 'Working Days';
$active_nav = 'working_days';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/notify.php';

$pdo     = getDB();
$success = '';
$error   = '';

$cur_month = (int)date('n');
$cur_year  = (int)date('Y');

$f_month = (int)($_GET['month'] ?? $cur_month);
$f_year  = (int)($_GET['year']  ?? $cur_year);

// ── SAVE working days (bulk submit) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_working_days'])) {
    $eff_month = (int)($_POST['period_month'] ?? $cur_month);
    $eff_year  = (int)($_POST['period_year']  ?? $cur_year);
    $days_data = $_POST['days'] ?? [];   // array: emp_id => working_days
    $notes_data= $_POST['notes'] ?? [];  // array: emp_id => notes

    if (empty($days_data)) {
        $error = 'No data submitted.';
    } else {
        try {
            $pdo->beginTransaction();
            $saved = 0;

            foreach ($days_data as $emp_id => $days) {
                $emp_id = trim($emp_id);
                $days   = (int)$days;
                $note   = trim($notes_data[$emp_id] ?? '');

                if ($emp_id === '' || $days < 0 || $days > 31) continue;

                // Upsert
                $chk = $pdo->prepare("
                    SELECT wd_id FROM working_days
                    WHERE emp_id = ? AND period_month = ? AND period_year = ?
                ");
                $chk->execute([$emp_id, $eff_month, $eff_year]);
                $existing = $chk->fetch();

                if ($existing) {
                    $pdo->prepare("
                        UPDATE working_days
                        SET    working_days = ?, notes = ?, submitted_by = ?
                        WHERE  wd_id = ?
                    ")->execute([$days, $note ?: null, $_SESSION['user_id'], $existing['wd_id']]);
                } else {
                    $pdo->prepare("
                        INSERT INTO working_days
                            (emp_id, period_month, period_year, working_days, notes, submitted_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $emp_id, $eff_month, $eff_year,
                        $days, $note ?: null, $_SESSION['user_id']
                    ]);
                }
                $saved++;
            }

            // Audit log
            $period_label = date('F', mktime(0,0,0,$eff_month,1)) . ' ' . $eff_year;
            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Submit Working Days', ?, ?, ?)
            ")->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                $period_label,
                "Submitted working days for {$saved} employees",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $pdo->commit();
            $f_month = $eff_month;
            $f_year  = $eff_year;
            $success = "Working days saved for <strong>{$saved} employees</strong> — {$period_label}. Finance can now process payroll.";

            // Notify all Finance users that working days are ready
            notify_role($pdo, 'finance',
                'Working Days Submitted — ' . $period_label,
                "HR has submitted working days for {$saved} employees for {$period_label}. You can now process payroll.",
                'success',
                '/pages/finance/process_payroll.php');

            // Notify admin
            notify_role($pdo, 'admin',
                'Working Days Submitted',
                "HR submitted working days for {$saved} employees — {$period_label}.",
                'info');

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Save failed: ' . $e->getMessage();
        }
    }
}

// ── Load all active employees with their working days ──────
$employees = $pdo->prepare("
    SELECT
        e.emp_id,
        e.full_name,
        e.basic_salary,
        e.status,
        d.dept_name,
        e.position,
        COALESCE(w.working_days, 30)  AS working_days,
        w.notes                       AS wd_notes,
        w.submitted_at                AS wd_submitted,
        w.wd_id
    FROM employees e
    JOIN departments d ON e.dept_id = d.dept_id
    LEFT JOIN working_days w
        ON  w.emp_id = e.emp_id
        AND w.period_month = ?
        AND w.period_year  = ?
    WHERE e.status IN ('active','on_leave')
    ORDER BY d.dept_name, e.full_name
");
$employees->execute([$f_month, $f_year]);
$employees = $employees->fetchAll();

// ── Stats for this period ──────────────────────────────────
$submitted_count = 0;
$full_month      = 0;
$partial_month   = 0;
foreach ($employees as $e) {
    if ($e['wd_id']) {
        $submitted_count++;
        if ($e['working_days'] == 30) $full_month++;
        else $partial_month++;
    }
}
$total_emp = count($employees);
$pending   = $total_emp - $submitted_count;

// ── Check if Finance can process (all submitted) ───────────
$ready_for_finance = ($submitted_count === $total_emp && $total_emp > 0);

// ── Period options ─────────────────────────────────────────
$period_options = [];
for ($i = 0; $i < 6; $i++) {
    $ts = mktime(0, 0, 0, date('n') - $i, 1, date('Y'));
    $period_options[] = [
        'label' => date('F Y', $ts),
        'month' => (int)date('n', $ts),
        'year'  => (int)date('Y', $ts),
    ];
}

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">HR</a><span>/</span><span>Working Days</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Working Days Entry</h1>
        <p>Enter actual working days per employee before submitting to Finance for payroll processing.</p>
    </div>
    <!-- Period switcher -->
    <form method="GET" action="" style="display:flex;gap:8px;align-items:center;">
        <select name="month" class="form-control" style="width:auto;">
            <?php foreach ($period_options as $opt): ?>
            <option value="<?= $opt['month'] ?>"
                    data-year="<?= $opt['year'] ?>"
                <?= ($opt['month'] === $f_month && $opt['year'] === $f_year) ? 'selected' : '' ?>>
                <?= $opt['label'] ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="year" id="yearInput" value="<?= $f_year ?>">
        <button type="submit" class="btn btn-secondary btn-sm">
            <i class="fas fa-filter"></i> View
        </button>
    </form>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<!-- ── Status Banner ── -->
<?php if ($ready_for_finance): ?>
<div class="alert alert-success">
    <i class="fas fa-check-double"></i>
    <div>
        <strong>Ready for Finance!</strong>
        All <?= $total_emp ?> employees have working days submitted for
        <?= date('F', mktime(0,0,0,$f_month,1)) ?> <?= $f_year ?>.
        Finance can now process payroll.
    </div>
</div>
<?php elseif ($submitted_count > 0): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong><?= $pending ?> employees pending.</strong>
        <?= $submitted_count ?> of <?= $total_emp ?> submitted for
        <?= date('F', mktime(0,0,0,$f_month,1)) ?> <?= $f_year ?>.
        Complete all entries before Finance processes payroll.
    </div>
</div>
<?php else: ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    <div>
        No working days submitted yet for
        <strong><?= date('F', mktime(0,0,0,$f_month,1)) ?> <?= $f_year ?></strong>.
        Default is 30 days (full month). Update any employee who worked fewer days.
    </div>
</div>
<?php endif; ?>

<!-- ── Stats ── -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <p>Total Employees</p>
            <h2><?= $total_emp ?></h2>
            <span class="stat-change up">Active + On Leave</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <p>Submitted</p>
            <h2><?= $submitted_count ?></h2>
            <span class="stat-change up"><?= $full_month ?> full month</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <p>Partial Month</p>
            <h2><?= $partial_month ?></h2>
            <span class="stat-change down">Less than 30 days</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $pending > 0 ? 'red' : 'green' ?>">
            <i class="fas fa-<?= $pending > 0 ? 'exclamation-circle' : 'check-double' ?>"></i>
        </div>
        <div class="stat-info">
            <p>Pending</p>
            <h2><?= $pending ?></h2>
            <span class="stat-change <?= $pending > 0 ? 'down' : 'up' ?>">
                <?= $pending > 0 ? 'Needs entry' : 'All done!' ?>
            </span>
        </div>
    </div>
</div>

<!-- ── Working Days Entry Form ── -->
<div class="card">
    <div class="card-header">
        <h3>
            <i class="fas fa-calendar-check" style="color:var(--primary);margin-right:8px"></i>
            Working Days — <?= date('F', mktime(0,0,0,$f_month,1)) ?> <?= $f_year ?>
        </h3>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary btn-sm" onclick="setAllDays(30)">
                <i class="fas fa-check-double"></i> Set All to 30
            </button>
            <button type="submit" form="wdForm" name="save_working_days"
                    class="btn btn-primary btn-sm">
                <i class="fas fa-save"></i> Save All
            </button>
        </div>
    </div>

    <form method="POST" action="" id="wdForm">
        <input type="hidden" name="period_month" value="<?= $f_month ?>">
        <input type="hidden" name="period_year"  value="<?= $f_year ?>">

        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Basic Salary (ETB)</th>
                            <th>Status</th>
                            <th style="min-width:120px;">
                                Working Days
                                <span style="font-weight:400;font-size:0.72rem;color:var(--gray-400);">
                                    (max 30)
                                </span>
                            </th>
                            <th style="min-width:160px;">Notes</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted" style="padding:32px;">
                                No active employees found.
                                <a href="register_employee.php">Register employees first.</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php
                        $status_badge = [
                            'active'   => 'badge-success',
                            'on_leave' => 'badge-warning',
                        ];
                        $i = 1;
                        foreach ($employees as $e):
                            $is_partial = ($e['working_days'] < 30);
                        ?>
                        <tr style="<?= $is_partial ? 'background:var(--warning-light);' : '' ?>">
                            <td class="text-muted"><?= $i++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($e['full_name']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($e['emp_id']) ?></small>
                            </td>
                            <td style="font-size:0.85rem;"><?= htmlspecialchars($e['dept_name']) ?></td>
                            <td style="font-size:0.82rem;"><?= htmlspecialchars($e['position']) ?></td>
                            <td>ETB <?= number_format($e['basic_salary'], 2) ?></td>
                            <td>
                                <span class="badge <?= $status_badge[$e['status']] ?? 'badge-gray' ?>">
                                    <?= ucfirst(str_replace('_',' ',$e['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <input type="number"
                                       name="days[<?= htmlspecialchars($e['emp_id']) ?>]"
                                       class="form-control wd-input"
                                       value="<?= (int)$e['working_days'] ?>"
                                       min="0" max="30" step="1"
                                       style="width:80px;text-align:center;
                                              <?= $is_partial ? 'border-color:var(--warning);font-weight:700;' : '' ?>"
                                       onchange="highlightRow(this)">
                            </td>
                            <td>
                                <input type="text"
                                       name="notes[<?= htmlspecialchars($e['emp_id']) ?>]"
                                       class="form-control"
                                       value="<?= htmlspecialchars($e['wd_notes'] ?? '') ?>"
                                       placeholder="e.g. On leave 2 days"
                                       style="font-size:0.82rem;">
                            </td>
                            <td style="text-align:center;">
                                <?php if ($e['wd_id']): ?>
                                <i class="fas fa-check-circle" style="color:var(--success);font-size:1.1rem;"
                                   title="Submitted: <?= date('M d, H:i', strtotime($e['wd_submitted'])) ?>"></i>
                                <?php else: ?>
                                <i class="fas fa-clock" style="color:var(--gray-400);font-size:1.1rem;"
                                   title="Not yet submitted"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Footer -->
        <div class="card-footer d-flex justify-between align-center" style="flex-wrap:wrap;gap:10px;">
            <div style="font-size:0.82rem;color:var(--gray-600);">
                <i class="fas fa-info-circle" style="color:var(--info);"></i>
                Default is <strong>30 days</strong> (full month).
                Rows highlighted in yellow have fewer than 30 days.
                Salary Expense = Basic × (Working Days ÷ 30).
            </div>
            <button type="submit" name="save_working_days" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Save Working Days for <?= date('F', mktime(0,0,0,$f_month,1)) ?> <?= $f_year ?>
            </button>
        </div>
    </form>
</div>

<!-- ── Salary Expense Preview ── -->
<?php if (!empty($employees) && $submitted_count > 0): ?>
<div class="card mt-3">
    <div class="card-header">
        <h3>
            <i class="fas fa-calculator" style="color:var(--primary);margin-right:8px"></i>
            Salary Expense Preview — <?= date('F', mktime(0,0,0,$f_month,1)) ?> <?= $f_year ?>
        </h3>
        <span class="badge badge-info">Based on submitted working days</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Basic Salary (ETB)</th>
                        <th>Working Days</th>
                        <th>Salary Expense (ETB)</th>
                        <th>Difference (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    $total_basic   = 0;
                    $total_expense = 0;
                    foreach ($employees as $e):
                        if (!$e['wd_id']) continue; // only submitted
                        $basic   = (float)$e['basic_salary'];
                        $expense = round($basic * ($e['working_days'] / 30), 2);
                        $diff    = round($expense - $basic, 2);
                        $total_basic   += $basic;
                        $total_expense += $expense;
                    ?>
                    <tr>
                        <td class="text-muted"><?= $i++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($e['full_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($e['emp_id']) ?></small>
                        </td>
                        <td><?= number_format($basic, 2) ?></td>
                        <td style="text-align:center;">
                            <span class="badge <?= $e['working_days'] == 30 ? 'badge-success' : 'badge-warning' ?>">
                                <?= $e['working_days'] ?> days
                            </span>
                        </td>
                        <td class="text-bold"><?= number_format($expense, 2) ?></td>
                        <td style="color:<?= $diff < 0 ? 'var(--danger)' : 'var(--gray-400)' ?>;font-size:0.85rem;">
                            <?= $diff < 0 ? '− ETB ' . number_format(abs($diff), 2) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--bg-light);font-weight:700;">
                        <td colspan="2" style="padding:12px 16px;color:var(--primary);">TOTALS</td>
                        <td style="padding:12px 16px;"><?= number_format($total_basic, 2) ?></td>
                        <td style="padding:12px 16px;"></td>
                        <td style="padding:12px 16px;color:var(--success);"><?= number_format($total_expense, 2) ?></td>
                        <td style="padding:12px 16px;color:var(--danger);">
                            <?php $total_diff = $total_expense - $total_basic;
                            echo $total_diff < 0 ? '− ETB ' . number_format(abs($total_diff), 2) : '—'; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="card-footer" style="font-size:0.82rem;color:var(--gray-600);">
        <i class="fas fa-arrow-right" style="color:var(--primary);"></i>
        Once all working days are submitted, Finance can process payroll using these salary expenses.
    </div>
</div>
<?php endif; ?>

<script>
// Highlight row yellow when days < 30
function highlightRow(input) {
    const val = parseInt(input.value);
    const row = input.closest('tr');
    if (val < 30 && val >= 0) {
        row.style.background = 'var(--warning-light)';
        input.style.borderColor = 'var(--warning)';
        input.style.fontWeight  = '700';
    } else {
        row.style.background = '';
        input.style.borderColor = '';
        input.style.fontWeight  = '';
    }
}

// Set all inputs to a given value
function setAllDays(days) {
    document.querySelectorAll('.wd-input').forEach(function(input) {
        input.value = days;
        highlightRow(input);
    });
}

// Sync year when month changes
document.querySelector('select[name="month"]')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('yearInput').value = opt.dataset.year || '<?= $f_year ?>';
});
</script>

<?php require_once $depth . 'includes/footer.php'; ?>

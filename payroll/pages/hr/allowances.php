<?php
$page_title = 'Manage Allowances';
$active_nav = 'allowances';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';

$pdo     = getDB();
$success = '';
$error   = '';

// ── Pre-select employee from query string ──────────────────
$selected_emp_id = trim($_GET['emp'] ?? '');

// ── Handle SAVE ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_allowances'])) {
    $emp_id         = trim($_POST['emp_id']            ?? '');
    $housing        = (float)($_POST['housing']        ?? 0);
    $transport      = (float)($_POST['transport']      ?? 0);
    $position_allow = (float)($_POST['position_allow'] ?? 0);
    $teaching       = (float)($_POST['teaching']       ?? 0);
    $other          = (float)($_POST['other']          ?? 0);
    $effective_from = trim($_POST['effective_from']    ?? date('Y-m-d'));

    if (empty($emp_id)) {
        $error = 'Please select an employee.';
    } else {
        try {
            // Close previous active allowance
            $pdo->prepare("
                UPDATE allowances
                SET    effective_to = CURDATE()
                WHERE  emp_id = ? AND effective_to IS NULL
            ")->execute([$emp_id]);

            // Insert new allowance record
            $pdo->prepare("
                INSERT INTO allowances
                    (emp_id, housing, transport, position_allowance,
                     teaching, other, effective_from, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $emp_id, $housing, $transport, $position_allow,
                $teaching, $other, $effective_from, $_SESSION['user_id']
            ]);

            // Audit log
            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Update Allowances', ?, ?, ?)
            ")->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                $emp_id,
                "Housing:{$housing} Transport:{$transport} Position:{$position_allow} Teaching:{$teaching}",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $selected_emp_id = $emp_id;
            $success = 'Allowances updated successfully.';
        } catch (PDOException $e) {
            $error = 'Save failed: ' . $e->getMessage();
        }
    }
}

// ── Load all active employees ──────────────────────────────
$employees = $pdo->query("
    SELECT e.emp_id, e.full_name, e.basic_salary, d.dept_name
    FROM   employees e
    JOIN   departments d ON e.dept_id = d.dept_id
    WHERE  e.status = 'active'
    ORDER  BY e.full_name
")->fetchAll();

// ── Load selected employee's current allowances ────────────
$current = null;
$sel_emp = null;
if ($selected_emp_id) {
    $sel_stmt = $pdo->prepare("
        SELECT e.emp_id, e.full_name, e.basic_salary, d.dept_name, e.position
        FROM   employees e
        JOIN   departments d ON e.dept_id = d.dept_id
        WHERE  e.emp_id = ?
    ");
    $sel_stmt->execute([$selected_emp_id]);
    $sel_emp = $sel_stmt->fetch();

    $cur_stmt = $pdo->prepare("
        SELECT * FROM allowances
        WHERE  emp_id = ? AND effective_to IS NULL
        ORDER  BY effective_from DESC LIMIT 1
    ");
    $cur_stmt->execute([$selected_emp_id]);
    $current = $cur_stmt->fetch();
}

// ── All employees allowances overview ─────────────────────
$overview = $pdo->query("
    SELECT e.emp_id, e.full_name, e.basic_salary,
           COALESCE(a.housing, 0)            AS housing,
           COALESCE(a.transport, 0)          AS transport,
           COALESCE(a.position_allowance, 0) AS position_allowance,
           COALESCE(a.teaching, 0)           AS teaching,
           COALESCE(a.other, 0)              AS other,
           COALESCE(a.housing + a.transport + a.position_allowance
                    + a.teaching + a.other, 0) AS total_allowances
    FROM   employees e
    LEFT JOIN allowances a ON e.emp_id = a.emp_id AND a.effective_to IS NULL
    WHERE  e.status = 'active'
    ORDER  BY e.full_name
    LIMIT  20
")->fetchAll();

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">HR</a><span>/</span><span>Manage Allowances</span>
</div>

<div class="page-header">
    <h1>Manage Employee Allowances</h1>
    <p>Define and update housing, transport, position, and other allowances for staff members.</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- ── Update Form ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-hand-holding-usd" style="color:var(--success);margin-right:8px"></i>
                Update Allowances
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">

                <!-- Employee selector -->
                <div class="form-group">
                    <label class="form-label">Select Employee <span style="color:var(--danger)">*</span></label>
                    <select name="emp_id" id="empSelect" class="form-control" required
                            onchange="this.form.action='allowances.php?emp='+this.value; this.form.submit();">
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
                <!-- Basic salary display -->
                <div style="background:var(--bg-light);border-radius:var(--radius);padding:14px;margin-bottom:18px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                        <div>
                            <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;">Employee</p>
                            <p style="font-weight:700;margin:0;"><?= htmlspecialchars($sel_emp['full_name']) ?></p>
                            <p style="font-size:0.8rem;color:var(--gray-600);margin:0;">
                                <?= htmlspecialchars($sel_emp['position']) ?> — <?= htmlspecialchars($sel_emp['dept_name']) ?>
                            </p>
                        </div>
                        <div style="text-align:right;">
                            <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;">Basic Salary</p>
                            <p style="font-size:1.2rem;font-weight:700;color:var(--primary);margin:0;">
                                ETB <?= number_format($sel_emp['basic_salary'], 2) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Allowance fields -->
                <div class="form-row">
                    <?php
                    $fields = [
                        ['housing',        'Housing Allowance'],
                        ['transport',      'Transport Allowance'],
                        ['position_allow', 'Position Allowance'],
                        ['teaching',       'Teaching Allowance'],
                        ['other',          'Other Allowance'],
                    ];
                    $db_keys = [
                        'housing'        => 'housing',
                        'transport'      => 'transport',
                        'position_allow' => 'position_allowance',
                        'teaching'       => 'teaching',
                        'other'          => 'other',
                    ];
                    foreach ($fields as [$fname, $flabel]):
                        $db_key  = $db_keys[$fname];
                        $cur_val = $current[$db_key] ?? 0;
                    ?>
                    <div class="form-group">
                        <label class="form-label"><?= $flabel ?> (ETB)</label>
                        <input type="number" name="<?= $fname ?>" id="<?= $fname ?>"
                               class="form-control allow-input"
                               value="<?= number_format($cur_val, 2, '.', '') ?>"
                               min="0" step="0.01">
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Effective From</label>
                    <input type="date" name="effective_from" class="form-control"
                           value="<?= date('Y-m-d') ?>">
                </div>

                <!-- Live gross preview -->
                <div style="background:var(--success-light);border-radius:var(--radius);
                     padding:14px;border-left:4px solid var(--success);margin-bottom:16px;">
                    <p style="font-size:0.78rem;color:var(--success);margin:0 0 4px;font-weight:700;">
                        ESTIMATED GROSS SALARY
                    </p>
                    <p id="grossAmt" style="font-size:1.3rem;font-weight:700;color:var(--success);margin:0;">
                        ETB <?= number_format($sel_emp['basic_salary'] + ($current ? array_sum(array_map(fn($k) => (float)$current[$k], ['housing','transport','position_allowance','teaching','other'])) : 0), 2) ?>
                    </p>
                    <p id="grossNote" style="font-size:0.75rem;color:var(--gray-600);margin:4px 0 0;">
                        Basic (<?= number_format($sel_emp['basic_salary'], 2) ?>) + Allowances
                    </p>
                </div>

                <button type="submit" name="save_allowances" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> Save Allowances
                </button>

                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <p>Select an employee above to manage their allowances.</p>
                </div>
                <?php endif; ?>

            </form>
        </div>
    </div>

    <!-- ── Overview Table ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list" style="color:var(--primary);margin-right:8px"></i>
                Current Allowances Overview
            </h3>
            <span class="badge badge-primary"><?= count($overview) ?> shown</span>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Housing</th>
                            <th>Transport</th>
                            <th>Position</th>
                            <th>Teaching</th>
                            <th>Total (ETB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($overview)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted" style="padding:24px;">
                                No allowance data yet.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($overview as $o): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($o['full_name']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($o['emp_id']) ?></small>
                            </td>
                            <td><?= number_format($o['housing'], 2) ?></td>
                            <td><?= number_format($o['transport'], 2) ?></td>
                            <td><?= number_format($o['position_allowance'], 2) ?></td>
                            <td><?= number_format($o['teaching'], 2) ?></td>
                            <td class="text-bold text-success">
                                <?= number_format($o['total_allowances'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <div class="alert alert-info" style="margin:0;font-size:0.82rem;">
                <i class="fas fa-info-circle"></i>
                Pension (7%) is on <strong>basic salary only</strong>.
                Tax is on <strong>Gross − Pension</strong>.
            </div>
        </div>
    </div>

</div>

<?php if ($sel_emp): ?>
<script>
const basic = <?= (float)$sel_emp['basic_salary'] ?>;

function updateGross() {
    let total = 0;
    document.querySelectorAll('.allow-input').forEach(i => total += parseFloat(i.value) || 0);
    const gross = basic + total;
    document.getElementById('grossAmt').textContent =
        'ETB ' + gross.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('grossNote').textContent =
        'Basic (' + basic.toLocaleString() + ') + Allowances (' +
        total.toLocaleString('en-US', {minimumFractionDigits:2}) + ')';
}
document.querySelectorAll('.allow-input').forEach(i => i.addEventListener('input', updateGross));
</script>
<?php endif; ?>

<?php require_once $depth . 'includes/footer.php'; ?>

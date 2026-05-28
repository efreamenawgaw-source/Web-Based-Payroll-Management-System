<?php
session_start();
$page_title = 'Employee Status';
$active_nav = 'status';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/notify.php';

$pdo     = getDB();
$success = '';
$error   = '';

// ── Pre-select employee from query string ──────────────────
$selected_emp_id = trim($_GET['emp'] ?? '');

// ── Handle STATUS UPDATE ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $emp_id         = trim($_POST['emp_id']         ?? '');
    $new_status     = trim($_POST['new_status']      ?? '');
    $effective_date = trim($_POST['effective_date']  ?? date('Y-m-d'));
    $reason         = trim($_POST['reason']          ?? '');

    $valid_statuses = ['active','on_leave','transferred','promoted','terminated'];

    if (empty($emp_id) || !in_array($new_status, $valid_statuses)) {
        $error = 'Please select an employee and a valid status.';
    } else {
        try {
            $pdo->beginTransaction();

            // Get current status for history
            $prev = $pdo->prepare("SELECT status, full_name FROM employees WHERE emp_id = ?");
            $prev->execute([$emp_id]);
            $prev_row = $prev->fetch();

            // Update employee status
            $pdo->prepare("
                UPDATE employees
                SET    status = ?
                WHERE  emp_id = ?
            ")->execute([$new_status, $emp_id]);

            // Insert status history
            $pdo->prepare("
                INSERT INTO employee_status_history
                    (emp_id, previous_status, new_status, effective_date, reason, changed_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $emp_id,
                $prev_row['status'],
                $new_status,
                $effective_date,
                $reason ?: null,
                $_SESSION['user_id']
            ]);

            // Audit log
            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Update Employee Status', ?, ?, ?)
            ")->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                $emp_id,
                "{$prev_row['status']} → {$new_status} | Reason: {$reason}",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $pdo->commit();
            $success = "Status of <strong>{$prev_row['full_name']}</strong> updated to
                        <strong>" . ucfirst(str_replace('_',' ',$new_status)) . "</strong>.";

            // Notify admin
            notify_role($pdo, 'admin',
                'Employee Status Changed',
                "{$prev_row['full_name']} ({$emp_id}): {$prev_row['status']} → {$new_status}",
                'info');

            // Notify finance if terminated (exclude from payroll)
            if ($new_status === 'terminated') {
                notify_role($pdo, 'finance',
                    'Employee Terminated',
                    "{$prev_row['full_name']} ({$emp_id}) has been terminated and will be excluded from future payroll.",
                    'warning');
            }

            // Notify the employee themselves if they have a user account
            $emp_user = $pdo->prepare("SELECT user_id FROM employees WHERE emp_id = ?");
            $emp_user->execute([$emp_id]);
            $eu = $emp_user->fetch();
            if ($eu) {
                $status_label = ucfirst(str_replace('_', ' ', $new_status));
                notify($pdo, $eu['user_id'],
                    'Employment Status Updated',
                    "Your employment status has been updated to: {$status_label}." .
                    ($reason ? " Reason: {$reason}" : ''),
                    $new_status === 'active' ? 'success' : 'warning');
            }

            $selected_emp_id = $emp_id;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

// ── Stats ──────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        SUM(CASE WHEN status = 'active'      THEN 1 ELSE 0 END) AS `active`,
        SUM(CASE WHEN status = 'on_leave'    THEN 1 ELSE 0 END) AS `on_leave`,
        SUM(CASE WHEN status = 'transferred' THEN 1 ELSE 0 END) AS `transferred`,
        SUM(CASE WHEN status = 'terminated'  THEN 1 ELSE 0 END) AS `terminated`,
        COUNT(*)                                                 AS `total`
    FROM employees
")->fetch();

// ── Active employees for dropdown ─────────────────────────
$employees = $pdo->query("
    SELECT e.emp_id, e.full_name, e.status, d.dept_name
    FROM   employees e
    JOIN   departments d ON e.dept_id = d.dept_id
    WHERE  e.status != 'terminated'
    ORDER  BY e.full_name
")->fetchAll();

// ── Selected employee details ──────────────────────────────
$sel_emp = null;
if ($selected_emp_id) {
    $s = $pdo->prepare("
        SELECT e.emp_id, e.full_name, e.status,
               d.dept_name, e.position
        FROM   employees e
        JOIN   departments d ON e.dept_id = d.dept_id
        WHERE  e.emp_id = ?
    ");
    $s->execute([$selected_emp_id]);
    $sel_emp = $s->fetch();
}

// ── Status history (last 10) ───────────────────────────────
$history = $pdo->query("
    SELECT h.emp_id, e.full_name,
           h.previous_status, h.new_status,
           h.effective_date, h.reason,
           u.full_name AS changed_by_name
    FROM   employee_status_history h
    JOIN   employees e ON h.emp_id = e.emp_id
    LEFT JOIN users u  ON h.changed_by = u.user_id
    ORDER  BY h.changed_at DESC
    LIMIT  10
")->fetchAll();

$status_badge = [
    'active'      => 'badge-success',
    'on_leave'    => 'badge-warning',
    'terminated'  => 'badge-danger',
    'transferred' => 'badge-info',
    'promoted'    => 'badge-primary',
];

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">HR</a><span>/</span><span>Employee Status</span>
</div>

<div class="page-header">
    <h1>Manage Employee Status</h1>
    <p>Update employment status — active, on leave, transferred, promoted, or terminated.</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<!-- ── Stats ── -->
<div class="stats-grid" style="margin-bottom:28px;">
    <?php
    $stat_rows = [
        ['Active',      $stats['active'],      'green', 'fas fa-user-check'],
        ['On Leave',    $stats['on_leave'],     'orange','fas fa-user-clock'],
        ['Transferred', $stats['transferred'],  'info',  'fas fa-exchange-alt'],
        ['Terminated',  $stats['terminated'],   'red',   'fas fa-user-times'],
    ];
    foreach ($stat_rows as [$label, $count, $color, $icon]):
        $pct = $stats['total'] > 0 ? round(($count / $stats['total']) * 100, 1) : 0;
    ?>
    <div class="stat-card">
        <div class="stat-icon <?= $color ?>"><i class="<?= $icon ?>"></i></div>
        <div class="stat-info">
            <p><?= $label ?></p>
            <h2><?= $count ?></h2>
            <span class="stat-change <?= $count > 0 ? 'up' : '' ?>"><?= $pct ?>% of staff</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid-2" style="gap:24px;">

    <!-- ── Update Form ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-toggle-on" style="color:var(--primary);margin-right:8px"></i>
                Update Employee Status
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">

                <!-- Employee selector -->
                <div class="form-group">
                    <label class="form-label">Select Employee <span style="color:var(--danger)">*</span></label>
                    <select name="emp_id" class="form-control" required
                            onchange="this.form.action='status.php?emp='+this.value; this.form.submit();">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= htmlspecialchars($e['emp_id']) ?>"
                            <?= $selected_emp_id === $e['emp_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['emp_id']) ?> — <?= htmlspecialchars($e['full_name']) ?>
                            (<?= ucfirst(str_replace('_',' ',$e['status'])) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($sel_emp): ?>
                <!-- Current status display -->
                <div class="form-group">
                    <label class="form-label">Current Status</label>
                    <div style="padding:10px 14px;background:var(--bg-light);border-radius:7px;
                                border:1.5px solid var(--gray-200);display:flex;align-items:center;gap:10px;">
                        <span class="badge <?= $status_badge[$sel_emp['status']] ?? 'badge-gray' ?>">
                            <?= ucfirst(str_replace('_',' ',$sel_emp['status'])) ?>
                        </span>
                        <span style="font-size:0.82rem;color:var(--gray-600);">
                            <?= htmlspecialchars($sel_emp['full_name']) ?> —
                            <?= htmlspecialchars($sel_emp['position']) ?>,
                            <?= htmlspecialchars($sel_emp['dept_name']) ?>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">New Status <span style="color:var(--danger)">*</span></label>
                    <select name="new_status" class="form-control" required>
                        <option value="">-- Select New Status --</option>
                        <?php
                        $status_options = [
                            'active'      => 'Active',
                            'on_leave'    => 'On Leave',
                            'transferred' => 'Transferred',
                            'promoted'    => 'Promoted',
                            'terminated'  => 'Terminated',
                        ];
                        foreach ($status_options as $val => $label):
                            if ($val === $sel_emp['status']) continue; // skip current
                        ?>
                        <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Effective Date</label>
                    <input type="date" name="effective_date" class="form-control"
                           value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Reason / Notes</label>
                    <textarea name="reason" class="form-control" rows="3"
                              placeholder="Reason for status change..."></textarea>
                </div>

                <button type="submit" name="update_status" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> Update Status
                </button>

                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-toggle-on"></i></div>
                    <p>Select an employee above to update their status.</p>
                </div>
                <?php endif; ?>

            </form>
        </div>
    </div>

    <!-- ── Status History ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>
                Recent Status Changes
            </h3>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Date</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:24px;">
                                No status changes recorded yet.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($h['full_name']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($h['emp_id']) ?></small>
                            </td>
                            <td>
                                <span class="badge <?= $status_badge[$h['previous_status']] ?? 'badge-gray' ?>">
                                    <?= ucfirst(str_replace('_',' ',$h['previous_status'] ?? 'N/A')) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $status_badge[$h['new_status']] ?? 'badge-gray' ?>">
                                    <?= ucfirst(str_replace('_',' ',$h['new_status'])) ?>
                                </span>
                            </td>
                            <td class="text-muted" style="white-space:nowrap;">
                                <?= htmlspecialchars($h['effective_date']) ?>
                            </td>
                            <td class="text-muted">
                                <?= htmlspecialchars($h['changed_by_name'] ?? 'System') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer" style="font-size:0.8rem;color:var(--gray-600);">
            <i class="fas fa-info-circle" style="color:var(--info);"></i>
            Only <strong>Active</strong> employees are included in payroll processing.
            Terminated employees are excluded from all future payroll cycles.
        </div>
    </div>

</div>

<?php require_once $depth . 'includes/footer.php'; ?>

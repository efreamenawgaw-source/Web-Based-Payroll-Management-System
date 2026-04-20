<?php
session_start();
$page_title = 'Assign Roles';
$active_nav = 'roles';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/notify.php';

$pdo     = getDB();
$success = '';
$error   = '';

// ── Pre-select user from query string ──────────────────────
$selected_uid = (int)($_GET['user'] ?? 0);

// ── Handle ROLE ASSIGNMENT ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_role'])) {
    $uid      = (int)($_POST['user_id']  ?? 0);
    $new_role = trim($_POST['new_role']  ?? '');
    $notes    = trim($_POST['notes']     ?? '');

    if (!$uid || !in_array($new_role, ['admin','hr','finance','employee'])) {
        $error = 'Please select a user and a valid role.';
    } else {
        // Get current role for audit
        $cur = $pdo->prepare("SELECT role, full_name, username FROM users WHERE user_id = ?");
        $cur->execute([$uid]);
        $cur_user = $cur->fetch();

        if (!$cur_user) {
            $error = 'User not found.';
        } elseif ($cur_user['role'] === $new_role) {
            $error = "User already has the role <strong>" . ucfirst($new_role) . "</strong>.";
        } else {
            try {
                $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?")
                    ->execute([$new_role, $uid]);

                // Audit log
                $pdo->prepare("
                    INSERT INTO audit_logs
                        (user_id, username, role, action, target, details, ip_address)
                    VALUES (?, ?, ?, 'Assign Role', ?, ?, ?)
                ")->execute([
                    $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                    $cur_user['username'],
                    "{$cur_user['role']} → {$new_role}" . ($notes ? " | Note: {$notes}" : ''),
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

                $success = "Role of <strong>{$cur_user['full_name']}</strong> changed from
                            <strong>" . ucfirst($cur_user['role']) . "</strong> to
                            <strong>" . ucfirst($new_role) . "</strong>.";

                // Notify the user whose role changed
                notify($pdo, $uid,
                    'Your Role Has Been Updated',
                    "Your system role has been changed from " . ucfirst($cur_user['role']) . " to " . ucfirst($new_role) . ". Please logout and login again to apply changes.",
                    'warning');

                // Notify all admins
                notify_role($pdo, 'admin',
                    'Role Assignment',
                    "Admin changed {$cur_user['full_name']}'s role: " . ucfirst($cur_user['role']) . " → " . ucfirst($new_role),
                    'info');
                $selected_uid = $uid;
            } catch (PDOException $e) {
                $error = 'Role update failed: ' . $e->getMessage();
            }
        }
    }
}

// ── Role stats ─────────────────────────────────────────────
$role_stats = $pdo->query("
    SELECT role, COUNT(*) AS cnt
    FROM   users
    WHERE  is_active = 1
    GROUP  BY role
")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── All active users for dropdown ─────────────────────────
$all_users = $pdo->query("
    SELECT user_id, username, full_name, role
    FROM   users
    WHERE  is_active = 1
    ORDER  BY full_name
")->fetchAll();

// ── Selected user details ──────────────────────────────────
$sel_user = null;
if ($selected_uid) {
    $su = $pdo->prepare("SELECT user_id, username, full_name, role FROM users WHERE user_id = ?");
    $su->execute([$selected_uid]);
    $sel_user = $su->fetch();
}

// ── Recent role changes from audit log ─────────────────────
$role_history = $pdo->query("
    SELECT a.target AS username_changed,
           a.details, a.logged_at,
           u.full_name AS changed_by
    FROM   audit_logs a
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE  a.action = 'Assign Role'
    ORDER  BY a.logged_at DESC
    LIMIT  8
")->fetchAll();

$role_badge = [
    'admin'    => 'badge-danger',
    'hr'       => 'badge-info',
    'finance'  => 'badge-warning',
    'employee' => 'badge-primary',
];

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">Admin</a><span>/</span><span>Assign Roles</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Assign Roles</h1>
        <p>Manage user roles and access permissions across the system.</p>
    </div>
    <a href="users.php" class="btn btn-secondary">
        <i class="fas fa-users"></i> Manage Users
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= $error ?></span></div>
<?php endif; ?>

<!-- Role Stats -->
<div class="stats-grid" style="margin-bottom:28px;">
    <?php
    $stat_defs = [
        ['admin',    'Administrators',  'red',    'fas fa-user-shield', 'Full system access'],
        ['hr',       'HR Personnel',    'info',   'fas fa-users',       'Write/Update access'],
        ['finance',  'Finance Officers','orange', 'fas fa-coins',       'Execute/Read access'],
        ['employee', 'Employees',       'blue',   'fas fa-id-badge',    'Read-only access'],
    ];
    foreach ($stat_defs as [$key, $label, $color, $icon, $desc]): ?>
    <div class="stat-card">
        <div class="stat-icon <?= $color ?>"><i class="<?= $icon ?>"></i></div>
        <div class="stat-info">
            <p><?= $label ?></p>
            <h2><?= $role_stats[$key] ?? 0 ?></h2>
            <span class="stat-change up"><?= $desc ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- ── Assign Role Form ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-tag" style="color:var(--primary);margin-right:8px"></i>
                Assign / Update Role
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">

                <div class="form-group">
                    <label class="form-label">Select User <span style="color:var(--danger)">*</span></label>
                    <select name="user_id" class="form-control" required
                            onchange="this.form.action='roles.php?user='+this.value; this.form.submit();">
                        <option value="">-- Select a User --</option>
                        <?php foreach ($all_users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"
                            <?= $selected_uid === (int)$u['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['full_name']) ?>
                            (<?= htmlspecialchars($u['username']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($sel_user): ?>
                <!-- Current role display -->
                <div class="form-group">
                    <label class="form-label">Current Role</label>
                    <div style="padding:10px 14px;background:var(--bg-light);border-radius:7px;
                                border:1.5px solid var(--gray-200);display:flex;align-items:center;gap:10px;">
                        <span class="badge <?= $role_badge[$sel_user['role']] ?? 'badge-gray' ?>">
                            <?= ucfirst($sel_user['role']) ?>
                        </span>
                        <span style="font-size:0.85rem;color:var(--gray-600);">
                            <?= htmlspecialchars($sel_user['full_name']) ?>
                            (<?= htmlspecialchars($sel_user['username']) ?>)
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Assign New Role <span style="color:var(--danger)">*</span></label>
                    <select name="new_role" class="form-control" required>
                        <option value="">-- Select Role --</option>
                        <?php foreach (['admin'=>'Administrator','hr'=>'HR Personnel','finance'=>'Finance Officer','employee'=>'Employee'] as $val => $lbl): ?>
                        <option value="<?= $val ?>"
                            <?= $sel_user['role'] === $val ? 'disabled style="color:var(--gray-400)"' : '' ?>>
                            <?= $lbl ?>
                            <?= $sel_user['role'] === $val ? '(current)' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason / Notes</label>
                    <textarea name="notes" class="form-control" rows="3"
                              placeholder="Optional: reason for role change..."></textarea>
                </div>

                <button type="submit" name="assign_role" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> Assign Role
                </button>

                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-user-tag"></i></div>
                    <p>Select a user above to assign or update their role.</p>
                </div>
                <?php endif; ?>

            </form>
        </div>
    </div>

    <!-- ── Permissions Matrix ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-shield-alt" style="color:var(--primary);margin-right:8px"></i>
                Role Permissions Matrix
            </h3>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Function</th>
                            <th style="text-align:center;">Admin</th>
                            <th style="text-align:center;">HR</th>
                            <th style="text-align:center;">Finance</th>
                            <th style="text-align:center;">Employee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $perms = [
                            ['Login / Logout',         true,  true,  true,  true],
                            ['Manage Users',           true,  false, false, false],
                            ['Assign Roles',           true,  false, false, false],
                            ['View Audit Log',         true,  false, false, false],
                            ['Register Employee',      false, true,  false, false],
                            ['Update Employee Info',   false, true,  false, false],
                            ['Manage Allowances',      false, true,  false, false],
                            ['Manage Employee Status', false, true,  false, false],
                            ['Process Payroll',        false, false, true,  false],
                            ['Verify Payroll',         false, false, true,  false],
                            ['Generate Payslips',      false, false, true,  false],
                            ['Generate Reports',       false, false, true,  false],
                            ['View Own Payslip',       false, false, false, true],
                            ['View Personal Info',     false, false, false, true],
                        ];
                        foreach ($perms as $p): ?>
                        <tr>
                            <td style="font-size:0.83rem;"><?= $p[0] ?></td>
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                            <td style="text-align:center;">
                                <i class="fas <?= $p[$i] ? 'fa-check-circle' : 'fa-times-circle' ?>"
                                   style="color:<?= $p[$i] ? 'var(--success)' : 'var(--gray-200)' ?>;"></i>
                            </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ── Recent Role Changes ── -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>
            Recent Role Changes
        </h3>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>User Changed</th>
                        <th>Change Details</th>
                        <th>Changed By</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($role_history)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted" style="padding:24px;">
                            No role changes recorded yet.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($role_history as $h): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($h['username_changed'] ?? '—') ?></strong></td>
                        <td style="font-size:0.82rem;"><?= htmlspecialchars($h['details'] ?? '—') ?></td>
                        <td class="text-muted"><?= htmlspecialchars($h['changed_by'] ?? 'System') ?></td>
                        <td class="text-muted" style="font-size:0.78rem;white-space:nowrap;">
                            <?= date('M d, Y H:i', strtotime($h['logged_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

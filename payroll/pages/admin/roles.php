<?php
$page_title = 'Assign Roles';
$active_nav = 'roles';
$depth      = '../../';

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_role'])) {
    $success = 'Role updated successfully for user: ' . htmlspecialchars($_POST['user_name'] ?? '');
}

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
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<!-- Role Overview Cards -->
<div class="stats-grid" style="margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-user-shield"></i></div>
        <div class="stat-info">
            <p>Administrators</p>
            <h2>5</h2>
            <span class="stat-change up">Full system access</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <p>HR Personnel</p>
            <h2>8</h2>
            <span class="stat-change up">Write/Update access</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-coins"></i></div>
        <div class="stat-info">
            <p>Finance Officers</p>
            <h2>12</h2>
            <span class="stat-change up">Execute/Read access</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-id-badge"></i></div>
        <div class="stat-info">
            <p>Employees</p>
            <h2>120</h2>
            <span class="stat-change up">Read-only access</span>
        </div>
    </div>
</div>

<div class="grid-2" style="gap:24px;">

    <!-- Assign Role Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-tag" style="color:var(--primary);margin-right:8px"></i>Assign / Update Role</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Select User <span style="color:var(--danger)">*</span></label>
                    <select name="user_id" class="form-control" required>
                        <option value="">-- Select a User --</option>
                        <option value="4">Admasu Dejene (admasu.d)</option>
                        <option value="5">Bekele Abebe (bekele.a)</option>
                        <option value="6">Chaltu Kebede (chaltu.k)</option>
                        <option value="7">Dawit Solomon (dawit.s)</option>
                        <option value="8">Eleni Tadesse (eleni.t)</option>
                    </select>
                </div>
                <input type="hidden" name="user_name" value="Admasu Dejene">
                <div class="form-group">
                    <label class="form-label">Current Role</label>
                    <input type="text" class="form-control" value="Employee" readonly
                        style="background:var(--gray-100);cursor:not-allowed;">
                </div>
                <div class="form-group">
                    <label class="form-label">Assign New Role <span style="color:var(--danger)">*</span></label>
                    <select name="new_role" class="form-control" required>
                        <option value="">-- Select Role --</option>
                        <option value="admin">Administrator</option>
                        <option value="hr">HR Personnel</option>
                        <option value="finance">Finance Officer</option>
                        <option value="employee">Employee</option>
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
            </form>
        </div>
    </div>

    <!-- Role Permissions Reference -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-shield-alt" style="color:var(--primary);margin-right:8px"></i>Role Permissions Reference</h3>
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
                                <?php if ($p[$i]): ?>
                                    <i class="fas fa-check-circle" style="color:var(--success);"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle" style="color:var(--gray-200);"></i>
                                <?php endif; ?>
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

<!-- Recent Role Changes -->
<div class="card mt-3">
    <div class="card-header">
        <h3><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>Recent Role Changes</h3>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>User</th><th>Previous Role</th><th>New Role</th><th>Changed By</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Bekele Abebe</strong></td>
                        <td><span class="badge badge-primary">Employee</span></td>
                        <td><span class="badge badge-info">HR Personnel</span></td>
                        <td>System Admin</td>
                        <td class="text-muted">2023-06-22</td>
                    </tr>
                    <tr>
                        <td><strong>Chaltu Kebede</strong></td>
                        <td><span class="badge badge-primary">Employee</span></td>
                        <td><span class="badge badge-warning">Finance Officer</span></td>
                        <td>System Admin</td>
                        <td class="text-muted">2023-06-21</td>
                    </tr>
                    <tr>
                        <td><strong>Dawit Solomon</strong></td>
                        <td><span class="badge badge-info">HR Personnel</span></td>
                        <td><span class="badge badge-primary">Employee</span></td>
                        <td>System Admin</td>
                        <td class="text-muted">2023-06-20</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

<?php
session_start();
$page_title = 'Administrator Dashboard';
$active_nav = 'dashboard';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/header.php';

$pdo = getDB();

// ── User stats ─────────────────────────────────────────────
$user_stats = $pdo->query("
    SELECT
        COUNT(*)                                                          AS `total`,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)                  AS `active`,
        SUM(CASE WHEN role = 'admin'    AND is_active=1 THEN 1 ELSE 0 END) AS `admins`,
        SUM(CASE WHEN role = 'hr'       AND is_active=1 THEN 1 ELSE 0 END) AS `hr`,
        SUM(CASE WHEN role = 'finance'  AND is_active=1 THEN 1 ELSE 0 END) AS `finance`,
        SUM(CASE WHEN role = 'employee' AND is_active=1 THEN 1 ELSE 0 END) AS `employees`,
        SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m')
                    = DATE_FORMAT(NOW(),'%Y-%m') THEN 1 ELSE 0 END)     AS `new_this_month`
    FROM users
")->fetch();

// ── Failed logins today ────────────────────────────────────
$failed_today = $pdo->query("
    SELECT COUNT(*) FROM audit_logs
    WHERE  status = 'failed'
    AND    action = 'Login'
    AND    DATE(logged_at) = CURDATE()
")->fetchColumn();

// ── Recent audit log (last 8) ──────────────────────────────
$recent_logs = $pdo->query("
    SELECT log_id, username, role, action, details, ip_address, status, logged_at
    FROM   audit_logs
    ORDER  BY logged_at DESC
    LIMIT  8
")->fetchAll();

// ── Role distribution ──────────────────────────────────────
$role_dist = $pdo->query("
    SELECT role,
           COUNT(*) AS cnt
    FROM   users
    WHERE  is_active = 1
    GROUP  BY role
    ORDER  BY cnt DESC
")->fetchAll(PDO::FETCH_KEY_PAIR);

$total_active = max((int)$user_stats['active'], 1);

$action_badge = [
    'Login'             => 'badge-primary',
    'Logout'            => 'badge-gray',
    'Login'             => 'badge-primary',
    'Register Employee' => 'badge-success',
    'Update Employee'   => 'badge-info',
    'Process Payroll'   => 'badge-info',
    'Verify Payroll'    => 'badge-info',
    'Assign Role'       => 'badge-warning',
    'Create User'       => 'badge-success',
    'Delete User'       => 'badge-danger',
    'Update Allowances' => 'badge-gray',
];
?>

<div class="breadcrumb">
    <i class="fas fa-home"></i><span>/</span>
    <span>Admin</span><span>/</span><span>Dashboard</span>
</div>

<div class="page-header">
    <h1>Administrator Dashboard</h1>
    <p>System overview — manage users, roles, and monitor activity.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <p>Total Users</p>
            <h2><?= $user_stats['total'] ?></h2>
            <span class="stat-change up">
                <i class="fas fa-arrow-up"></i> <?= $user_stats['new_this_month'] ?> this month
            </span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-shield"></i></div>
        <div class="stat-info">
            <p>Active Admins</p>
            <h2><?= $user_stats['admins'] ?></h2>
            <span class="stat-change up"><i class="fas fa-check-circle"></i> All active</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info">
            <p>Failed Logins Today</p>
            <h2><?= $failed_today ?></h2>
            <span class="stat-change <?= $failed_today > 0 ? 'down' : 'up' ?>">
                <?= $failed_today > 0 ? 'Needs review' : 'All clear' ?>
            </span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-id-badge"></i></div>
        <div class="stat-info">
            <p>Active Employees</p>
            <h2><?= $user_stats['employees'] ?></h2>
            <span class="stat-change up">Staff accounts</span>
        </div>
    </div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>
                Recent System Activity
            </h3>
            <a href="audit.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Role</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:24px;">
                                No activity recorded yet.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></strong></td>
                            <td>
                                <span class="badge <?= $action_badge[$log['action']] ?? 'badge-gray' ?>">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-gray">
                                    <?= htmlspecialchars(ucfirst($log['role'] ?? '—')) ?>
                                </span>
                            </td>
                            <td class="text-muted" style="font-size:0.78rem;white-space:nowrap;">
                                <?= date('M d, H:i', strtotime($log['logged_at'])) ?>
                            </td>
                            <td>
                                <span class="badge <?= $log['status'] === 'success' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= ucfirst($log['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Actions + Role Distribution -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt" style="color:var(--primary);margin-right:8px"></i>Quick Actions</h3>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:12px;">
                <a href="users.php?action=create" class="btn btn-primary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-user-plus"></i> Create New User Account
                </a>
                <a href="roles.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-user-tag"></i> Assign / Update Roles
                </a>
                <a href="audit.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-history"></i> View Full Audit Log
                </a>
                <a href="settings.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-cog"></i> System Settings
                </a>
            </div>

            <!-- Role Distribution -->
            <div style="margin-top:24px;">
                <h4 style="margin-bottom:14px;font-size:0.9rem;color:var(--gray-600);">
                    USER ROLE DISTRIBUTION
                </h4>
                <?php
                $role_labels = [
                    'employee' => ['Employees',       'var(--primary)'],
                    'finance'  => ['Finance Officers','var(--warning)'],
                    'hr'       => ['HR Personnel',    'var(--success)'],
                    'admin'    => ['Administrators',  'var(--danger)'],
                ];
                foreach ($role_labels as $key => [$label, $color]):
                    $count = $role_dist[$key] ?? 0;
                    $pct   = $total_active > 0 ? round(($count / $total_active) * 100) : 0;
                ?>
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;">
                        <span><?= $label ?></span>
                        <span style="font-weight:700;"><?= $count ?></span>
                    </div>
                    <div style="background:var(--gray-200);border-radius:20px;height:7px;">
                        <div style="width:<?= $pct ?>%;background:<?= $color ?>;height:7px;border-radius:20px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<?php require_once $depth . 'includes/footer.php'; ?>

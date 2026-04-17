<?php
$page_title = 'HR Dashboard';
$active_nav = 'dashboard';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/header.php';

$pdo = getDB();

// ── Stats from DB ──────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(*)                                                        AS total,
        SUM(CASE WHEN status = 'active'      THEN 1 ELSE 0 END)       AS active,
        SUM(CASE WHEN status = 'on_leave'    THEN 1 ELSE 0 END)       AS on_leave,
        SUM(CASE WHEN status = 'terminated'  THEN 1 ELSE 0 END)       AS terminated,
        SUM(CASE WHEN status = 'transferred' THEN 1 ELSE 0 END)       AS transferred,
        SUM(CASE WHEN DATE_FORMAT(employment_date,'%Y-%m')
                    = DATE_FORMAT(NOW(),'%Y-%m') THEN 1 ELSE 0 END)   AS new_this_month
    FROM employees
")->fetch();

// ── Recent employees (last 5 registered) ──────────────────
$recent = $pdo->query("
    SELECT e.emp_id, e.full_name, d.dept_name, e.position,
           e.basic_salary, e.status, e.created_at
    FROM   employees e
    JOIN   departments d ON e.dept_id = d.dept_id
    ORDER  BY e.created_at DESC
    LIMIT  5
")->fetchAll();

// ── Department breakdown ───────────────────────────────────
$depts = $pdo->query("
    SELECT d.dept_name,
           COUNT(e.emp_id) AS emp_count
    FROM   departments d
    LEFT JOIN employees e ON d.dept_id = e.dept_id AND e.status = 'active'
    GROUP  BY d.dept_id
    ORDER  BY emp_count DESC
    LIMIT  5
")->fetchAll();

$total_active = max((int)$stats['active'], 1);

$status_badge = [
    'active'      => 'badge-success',
    'on_leave'    => 'badge-warning',
    'terminated'  => 'badge-danger',
    'transferred' => 'badge-info',
    'promoted'    => 'badge-primary',
];
?>

<div class="breadcrumb">
    <i class="fas fa-home"></i><span>/</span>
    <span>HR</span><span>/</span><span>Dashboard</span>
</div>

<div class="page-header">
    <h1>Human Resources Dashboard</h1>
    <p>Manage employee records, allowances, and employment status.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <p>Total Employees</p>
            <h2><?= $stats['total'] ?></h2>
            <span class="stat-change up">
                <i class="fas fa-arrow-up"></i> <?= $stats['new_this_month'] ?> this month
            </span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
            <p>Active</p>
            <h2><?= $stats['active'] ?></h2>
            <span class="stat-change up">
                <?= $stats['total'] > 0
                    ? number_format(($stats['active'] / $stats['total']) * 100, 1)
                    : 0 ?>% active rate
            </span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-user-clock"></i></div>
        <div class="stat-info">
            <p>On Leave</p>
            <h2><?= $stats['on_leave'] ?></h2>
            <span class="stat-change down">Needs review</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-user-plus"></i></div>
        <div class="stat-info">
            <p>New This Month</p>
            <h2><?= $stats['new_this_month'] ?></h2>
            <span class="stat-change up">Registered</span>
        </div>
    </div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt" style="color:var(--primary);margin-right:8px"></i>Quick Actions</h3>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:12px;">
                <a href="register_employee.php" class="btn btn-primary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-user-plus"></i> Register New Employee
                </a>
                <a href="employees.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-id-card"></i> View All Employees
                </a>
                <a href="allowances.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-hand-holding-usd"></i> Manage Allowances
                </a>
                <a href="status.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-toggle-on"></i> Manage Employee Status
                </a>
            </div>

            <!-- Department Breakdown -->
            <div style="margin-top:24px;">
                <h4 style="margin-bottom:14px;font-size:0.9rem;color:var(--gray-600);">
                    ACTIVE STAFF BY DEPARTMENT
                </h4>
                <?php foreach ($depts as $d):
                    $pct = $total_active > 0
                         ? min(100, round(($d['emp_count'] / $total_active) * 100))
                         : 0;
                ?>
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;">
                        <span><?= htmlspecialchars($d['dept_name']) ?></span>
                        <span style="font-weight:700;"><?= $d['emp_count'] ?></span>
                    </div>
                    <div style="background:var(--gray-200);border-radius:20px;height:7px;">
                        <div style="width:<?= $pct ?>%;background:var(--primary);height:7px;border-radius:20px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Status Summary -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie" style="color:var(--primary);margin-right:8px"></i>Employment Status Summary</h3>
        </div>
        <div class="card-body">
            <?php
            $status_rows = [
                ['Active',      $stats['active'],      'var(--success)', 'fas fa-user-check'],
                ['On Leave',    $stats['on_leave'],     'var(--warning)', 'fas fa-user-clock'],
                ['Transferred', $stats['transferred'],  'var(--info)',    'fas fa-exchange-alt'],
                ['Terminated',  $stats['terminated'],   'var(--danger)',  'fas fa-user-times'],
            ];
            foreach ($status_rows as $sr):
                $pct = $stats['total'] > 0
                     ? round(($sr[1] / $stats['total']) * 100, 1)
                     : 0;
            ?>
            <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--gray-200);">
                <i class="<?= $sr[3] ?>" style="color:<?= $sr[2] ?>;font-size:1.2rem;width:20px;text-align:center;"></i>
                <div style="flex:1;">
                    <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px;">
                        <span style="font-weight:600;"><?= $sr[0] ?></span>
                        <span style="font-weight:700;color:<?= $sr[2] ?>;"><?= $sr[1] ?> (<?= $pct ?>%)</span>
                    </div>
                    <div style="background:var(--gray-200);border-radius:20px;height:6px;">
                        <div style="width:<?= $pct ?>%;background:<?= $sr[2] ?>;height:6px;border-radius:20px;"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Recently Registered -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-plus" style="color:var(--success);margin-right:8px"></i>Recently Registered Employees</h3>
        <a href="register_employee.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add New
        </a>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Emp ID</th><th>Full Name</th><th>Department</th>
                        <th>Position</th><th>Basic Salary (ETB)</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding:32px;">
                            <i class="fas fa-users" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:8px;"></i>
                            No employees registered yet.
                            <a href="register_employee.php">Register the first employee</a>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recent as $e): ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($e['emp_id']) ?></span></td>
                        <td><strong><?= htmlspecialchars($e['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($e['dept_name']) ?></td>
                        <td><?= htmlspecialchars($e['position']) ?></td>
                        <td class="text-bold">ETB <?= number_format($e['basic_salary'], 2) ?></td>
                        <td>
                            <span class="badge <?= $status_badge[$e['status']] ?? 'badge-gray' ?>">
                                <?= ucfirst(str_replace('_', ' ', $e['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <a href="employees.php?edit=<?= urlencode($e['emp_id']) ?>"
                               class="btn btn-secondary btn-sm btn-icon-only" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="allowances.php?emp=<?= urlencode($e['emp_id']) ?>"
                               class="btn btn-info btn-sm btn-icon-only" title="Allowances">
                                <i class="fas fa-hand-holding-usd"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <a href="employees.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-list"></i> View All Employees
        </a>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

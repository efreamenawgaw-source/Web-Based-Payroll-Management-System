<?php
session_start();
$page_title = 'Audit Log';
$active_nav = 'audit';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/header.php';

$pdo = getDB();

// ── Filters ───────────────────────────────────────────────
$search   = trim($_GET['search']  ?? '');
$f_action = trim($_GET['action']  ?? '');
$f_role   = trim($_GET['role']    ?? '');
$f_status = trim($_GET['status']  ?? '');
$f_date   = trim($_GET['date']    ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(a.username LIKE ? OR a.action LIKE ? OR a.details LIKE ? OR a.target LIKE ?)';
    $like     = "%{$search}%";
    $params   = array_merge($params, [$like, $like, $like, $like]);
}
if ($f_action) {
    $where[]  = 'a.action = ?';
    $params[] = $f_action;
}
if ($f_role) {
    $where[]  = 'a.role = ?';
    $params[] = $f_role;
}
if ($f_status) {
    $where[]  = 'a.status = ?';
    $params[] = $f_status;
}
if ($f_date) {
    $where[]  = 'DATE(a.logged_at) = ?';
    $params[] = $f_date;
}

$where_sql = implode(' AND ', $where);

// Total count
$cnt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a WHERE {$where_sql}");
$cnt->execute($params);
$total_rows  = (int)$cnt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Fetch logs
$stmt = $pdo->prepare("
    SELECT a.log_id, a.username, a.role, a.action,
           a.target, a.details, a.ip_address, a.status, a.logged_at
    FROM   audit_logs a
    WHERE  {$where_sql}
    ORDER  BY a.logged_at DESC
    LIMIT  {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct actions for filter dropdown
$actions = $pdo->query("
    SELECT DISTINCT action FROM audit_logs ORDER BY action
")->fetchAll(PDO::FETCH_COLUMN);

// Today's summary stats
$today_stats = $pdo->query("
    SELECT
        COUNT(*)                                                          AS `total`,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END)             AS `success`,
        SUM(CASE WHEN status = 'failed'  THEN 1 ELSE 0 END)             AS `failed`,
        SUM(CASE WHEN action = 'Login' AND status='success' THEN 1 ELSE 0 END) AS `logins`
    FROM audit_logs
    WHERE DATE(logged_at) = CURDATE()
")->fetch();

$action_badge = [
    'Login'                  => 'badge-primary',
    'Logout'                 => 'badge-gray',
    'Register Employee'      => 'badge-success',
    'Update Employee'        => 'badge-info',
    'Update Allowances'      => 'badge-info',
    'Update Employee Status' => 'badge-warning',
    'Process Payroll'        => 'badge-info',
    'Verify Payroll'         => 'badge-info',
    'Generate Payslip'       => 'badge-success',
    'Assign Role'            => 'badge-warning',
    'Create User'            => 'badge-success',
    'Update User'            => 'badge-info',
    'Deactivate User'        => 'badge-danger',
    'Terminate Employee'     => 'badge-danger',
];
?>

<div class="breadcrumb">
    <a href="dashboard.php">Admin</a><span>/</span><span>Audit Log</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Audit Log</h1>
        <p>Track all system actions for accountability and security monitoring.</p>
    </div>
    <button class="btn btn-secondary" onclick="window.print()">
        <i class="fas fa-print"></i> Print Log
    </button>
</div>

<!-- Today's Stats -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-list-alt"></i></div>
        <div class="stat-info">
            <p>Total Actions Today</p>
            <h2><?= $today_stats['total'] ?></h2>
            <span class="stat-change up">All recorded</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-sign-in-alt"></i></div>
        <div class="stat-info">
            <p>Successful Logins Today</p>
            <h2><?= $today_stats['logins'] ?></h2>
            <span class="stat-change up">Active sessions</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <p>Successful Actions</p>
            <h2><?= $today_stats['success'] ?></h2>
            <span class="stat-change up">Today</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
        <div class="stat-info">
            <p>Failed Attempts</p>
            <h2><?= $today_stats['failed'] ?></h2>
            <span class="stat-change <?= $today_stats['failed'] > 0 ? 'down' : 'up' ?>">
                <?= $today_stats['failed'] > 0 ? 'Needs review' : 'All clear' ?>
            </span>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="">
            <div class="filter-bar">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="form-control"
                           placeholder="Search user, action, or details..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="action" class="form-control" style="width:auto;">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>"
                        <?= $f_action === $act ? 'selected' : '' ?>>
                        <?= htmlspecialchars($act) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="role" class="form-control" style="width:auto;">
                    <option value="">All Roles</option>
                    <option value="admin"    <?= $f_role === 'admin'    ? 'selected' : '' ?>>Admin</option>
                    <option value="hr"       <?= $f_role === 'hr'       ? 'selected' : '' ?>>HR</option>
                    <option value="finance"  <?= $f_role === 'finance'  ? 'selected' : '' ?>>Finance</option>
                    <option value="employee" <?= $f_role === 'employee' ? 'selected' : '' ?>>Employee</option>
                </select>
                <select name="status" class="form-control" style="width:auto;">
                    <option value="">All Status</option>
                    <option value="success" <?= $f_status === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="failed"  <?= $f_status === 'failed'  ? 'selected' : '' ?>>Failed</option>
                </select>
                <input type="date" name="date" class="form-control" style="width:auto;"
                       value="<?= htmlspecialchars($f_date) ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if ($search || $f_action || $f_role || $f_status || $f_date): ?>
                <a href="audit.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Log Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>
            System Activity Log
        </h3>
        <span class="badge badge-primary"><?= $total_rows ?> entries</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted" style="padding:32px;">
                            No log entries found.
                            <?php if ($search || $f_action || $f_role || $f_status || $f_date): ?>
                            <a href="audit.php">Clear filters</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-muted" style="font-size:0.78rem;"><?= $log['log_id'] ?></td>
                        <td style="font-size:0.78rem;white-space:nowrap;" class="text-muted">
                            <?= date('M d, Y', strtotime($log['logged_at'])) ?><br>
                            <span style="color:var(--gray-600);">
                                <?= date('H:i:s', strtotime($log['logged_at'])) ?>
                            </span>
                        </td>
                        <td><strong><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></strong></td>
                        <td>
                            <span class="badge badge-gray">
                                <?= ucfirst(htmlspecialchars($log['role'] ?? '—')) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $action_badge[$log['action']] ?? 'badge-gray' ?>">
                                <?= htmlspecialchars($log['action']) ?>
                            </span>
                        </td>
                        <td style="font-size:0.8rem;" class="text-muted">
                            <?= htmlspecialchars($log['target'] ?? '—') ?>
                        </td>
                        <td style="font-size:0.8rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            title="<?= htmlspecialchars($log['details'] ?? '') ?>">
                            <?= htmlspecialchars($log['details'] ?? '—') ?>
                        </td>
                        <td style="font-size:0.78rem;" class="text-muted">
                            <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
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
    <div class="card-footer d-flex justify-between align-center" style="flex-wrap:wrap;gap:8px;">
        <span class="text-muted" style="font-size:0.8rem;">
            Showing <?= min($offset+1,$total_rows) ?>–<?= min($offset+$per_page,$total_rows) ?>
            of <?= $total_rows ?> entries
        </span>
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php $qs = http_build_query(array_filter([
                'search'=>$search,'action'=>$f_action,
                'role'=>$f_role,'status'=>$f_status,'date'=>$f_date
            ])); ?>
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&<?= $qs ?>">&laquo;</a>
            <?php endif; ?>
            <?php for ($p = max(1,$page-2); $p <= min($total_pages,$page+2); $p++): ?>
            <a href="?page=<?= $p ?>&<?= $qs ?>"
               class="<?= $p===$page?'active':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&<?= $qs ?>">&raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

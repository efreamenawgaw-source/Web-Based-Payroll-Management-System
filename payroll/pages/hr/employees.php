<?php
session_start();
$page_title = 'All Employees';
$active_nav = 'employees';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';

$pdo     = getDB();
$success = '';
$error   = '';

// ── Load departments for filter & edit form ────────────────
$departments = $pdo->query("
    SELECT dept_id, dept_name FROM departments
    WHERE  is_active = 1 ORDER BY dept_name
")->fetchAll();

// ── Handle UPDATE ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    $emp_id       = trim($_POST['emp_id']       ?? '');
    $full_name    = trim($_POST['full_name']     ?? '');
    $dept_id      = (int)($_POST['dept_id']      ?? 0);
    $position     = trim($_POST['position']      ?? '');
    $basic_salary = (float)($_POST['basic_salary'] ?? 0);
    $emp_type     = trim($_POST['emp_type']      ?? 'permanent');
    $email        = trim($_POST['email']         ?? '') ?: null;
    $phone        = trim($_POST['phone']         ?? '');

    if ($emp_id && $full_name && $dept_id && $position && $basic_salary > 0) {
        try {
            $stmt = $pdo->prepare("
                UPDATE employees
                SET    full_name = ?, dept_id = ?, position = ?,
                       basic_salary = ?, employment_type = ?,
                       email = ?, phone = ?
                WHERE  emp_id = ?
            ");
            $stmt->execute([
                $full_name, $dept_id, $position,
                $basic_salary, $emp_type,
                $email, $phone, $emp_id
            ]);

            // Audit log
            $log = $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Update Employee', ?, ?, ?)
            ");
            $log->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                $emp_id, "Updated: {$full_name}", $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $success = "Employee <strong>{$full_name}</strong> updated successfully.";
        } catch (PDOException $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// ── Handle DELETE ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    $del_id = trim($_POST['del_emp_id'] ?? '');
    if ($del_id) {
        try {
            // Soft-delete: set status to terminated
            $pdo->prepare("UPDATE employees SET status = 'terminated' WHERE emp_id = ?")
                ->execute([$del_id]);

            $log = $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, ip_address)
                VALUES (?, ?, ?, 'Terminate Employee', ?, ?)
            ");
            $log->execute([
                $_SESSION['user_id'], $_SESSION['username'],
                $_SESSION['role'], $del_id, $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            $success = "Employee <strong>{$del_id}</strong> has been terminated.";
        } catch (PDOException $e) {
            $error = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// ── Filters ───────────────────────────────────────────────
$search    = trim($_GET['search']  ?? '');
$f_dept    = (int)($_GET['dept']   ?? 0);
$f_status  = trim($_GET['status']  ?? '');
$f_type    = trim($_GET['type']    ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 15;
$offset    = ($page - 1) * $per_page;

// Build WHERE
$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(e.emp_id LIKE ? OR e.full_name LIKE ? OR e.position LIKE ?)';
    $like     = "%{$search}%";
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($f_dept) {
    $where[]  = 'e.dept_id = ?';
    $params[] = $f_dept;
}
if ($f_status) {
    $where[]  = 'e.status = ?';
    $params[] = $f_status;
}
if ($f_type) {
    $where[]  = 'e.employment_type = ?';
    $params[] = $f_type;
}

$where_sql = implode(' AND ', $where);

// Total count
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM employees e WHERE {$where_sql}
");
$count_stmt->execute($params);
$total_rows  = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Fetch employees
$stmt = $pdo->prepare("
    SELECT e.emp_id, e.full_name, e.gender, e.phone, e.email,
           e.position, e.employment_type, e.basic_salary,
           e.status, e.employment_date,
           d.dept_name, d.dept_id
    FROM   employees e
    JOIN   departments d ON e.dept_id = d.dept_id
    WHERE  {$where_sql}
    ORDER  BY e.created_at DESC
    LIMIT  {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Fetch employee for edit modal if ?edit= is set
$edit_emp = null;
if (!empty($_GET['edit'])) {
    $edit_stmt = $pdo->prepare("
        SELECT e.*, d.dept_id FROM employees e
        JOIN departments d ON e.dept_id = d.dept_id
        WHERE e.emp_id = ?
    ");
    $edit_stmt->execute([trim($_GET['edit'])]);
    $edit_emp = $edit_stmt->fetch();
}

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
    <a href="dashboard.php">HR</a><span>/</span><span>All Employees</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Employee Records</h1>
        <p>View, search, and manage all registered staff members.</p>
    </div>
    <a href="register_employee.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Register New
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<!-- ── Filter Bar ── -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="">
            <div class="filter-bar">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="form-control"
                           placeholder="Search by name, ID, or position..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="dept" class="form-control" style="width:auto;">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['dept_id'] ?>"
                        <?= $f_dept == $d['dept_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['dept_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-control" style="width:auto;">
                    <option value="">All Status</option>
                    <option value="active"      <?= $f_status === 'active'      ? 'selected' : '' ?>>Active</option>
                    <option value="on_leave"    <?= $f_status === 'on_leave'    ? 'selected' : '' ?>>On Leave</option>
                    <option value="transferred" <?= $f_status === 'transferred' ? 'selected' : '' ?>>Transferred</option>
                    <option value="promoted"    <?= $f_status === 'promoted'    ? 'selected' : '' ?>>Promoted</option>
                    <option value="terminated"  <?= $f_status === 'terminated'  ? 'selected' : '' ?>>Terminated</option>
                </select>
                <select name="type" class="form-control" style="width:auto;">
                    <option value="">All Types</option>
                    <option value="permanent" <?= $f_type === 'permanent' ? 'selected' : '' ?>>Permanent</option>
                    <option value="contract"  <?= $f_type === 'contract'  ? 'selected' : '' ?>>Contract</option>
                    <option value="part_time" <?= $f_type === 'part_time' ? 'selected' : '' ?>>Part-Time</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if ($search || $f_dept || $f_status || $f_type): ?>
                <a href="employees.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ── Employee Table ── -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-id-card" style="color:var(--primary);margin-right:8px"></i>
            All Staff Members
        </h3>
        <span class="badge badge-primary"><?= $total_rows ?> Total</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Emp ID</th>
                        <th>Full Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Basic Salary (ETB)</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding:40px;">
                            <i class="fas fa-search" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:8px;"></i>
                            No employees found.
                            <?php if ($search || $f_dept || $f_status): ?>
                                <a href="employees.php">Clear filters</a>
                            <?php else: ?>
                                <a href="register_employee.php">Register the first employee</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($employees as $e): ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($e['emp_id']) ?></span></td>
                        <td>
                            <strong><?= htmlspecialchars($e['full_name']) ?></strong>
                            <?php if ($e['email']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($e['email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($e['dept_name']) ?></td>
                        <td><?= htmlspecialchars($e['position']) ?></td>
                        <td class="text-bold">ETB <?= number_format($e['basic_salary'], 2) ?></td>
                        <td>
                            <span class="badge badge-gray">
                                <?= ucfirst(str_replace('_', '-', $e['employment_type'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $status_badge[$e['status']] ?? 'badge-gray' ?>">
                                <?= ucfirst(str_replace('_', ' ', $e['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <a href="employees.php?edit=<?= urlencode($e['emp_id']) ?><?= $search ? '&search='.urlencode($search) : '' ?>"
                               class="btn btn-secondary btn-sm btn-icon-only" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="allowances.php?emp=<?= urlencode($e['emp_id']) ?>"
                               class="btn btn-info btn-sm btn-icon-only" title="Manage Allowances">
                                <i class="fas fa-hand-holding-usd"></i>
                            </a>
                            <a href="status.php?emp=<?= urlencode($e['emp_id']) ?>"
                               class="btn btn-warning btn-sm btn-icon-only" title="Change Status">
                                <i class="fas fa-toggle-on"></i>
                            </a>
                            <!-- Terminate (soft delete) -->
                            <?php if ($e['status'] !== 'terminated'): ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Terminate <?= htmlspecialchars(addslashes($e['full_name'])) ?>? This cannot be undone easily.')">
                                <input type="hidden" name="del_emp_id" value="<?= htmlspecialchars($e['emp_id']) ?>">
                                <button type="submit" name="delete_employee"
                                        class="btn btn-danger btn-sm btn-icon-only" title="Terminate">
                                    <i class="fas fa-user-times"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="card-footer d-flex justify-between align-center" style="flex-wrap:wrap;gap:8px;">
        <span class="text-muted" style="font-size:0.8rem;">
            Showing <?= min($offset + 1, $total_rows) ?>–<?= min($offset + $per_page, $total_rows) ?>
            of <?= $total_rows ?> employees
        </span>
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $qs = http_build_query(array_filter([
                'search' => $search, 'dept' => $f_dept ?: '',
                'status' => $f_status, 'type' => $f_type
            ]));
            ?>
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&<?= $qs ?>">&laquo;</a>
            <?php endif; ?>
            <?php for ($p = max(1,$page-2); $p <= min($total_pages,$page+2); $p++): ?>
            <a href="?page=<?= $p ?>&<?= $qs ?>"
               class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&<?= $qs ?>">&raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Edit Employee Modal ── -->
<?php if ($edit_emp): ?>
<div class="modal-overlay active" id="editModal">
    <div class="modal" style="max-width:640px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);margin-right:8px"></i>
                Update Employee — <?= htmlspecialchars($edit_emp['emp_id']) ?>
            </h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" action="employees.php<?= $search ? '?search='.urlencode($search) : '' ?>">
            <input type="hidden" name="emp_id" value="<?= htmlspecialchars($edit_emp['emp_id']) ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control"
                               value="<?= htmlspecialchars($edit_emp['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Employee ID</label>
                        <input type="text" class="form-control"
                               value="<?= htmlspecialchars($edit_emp['emp_id']) ?>"
                               readonly style="background:var(--gray-100);">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($edit_emp['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control"
                               value="<?= htmlspecialchars($edit_emp['phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Department *</label>
                        <select name="dept_id" class="form-control" required>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['dept_id'] ?>"
                                <?= $edit_emp['dept_id'] == $d['dept_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['dept_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position *</label>
                        <select name="position" class="form-control" required>
                            <?php
                            $positions = [
                                'Professor','Associate Professor','Senior Lecturer','Lecturer',
                                'Assistant Lecturer','Administrative Officer','HR Officer',
                                'Finance Officer','Technician','Librarian','Security Staff',
                                'IT Officer','Cleaner','Driver',
                            ];
                            foreach ($positions as $p): ?>
                            <option value="<?= $p ?>"
                                <?= $edit_emp['position'] === $p ? 'selected' : '' ?>>
                                <?= $p ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Basic Salary (ETB) *</label>
                        <input type="number" name="basic_salary" class="form-control"
                               value="<?= $edit_emp['basic_salary'] ?>"
                               min="1" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Employment Type</label>
                        <select name="emp_type" class="form-control">
                            <option value="permanent" <?= $edit_emp['employment_type'] === 'permanent' ? 'selected' : '' ?>>Permanent</option>
                            <option value="contract"  <?= $edit_emp['employment_type'] === 'contract'  ? 'selected' : '' ?>>Contract</option>
                            <option value="part_time" <?= $edit_emp['employment_type'] === 'part_time' ? 'selected' : '' ?>>Part-Time</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="employees.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="update_employee" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once $depth . 'includes/footer.php'; ?>

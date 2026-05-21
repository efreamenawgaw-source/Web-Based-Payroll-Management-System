<?php
session_start();
$page_title = 'Manage Users';
$active_nav = 'users';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/notify.php';
require_once $depth . 'includes/mailer.php';

$pdo     = getDB();
$success = '';
$error   = '';

// ── Allowed positions & types (whitelist) ─────────────────
$VALID_ROLES      = ['admin','hr','finance','employee'];
$VALID_EMP_TYPES  = ['permanent','contract','part_time'];
$VALID_STATUSES   = ['active','on_leave'];
$VALID_POSITIONS  = [
    'Professor','Associate Professor','Senior Lecturer','Lecturer',
    'Assistant Lecturer','Administrative Officer','HR Officer',
    'Finance Officer','Technician','Librarian','Security Staff',
    'IT Officer','Cleaner','Driver',
];

// ── CREATE user ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = strtolower(trim($_POST['username'] ?? ''));
    $password  = $_POST['password'] ?? '';          // do NOT trim passwords
    $role      = trim($_POST['role']      ?? '');
    $email     = trim($_POST['email']     ?? '') ?: null;

    $errs = [];

    // Full name
    if (empty($full_name))
        $errs[] = 'Full name is required.';
    elseif (strlen($full_name) < 2 || strlen($full_name) > 100)
        $errs[] = 'Full name must be between 2 and 100 characters.';
    elseif (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $full_name))
        $errs[] = 'Full name may only contain letters, spaces, hyphens, apostrophes, and dots.';

    // Username
    if (empty($username))
        $errs[] = 'Username is required.';
    elseif (strlen($username) < 3 || strlen($username) > 30)
        $errs[] = 'Username must be between 3 and 30 characters.';
    elseif (!preg_match('/^[a-z0-9_\.]+$/', $username))
        $errs[] = 'Username may only contain lowercase letters, numbers, underscores, and dots.';

    // Password
    if (strlen($password) < 8)
        $errs[] = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/', $password))
        $errs[] = 'Password must contain at least one uppercase letter.';
    elseif (!preg_match('/[0-9]/', $password))
        $errs[] = 'Password must contain at least one number.';

    // Role
    if (!in_array($role, $VALID_ROLES, true))
        $errs[] = 'Please select a valid role.';

    // Email (optional but must be valid if provided)
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errs[] = 'Please enter a valid email address.';
    elseif ($email !== null && strlen($email) > 180)
        $errs[] = 'Email address is too long (max 180 characters).';

    if (empty($errs)) {
        // Check duplicate username
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch())
            $errs[] = 'Username <strong>' . htmlspecialchars($username) . '</strong> already exists.';

        // Check duplicate email
        if ($email) {
            $echk = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $echk->execute([$email]);
            if ($echk->fetch())
                $errs[] = 'Email <strong>' . htmlspecialchars($email) . '</strong> is already registered.';
        }
    }

    if (empty($errs)) {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("
                INSERT INTO users (username, password, role, full_name, email)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$username, $hash, $role, $full_name, $email]);

            $new_id = $pdo->lastInsertId();

            // Audit log
            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Create User', ?, ?, ?)
            ")->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                $username, "Created: {$full_name} | Role: {$role}",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $success = "User <strong>" . htmlspecialchars($full_name) . "</strong> ("
                     . htmlspecialchars($username) . ") created successfully.";

            // Store credentials to show on screen (admin can give to user manually)
            $_SESSION['new_user_credentials'] = [
                'full_name' => $full_name,
                'username'  => $username,
                'password'  => $password,
                'role'      => ucfirst($role),
                'email'     => $email,
            ];

            // Try to send welcome email — but don't block if it fails
            if ($email) {
                try {
                    $login_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                               . '://' . $_SERVER['HTTP_HOST']
                               . dirname(dirname($_SERVER['SCRIPT_NAME']))
                               . '/auth/login.php';

                    $html = buildWelcomeEmail($full_name, $username, $password, $role, $login_url);
                    $mail_result = sendMail($email, $full_name,
                        'Welcome to BiT Payroll System — Your Account Details',
                        $html);

                    if ($mail_result['success']) {
                        $success .= ' <span style="color:var(--success);">✉️ Welcome email sent to '
                                  . htmlspecialchars($email) . '</span>';
                        // Email sent — no need to show credentials on screen
                        unset($_SESSION['new_user_credentials']);
                    }
                    // If email fails, credentials stay in session to show on screen
                } catch (Exception $e) {
                    // Email failed silently — credentials will show on screen
                }
            }

            // Notify the new user (in-app)
            notify($pdo, (int)$new_id,
                'Welcome to BiT Payroll System',
                "Your account has been created. Username: {$username} | Role: " . ucfirst($role) . ". Login to get started.",
                'success');

            // Notify admin (other admins)
            notify_role($pdo, 'admin',
                'New User Created',
                "Admin created new user: {$full_name} ({$username}) with role: " . ucfirst($role),
                'info');
        } catch (PDOException $e) {
            $error = 'Create failed: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errs);
    }
}

// ── UPDATE user ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $uid       = (int)($_POST['user_id']   ?? 0);
    $full_name = trim($_POST['full_name']  ?? '');
    $role      = trim($_POST['role']       ?? '');
    $email     = trim($_POST['email']      ?? '') ?: null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_pass  = $_POST['new_password'] ?? '';   // do NOT trim passwords

    $errs = [];

    if (empty($full_name))
        $errs[] = 'Full name is required.';
    elseif (strlen($full_name) < 2 || strlen($full_name) > 100)
        $errs[] = 'Full name must be between 2 and 100 characters.';

    if (!in_array($role, $VALID_ROLES, true))
        $errs[] = 'Please select a valid role.';

    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errs[] = 'Please enter a valid email address.';

    if (!empty($new_pass)) {
        if (strlen($new_pass) < 8)
            $errs[] = 'New password must be at least 8 characters.';
        elseif (!preg_match('/[A-Z]/', $new_pass))
            $errs[] = 'New password must contain at least one uppercase letter.';
        elseif (!preg_match('/[0-9]/', $new_pass))
            $errs[] = 'New password must contain at least one number.';
    }

    if (!$uid)
        $errs[] = 'Invalid user.';

    if (empty($errs)) {
        try {
            if (!empty($new_pass)) {
                $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("
                    UPDATE users SET full_name=?, role=?, email=?, is_active=?, password=?
                    WHERE user_id=?
                ")->execute([$full_name, $role, $email, $is_active, $hash, $uid]);
            } else {
                $pdo->prepare("
                    UPDATE users SET full_name=?, role=?, email=?, is_active=?
                    WHERE user_id=?
                ")->execute([$full_name, $role, $email, $is_active, $uid]);
            }

            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Update User', ?, ?, ?)
            ")->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                "user_id:{$uid}", "Updated: {$full_name} | Role: {$role}",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $success = "User <strong>{$full_name}</strong> updated successfully.";

            // Send password reset email if password was changed
            if ($new_pass && strlen($new_pass) >= 6 && $email) {
                $login_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                           . '://' . $_SERVER['HTTP_HOST']
                           . dirname(dirname($_SERVER['SCRIPT_NAME']))
                           . '/auth/login.php';

                // Get username for the email
                $uname_stmt = $pdo->prepare("SELECT username FROM users WHERE user_id=?");
                $uname_stmt->execute([$uid]);
                $uname_row = $uname_stmt->fetch();

                $html = buildPasswordResetEmail($full_name, $uname_row['username'] ?? '', $new_pass, $login_url);
                $mail_result = sendMail($email, $full_name,
                    'BiT Payroll System — Your Password Has Been Reset',
                    $html);

                if ($mail_result['success']) {
                    $success .= ' <span style="color:var(--success);">✉️ Password reset email sent.</span>';
                } else {
                    $success .= ' <span style="color:var(--warning);">⚠️ Email not sent: ' . htmlspecialchars($mail_result['error']) . '</span>';
                }
            }
        } catch (PDOException $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errs);
    }
}

// ── LINK employee to user ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_employee'])) {
    $uid    = (int)($_POST['link_user_id'] ?? 0);
    $emp_id = trim($_POST['link_emp_id']   ?? '');

    if ($uid && $emp_id) {
        try {
            // Remove any existing link for this employee
            $pdo->prepare("UPDATE employees SET user_id = NULL WHERE user_id = ?")
                ->execute([$uid]);
            // Set new link
            $pdo->prepare("UPDATE employees SET user_id = ? WHERE emp_id = ?")
                ->execute([$uid, $emp_id]);

            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Link Employee', ?, ?, ?)
            ")->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                "user_id:{$uid}", "Linked to emp_id:{$emp_id}",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            $success = "User account linked to employee <strong>{$emp_id}</strong> successfully.";
        } catch (PDOException $e) {
            $error = 'Link failed: ' . $e->getMessage();
        }
    }
}
// ── DEACTIVATE user (soft disable) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_user'])) {
    $uid = (int)($_POST['del_user_id'] ?? 0);
    if ($uid && $uid !== (int)$_SESSION['user_id']) {
        $pdo->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?")
            ->execute([$uid]);
        $pdo->prepare("
            INSERT INTO audit_logs (user_id, username, role, action, target, ip_address)
            VALUES (?, ?, ?, 'Deactivate User', ?, ?)
        ")->execute([
            $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
            "user_id:{$uid}", $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        $success = 'User account <strong>deactivated</strong>. They can no longer login.';
    } else {
        $error = 'Cannot deactivate your own account.';
    }
}

// ── REACTIVATE user ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_user'])) {
    $uid = (int)($_POST['reactivate_user_id'] ?? 0);
    if ($uid) {
        $pdo->prepare("UPDATE users SET is_active = 1 WHERE user_id = ?")
            ->execute([$uid]);
        $pdo->prepare("
            INSERT INTO audit_logs (user_id, username, role, action, target, ip_address)
            VALUES (?, ?, ?, 'Reactivate User', ?, ?)
        ")->execute([
            $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
            "user_id:{$uid}", $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        $success = 'User account <strong>reactivated</strong>. They can now login.';
    }
}

// ── PERMANENTLY DELETE user ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $uid = (int)($_POST['delete_user_id'] ?? 0);
    if ($uid && $uid !== (int)$_SESSION['user_id']) {
        try {
            // Get user info before deleting
            $del_info = $pdo->prepare("SELECT username, full_name FROM users WHERE user_id = ?");
            $del_info->execute([$uid]);
            $del_row = $del_info->fetch();

            if ($del_row) {
                // Unlink from employee record first
                $pdo->prepare("UPDATE employees SET user_id = NULL WHERE user_id = ?")
                    ->execute([$uid]);

                // Delete the user
                $pdo->prepare("DELETE FROM users WHERE user_id = ?")
                    ->execute([$uid]);

                // Audit log (use current admin's ID since user is gone)
                $pdo->prepare("
                    INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                    VALUES (?, ?, ?, 'Delete User', ?, ?, ?)
                ")->execute([
                    $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                    $del_row['username'],
                    "Permanently deleted: {$del_row['full_name']} ({$del_row['username']})",
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

                $success = "User <strong>{$del_row['full_name']}</strong> ({$del_row['username']}) permanently deleted.";
            }
        } catch (PDOException $e) {
            $error = 'Delete failed: ' . $e->getMessage();
        }
    } else {
        $error = 'Cannot delete your own account.';
    }
}

// ── Filters & pagination ───────────────────────────────────
$search   = trim($_GET['search'] ?? '');
$f_role   = trim($_GET['role']   ?? '');
$f_status = trim($_GET['status'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(username LIKE ? OR full_name LIKE ? OR email LIKE ?)';
    $like     = "%{$search}%";
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($f_role) {
    $where[]  = 'role = ?';
    $params[] = $f_role;
}
if ($f_status !== '') {
    $where[]  = 'is_active = ?';
    $params[] = $f_status === 'active' ? 1 : 0;
}
$where_sql = implode(' AND ', $where);

$total_rows  = (int)$pdo->prepare("SELECT COUNT(*) FROM users WHERE {$where_sql}")
                         ->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$where_sql}")
                         ->execute($params) : 0;

// Re-run count properly
$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$where_sql}");
$cnt_stmt->execute($params);
$total_rows  = (int)$cnt_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$stmt = $pdo->prepare("
    SELECT u.user_id, u.username, u.full_name, u.email, u.role, u.is_active, u.last_login, u.created_at,
           e.emp_id AS linked_emp_id
    FROM   users u
    LEFT JOIN employees e ON e.user_id = u.user_id
    WHERE  {$where_sql}
    ORDER  BY u.created_at DESC
    LIMIT  {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Fetch user for edit modal
$edit_user = null;
if (!empty($_GET['edit'])) {
    $es = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $es->execute([(int)$_GET['edit']]);
    $edit_user = $es->fetch();
}

// Load unlinked employees (no user_id) for linking dropdown
$unlinked_employees = $pdo->query("
    SELECT emp_id, full_name, position FROM employees
    WHERE user_id IS NULL AND status = 'active'
    ORDER BY full_name
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
    <a href="dashboard.php">Admin</a><span>/</span><span>Manage Users</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Manage Users</h1>
        <p>Create, update, and manage system user accounts.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addUserModal')">
        <i class="fas fa-user-plus"></i> Add New User
    </button>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= $error ?></span></div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="">
            <div class="filter-bar">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="form-control"
                           placeholder="Search by name, username, or email..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="role" class="form-control" style="width:auto;">
                    <option value="">All Roles</option>
                    <option value="admin"    <?= $f_role === 'admin'    ? 'selected' : '' ?>>Admin</option>
                    <option value="hr"       <?= $f_role === 'hr'       ? 'selected' : '' ?>>HR</option>
                    <option value="finance"  <?= $f_role === 'finance'  ? 'selected' : '' ?>>Finance</option>
                    <option value="employee" <?= $f_role === 'employee' ? 'selected' : '' ?>>Employee</option>
                </select>
                <select name="status" class="form-control" style="width:auto;">
                    <option value="">All Status</option>
                    <option value="active"   <?= $f_status === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $f_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if ($search || $f_role || $f_status !== ''): ?>
                <a href="users.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users" style="color:var(--primary);margin-right:8px"></i>All System Users</h3>
        <span class="badge badge-primary"><?= $total_rows ?> Total</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding:32px;">
                            No users found. <a href="?">Clear filters</a>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="text-muted"><?= $u['user_id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                        <td class="text-muted"><?= htmlspecialchars($u['username']) ?></td>
                        <td class="text-muted" style="font-size:0.82rem;">
                            <?= htmlspecialchars($u['email'] ?? '—') ?>
                        </td>
                        <td>
                            <span class="badge <?= $role_badge[$u['role']] ?? 'badge-gray' ?>">
                                <?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                            <?php if ($u['role'] === 'employee'): ?>
                            <br>
                            <span class="badge <?= $u['linked_emp_id'] ? 'badge-info' : 'badge-warning' ?>"
                                  style="margin-top:3px;font-size:0.65rem;">
                                <?= $u['linked_emp_id']
                                    ? '<i class="fas fa-link"></i> ' . htmlspecialchars($u['linked_emp_id'])
                                    : '<i class="fas fa-unlink"></i> Not linked' ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted" style="font-size:0.78rem;white-space:nowrap;">
                            <?= $u['last_login']
                                ? date('M d, Y H:i', strtotime($u['last_login']))
                                : 'Never' ?>
                        </td>
                        <td>
                            <a href="users.php?edit=<?= $u['user_id'] ?>"
                               class="btn btn-secondary btn-sm btn-icon-only" title="Edit User">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="roles.php?user=<?= $u['user_id'] ?>"
                               class="btn btn-warning btn-sm btn-icon-only" title="Assign Role">
                                <i class="fas fa-user-tag"></i>
                            </a>

                            <?php if ($u['user_id'] !== (int)$_SESSION['user_id']): ?>

                            <?php if ($u['is_active']): ?>
                            <!-- Deactivate -->
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Deactivate <?= htmlspecialchars(addslashes($u['full_name'])) ?>? They will not be able to login.')">
                                <input type="hidden" name="del_user_id" value="<?= $u['user_id'] ?>">
                                <button type="submit" name="deactivate_user"
                                        class="btn btn-warning btn-sm btn-icon-only"
                                        title="Deactivate (disable login)">
                                    <i class="fas fa-user-slash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <!-- Reactivate -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="reactivate_user_id" value="<?= $u['user_id'] ?>">
                                <button type="submit" name="reactivate_user"
                                        class="btn btn-success btn-sm btn-icon-only"
                                        title="Reactivate account">
                                    <i class="fas fa-user-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                            <!-- Permanently Delete -->
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('⚠️ PERMANENTLY DELETE <?= htmlspecialchars(addslashes($u['full_name'])) ?>?\n\nThis cannot be undone. All their data will be removed.')">
                                <input type="hidden" name="delete_user_id" value="<?= $u['user_id'] ?>">
                                <button type="submit" name="delete_user"
                                        class="btn btn-danger btn-sm btn-icon-only"
                                        title="Permanently Delete">
                                    <i class="fas fa-trash"></i>
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
    <div class="card-footer d-flex justify-between align-center" style="flex-wrap:wrap;gap:8px;">
        <span class="text-muted" style="font-size:0.8rem;">
            Showing <?= min($offset+1,$total_rows) ?>–<?= min($offset+$per_page,$total_rows) ?>
            of <?= $total_rows ?> users
            &nbsp;|&nbsp;
            <i class="fas fa-edit" style="color:var(--gray-400);"></i> Edit
            &nbsp;
            <i class="fas fa-user-tag" style="color:var(--warning);"></i> Role
            &nbsp;
            <i class="fas fa-user-slash" style="color:var(--warning);"></i> Deactivate
            &nbsp;
            <i class="fas fa-user-check" style="color:var(--success);"></i> Reactivate
            &nbsp;
            <i class="fas fa-trash" style="color:var(--danger);"></i> Delete permanently
        </span>
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php $qs = http_build_query(array_filter(['search'=>$search,'role'=>$f_role,'status'=>$f_status])); ?>
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

<!-- ── Add User Modal ── -->
<div class="modal-overlay" id="addUserModal"
     <?= (isset($_POST['create_user']) && $error) ? 'style="opacity:1;pointer-events:all;"' : '' ?>>
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px"></i>Create New User</h3>
            <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">

                <!-- Email notice -->
                <div style="background:var(--success-light);border-radius:var(--radius);
                            padding:10px 14px;margin-bottom:16px;font-size:0.82rem;
                            color:var(--success);border-left:4px solid var(--success);">
                    <i class="fas fa-envelope"></i>
                    <strong>Welcome email</strong> with username &amp; password will be sent
                    automatically to the employee's Gmail address.
                </div>

                <div class="form-group">
                    <label class="form-label">Full Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="full_name" class="form-control"
                           placeholder="e.g. Admasu Dejene"
                           minlength="2" maxlength="100" required>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Email Address <span style="color:var(--danger)">*</span>
                        <span style="font-weight:400;color:var(--success);font-size:0.75rem;">
                            — welcome email sent here
                        </span>
                    </label>
                    <input type="email" name="email" class="form-control"
                           placeholder="employee@gmail.com" maxlength="180" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Username <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="username" class="form-control"
                               placeholder="e.g. admasu.d"
                               minlength="3" maxlength="30"
                               pattern="[a-z0-9_\.]+"
                               title="Lowercase letters, numbers, underscores and dots only"
                               required>
                        <span class="form-hint">Lowercase letters, numbers, underscores and dots only</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role <span style="color:var(--danger)">*</span></label>
                        <select name="role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="admin">Administrator</option>
                            <option value="hr">HR Personnel</option>
                            <option value="finance">Finance Officer</option>
                            <option value="employee">Employee</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Password <span style="color:var(--danger)">*</span>
                        <span style="font-weight:400;color:var(--info);font-size:0.75rem;">
                            — sent in welcome email
                        </span>
                    </label>
                    <div style="position:relative;">
                        <input type="password" name="password" id="newUserPass"
                               class="form-control"
                               placeholder="Min. 8 chars, 1 uppercase, 1 number"
                               minlength="8" maxlength="100" required
                               style="padding-right:110px;">
                        <button type="button" onclick="generatePassword()"
                                style="position:absolute;right:6px;top:50%;transform:translateY(-50%);
                                       background:var(--primary);color:white;border:none;
                                       border-radius:5px;padding:4px 10px;font-size:0.75rem;
                                       cursor:pointer;font-weight:600;">
                            Generate
                        </button>
                    </div>
                    <span class="form-hint">
                        Min. 8 characters with at least 1 uppercase letter and 1 number.
                        The employee will receive this password by email.
                    </span>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                        onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" name="create_user" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Create &amp; Send Welcome Email
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit User Modal ── -->
<?php if ($edit_user): ?>
<div class="modal-overlay active" id="editUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);margin-right:8px"></i>
                Edit User — <?= htmlspecialchars($edit_user['username']) ?>
            </h3>
            <button class="modal-close"
                    onclick="window.location='users.php'">&times;</button>
        </div>
        <form method="POST" action="users.php">
            <input type="hidden" name="user_id" value="<?= $edit_user['user_id'] ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control"
                               value="<?= htmlspecialchars($edit_user['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-control" required>
                            <option value="admin"    <?= $edit_user['role']==='admin'    ?'selected':'' ?>>Administrator</option>
                            <option value="hr"       <?= $edit_user['role']==='hr'       ?'selected':'' ?>>HR Personnel</option>
                            <option value="finance"  <?= $edit_user['role']==='finance'  ?'selected':'' ?>>Finance Officer</option>
                            <option value="employee" <?= $edit_user['role']==='employee' ?'selected':'' ?>>Employee</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div style="padding:10px 0;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="is_active"
                                       <?= $edit_user['is_active'] ? 'checked' : '' ?>
                                       style="width:16px;height:16px;accent-color:var(--primary);">
                                <span>Account Active</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control"
                           placeholder="Leave blank to keep current password">
                    <span class="form-hint">Min. 6 characters. Leave blank to keep existing password.</span>
                </div>

                <?php if ($edit_user['role'] === 'employee'): ?>
                <!-- Link to employee record -->
                <div style="margin-top:16px;padding:14px;background:var(--bg-light);border-radius:var(--radius);">
                    <h4 style="font-size:0.85rem;color:var(--primary);margin-bottom:10px;">
                        <i class="fas fa-link"></i> Link to Employee Record
                    </h4>
                    <?php
                    // Check if already linked
                    $linked = $pdo->prepare("SELECT emp_id, full_name FROM employees WHERE user_id = ?");
                    $linked->execute([$edit_user['user_id']]);
                    $linked_emp = $linked->fetch();
                    ?>
                    <?php if ($linked_emp): ?>
                    <div style="padding:8px 12px;background:var(--success-light);border-radius:6px;
                                font-size:0.82rem;color:var(--success);margin-bottom:10px;">
                        <i class="fas fa-check-circle"></i>
                        Currently linked to: <strong><?= htmlspecialchars($linked_emp['emp_id']) ?>
                        — <?= htmlspecialchars($linked_emp['full_name']) ?></strong>
                    </div>
                    <?php else: ?>
                    <div style="padding:8px 12px;background:var(--warning-light);border-radius:6px;
                                font-size:0.82rem;color:var(--warning);margin-bottom:10px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Not linked</strong> — employee cannot view payslips until linked.
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($unlinked_employees) || $linked_emp): ?>
                    <form method="POST" action="users.php?edit=<?= $edit_user['user_id'] ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="link_user_id" value="<?= $edit_user['user_id'] ?>">
                        <div class="form-group" style="margin:0;flex:1;">
                            <label class="form-label">Select Employee Record</label>
                            <select name="link_emp_id" class="form-control" required>
                                <option value="">-- Select Employee --</option>
                                <?php if ($linked_emp): ?>
                                <option value="<?= $linked_emp['emp_id'] ?>" selected>
                                    <?= htmlspecialchars($linked_emp['emp_id']) ?> — <?= htmlspecialchars($linked_emp['full_name']) ?> (current)
                                </option>
                                <?php endif; ?>
                                <?php foreach ($unlinked_employees as $ue): ?>
                                <option value="<?= htmlspecialchars($ue['emp_id']) ?>">
                                    <?= htmlspecialchars($ue['emp_id']) ?> — <?= htmlspecialchars($ue['full_name']) ?>
                                    (<?= htmlspecialchars($ue['position']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="padding-bottom:18px;">
                            <button type="submit" name="link_employee" class="btn btn-primary btn-sm">
                                <i class="fas fa-link"></i> Link
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <p style="font-size:0.82rem;color:var(--gray-400);">
                        No unlinked employee records available. Register the employee in HR first.
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <a href="users.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="update_user" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Generate a strong random password that meets validation rules:
// min 8 chars, at least 1 uppercase, 1 number, 1 special char
function generatePassword() {
    const upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower   = 'abcdefghjkmnpqrstuvwxyz';
    const digits  = '23456789';
    const special = '@#$!';
    const all     = upper + lower + digits + special;

    // Guarantee at least one of each required type
    let pass = [
        upper.charAt(Math.floor(Math.random() * upper.length)),
        digits.charAt(Math.floor(Math.random() * digits.length)),
        special.charAt(Math.floor(Math.random() * special.length)),
    ];
    // Fill remaining 7 chars from all
    for (let i = 0; i < 7; i++) {
        pass.push(all.charAt(Math.floor(Math.random() * all.length)));
    }
    // Shuffle
    pass = pass.sort(() => Math.random() - 0.5).join('');

    const input = document.getElementById('newUserPass');
    if (input) {
        input.value = pass;
        input.type  = 'text';   // show generated password briefly
        input.focus();
        // Hide after 3 seconds
        setTimeout(() => { if (input.type === 'text') input.type = 'password'; }, 3000);
    }
}
</script>

<?php require_once $depth . 'includes/footer.php'; ?>

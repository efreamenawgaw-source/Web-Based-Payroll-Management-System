<?php
session_start();
$page_title = 'Register Employee';
$active_nav = 'register';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/notify.php';

$pdo     = getDB();
$success = '';
$error   = '';
$post    = [];   // repopulate form on error

// â”€â”€ Load departments from DB â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$departments = $pdo->query("
    SELECT dept_id, dept_name FROM departments
    WHERE  is_active = 1
    ORDER  BY dept_name
")->fetchAll();

// â”€â”€ Handle form submission â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = $_POST;

    // Sanitise inputs
    $emp_id             = strtoupper(trim($post['emp_id']             ?? ''));
    $full_name          = trim($post['full_name']                     ?? '');
    $last_name          = trim($post['last_name']                     ?? '') ?: null;
    $cbe_account_number = trim($post['cbe_account_number']            ?? '') ?: null;
    $cbe_account_name   = trim($post['cbe_account_name']              ?? '') ?: null;
    $gender             = trim($post['gender']                        ?? '');
    $dob             = trim($post['dob']                        ?? '') ?: null;
    $email           = trim($post['email']                      ?? '') ?: null;
    $phone           = trim($post['phone']                      ?? '');
    $dept_id         = (int)($post['dept_id']                   ?? 0);
    $position        = trim($post['position']                   ?? '');
    $basic_salary    = (float)($post['basic_salary']            ?? 0);
    $employment_date = trim($post['employment_date']            ?? '');
    $emp_type        = trim($post['emp_type']                   ?? 'permanent');
    $status          = trim($post['status']                     ?? 'active');
    $housing         = (float)($post['housing']                 ?? 0);
    $transport       = (float)($post['transport']               ?? 0);
    $position_allow  = (float)($post['position_allowance']      ?? 0);
    $teaching        = (float)($post['teaching']                ?? 0);
    $other           = (float)($post['other']                   ?? 0);

    // Validation
    $errors = [];
    if (empty($emp_id))          $errors[] = 'Employee ID is required.';
    if (empty($full_name))       $errors[] = 'Full name is required.';
    if (empty($gender))          $errors[] = 'Gender is required.';
    if (empty($phone))           $errors[] = 'Phone number is required.';
    if ($dept_id === 0)          $errors[] = 'Department is required.';
    if (empty($position))        $errors[] = 'Position is required.';
    if ($basic_salary <= 0)      $errors[] = 'Basic salary must be greater than 0.';
    if (empty($employment_date)) $errors[] = 'Employment date is required.';

    // Check duplicate emp_id
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT emp_id FROM employees WHERE emp_id = ?");
        $chk->execute([$emp_id]);
        if ($chk->fetch()) {
            $errors[] = "Employee ID <strong>{$emp_id}</strong> already exists.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert employee
            $stmt = $pdo->prepare("
                INSERT INTO employees
                    (emp_id, full_name, last_name, cbe_account_number, cbe_account_name,
                     gender, date_of_birth, phone, email,
                     dept_id, position, employment_type, basic_salary,
                     employment_date, status, created_by)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $emp_id, $full_name, $last_name, $cbe_account_number, $cbe_account_name,
                $gender, $dob, $phone, $email,
                $dept_id, $position, $emp_type, $basic_salary,
                $employment_date, $status, $_SESSION['user_id']
            ]);

            // Insert initial allowances
            $allow = $pdo->prepare("
                INSERT INTO allowances
                    (emp_id, housing, transport, position_allowance,
                     teaching, other, effective_from, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $allow->execute([
                $emp_id, $housing, $transport, $position_allow,
                $teaching, $other, $employment_date, $_SESSION['user_id']
            ]);

            // Audit log
            $log = $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Register Employee', ?, ?, ?)
            ");
            $log->execute([
                $_SESSION['user_id'],
                $_SESSION['username'],
                $_SESSION['role'],
                $emp_id,
                "Registered: {$full_name} | Dept ID: {$dept_id} | Salary: {$basic_salary}",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $pdo->commit();
            $success = "Employee <strong>{$full_name}</strong> ({$emp_id}) registered successfully!";

            // Notify admin and finance
            notify_role($pdo, 'admin',
                'New Employee Registered',
                "HR registered new employee: {$full_name} ({$emp_id})",
                'success');
            notify_role($pdo, 'finance',
                'New Employee Added',
                "{$full_name} ({$emp_id}) has been registered. Update allowances and deductions before payroll.",
                'info');

            $post = [];

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">HR</a><span>/</span>
    <a href="employees.php">Employees</a><span>/</span>
    <span>Register Employee</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Register New Employee</h1>
        <p>Add a new staff member to the payroll system.</p>
    </div>
    <a href="employees.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <span><?= $success ?></span>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <span><?= $error ?></span>
</div>
<?php endif; ?>

<form method="POST" action="" novalidate>
<div class="grid-2" style="gap:24px;align-items:start;">

    <!-- â”€â”€ Personal Information â”€â”€ -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user" style="color:var(--primary);margin-right:8px"></i>Personal Information</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Full Name <span style="color:var(--danger)">*</span></label>
                <input type="text" name="full_name" class="form-control"
                       placeholder="e.g. Admasu Dejene"
                       value="<?= htmlspecialchars($post['full_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Last Name (Father's Name)</label>
                <input type="text" name="last_name" class="form-control"
                       placeholder="e.g. Simane"
                       value="<?= htmlspecialchars($post['last_name'] ?? '') ?>">
                <span class="form-hint">Optional &mdash; 3rd name / family name</span>
            </div>

            <!-- CBE Bank Account -->
            <div style="background:var(--info-light);border-radius:var(--radius);
                        padding:14px;margin-bottom:4px;border-left:3px solid var(--info);">
                <p style="font-size:0.78rem;font-weight:700;color:var(--info);
                           text-transform:uppercase;margin:0 0 10px;">
                    <i class="fas fa-university"></i> CBE Bank Account (for salary transfer)
                </p>
                <div class="form-row" style="margin-bottom:0;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">CBE Account Number</label>
                        <input type="text" name="cbe_account_number" class="form-control"
                               placeholder="e.g. 1000123456789"
                               maxlength="20"
                               value="<?= htmlspecialchars($post['cbe_account_number'] ?? '') ?>">
                        <span class="form-hint">Commercial Bank of Ethiopia account number</span>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Account Holder Name</label>
                        <input type="text" name="cbe_account_name" class="form-control"
                               placeholder="Name as on CBE account"
                               value="<?= htmlspecialchars($post['cbe_account_name'] ?? '') ?>">
                        <span class="form-hint">Must match the name registered at CBE</span>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Employee ID <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="emp_id" class="form-control"
                           placeholder="e.g. EMP-101"
                           value="<?= htmlspecialchars($post['emp_id'] ?? '') ?>"
                           style="text-transform:uppercase" required>
                    <span class="form-hint">Must be unique &mdash; e.g. EMP-101</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Gender <span style="color:var(--danger)">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="male"   <?= ($post['gender'] ?? '') === 'male'   ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($post['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other"  <?= ($post['gender'] ?? '') === 'other'  ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control"
                       placeholder="employee@gmail.com"
                       value="<?= htmlspecialchars($post['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Phone Number <span style="color:var(--danger)">*</span></label>
                <input type="tel" name="phone" class="form-control"
                       placeholder="+251 9XX XXX XXX"
                       value="<?= htmlspecialchars($post['phone'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="dob" class="form-control"
                       value="<?= htmlspecialchars($post['dob'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- â”€â”€ Employment Information â”€â”€ -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-briefcase" style="color:var(--primary);margin-right:8px"></i>Employment Information</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Department <span style="color:var(--danger)">*</span></label>
                <select name="dept_id" class="form-control" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['dept_id'] ?>"
                        <?= ($post['dept_id'] ?? '') == $d['dept_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['dept_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Position / Job Title <span style="color:var(--danger)">*</span></label>
                <select name="position" class="form-control" required>
                    <option value="">Select Position</option>
                    <?php
                    $positions = [
                        'Professor','Associate Professor','Senior Lecturer','Lecturer',
                        'Assistant Lecturer','Administrative Officer','HR Officer',
                        'Finance Officer','Technician','Librarian','Security Staff',
                        'IT Officer','Cleaner','Driver',
                    ];
                    foreach ($positions as $p): ?>
                    <option value="<?= $p ?>"
                        <?= ($post['position'] ?? '') === $p ? 'selected' : '' ?>>
                        <?= $p ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Basic Salary (ETB) <span style="color:var(--danger)">*</span></label>
                    <input type="number" name="basic_salary" class="form-control"
                           placeholder="e.g. 12500" min="1" step="0.01"
                           value="<?= htmlspecialchars($post['basic_salary'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Employment Date <span style="color:var(--danger)">*</span></label>
                    <input type="date" name="employment_date" class="form-control"
                           value="<?= htmlspecialchars($post['employment_date'] ?? date('Y-m-d')) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Employment Type</label>
                    <select name="emp_type" class="form-control">
                        <option value="permanent" <?= ($post['emp_type'] ?? 'permanent') === 'permanent' ? 'selected' : '' ?>>Permanent</option>
                        <option value="contract"  <?= ($post['emp_type'] ?? '') === 'contract'  ? 'selected' : '' ?>>Contract</option>
                        <option value="part_time" <?= ($post['emp_type'] ?? '') === 'part_time' ? 'selected' : '' ?>>Part-Time</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Initial Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= ($post['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="on_leave" <?= ($post['status'] ?? '') === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- â”€â”€ Allowances â”€â”€ -->
<div class="card mt-3">
    <div class="card-header">
        <h3><i class="fas fa-hand-holding-usd" style="color:var(--success);margin-right:8px"></i>
            Initial Allowances (ETB)
        </h3>
        <span class="text-muted" style="font-size:0.8rem;">Optional &mdash; can be updated later</span>
    </div>
    <div class="card-body">
        <div class="form-row">
            <?php
            $allow_fields = [
                ['housing',            'Housing Allowance'],
                ['transport',          'Transport Allowance'],
                ['position_allowance', 'Position Allowance'],
                ['teaching',           'Teaching Allowance'],
                ['other',              'Other Allowance'],
            ];
            foreach ($allow_fields as [$fname, $flabel]): ?>
            <div class="form-group">
                <label class="form-label"><?= $flabel ?> (ETB)</label>
                <input type="number" name="<?= $fname ?>" class="form-control"
                       placeholder="0.00" min="0" step="0.01"
                       value="<?= htmlspecialchars($post[$fname] ?? '0') ?>">
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Live gross preview -->
        <div id="grossPreview" style="background:var(--success-light);border-radius:var(--radius);
             padding:14px;border-left:4px solid var(--success);margin-top:8px;">
            <p style="font-size:0.78rem;color:var(--success);margin:0 0 4px;font-weight:700;">
                ESTIMATED GROSS SALARY
            </p>
            <p id="grossAmount" style="font-size:1.3rem;font-weight:700;color:var(--success);margin:0;">
                ETB 0.00
            </p>
            <p id="grossBreakdown" style="font-size:0.75rem;color:var(--gray-600);margin:4px 0 0;">
                Basic (0) + Allowances (0)
            </p>
        </div>

        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Auto-calculated on payroll:</strong>
                Pension (11% employee / 18% employer) and income tax are applied automatically
                per <strong>Revised Monthly Employment Tax Brackets 2025</strong>.
            </div>
        </div>
    </div>
</div>

<!-- â”€â”€ Actions â”€â”€ -->
<div class="card mt-3">
    <div class="card-body d-flex gap-2" style="justify-content:flex-end;flex-wrap:wrap;">
        <a href="employees.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
        <button type="reset" class="btn btn-secondary" onclick="updateGross()">
            <i class="fas fa-undo"></i> Reset
        </button>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Register Employee
        </button>
    </div>
</div>

</form>

<script>
// Live gross salary preview
function updateGross() {
    const basic     = parseFloat(document.querySelector('[name=basic_salary]')?.value)     || 0;
    const housing   = parseFloat(document.querySelector('[name=housing]')?.value)          || 0;
    const transport = parseFloat(document.querySelector('[name=transport]')?.value)        || 0;
    const position  = parseFloat(document.querySelector('[name=position_allowance]')?.value) || 0;
    const teaching  = parseFloat(document.querySelector('[name=teaching]')?.value)         || 0;
    const other     = parseFloat(document.querySelector('[name=other]')?.value)            || 0;

    const allowances = housing + transport + position + teaching + other;
    const gross      = basic + allowances;

    document.getElementById('grossAmount').textContent =
        'ETB ' + gross.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('grossBreakdown').textContent =
        'Basic (' + basic.toLocaleString() + ') + Allowances (' + allowances.toLocaleString() + ')';
}

// Attach listeners
document.querySelectorAll('[name=basic_salary],[name=housing],[name=transport],[name=position_allowance],[name=teaching],[name=other]')
    .forEach(el => el.addEventListener('input', updateGross));

updateGross();
</script>

<?php require_once $depth . 'includes/footer.php'; ?>


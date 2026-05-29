<?php
session_start();
$page_title = 'Register Employee';
$active_nav = 'register';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/notify.php';

// ============================================================
// EmployeeRegistration — OOP class
// Handles ID generation, validation, and DB insertion
// ============================================================
class EmployeeRegistration
{
    private PDO $pdo;

    // ── Whitelists ───────────────────────────────────────────
    private const VALID_POSITIONS = [
        'Professor', 'Associate Professor', 'Senior Lecturer', 'Lecturer',
        'Assistant Lecturer', 'Administrative Officer', 'HR Officer',
        'Finance Officer', 'Technician', 'Librarian', 'Security Staff',
        'IT Officer', 'Cleaner', 'Driver',
    ];
    private const VALID_EMP_TYPES = ['permanent', 'contract', 'part_time'];
    private const VALID_STATUSES  = ['active', 'on_leave'];
    private const VALID_GENDERS   = ['male', 'female', 'other'];
    private const EMP_ID_PREFIX   = 'BIT';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Generate next BIT-XXXX employee ID ──────────────────
    public function generateNextEmpId(): string
    {
        $stmt = $this->pdo->prepare("
            SELECT emp_id FROM employees
            WHERE emp_id LIKE 'BIT-%'
            ORDER BY CAST(SUBSTRING(emp_id, 5) AS UNSIGNED) DESC
            LIMIT 1
        ");
        $stmt->execute();
        $last = $stmt->fetchColumn();

        if ($last) {
            // Extract numeric part and increment
            $num = (int) substr($last, 4); // strip 'BIT-'
            $next = $num + 1;
        } else {
            $next = 1;
        }

        return self::EMP_ID_PREFIX . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // ── Load active departments ──────────────────────────────
    public function getDepartments(): array
    {
        return $this->pdo->query("
            SELECT dept_id, dept_name FROM departments
            WHERE  is_active = 1
            ORDER  BY dept_name
        ")->fetchAll();
    }

    // ── Validate form input, return array of error strings ──
    public function validate(array $data): array
    {
        $errors = [];

        $full_name          = trim($data['full_name']          ?? '');
        $last_name          = trim($data['last_name']          ?? '') ?: null;
        $cbe_account_number = trim($data['cbe_account_number'] ?? '') ?: null;
        $gender             = trim($data['gender']             ?? '');
        $dob                = trim($data['dob']                ?? '') ?: null;
        $email              = trim($data['email']              ?? '') ?: null;
        $phone              = trim($data['phone']              ?? '');
        $dept_id            = (int)($data['dept_id']           ?? 0);
        $position           = trim($data['position']           ?? '');
        $basic_salary       = (float)($data['basic_salary']    ?? 0);
        $employment_date    = trim($data['employment_date']    ?? '');
        $emp_type           = trim($data['emp_type']           ?? 'permanent');
        $status             = trim($data['status']             ?? 'active');

        // Allowances
        foreach (['housing', 'transport', 'position_allowance', 'teaching', 'other'] as $f) {
            if ((float)($data[$f] ?? 0) < 0)
                $errors[] = ucwords(str_replace('_', ' ', $f)) . ' cannot be negative.';
        }

        // Full name
        if (empty($full_name))
            $errors[] = 'Full name is required.';
        elseif (strlen($full_name) < 2 || strlen($full_name) > 100)
            $errors[] = 'Full name must be 2–100 characters.';
        elseif (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $full_name))
            $errors[] = 'Full name may only contain letters, spaces, hyphens, apostrophes, and dots.';

        // Last name (optional)
        if ($last_name !== null && strlen($last_name) > 100)
            $errors[] = 'Last name must be 100 characters or fewer.';

        // Gender
        if (!in_array($gender, self::VALID_GENDERS, true))
            $errors[] = 'Please select a valid gender.';

        // Phone
        if (empty($phone))
            $errors[] = 'Phone number is required.';
        elseif (!preg_match('/^(\+251|0)[0-9]{8,13}$/', preg_replace('/[\s\-]/', '', $phone)))
            $errors[] = 'Phone must be a valid Ethiopian number (e.g. +251911234567 or 0911234567).';

        // Email (optional)
        if ($email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                $errors[] = 'Please enter a valid email address.';
            elseif (strlen($email) > 180)
                $errors[] = 'Email address is too long (max 180 characters).';
        }

        // Department
        if ($dept_id === 0)
            $errors[] = 'Department is required.';

        // Position
        if (!in_array($position, self::VALID_POSITIONS, true))
            $errors[] = 'Please select a valid position.';

        // Basic salary
        if ($basic_salary < 0)
            $errors[] = 'Basic salary cannot be negative.';
        elseif ($basic_salary > 500000)
            $errors[] = 'Basic salary seems too high. Please verify.';

        // Employment date
        if (empty($employment_date))
            $errors[] = 'Employment date is required.';
        elseif (strtotime($employment_date) > strtotime('today'))
            $errors[] = 'Employment date cannot be in the future.';
        elseif (strtotime($employment_date) < strtotime('1990-01-01'))
            $errors[] = 'Employment date seems too far in the past.';

        // Date of birth (optional)
        if ($dob !== null) {
            $age = (int) date_diff(date_create($dob), date_create('today'))->y;
            if (strtotime($dob) >= strtotime('today'))
                $errors[] = 'Date of birth must be in the past.';
            elseif ($age < 18 || $age > 80)
                $errors[] = 'Employee age must be between 18 and 80 years.';
        }

        // Employment type
        if (!in_array($emp_type, self::VALID_EMP_TYPES, true))
            $errors[] = 'Please select a valid employment type.';

        // Status
        if (!in_array($status, self::VALID_STATUSES, true))
            $errors[] = 'Please select a valid status.';

        // CBE account number (optional)
        if ($cbe_account_number !== null) {
            if (!preg_match('/^[0-9]{10,20}$/', $cbe_account_number))
                $errors[] = 'CBE account number must be 10–20 digits only.';
        }

        return $errors;
    }

    // ── Register employee — returns emp_id on success ───────
    public function register(array $data, int $createdBy): string
    {
        $emp_id             = $this->generateNextEmpId();
        $full_name          = trim($data['full_name']);
        $last_name          = trim($data['last_name']          ?? '') ?: null;
        $cbe_account_number = trim($data['cbe_account_number'] ?? '') ?: null;
        $cbe_account_name   = trim($data['cbe_account_name']   ?? '') ?: null;
        $gender             = trim($data['gender']);
        $dob                = trim($data['dob']                ?? '') ?: null;
        $email              = trim($data['email']              ?? '') ?: null;
        $phone              = trim($data['phone']);
        $dept_id            = (int)$data['dept_id'];
        $position           = trim($data['position']);
        $basic_salary       = (float)$data['basic_salary'];
        $employment_date    = trim($data['employment_date']);
        $emp_type           = trim($data['emp_type']           ?? 'permanent');
        $status             = trim($data['status']             ?? 'active');
        $housing            = (float)($data['housing']             ?? 0);
        $transport          = (float)($data['transport']           ?? 0);
        $position_allow     = (float)($data['position_allowance']  ?? 0);
        $teaching           = (float)($data['teaching']            ?? 0);
        $other              = (float)($data['other']               ?? 0);

        $this->pdo->beginTransaction();

        try {
            // Insert employee
            $stmt = $this->pdo->prepare("
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
                $employment_date, $status, $createdBy,
            ]);

            // Insert initial allowances
            $allow = $this->pdo->prepare("
                INSERT INTO allowances
                    (emp_id, housing, transport, position_allowance,
                     teaching, other, effective_from, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $allow->execute([
                $emp_id, $housing, $transport, $position_allow,
                $teaching, $other, $employment_date, $createdBy,
            ]);

            // Insert initial deductions with 0 defaults
            $ded_month = (int)date('m', strtotime($employment_date));
            $ded_year  = (int)date('Y', strtotime($employment_date));
            $this->pdo->prepare("
                INSERT INTO deductions
                    (emp_id, credit_association, renaissance_dam,
                     loan_repayment, penalty, other,
                     effective_month, effective_year, status, created_by)
                VALUES (?, 0, 0, 0, 0, 0, ?, ?, 'active', ?)
            ")->execute([$emp_id, $ded_month, $ded_year, $createdBy]);

            // Audit log
            $log = $this->pdo->prepare("
                INSERT INTO audit_logs
                    (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Register Employee', ?, ?, ?)
            ");
            $log->execute([
                $_SESSION['user_id'],
                $_SESSION['username'],
                $_SESSION['role'],
                $emp_id,
                "Registered: {$full_name} | Dept ID: {$dept_id} | Salary: {$basic_salary}",
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            $this->pdo->commit();
            return $emp_id;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ── Expose position list for the view ───────────────────
    public function getPositions(): array
    {
        return self::VALID_POSITIONS;
    }
}

// ============================================================
// Controller logic
// ============================================================
$pdo        = getDB();
$service    = new EmployeeRegistration($pdo);
$departments = $service->getDepartments();
$nextEmpId  = $service->generateNextEmpId();

$success = '';
$error   = '';
$post    = [];   // repopulate form on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post   = $_POST;
    $errors = $service->validate($post);

    if (empty($errors)) {
        try {
            $emp_id   = $service->register($post, (int)$_SESSION['user_id']);
            $fullName = trim($post['full_name']);
            $empEmail = trim($post['email'] ?? '') ?: null;

            $success = "Employee <strong>" . htmlspecialchars($fullName) . "</strong> registered with ID <strong>{$emp_id}</strong> successfully!";

            // Notify admin with full employee details to create user account
            $email_line = $empEmail ? $empEmail : 'No email provided';

            notify_role($pdo, 'admin',
                "Create User Account — {$fullName}",
                "New employee registered by HR. Name: {$fullName} | ID: {$emp_id} | Email: {$email_line} — Please create a login account and link it.",
                'warning',
                '/pages/admin/users.php');

            notify_role($pdo, 'finance',
                'New Employee Added',
                "{$fullName} ({$emp_id}) has been registered. Update allowances and deductions before payroll.",
                'info');

            $nextEmpId = $service->generateNextEmpId();
            $post      = [];

        } catch (PDOException $e) {
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

    <!-- ── Personal Information ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user" style="color:var(--primary);margin-right:8px"></i>Personal Information</h3>
        </div>
        <div class="card-body">

            <!-- Auto-generated Employee ID (read-only) -->
            <div class="form-group">
                <label class="form-label">Employee ID</label>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="text" class="form-control"
                           value="<?= htmlspecialchars($nextEmpId) ?>"
                           readonly
                           style="background:var(--gray-100);color:var(--primary);
                                  font-weight:700;letter-spacing:1px;cursor:default;">
                </div>
                <span class="form-hint">
                    <i class="fas fa-magic" style="color:var(--primary)"></i>
                    Auto-assigned — next available ID in the <strong>BIT-XXXX</strong> sequence
                </span>
            </div>

            <div class="form-group">
                <label class="form-label">Full Name <span style="color:var(--danger)">*</span></label>
                <input type="text" name="full_name" class="form-control"
                       placeholder="e.g. Efream Enawgaw"
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

            <div class="form-row" style="margin-top:12px;">
                <div class="form-group">
                    <label class="form-label">Gender <span style="color:var(--danger)">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="male"   <?= ($post['gender'] ?? '') === 'male'   ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($post['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="dob" class="form-control"
                           value="<?= htmlspecialchars($post['dob'] ?? '') ?>">
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

        </div>
    </div>

    <!-- ── Employment Information ── -->
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
                    <?php foreach ($service->getPositions() as $p): ?>
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
                           placeholder="e.g. 12500" min="0" step="0.01"
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
                        <option value="active"   <?= ($post['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
                        <option value="on_leave" <?= ($post['status'] ?? '') === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ── Allowances ── -->
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

<!-- ── Actions ── -->
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
    const basic     = parseFloat(document.querySelector('[name=basic_salary]')?.value)        || 0;
    const housing   = parseFloat(document.querySelector('[name=housing]')?.value)             || 0;
    const transport = parseFloat(document.querySelector('[name=transport]')?.value)           || 0;
    const position  = parseFloat(document.querySelector('[name=position_allowance]')?.value)  || 0;
    const teaching  = parseFloat(document.querySelector('[name=teaching]')?.value)            || 0;
    const other     = parseFloat(document.querySelector('[name=other]')?.value)               || 0;

    const allowances = housing + transport + position + teaching + other;
    const gross      = basic + allowances;

    document.getElementById('grossAmount').textContent =
        'ETB ' + gross.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('grossBreakdown').textContent =
        'Basic (' + basic.toLocaleString() + ') + Allowances (' + allowances.toLocaleString() + ')';
}

document.querySelectorAll('[name=basic_salary],[name=housing],[name=transport],[name=position_allowance],[name=teaching],[name=other]')
    .forEach(el => el.addEventListener('input', updateGross));

updateGross();
</script>

<?php require_once $depth . 'includes/footer.php'; ?>

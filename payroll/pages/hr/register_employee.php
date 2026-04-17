<?php
$page_title = 'Register Employee';
$active_nav = 'register';
$depth      = '../../';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation (in production, use DB)
    $required = ['full_name','emp_id','department','position','basic_salary','email','phone','employment_date'];
    $missing  = [];
    foreach ($required as $field) {
        if (empty(trim($_POST[$field] ?? ''))) $missing[] = $field;
    }
    if ($missing) {
        $error = 'Please fill in all required fields: ' . implode(', ', $missing);
    } else {
        $success = 'Employee "' . htmlspecialchars($_POST['full_name']) . '" registered successfully!';
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
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<form method="POST" action="">
<div class="grid-2" style="gap:24px;align-items:start;">

    <!-- Personal Information -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user" style="color:var(--primary);margin-right:8px"></i>Personal Information</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Full Name <span style="color:var(--danger)">*</span></label>
                <input type="text" name="full_name" class="form-control" placeholder="e.g. Admasu Dejene"
                    value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Employee ID <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="emp_id" class="form-control" placeholder="e.g. EMP-101"
                        value="<?= htmlspecialchars($_POST['emp_id'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">Select Gender</option>
                        <option value="male"   <?= ($_POST['gender']??'')==='male'?'selected':'' ?>>Male</option>
                        <option value="female" <?= ($_POST['gender']??'')==='female'?'selected':'' ?>>Female</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="employee@bit.edu.et"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number <span style="color:var(--danger)">*</span></label>
                <input type="tel" name="phone" class="form-control" placeholder="+251 9XX XXX XXX"
                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="dob" class="form-control"
                    value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- Employment Information -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-briefcase" style="color:var(--primary);margin-right:8px"></i>Employment Information</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Department <span style="color:var(--danger)">*</span></label>
                <select name="department" class="form-control" required>
                    <option value="">Select Department</option>
                    <option value="computing"      <?= ($_POST['department']??'')==='computing'?'selected':'' ?>>Faculty of Computing</option>
                    <option value="engineering"    <?= ($_POST['department']??'')==='engineering'?'selected':'' ?>>Faculty of Engineering</option>
                    <option value="science"        <?= ($_POST['department']??'')==='science'?'selected':'' ?>>Faculty of Science</option>
                    <option value="admin"          <?= ($_POST['department']??'')==='admin'?'selected':'' ?>>Administrative Office</option>
                    <option value="finance"        <?= ($_POST['department']??'')==='finance'?'selected':'' ?>>Finance Office</option>
                    <option value="hr"             <?= ($_POST['department']??'')==='hr'?'selected':'' ?>>HR Office</option>
                    <option value="library"        <?= ($_POST['department']??'')==='library'?'selected':'' ?>>Library</option>
                    <option value="it"             <?= ($_POST['department']??'')==='it'?'selected':'' ?>>IT Support</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Position / Job Title <span style="color:var(--danger)">*</span></label>
                <select name="position" class="form-control" required>
                    <option value="">Select Position</option>
                    <option value="professor"        <?= ($_POST['position']??'')==='professor'?'selected':'' ?>>Professor</option>
                    <option value="associate_prof"   <?= ($_POST['position']??'')==='associate_prof'?'selected':'' ?>>Associate Professor</option>
                    <option value="senior_lecturer"  <?= ($_POST['position']??'')==='senior_lecturer'?'selected':'' ?>>Senior Lecturer</option>
                    <option value="lecturer"         <?= ($_POST['position']??'')==='lecturer'?'selected':'' ?>>Lecturer</option>
                    <option value="assistant_lecturer"<?= ($_POST['position']??'')==='assistant_lecturer'?'selected':'' ?>>Assistant Lecturer</option>
                    <option value="admin_officer"    <?= ($_POST['position']??'')==='admin_officer'?'selected':'' ?>>Administrative Officer</option>
                    <option value="hr_officer"       <?= ($_POST['position']??'')==='hr_officer'?'selected':'' ?>>HR Officer</option>
                    <option value="finance_officer"  <?= ($_POST['position']??'')==='finance_officer'?'selected':'' ?>>Finance Officer</option>
                    <option value="technician"       <?= ($_POST['position']??'')==='technician'?'selected':'' ?>>Technician</option>
                    <option value="security"         <?= ($_POST['position']??'')==='security'?'selected':'' ?>>Security Staff</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Basic Salary (ETB) <span style="color:var(--danger)">*</span></label>
                    <input type="number" name="basic_salary" class="form-control" placeholder="e.g. 12500"
                        min="0" step="0.01"
                        value="<?= htmlspecialchars($_POST['basic_salary'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Employment Date <span style="color:var(--danger)">*</span></label>
                    <input type="date" name="employment_date" class="form-control"
                        value="<?= htmlspecialchars($_POST['employment_date'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Employment Type</label>
                <select name="emp_type" class="form-control">
                    <option value="permanent"  <?= ($_POST['emp_type']??'')==='permanent'?'selected':'' ?>>Permanent</option>
                    <option value="contract"   <?= ($_POST['emp_type']??'')==='contract'?'selected':'' ?>>Contract</option>
                    <option value="part_time"  <?= ($_POST['emp_type']??'')==='part_time'?'selected':'' ?>>Part-Time</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Initial Status</label>
                <select name="status" class="form-control">
                    <option value="active"     <?= ($_POST['status']??'')==='active'?'selected':'' ?>>Active</option>
                    <option value="inactive"   <?= ($_POST['status']??'')==='inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>
        </div>
    </div>

</div>

<!-- Allowances Section -->
<div class="card mt-3">
    <div class="card-header">
        <h3><i class="fas fa-hand-holding-usd" style="color:var(--success);margin-right:8px"></i>Initial Allowances (ETB)</h3>
        <span class="text-muted" style="font-size:0.8rem;">Optional — can be updated later</span>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Housing Allowance</label>
                <input type="number" name="housing" class="form-control" placeholder="0.00" min="0" step="0.01"
                    value="<?= htmlspecialchars($_POST['housing'] ?? '0') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Transport Allowance</label>
                <input type="number" name="transport" class="form-control" placeholder="0.00" min="0" step="0.01"
                    value="<?= htmlspecialchars($_POST['transport'] ?? '0') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Position Allowance</label>
                <input type="number" name="position_allowance" class="form-control" placeholder="0.00" min="0" step="0.01"
                    value="<?= htmlspecialchars($_POST['position_allowance'] ?? '0') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Teaching Allowance</label>
                <input type="number" name="teaching" class="form-control" placeholder="0.00" min="0" step="0.01"
                    value="<?= htmlspecialchars($_POST['teaching'] ?? '0') ?>">
            </div>
        </div>

        <!-- Pension Info Box -->
        <div class="alert alert-info mt-2">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Automatic Calculations:</strong> Pension (7% employee / 11% employer) and income tax will be
                automatically calculated based on Ethiopian regulations when payroll is processed.
            </div>
        </div>
    </div>
</div>

<!-- Form Actions -->
<div class="card mt-3">
    <div class="card-body d-flex gap-2" style="justify-content:flex-end;">
        <a href="employees.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
        <button type="reset" class="btn btn-secondary">
            <i class="fas fa-undo"></i> Reset Form
        </button>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Register Employee
        </button>
    </div>
</div>

</form>

<?php require_once $depth . 'includes/footer.php'; ?>

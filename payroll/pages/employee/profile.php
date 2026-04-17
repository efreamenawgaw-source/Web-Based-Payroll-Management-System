<?php
$page_title = 'My Profile';
$active_nav = 'profile';
$depth      = '../../';
require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">Employee</a><span>/</span><span>My Profile</span>
</div>

<div class="page-header">
    <h1>My Personal Information</h1>
    <p>View your profile, salary structure, allowances, and deduction details.</p>
</div>

<!-- Profile Header Card -->
<div class="card mb-3">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
            <!-- Avatar -->
            <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:white;font-size:2rem;font-weight:700;flex-shrink:0;">
                A
            </div>
            <!-- Info -->
            <div style="flex:1;">
                <h2 style="margin:0 0 4px;">Admasu Dejene</h2>
                <p style="color:var(--gray-600);margin:0 0 8px;">Lecturer — Faculty of Computing</p>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <span class="badge badge-success">Active</span>
                    <span class="badge badge-primary">EMP-101</span>
                    <span class="badge badge-gray">Permanent</span>
                </div>
            </div>
            <!-- Contact -->
            <div style="text-align:right;">
                <p style="font-size:0.82rem;color:var(--gray-400);margin:0;">Email</p>
                <p style="font-weight:600;margin:0 0 6px;">admasu.d@bit.edu.et</p>
                <p style="font-size:0.82rem;color:var(--gray-400);margin:0;">Phone</p>
                <p style="font-weight:600;margin:0;">+251 91 234 5678</p>
            </div>
        </div>
    </div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- Personal Details -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user" style="color:var(--primary);margin-right:8px"></i>Personal Details</h3>
        </div>
        <div class="card-body">
            <?php
            $personal = [
                ['Full Name',         'Admasu Dejene',          'fas fa-user'],
                ['Employee ID',       'EMP-101',                'fas fa-id-badge'],
                ['Gender',            'Male',                   'fas fa-venus-mars'],
                ['Date of Birth',     '1985-03-15',             'fas fa-birthday-cake'],
                ['Email',             'admasu.d@bit.edu.et',    'fas fa-envelope'],
                ['Phone',             '+251 91 234 5678',       'fas fa-phone'],
                ['Employment Date',   '2010-09-01',             'fas fa-calendar-check'],
                ['Employment Type',   'Permanent',              'fas fa-briefcase'],
            ];
            foreach ($personal as $p): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--gray-200);">
                <div style="width:32px;height:32px;background:var(--bg-light);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:0.9rem;flex-shrink:0;">
                    <i class="<?= $p[2] ?>"></i>
                </div>
                <div>
                    <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;letter-spacing:0.4px;"><?= $p[0] ?></p>
                    <p style="font-weight:600;margin:0;font-size:0.9rem;"><?= $p[1] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Employment & Salary -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-briefcase" style="color:var(--primary);margin-right:8px"></i>Employment & Salary Structure</h3>
        </div>
        <div class="card-body">
            <?php
            $employment = [
                ['Department',          'Faculty of Computing',  'fas fa-building'],
                ['Position',            'Lecturer',              'fas fa-chalkboard-teacher'],
                ['Basic Salary',        'ETB 12,500.00',         'fas fa-money-bill'],
                ['Housing Allowance',   'ETB 1,000.00',          'fas fa-home'],
                ['Transport Allowance', 'ETB 500.00',            'fas fa-bus'],
                ['Position Allowance',  'ETB 0.00',              'fas fa-star'],
                ['Gross Salary',        'ETB 14,000.00',         'fas fa-wallet'],
                ['Employment Status',   'Active',                'fas fa-check-circle'],
            ];
            foreach ($employment as $e): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--gray-200);">
                <div style="width:32px;height:32px;background:var(--bg-light);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:0.9rem;flex-shrink:0;">
                    <i class="<?= $e[2] ?>"></i>
                </div>
                <div>
                    <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;letter-spacing:0.4px;"><?= $e[0] ?></p>
                    <p style="font-weight:600;margin:0;font-size:0.9rem;"><?= $e[1] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Deductions Breakdown -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-receipt" style="color:var(--danger);margin-right:8px"></i>Monthly Deductions Breakdown</h3>
        <span class="badge badge-info">Based on June 2023</span>
    </div>
    <div class="card-body">
        <div class="grid-3" style="gap:20px;">
            <?php
            $deductions = [
                ['Employee Pension (7%)',  'ETB 875.00',    'Deducted from basic salary', 'var(--warning)', 'fas fa-piggy-bank'],
                ['Income Tax',            'ETB 1,948.25',  'Based on Ethiopian tax brackets', 'var(--danger)', 'fas fa-percent'],
                ['Employer Pension (11%)','ETB 1,375.00',  'Paid by BiT on your behalf', 'var(--info)', 'fas fa-shield-alt'],
            ];
            foreach ($deductions as $d): ?>
            <div style="padding:18px;background:var(--bg-light);border-radius:var(--radius);border-left:4px solid <?= $d[3] ?>;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                    <i class="<?= $d[4] ?>" style="color:<?= $d[3] ?>;font-size:1.3rem;"></i>
                    <span style="font-weight:700;font-size:0.85rem;"><?= $d[0] ?></span>
                </div>
                <p style="font-size:1.4rem;font-weight:700;color:<?= $d[3] ?>;margin:0 0 4px;"><?= $d[1] ?></p>
                <p style="font-size:0.75rem;color:var(--gray-400);margin:0;"><?= $d[2] ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Net Pay Summary -->
        <div style="margin-top:20px;padding:18px;background:var(--success-light);border-radius:var(--radius);border:2px solid var(--success);display:flex;justify-content:space-between;align-items:center;">
            <div>
                <p style="font-size:0.82rem;color:var(--success);font-weight:700;margin:0;text-transform:uppercase;">June 2023 Net Pay</p>
                <p style="font-size:0.78rem;color:var(--gray-600);margin:4px 0 0;">Gross (14,000) − Pension (875) − Tax (1,948.25)</p>
            </div>
            <p style="font-size:2rem;font-weight:800;color:var(--success);margin:0;">ETB 11,176.75</p>
        </div>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

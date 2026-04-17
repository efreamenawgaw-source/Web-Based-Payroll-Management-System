<?php
$page_title = 'HR Personnel Dashboard';
$active_nav = 'dashboard';
$depth      = '../../';
require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <i class="fas fa-home"></i><span>/</span><span>HR</span><span>/</span><span>Dashboard</span>
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
            <h2>135</h2>
            <span class="stat-change up"><i class="fas fa-arrow-up"></i> 3 registered this month</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
            <p>Active Employees</p>
            <h2>128</h2>
            <span class="stat-change up"><i class="fas fa-check-circle"></i> 94.8% active rate</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-user-clock"></i></div>
        <div class="stat-info">
            <p>Pending Status Updates</p>
            <h2>1</h2>
            <span class="stat-change down"><i class="fas fa-exclamation-circle"></i> Needs review</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-user-plus"></i></div>
        <div class="stat-info">
            <p>New This Month</p>
            <h2>3</h2>
            <span class="stat-change up"><i class="fas fa-arrow-up"></i> Registered</span>
        </div>
    </div>
</div>

<!-- Grid -->
<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- Employee Management Actions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tasks" style="color:var(--primary);margin-right:8px"></i>Employee Management Actions</h3>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Name</th><th>Status</th><th>Action</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>101</td>
                            <td><strong>Admasu Dejene</strong></td>
                            <td><span class="badge badge-success">Active</span></td>
                            <td>Update Information</td>
                            <td class="text-muted">2023-06-23</td>
                        </tr>
                        <tr>
                            <td>102</td>
                            <td><strong>Bekele Abebe</strong></td>
                            <td><span class="badge badge-warning">On Leave</span></td>
                            <td>Register Employee</td>
                            <td class="text-muted">2023-06-23</td>
                        </tr>
                        <tr>
                            <td>103</td>
                            <td><strong>Chaltu Kebede</strong></td>
                            <td><span class="badge badge-danger">Terminated</span></td>
                            <td>Manage Allowances</td>
                            <td class="text-muted">2023-06-23</td>
                        </tr>
                        <tr>
                            <td>104</td>
                            <td><strong>Dawit Solomon</strong></td>
                            <td><span class="badge badge-success">Active</span></td>
                            <td>Manage Status</td>
                            <td class="text-muted">2023-06-23</td>
                        </tr>
                        <tr>
                            <td>105</td>
                            <td><strong>Eleni Tadesse</strong></td>
                            <td><span class="badge badge-success">Active</span></td>
                            <td>Manage Allowances</td>
                            <td class="text-muted">2023-06-23</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <a href="employees.php" class="btn btn-primary btn-sm">
                <i class="fas fa-list"></i> View All Employees
            </a>
        </div>
    </div>

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
                    <i class="fas fa-edit"></i> Update Employee Information
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
                <h4 style="margin-bottom:14px;font-size:0.9rem;color:var(--gray-600);">DEPARTMENT BREAKDOWN</h4>
                <?php
                $depts = [
                    ['name'=>'Academic Staff',       'count'=>72, 'pct'=>53],
                    ['name'=>'Administrative Staff', 'count'=>38, 'pct'=>28],
                    ['name'=>'Technical Staff',      'count'=>25, 'pct'=>19],
                ];
                foreach ($depts as $d): ?>
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;">
                        <span><?= $d['name'] ?></span>
                        <span style="font-weight:700;"><?= $d['count'] ?></span>
                    </div>
                    <div style="background:var(--gray-200);border-radius:20px;height:7px;">
                        <div style="width:<?= $d['pct'] ?>%;background:var(--primary);height:7px;border-radius:20px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<!-- Recent Registrations -->
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
                    <?php
                    $employees = [
                        ['EMP-101','Admasu Dejene','Computing','Lecturer','12,500','Active'],
                        ['EMP-102','Bekele Abebe','Engineering','Senior Lecturer','15,200','On Leave'],
                        ['EMP-103','Chaltu Kebede','Admin','HR Officer','9,800','Active'],
                    ];
                    foreach ($employees as $e): ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= $e[0] ?></span></td>
                        <td><strong><?= $e[1] ?></strong></td>
                        <td><?= $e[2] ?></td>
                        <td><?= $e[3] ?></td>
                        <td class="text-bold">ETB <?= $e[4] ?></td>
                        <td>
                            <span class="badge <?= $e[5]==='Active'?'badge-success':($e[5]==='On Leave'?'badge-warning':'badge-danger') ?>">
                                <?= $e[5] ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-secondary btn-sm btn-icon-only" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm btn-icon-only" title="Delete"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

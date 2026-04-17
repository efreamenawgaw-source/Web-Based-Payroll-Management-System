<?php
$page_title = 'Audit Log';
$active_nav = 'audit';
$depth      = '../../';
require_once $depth . 'includes/header.php';
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
        <i class="fas fa-print"></i> Export Log
    </button>
</div>

<!-- Filter Bar -->
<div class="card mb-3">
    <div class="card-body">
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="form-control" placeholder="Search by user or action...">
            </div>
            <select class="form-control" style="width:auto;">
                <option>All Actions</option>
                <option>Login</option>
                <option>Create</option>
                <option>Update</option>
                <option>Delete</option>
                <option>Payroll</option>
            </select>
            <select class="form-control" style="width:auto;">
                <option>All Roles</option>
                <option>Admin</option>
                <option>HR</option>
                <option>Finance</option>
                <option>Employee</option>
            </select>
            <input type="date" class="form-control" style="width:auto;" value="2023-06-23">
            <button class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>System Activity Log</h3>
        <span class="badge badge-primary">247 entries today</span>
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
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $logs = [
                        [247,'2023-06-23 14:32:05','System Admin','Admin','Login','Successful login','192.168.1.10','Success'],
                        [246,'2023-06-23 14:28:12','HR Manager','HR','Register Employee','Added EMP-136: Fatuma Ali','192.168.1.15','Success'],
                        [245,'2023-06-23 13:55:44','Finance Officer','Finance','Process Payroll','Calculated payroll for June 2023','192.168.1.20','Success'],
                        [244,'2023-06-23 13:40:22','Finance Officer','Finance','Verify Payroll','Verified June 2023 payroll','192.168.1.20','Success'],
                        [243,'2023-06-23 12:30:11','System Admin','Admin','Assign Role','Changed role: bekele.a → HR Personnel','192.168.1.10','Success'],
                        [242,'2023-06-23 12:15:08','HR Manager','HR','Update Employee','Updated salary: EMP-102 Bekele Abebe','192.168.1.15','Success'],
                        [241,'2023-06-23 11:50:33','admasu.d','Employee','View Payslip','Viewed June 2023 payslip','192.168.1.45','Success'],
                        [240,'2023-06-23 11:45:19','admasu.d','Employee','Download Payslip','Downloaded June 2023 PDF','192.168.1.45','Success'],
                        [239,'2023-06-23 10:22:07','unknown','—','Login Failed','Invalid credentials for: test_user','192.168.1.99','Failed'],
                        [238,'2023-06-23 09:15:55','System Admin','Admin','Delete User','Removed user: old.account','192.168.1.10','Success'],
                    ];
                    $action_colors = [
                        'Login'            => 'badge-primary',
                        'Login Failed'     => 'badge-danger',
                        'Register Employee'=> 'badge-success',
                        'Process Payroll'  => 'badge-info',
                        'Verify Payroll'   => 'badge-info',
                        'Assign Role'      => 'badge-warning',
                        'Update Employee'  => 'badge-gray',
                        'View Payslip'     => 'badge-primary',
                        'Download Payslip' => 'badge-primary',
                        'Delete User'      => 'badge-danger',
                    ];
                    foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-muted"><?= $log[0] ?></td>
                        <td style="font-size:0.8rem;white-space:nowrap;" class="text-muted"><?= $log[1] ?></td>
                        <td><strong><?= $log[2] ?></strong></td>
                        <td><span class="badge badge-gray"><?= $log[3] ?></span></td>
                        <td>
                            <span class="badge <?= $action_colors[$log[4]] ?? 'badge-gray' ?>">
                                <?= $log[4] ?>
                            </span>
                        </td>
                        <td style="font-size:0.82rem;max-width:220px;"><?= $log[5] ?></td>
                        <td style="font-size:0.8rem;" class="text-muted"><?= $log[6] ?></td>
                        <td>
                            <span class="badge <?= $log[7]==='Success'?'badge-success':'badge-danger' ?>">
                                <?= $log[7] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-between align-center">
        <span class="text-muted" style="font-size:0.8rem;">Showing 10 of 247 entries</span>
        <div class="pagination">
            <a href="#">&laquo;</a>
            <a href="#" class="active">1</a>
            <a href="#">2</a>
            <a href="#">3</a>
            <a href="#">...</a>
            <a href="#">25</a>
            <a href="#">&raquo;</a>
        </div>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

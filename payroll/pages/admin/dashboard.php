<?php
$page_title = 'Administrator Dashboard';
$active_nav = 'dashboard';
$depth      = '../../';
require_once $depth . 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <i class="fas fa-home"></i>
    <span>/</span>
    <span>Admin</span>
    <span>/</span>
    <span>Dashboard</span>
</div>

<div class="page-header">
    <h1>Administrator Dashboard</h1>
    <p>System overview — manage users, roles, and monitor activity.</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <p>Total Users</p>
            <h2>145</h2>
            <span class="stat-change up"><i class="fas fa-arrow-up"></i> 3 this month</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
            <p>Active Admins</p>
            <h2>5</h2>
            <span class="stat-change up"><i class="fas fa-circle" style="font-size:0.5rem"></i> All active</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-user-tag"></i></div>
        <div class="stat-info">
            <p>Pending Role Assignments</p>
            <h2>3</h2>
            <span class="stat-change down"><i class="fas fa-exclamation-circle"></i> Needs action</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-shield-alt"></i></div>
        <div class="stat-info">
            <p>Security Alerts</p>
            <h2>0</h2>
            <span class="stat-change up"><i class="fas fa-check-circle"></i> All clear</span>
        </div>
    </div>
</div>

<!-- Main Grid -->
<div class="grid-2" style="gap:24px; margin-bottom:24px;">

    <!-- Recent User Activity -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>Recent User Activity</h3>
            <a href="users.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Action</th>
                            <th>Role</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td><strong>Admin Nayaut</strong></td>
                            <td><span class="badge badge-primary">Create Account</span></td>
                            <td>Admin</td>
                            <td class="text-muted">2023-06-23 12:28</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td><strong>Admit Name</strong></td>
                            <td><span class="badge badge-info">Assign Role</span></td>
                            <td>HR Personnel</td>
                            <td class="text-muted">2023-06-23 19:36</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td><strong>Mator Rerman</strong></td>
                            <td><span class="badge badge-gray">Update User</span></td>
                            <td>Employee</td>
                            <td class="text-muted">2023-06-23 10:32</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td><strong>Admin Admin</strong></td>
                            <td><span class="badge badge-success">Assign Role</span></td>
                            <td>Finance</td>
                            <td class="text-muted">2023-06-23 10:33</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td><strong>Erlan Sayali</strong></td>
                            <td><span class="badge badge-warning">Delete User</span></td>
                            <td>Finance</td>
                            <td class="text-muted">2023-06-23 10:32</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt" style="color:var(--primary);margin-right:8px"></i>Quick Actions</h3>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:12px;">
                <a href="users.php" class="btn btn-primary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-user-plus"></i> Create New User Account
                </a>
                <a href="roles.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-user-tag"></i> Assign / Update Roles
                </a>
                <a href="audit.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-history"></i> View Audit Log
                </a>
                <a href="settings.php" class="btn btn-secondary w-100" style="justify-content:flex-start;">
                    <i class="fas fa-cog"></i> System Settings
                </a>
            </div>

            <!-- Role Distribution -->
            <div style="margin-top:24px;">
                <h4 style="margin-bottom:14px;font-size:0.9rem;color:var(--gray-600);">USER ROLE DISTRIBUTION</h4>
                <?php
                $roles_data = [
                    ['label'=>'Employees',        'count'=>120, 'pct'=>83, 'color'=>'var(--primary)'],
                    ['label'=>'HR Personnel',     'count'=>8,   'pct'=>5,  'color'=>'var(--success)'],
                    ['label'=>'Finance Officers', 'count'=>12,  'pct'=>8,  'color'=>'var(--warning)'],
                    ['label'=>'Administrators',   'count'=>5,   'pct'=>4,  'color'=>'var(--danger)'],
                ];
                foreach ($roles_data as $r): ?>
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;">
                        <span><?= $r['label'] ?></span>
                        <span style="font-weight:700;"><?= $r['count'] ?></span>
                    </div>
                    <div style="background:var(--gray-200);border-radius:20px;height:7px;">
                        <div style="width:<?= $r['pct'] ?>%;background:<?= $r['color'] ?>;height:7px;border-radius:20px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<!-- Pending Role Assignments -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-tag" style="color:var(--warning);margin-right:8px"></i>Pending Role Assignments</h3>
        <span class="badge badge-warning">3 Pending</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Username</th>
                        <th>Requested Role</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Bekele Abebe</strong></td>
                        <td>bekele.a</td>
                        <td><span class="badge badge-info">HR Personnel</span></td>
                        <td class="text-muted">2023-06-22</td>
                        <td>
                            <button class="btn btn-success btn-sm btn-icon-only" title="Approve"><i class="fas fa-check"></i></button>
                            <button class="btn btn-danger btn-sm btn-icon-only" title="Reject"><i class="fas fa-times"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Chaltu Kebede</strong></td>
                        <td>chaltu.k</td>
                        <td><span class="badge badge-warning">Finance Officer</span></td>
                        <td class="text-muted">2023-06-21</td>
                        <td>
                            <button class="btn btn-success btn-sm btn-icon-only" title="Approve"><i class="fas fa-check"></i></button>
                            <button class="btn btn-danger btn-sm btn-icon-only" title="Reject"><i class="fas fa-times"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Dawit Solomon</strong></td>
                        <td>dawit.s</td>
                        <td><span class="badge badge-primary">Admin</span></td>
                        <td class="text-muted">2023-06-20</td>
                        <td>
                            <button class="btn btn-success btn-sm btn-icon-only" title="Approve"><i class="fas fa-check"></i></button>
                            <button class="btn btn-danger btn-sm btn-icon-only" title="Reject"><i class="fas fa-times"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

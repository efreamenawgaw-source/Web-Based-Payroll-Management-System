<?php
$page_title = 'Manage Users';
$active_nav = 'users';
$depth      = '../../';
require_once $depth . 'includes/header.php';
?>
<div class="breadcrumb"><a href="dashboard.php">Admin</a><span>/</span><span>Manage Users</span></div>
<div class="page-header d-flex justify-between align-center">
    <div><h1>Manage Users</h1><p>Create, update, and delete system user accounts.</p></div>
    <button class="btn btn-primary" onclick="openModal('addUserModal')">
        <i class="fas fa-user-plus"></i> Add New User
    </button>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users" style="color:var(--primary);margin-right:8px"></i>All System Users</h3>
        <div class="filter-bar" style="margin:0;">
            <div class="search-box" style="min-width:220px;">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="form-control" placeholder="Search users...">
            </div>
            <select class="form-control" style="width:auto;">
                <option>All Roles</option>
                <option>Admin</option>
                <option>HR</option>
                <option>Finance</option>
                <option>Employee</option>
            </select>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>#</th><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php
                    $users = [
                        [1,'System Admin','admin','Admin','Active','2023-01-01'],
                        [2,'HR Manager','hr','HR Personnel','Active','2023-01-05'],
                        [3,'Finance Officer','finance','Finance','Active','2023-01-05'],
                        [4,'Admasu Dejene','admasu.d','Employee','Active','2023-02-10'],
                        [5,'Bekele Abebe','bekele.a','Employee','Active','2023-02-15'],
                        [6,'Chaltu Kebede','chaltu.k','Employee','Inactive','2023-03-01'],
                    ];
                    $role_badges = ['Admin'=>'badge-danger','HR Personnel'=>'badge-info','Finance'=>'badge-warning','Employee'=>'badge-primary'];
                    foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u[0] ?></td>
                        <td><strong><?= $u[1] ?></strong></td>
                        <td class="text-muted"><?= $u[2] ?></td>
                        <td><span class="badge <?= $role_badges[$u[3]] ?? 'badge-gray' ?>"><?= $u[3] ?></span></td>
                        <td><span class="badge <?= $u[4]==='Active'?'badge-success':'badge-danger' ?>"><?= $u[4] ?></span></td>
                        <td class="text-muted"><?= $u[5] ?></td>
                        <td>
                            <button class="btn btn-secondary btn-sm btn-icon-only" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-warning btn-sm btn-icon-only" title="Assign Role"><i class="fas fa-user-tag"></i></button>
                            <button class="btn btn-danger btn-sm btn-icon-only" title="Delete"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-between align-center">
        <span class="text-muted" style="font-size:0.8rem;">Showing 6 of 145 users</span>
        <div class="pagination">
            <a href="#">&laquo;</a>
            <a href="#" class="active">1</a>
            <a href="#">2</a>
            <a href="#">3</a>
            <a href="#">&raquo;</a>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px"></i>Create New User</h3>
            <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" placeholder="Enter full name">
            </div>
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" placeholder="Enter username">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" placeholder="Enter password">
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select class="form-control">
                    <option>Select Role</option>
                    <option>Admin</option>
                    <option>HR Personnel</option>
                    <option>Finance Officer</option>
                    <option>Employee</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
            <button class="btn btn-primary"><i class="fas fa-save"></i> Create User</button>
        </div>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

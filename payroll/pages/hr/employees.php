<?php
$page_title = 'All Employees';
$active_nav = 'employees';
$depth      = '../../';
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
        <i class="fas fa-user-plus"></i> Register New Employee
    </a>
</div>

<!-- Filter Bar -->
<div class="card mb-3">
    <div class="card-body">
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="form-control" placeholder="Search by name, ID, or department...">
            </div>
            <select class="form-control" style="width:auto;">
                <option>All Departments</option>
                <option>Faculty of Computing</option>
                <option>Faculty of Engineering</option>
                <option>Faculty of Science</option>
                <option>Administrative Office</option>
                <option>Finance Office</option>
                <option>HR Office</option>
            </select>
            <select class="form-control" style="width:auto;">
                <option>All Status</option>
                <option>Active</option>
                <option>On Leave</option>
                <option>Terminated</option>
                <option>Transferred</option>
            </select>
            <button class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-id-card" style="color:var(--primary);margin-right:8px"></i>All Staff Members</h3>
        <span class="badge badge-primary">135 Total</span>
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
                    <?php
                    $employees = [
                        ['EMP-101','Admasu Dejene',   'Faculty of Computing',  'Lecturer',          '12,500','Permanent','Active'],
                        ['EMP-102','Bekele Abebe',    'Faculty of Engineering','Senior Lecturer',   '15,200','Permanent','On Leave'],
                        ['EMP-103','Chaltu Kebede',   'Administrative Office', 'HR Officer',        '9,800', 'Permanent','Active'],
                        ['EMP-104','Dawit Solomon',   'Finance Office',        'Finance Officer',   '11,000','Permanent','Active'],
                        ['EMP-105','Eleni Tadesse',   'Faculty of Computing',  'Assistant Lecturer','13,500','Contract', 'Active'],
                        ['EMP-106','Fatuma Ali',      'Faculty of Science',    'Lecturer',          '12,000','Permanent','Active'],
                        ['EMP-107','Girma Haile',     'IT Support',            'Technician',        '8,500', 'Permanent','Active'],
                        ['EMP-108','Hana Tesfaye',    'Library',               'Librarian',         '9,200', 'Permanent','Transferred'],
                        ['EMP-109','Ibrahim Yusuf',   'Faculty of Engineering','Professor',         '22,000','Permanent','Active'],
                        ['EMP-110','Kidist Mekonnen', 'Administrative Office', 'Admin Officer',     '8,800', 'Contract', 'Active'],
                    ];
                    $status_badge = ['Active'=>'badge-success','On Leave'=>'badge-warning','Terminated'=>'badge-danger','Transferred'=>'badge-info'];
                    foreach ($employees as $e): ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= $e[0] ?></span></td>
                        <td><strong><?= $e[1] ?></strong></td>
                        <td><?= $e[2] ?></td>
                        <td><?= $e[3] ?></td>
                        <td class="text-bold">ETB <?= $e[4] ?></td>
                        <td><span class="badge badge-gray"><?= $e[5] ?></span></td>
                        <td>
                            <span class="badge <?= $status_badge[$e[6]] ?? 'badge-gray' ?>">
                                <?= $e[6] ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-secondary btn-sm btn-icon-only" title="View / Edit"
                                onclick="openModal('editModal')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-info btn-sm btn-icon-only" title="Manage Allowances">
                                <i class="fas fa-hand-holding-usd"></i>
                            </button>
                            <button class="btn btn-warning btn-sm btn-icon-only" title="Change Status">
                                <i class="fas fa-toggle-on"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-between align-center">
        <span class="text-muted" style="font-size:0.8rem;">Showing 10 of 135 employees</span>
        <div class="pagination">
            <a href="#">&laquo;</a>
            <a href="#" class="active">1</a>
            <a href="#">2</a>
            <a href="#">3</a>
            <a href="#">...</a>
            <a href="#">14</a>
            <a href="#">&raquo;</a>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);margin-right:8px"></i>Update Employee Information</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" value="Admasu Dejene">
                </div>
                <div class="form-group">
                    <label class="form-label">Employee ID</label>
                    <input type="text" class="form-control" value="EMP-101" readonly
                        style="background:var(--gray-100);">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select class="form-control">
                        <option selected>Faculty of Computing</option>
                        <option>Faculty of Engineering</option>
                        <option>Administrative Office</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <select class="form-control">
                        <option selected>Lecturer</option>
                        <option>Senior Lecturer</option>
                        <option>Professor</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Basic Salary (ETB)</label>
                    <input type="number" class="form-control" value="12500">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control">
                        <option selected>Active</option>
                        <option>On Leave</option>
                        <option>Transferred</option>
                        <option>Terminated</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
            <button class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

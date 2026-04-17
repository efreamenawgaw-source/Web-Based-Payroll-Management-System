<?php
$page_title = 'Employee Status';
$active_nav = 'status';
$depth      = '../../';

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $success = 'Status updated to "' . htmlspecialchars($_POST['new_status'] ?? '') . '" for ' . htmlspecialchars($_POST['emp_name'] ?? 'employee') . '.';
}

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">HR</a><span>/</span><span>Employee Status</span>
</div>

<div class="page-header">
    <h1>Manage Employee Status</h1>
    <p>Update employment status — active, on leave, transferred, promoted, or terminated.</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<!-- Status Summary -->
<div class="stats-grid" style="margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
            <p>Active</p>
            <h2>128</h2>
            <span class="stat-change up">94.8% of staff</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-user-clock"></i></div>
        <div class="stat-info">
            <p>On Leave</p>
            <h2>3</h2>
            <span class="stat-change down">2.2% of staff</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-exchange-alt"></i></div>
        <div class="stat-info">
            <p>Transferred</p>
            <h2>2</h2>
            <span class="stat-change up">1.5% of staff</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-user-times"></i></div>
        <div class="stat-info">
            <p>Terminated</p>
            <h2>2</h2>
            <span class="stat-change down">1.5% of staff</span>
        </div>
    </div>
</div>

<div class="grid-2" style="gap:24px;">

    <!-- Update Status Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-toggle-on" style="color:var(--primary);margin-right:8px"></i>Update Employee Status</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Select Employee <span style="color:var(--danger)">*</span></label>
                    <select name="emp_id" class="form-control" required>
                        <option value="">-- Select Employee --</option>
                        <option value="EMP-101">EMP-101 — Admasu Dejene (Active)</option>
                        <option value="EMP-102">EMP-102 — Bekele Abebe (On Leave)</option>
                        <option value="EMP-103">EMP-103 — Chaltu Kebede (Active)</option>
                        <option value="EMP-104">EMP-104 — Dawit Solomon (Active)</option>
                        <option value="EMP-105">EMP-105 — Eleni Tadesse (Active)</option>
                        <option value="EMP-108">EMP-108 — Hana Tesfaye (Transferred)</option>
                    </select>
                    <input type="hidden" name="emp_name" value="Bekele Abebe">
                </div>

                <div class="form-group">
                    <label class="form-label">Current Status</label>
                    <div style="padding:10px 14px;background:var(--bg-light);border-radius:7px;border:1.5px solid var(--gray-200);">
                        <span class="badge badge-warning">On Leave</span>
                        <span style="font-size:0.82rem;color:var(--gray-600);margin-left:8px;">Since: 2023-06-01</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">New Status <span style="color:var(--danger)">*</span></label>
                    <select name="new_status" class="form-control" required>
                        <option value="">-- Select New Status --</option>
                        <option value="Active">Active</option>
                        <option value="On Leave">On Leave</option>
                        <option value="Transferred">Transferred</option>
                        <option value="Promoted">Promoted</option>
                        <option value="Terminated">Terminated</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Effective Date</label>
                    <input type="date" name="effective_date" class="form-control"
                        value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Reason / Notes</label>
                    <textarea name="reason" class="form-control" rows="3"
                        placeholder="Reason for status change..."></textarea>
                </div>

                <button type="submit" name="update_status" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </form>
        </div>
    </div>

    <!-- Status History -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>Recent Status Changes</h3>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Date</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $history = [
                            ['Bekele Abebe',  'Active',      'On Leave',    '2023-06-01','HR Manager'],
                            ['Hana Tesfaye',  'Active',      'Transferred', '2023-05-15','HR Manager'],
                            ['Chaltu Kebede', 'Terminated',  'Active',      '2023-05-01','HR Manager'],
                            ['Dawit Solomon', 'On Leave',    'Active',      '2023-04-20','HR Manager'],
                            ['Fatuma Ali',    'Active',      'Promoted',    '2023-04-01','HR Manager'],
                        ];
                        $badge = ['Active'=>'badge-success','On Leave'=>'badge-warning','Terminated'=>'badge-danger','Transferred'=>'badge-info','Promoted'=>'badge-primary'];
                        foreach ($history as $h): ?>
                        <tr>
                            <td><strong><?= $h[0] ?></strong></td>
                            <td><span class="badge <?= $badge[$h[1]] ?? 'badge-gray' ?>"><?= $h[1] ?></span></td>
                            <td><span class="badge <?= $badge[$h[2]] ?? 'badge-gray' ?>"><?= $h[2] ?></span></td>
                            <td class="text-muted"><?= $h[3] ?></td>
                            <td class="text-muted"><?= $h[4] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Status Rules Info -->
        <div class="card-footer">
            <div style="font-size:0.8rem;color:var(--gray-600);">
                <strong style="color:var(--primary);">Note:</strong>
                Only <strong>Active</strong> employees are included in payroll processing.
                Terminated employees are excluded from all future payroll cycles.
            </div>
        </div>
    </div>

</div>

<?php require_once $depth . 'includes/footer.php'; ?>

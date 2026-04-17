<?php
$page_title = 'Manage Allowances';
$active_nav = 'allowances';
$depth      = '../../';

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_allowances'])) {
    $success = 'Allowances updated successfully for ' . htmlspecialchars($_POST['emp_name'] ?? 'employee') . '.';
}

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">HR</a><span>/</span><span>Manage Allowances</span>
</div>

<div class="page-header">
    <h1>Manage Employee Allowances</h1>
    <p>Define and update housing, transport, position, and other allowances for staff members.</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<div class="grid-2" style="gap:24px;">

    <!-- Select Employee & Update Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-hand-holding-usd" style="color:var(--success);margin-right:8px"></i>Update Allowances</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Select Employee <span style="color:var(--danger)">*</span></label>
                    <select name="emp_id" class="form-control" required>
                        <option value="">-- Select Employee --</option>
                        <option value="EMP-101" selected>EMP-101 — Admasu Dejene</option>
                        <option value="EMP-102">EMP-102 — Bekele Abebe</option>
                        <option value="EMP-103">EMP-103 — Chaltu Kebede</option>
                        <option value="EMP-104">EMP-104 — Dawit Solomon</option>
                        <option value="EMP-105">EMP-105 — Eleni Tadesse</option>
                    </select>
                    <input type="hidden" name="emp_name" value="Admasu Dejene">
                </div>

                <!-- Current Allowances (pre-filled) -->
                <div style="background:var(--bg-light);border-radius:var(--radius);padding:14px;margin-bottom:18px;">
                    <p style="font-size:0.78rem;color:var(--gray-600);margin:0 0 8px;font-weight:700;text-transform:uppercase;">
                        Current Basic Salary
                    </p>
                    <p style="font-size:1.2rem;font-weight:700;color:var(--primary);margin:0;">ETB 12,500.00</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Housing Allowance (ETB)</label>
                        <input type="number" name="housing" class="form-control"
                            value="1000" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Transport Allowance (ETB)</label>
                        <input type="number" name="transport" class="form-control"
                            value="500" min="0" step="0.01">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Position Allowance (ETB)</label>
                        <input type="number" name="position_allowance" class="form-control"
                            value="0" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Teaching Allowance (ETB)</label>
                        <input type="number" name="teaching" class="form-control"
                            value="0" min="0" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Other Allowance (ETB)</label>
                    <input type="number" name="other" class="form-control"
                        value="0" min="0" step="0.01">
                    <span class="form-hint">Any additional allowance not listed above</span>
                </div>

                <!-- Calculated Preview -->
                <div style="background:var(--success-light);border-radius:var(--radius);padding:14px;margin-bottom:18px;border-left:4px solid var(--success);">
                    <p style="font-size:0.78rem;color:var(--success);margin:0 0 4px;font-weight:700;">ESTIMATED GROSS SALARY</p>
                    <p style="font-size:1.3rem;font-weight:700;color:var(--success);margin:0;">ETB 14,000.00</p>
                    <p style="font-size:0.75rem;color:var(--gray-600);margin:4px 0 0;">Basic (12,500) + Allowances (1,500)</p>
                </div>

                <button type="submit" name="save_allowances" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> Save Allowances
                </button>
            </form>
        </div>
    </div>

    <!-- Allowances Summary Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list" style="color:var(--primary);margin-right:8px"></i>Current Allowances Overview</h3>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Housing</th>
                            <th>Transport</th>
                            <th>Position</th>
                            <th>Total Allow.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $allowances = [
                            ['Admasu Dejene',   '1,000','500','0',    '1,500'],
                            ['Bekele Abebe',    '1,500','700','500',  '2,700'],
                            ['Chaltu Kebede',   '800',  '400','0',    '1,200'],
                            ['Dawit Solomon',   '1,000','500','200',  '1,700'],
                            ['Eleni Tadesse',   '1,200','600','300',  '2,100'],
                            ['Fatuma Ali',      '1,000','500','0',    '1,500'],
                            ['Girma Haile',     '600',  '300','0',    '900'],
                        ];
                        foreach ($allowances as $a): ?>
                        <tr>
                            <td><strong><?= $a[0] ?></strong></td>
                            <td><?= $a[1] ?></td>
                            <td><?= $a[2] ?></td>
                            <td><?= $a[3] ?></td>
                            <td class="text-bold text-success">ETB <?= $a[4] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info Box -->
        <div class="card-footer">
            <div class="alert alert-info" style="margin:0;">
                <i class="fas fa-info-circle"></i>
                <div style="font-size:0.82rem;">
                    Allowances are added to basic salary to compute <strong>Gross Salary</strong>.
                    Pension (7%) is calculated on basic salary only. Tax is applied on taxable income
                    (Gross − Pension).
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once $depth . 'includes/footer.php'; ?>

<?php
$page_title = 'System Settings';
$active_nav = 'settings';
$depth      = '../../';

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = 'Settings saved successfully.';
}

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">Admin</a><span>/</span><span>Settings</span>
</div>

<div class="page-header">
    <h1>System Settings</h1>
    <p>Configure system-wide settings, tax brackets, and institutional information.</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<form method="POST" action="">
<div class="grid-2" style="gap:24px;">

    <!-- Institution Info -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-university" style="color:var(--primary);margin-right:8px"></i>Institution Information</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Institution Name</label>
                <input type="text" name="inst_name" class="form-control" value="Bahir Dar Institute of Technology">
            </div>
            <div class="form-group">
                <label class="form-label">Short Name / Acronym</label>
                <input type="text" name="inst_short" class="form-control" value="BiT">
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <input type="text" name="inst_address" class="form-control" value="Bahir Dar, Amhara Region, Ethiopia">
            </div>
            <div class="form-group">
                <label class="form-label">Contact Email</label>
                <input type="email" name="inst_email" class="form-control" value="payroll@bit.edu.et">
            </div>
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="text" name="inst_phone" class="form-control" value="+251 58 220 6112">
            </div>
        </div>
    </div>

    <!-- Payroll Settings -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-cog" style="color:var(--primary);margin-right:8px"></i>Payroll Configuration</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Employee Pension Rate (%)</label>
                <input type="number" name="pension_emp" class="form-control" value="7" min="0" max="100" step="0.1">
                <span class="form-hint">Standard: 7% of basic salary</span>
            </div>
            <div class="form-group">
                <label class="form-label">Employer Pension Rate (%)</label>
                <input type="number" name="pension_org" class="form-control" value="11" min="0" max="100" step="0.1">
                <span class="form-hint">Standard: 11% of basic salary</span>
            </div>
            <div class="form-group">
                <label class="form-label">Payroll Processing Day</label>
                <select name="payroll_day" class="form-control">
                    <option value="25">25th of each month</option>
                    <option value="28" selected>28th of each month</option>
                    <option value="30">Last day of month</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Currency</label>
                <select name="currency" class="form-control">
                    <option value="ETB" selected>ETB — Ethiopian Birr</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Session Timeout (minutes)</label>
                <input type="number" name="session_timeout" class="form-control" value="15" min="5" max="120">
                <span class="form-hint">Auto-logout after inactivity</span>
            </div>
        </div>
    </div>

</div>

<!-- Ethiopian Tax Brackets -->
<div class="card mt-3">
    <div class="card-header">
        <h3><i class="fas fa-percent" style="color:var(--warning);margin-right:8px"></i>Revised Monthly Employment Income Tax Brackets (2025)</h3>
        <span class="badge badge-warning">Read Only — Set by Law</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Bracket</th>
                        <th>Monthly Taxable Income (ETB)</th>
                        <th>Tax Rate</th>
                        <th>Deduction (ETB)</th>
                        <th>Formula</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Revised 2025 brackets
                    $brackets = [
                        [1, '0 — 2,000',       '0% (Exempt)', '0',       'Tax = 0'],
                        [2, '2,001 — 4,000',   '15%',         '300',     'Tax = (Income × 0.15) − 300'],
                        [3, '4,001 — 7,000',   '20%',         '500',     'Tax = (Income × 0.20) − 500'],
                        [4, '7,001 — 10,000',  '25%',         '850',     'Tax = (Income × 0.25) − 850'],
                        [5, '10,001 — 14,000', '30%',         '1,350',   'Tax = (Income × 0.30) − 1,350'],
                        [6, 'Over 14,000',     '35%',         '2,050',   'Tax = (Income × 0.35) − 2,050'],
                    ];
                    $badge_colors = [1=>'badge-success',2=>'badge-info',3=>'badge-primary',4=>'badge-warning',5=>'badge-warning',6=>'badge-danger'];
                    foreach ($brackets as $b): ?>
                    <tr>
                        <td><span class="badge <?= $badge_colors[$b[0]] ?>"><?= $b[0] ?></span></td>
                        <td><strong><?= $b[1] ?></strong></td>
                        <td><span class="badge <?= $badge_colors[$b[0]] ?>"><?= $b[2] ?></span></td>
                        <td>ETB <?= $b[3] ?></td>
                        <td style="font-size:0.8rem;font-family:monospace;color:var(--gray-600);"><?= $b[4] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:10px 16px;background:var(--success-light);border-top:1px solid var(--gray-200);font-size:0.8rem;color:var(--success);">
            <i class="fas fa-arrow-up"></i>
            <strong>2025 Update:</strong> Exempt threshold raised from ETB 600 → <strong>ETB 2,000/month</strong>.
            The 10% bracket has been removed. Minimum taxable rate is now 15%.
        </div>
    </div>
</div>

<!-- Save Button -->
<div class="card mt-3">
    <div class="card-body d-flex gap-2" style="justify-content:flex-end;">
        <button type="reset" class="btn btn-secondary">
            <i class="fas fa-undo"></i> Reset
        </button>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Settings
        </button>
    </div>
</div>

</form>

<?php require_once $depth . 'includes/footer.php'; ?>

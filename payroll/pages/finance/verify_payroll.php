<?php
$page_title = 'Verify Payroll';
$active_nav = 'verify';
$depth      = '../../';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $success = 'Payroll for ' . htmlspecialchars($_POST['period'] ?? '') . ' has been verified and approved. Payslips can now be generated.';
    } elseif (isset($_POST['reject'])) {
        $error = 'Payroll for ' . htmlspecialchars($_POST['period'] ?? '') . ' has been marked as "Not Verified" and sent back for re-processing.';
    }
}

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">Finance</a><span>/</span><span>Verify Payroll</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Verify Payroll</h1>
        <p>Review and verify processed payroll data before generating payslips.</p>
    </div>
    <a href="process_payroll.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Process
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div>
<?php endif; ?>

<!-- Period Selection -->
<div class="card mb-3">
    <div class="card-header">
        <h3><i class="fas fa-calendar-check" style="color:var(--primary);margin-right:8px"></i>Select Payroll Period to Verify</h3>
    </div>
    <div class="card-body">
        <div class="filter-bar">
            <select class="form-control" style="width:auto;">
                <option selected>June 2023 — Processed</option>
                <option>May 2023 — Verified</option>
                <option>April 2023 — Verified</option>
            </select>
            <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Load Payroll</button>
        </div>

        <!-- Status Banner -->
        <div style="margin-top:16px;padding:14px 18px;background:var(--warning-light);border-radius:var(--radius);border-left:4px solid var(--warning);display:flex;align-items:center;gap:12px;">
            <i class="fas fa-clock" style="color:var(--warning);font-size:1.3rem;"></i>
            <div>
                <p style="font-weight:700;color:var(--warning);margin:0;">Pending Verification — June 2023</p>
                <p style="font-size:0.82rem;color:var(--gray-600);margin:0;">Processed on 2023-06-23 13:55 by Finance Officer. Awaiting your review.</p>
            </div>
        </div>
    </div>
</div>

<!-- Payroll Summary -->
<div class="grid-2" style="gap:24px;margin-bottom:24px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie" style="color:var(--primary);margin-right:8px"></i>Payroll Summary — June 2023</h3>
        </div>
        <div class="card-body">
            <?php
            $summary = [
                ['Total Employees Processed', '135',          'var(--primary)'],
                ['Total Gross Salary',        'ETB 1,687,500','var(--primary)'],
                ['Total Employee Pension (7%)','ETB 118,125', 'var(--warning)'],
                ['Total Employer Pension (11%)','ETB 185,625','var(--info)'],
                ['Total Income Tax',          'ETB 284,985',  'var(--danger)'],
                ['Total Net Pay',             'ETB 1,284,390','var(--success)'],
            ];
            foreach ($summary as $s): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--gray-200);">
                <span style="font-size:0.875rem;color:var(--gray-600);"><?= $s[0] ?></span>
                <span style="font-weight:700;color:<?= $s[2] ?>;"><?= $s[1] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Verification Checklist -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clipboard-check" style="color:var(--primary);margin-right:8px"></i>Verification Checklist</h3>
        </div>
        <div class="card-body">
            <?php
            $checks = [
                ['All active employees included (135/135)',    true],
                ['Pension rates applied correctly (7% / 11%)', true],
                ['Ethiopian tax brackets applied',             true],
                ['No negative net pay values',                 true],
                ['Allowances correctly added to gross',        true],
                ['No duplicate payroll entries',               true],
                ['Period matches selected month',              true],
            ];
            foreach ($checks as $c): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <i class="fas <?= $c[1] ? 'fa-check-circle' : 'fa-times-circle' ?>"
                   style="color:<?= $c[1] ? 'var(--success)' : 'var(--danger)' ?>;font-size:1.1rem;"></i>
                <span style="font-size:0.875rem;"><?= $c[0] ?></span>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:16px;padding:12px;background:var(--success-light);border-radius:var(--radius);text-align:center;">
                <i class="fas fa-check-circle" style="color:var(--success);font-size:1.5rem;"></i>
                <p style="color:var(--success);font-weight:700;margin:6px 0 0;">All checks passed — Ready for approval</p>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Payroll Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-table" style="color:var(--primary);margin-right:8px"></i>Detailed Payroll Review — June 2023</h3>
        <span class="badge badge-warning">Pending Verification</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Basic (ETB)</th>
                        <th>Gross (ETB)</th>
                        <th>Pension 7% (ETB)</th>
                        <th>Tax (ETB)</th>
                        <th>Net Pay (ETB)</th>
                        <th>Check</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Revised 2025 tax brackets
                    function verifyCalcTax(float $taxable): float {
                        if ($taxable <= 2000)  return 0.00;
                        if ($taxable <= 4000)  return ($taxable * 0.15) - 300.00;
                        if ($taxable <= 7000)  return ($taxable * 0.20) - 500.00;
                        if ($taxable <= 10000) return ($taxable * 0.25) - 850.00;
                        if ($taxable <= 14000) return ($taxable * 0.30) - 1350.00;
                        return ($taxable * 0.35) - 2050.00;
                    }
                    $payroll = [
                        ['EMP-101','Admasu Dejene',  12500, 14000],
                        ['EMP-102','Bekele Abebe',   15200, 17900],
                        ['EMP-103','Chaltu Kebede',  9800,  11000],
                        ['EMP-104','Dawit Solomon',  11000, 12700],
                        ['EMP-105','Eleni Tadesse',  13500, 15600],
                    ];
                    foreach ($payroll as $p):
                        $gross       = $p[3];
                        $pension_emp = round($p[2] * 0.07, 2);
                        $taxable     = round($gross - $pension_emp, 2);
                        $tax         = round(verifyCalcTax($taxable), 2);
                        $net         = round($taxable - $tax, 2);
                    ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= $p[0] ?></span></td>
                        <td><strong><?= $p[1] ?></strong></td>
                        <td><?= number_format($p[2], 2) ?></td>
                        <td><?= number_format($gross, 2) ?></td>
                        <td class="text-warning"><?= number_format($pension_emp, 2) ?></td>
                        <td class="text-danger"><?= number_format($tax, 2) ?></td>
                        <td class="text-bold text-success"><?= number_format($net, 2) ?></td>
                        <td><i class="fas fa-check-circle" style="color:var(--success);"></i></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Approve / Reject Actions -->
    <div class="card-footer">
        <form method="POST" action="" style="display:flex;gap:12px;justify-content:flex-end;">
            <input type="hidden" name="period" value="June 2023">
            <button type="submit" name="reject" class="btn btn-danger"
                onclick="return confirm('Reject this payroll and send back for re-processing?')">
                <i class="fas fa-times"></i> Reject — Send Back
            </button>
            <button type="submit" name="approve" class="btn btn-success"
                onclick="return confirm('Approve and finalize this payroll? Payslips will be generated.')">
                <i class="fas fa-check-double"></i> Approve & Finalize
            </button>
        </form>
    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>

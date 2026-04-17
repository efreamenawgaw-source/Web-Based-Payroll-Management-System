<?php
$page_title = 'My Payslips';
$active_nav = 'payslips';
$depth      = '../../';

// 2025 tax calculation for display
function payslipTax(float $taxable): float {
    if ($taxable <= 2000)  return 0.00;
    if ($taxable <= 4000)  return ($taxable * 0.15) - 300.00;
    if ($taxable <= 7000)  return ($taxable * 0.20) - 500.00;
    if ($taxable <= 10000) return ($taxable * 0.25) - 850.00;
    if ($taxable <= 14000) return ($taxable * 0.30) - 1350.00;
    return ($taxable * 0.35) - 2050.00;
}

// EMP-101 sample: basic 12,500 | housing 1,000 | transport 500
$basic      = 12500;
$housing    = 1000;
$transport  = 500;
$gross      = $basic + $housing + $transport;          // 14,000
$pension    = round($basic * 0.07, 2);                 // 875
$emp_pension_org = round($basic * 0.11, 2);            // 1,375
$taxable    = round($gross - $pension, 2);             // 13,125
$tax        = round(payslipTax($taxable), 2);          // 2,587.50
$net        = round($taxable - $tax, 2);               // 10,537.50

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">Employee</a><span>/</span><span>My Payslips</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>My Payslips</h1>
        <p>View and download your monthly payslips.</p>
    </div>
    <select class="form-control" style="width:auto;">
        <option>All Years</option>
        <option selected><?= date('Y') ?></option>
        <option><?= date('Y') - 1 ?></option>
    </select>
</div>

<!-- Payslip History Table -->
<div class="card">
    <div class="card-header">
        <h3>
            <i class="fas fa-file-invoice" style="color:var(--primary);margin-right:8px"></i>
            Payslip History
        </h3>
        <span class="badge badge-primary"><?= date('Y') ?></span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Gross (ETB)</th>
                        <th>Pension 7% (ETB)</th>
                        <th>Tax (ETB)</th>
                        <th>Net Pay (ETB)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $months = ['June','May','April','March','February','January'];
                    foreach ($months as $m): ?>
                    <tr>
                        <td><strong><?= $m ?> <?= date('Y') ?></strong></td>
                        <td><?= number_format($gross, 2) ?></td>
                        <td class="text-warning"><?= number_format($pension, 2) ?></td>
                        <td class="text-danger"><?= number_format($tax, 2) ?></td>
                        <td class="text-bold text-success"><?= number_format($net, 2) ?></td>
                        <td><span class="badge badge-success">Available</span></td>
                        <td>
                            <button class="btn btn-secondary btn-sm view-payslip-btn"
                                    data-month="<?= $m ?>" title="View Payslip">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn btn-primary btn-sm" title="Download PDF"
                                    onclick="window.print()">
                                <i class="fas fa-download"></i> PDF
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <div style="font-size:0.8rem;color:var(--gray-600);display:flex;align-items:center;gap:6px;">
            <i class="fas fa-info-circle" style="color:var(--info);"></i>
            Tax calculated per <strong>Revised Monthly Employment Tax Brackets 2025</strong>.
            Exempt up to ETB 2,000 taxable income.
        </div>
    </div>
</div>

<!-- ===== Payslip View Modal ===== -->
<div class="modal-overlay" id="payslipModal">
    <div class="modal" style="max-width:620px;">
        <div class="modal-header">
            <h3>
                <i class="fas fa-file-invoice-dollar" style="color:var(--primary);margin-right:8px"></i>
                Payslip — <span id="modalMonth">June</span> <?= date('Y') ?>
            </h3>
            <button class="modal-close" onclick="closeModal('payslipModal')">&times;</button>
        </div>
        <div class="modal-body" id="payslipPrintArea">

            <!-- Payslip Header -->
            <div style="text-align:center;padding:16px;background:var(--primary);border-radius:var(--radius);margin-bottom:16px;">
                <div style="font-size:1.6rem;font-weight:900;color:var(--white);letter-spacing:-1px;">BiT</div>
                <div style="font-weight:700;font-size:1rem;color:var(--white);">Bahir Dar Institute of Technology</div>
                <div style="font-size:0.82rem;color:rgba(255,255,255,0.80);">
                    PAYSLIP — <span id="modalMonthHeader">JUNE</span> <?= strtoupper(date('Y')) ?>
                </div>
            </div>

            <!-- Employee Info Grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;background:var(--bg-light);padding:14px;border-radius:var(--radius);">
                <?php
                $emp_info = [
                    ['Employee Name', 'Admasu Dejene'],
                    ['Employee ID',   'EMP-101'],
                    ['Department',    'Faculty of Computing'],
                    ['Position',      'Lecturer'],
                    ['Bank Account',  'CBE — 1000XXXXXXXX'],
                    ['Pay Period',    '<span id="modalMonthInfo">June</span> ' . date('Y')],
                ];
                foreach ($emp_info as $ei): ?>
                <div>
                    <p style="font-size:0.7rem;color:var(--gray-400);margin:0;text-transform:uppercase;"><?= $ei[0] ?></p>
                    <p style="font-weight:600;margin:0;font-size:0.88rem;"><?= $ei[1] ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Earnings & Deductions Table -->
            <table style="width:100%;font-size:0.875rem;border-collapse:collapse;margin-bottom:12px;">
                <!-- Earnings -->
                <tr style="background:var(--bg-light);">
                    <th style="padding:9px 12px;text-align:left;color:var(--primary);font-size:0.78rem;text-transform:uppercase;">Earnings</th>
                    <th style="padding:9px 12px;text-align:right;color:var(--primary);font-size:0.78rem;text-transform:uppercase;">Amount (ETB)</th>
                </tr>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:9px 12px;">Basic Salary</td>
                    <td style="padding:9px 12px;text-align:right;"><?= number_format($basic, 2) ?></td>
                </tr>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:9px 12px;">Housing Allowance</td>
                    <td style="padding:9px 12px;text-align:right;"><?= number_format($housing, 2) ?></td>
                </tr>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:9px 12px;">Transport Allowance</td>
                    <td style="padding:9px 12px;text-align:right;"><?= number_format($transport, 2) ?></td>
                </tr>
                <tr style="background:var(--bg-light);font-weight:700;border-bottom:2px solid var(--accent-light);">
                    <td style="padding:9px 12px;">Gross Salary</td>
                    <td style="padding:9px 12px;text-align:right;color:var(--primary);"><?= number_format($gross, 2) ?></td>
                </tr>

                <!-- Deductions -->
                <tr style="background:var(--bg-light);">
                    <th style="padding:9px 12px;text-align:left;color:var(--danger);font-size:0.78rem;text-transform:uppercase;">Deductions</th>
                    <th style="padding:9px 12px;text-align:right;color:var(--danger);font-size:0.78rem;text-transform:uppercase;">Amount (ETB)</th>
                </tr>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:9px 12px;">Employee Pension (7% of basic)</td>
                    <td style="padding:9px 12px;text-align:right;color:var(--warning);"><?= number_format($pension, 2) ?></td>
                </tr>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:9px 12px;">
                        Income Tax
                        <span style="font-size:0.72rem;color:var(--gray-400);">
                            (Taxable: ETB <?= number_format($taxable, 2) ?> — 2025 Brackets)
                        </span>
                    </td>
                    <td style="padding:9px 12px;text-align:right;color:var(--danger);"><?= number_format($tax, 2) ?></td>
                </tr>

                <!-- Net Pay -->
                <tr style="background:var(--success-light);font-weight:700;">
                    <td style="padding:12px;color:var(--success);font-size:1rem;">NET PAY</td>
                    <td style="padding:12px;text-align:right;color:var(--success);font-size:1.2rem;"><?= number_format($net, 2) ?></td>
                </tr>
            </table>

            <!-- Employer contribution note -->
            <div style="padding:10px 12px;background:var(--info-light);border-radius:var(--radius);font-size:0.78rem;color:var(--info);text-align:center;">
                <i class="fas fa-shield-alt"></i>
                Employer Pension Contribution (11% of basic): <strong>ETB <?= number_format($emp_pension_org, 2) ?></strong> — paid by BiT on your behalf
            </div>

            <!-- Tax note -->
            <div style="margin-top:8px;font-size:0.72rem;color:var(--gray-400);text-align:center;">
                Tax calculated per Revised Monthly Employment Tax Brackets (Ethiopia, 2025)
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('payslipModal')">
                <i class="fas fa-times"></i> Close
            </button>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-download"></i> Download PDF
            </button>
        </div>
    </div>
</div>

<script>
// Open payslip modal and update month label
document.querySelectorAll('.view-payslip-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var month = this.getAttribute('data-month');
        document.getElementById('modalMonth').textContent     = month;
        document.getElementById('modalMonthHeader').textContent = month.toUpperCase();
        document.getElementById('modalMonthInfo').textContent = month;
        openModal('payslipModal');
    });
});
</script>

<?php require_once $depth . 'includes/footer.php'; ?>

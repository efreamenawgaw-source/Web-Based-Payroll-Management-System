<?php
$page_title = 'My Payslips';
$active_nav = 'payslips';
$depth      = '../../';
require_once $depth . 'includes/header.php';
?>
<div class="breadcrumb"><a href="dashboard.php">Employee</a><span>/</span><span>My Payslips</span></div>
<div class="page-header"><h1>My Payslips</h1><p>View and download your monthly payslips.</p></div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-invoice" style="color:var(--primary);margin-right:8px"></i>Payslip History</h3>
        <select class="form-control" style="width:auto;">
            <option>All Years</option>
            <option selected>2023</option>
            <option>2022</option>
        </select>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Period</th><th>Gross (ETB)</th><th>Pension (ETB)</th>
                        <th>Tax (ETB)</th><th>Net Pay (ETB)</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $months = ['June','May','April','March','February','January'];
                    foreach ($months as $m): ?>
                    <tr>
                        <td><strong><?= $m ?> 2023</strong></td>
                        <td>80,000.00</td>
                        <td class="text-warning">7,200.00</td>
                        <td class="text-danger">21,110.00</td>
                        <td class="text-bold text-success">5,210.00</td>
                        <td><span class="badge badge-success">Available</span></td>
                        <td>
                            <button class="btn btn-secondary btn-sm" title="View Payslip">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn btn-primary btn-sm" title="Download PDF">
                                <i class="fas fa-download"></i> PDF
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payslip View Modal -->
<div class="modal-overlay" id="payslipModal">
    <div class="modal" style="max-width:640px;">
        <div class="modal-header">
            <h3>Payslip — June 2023</h3>
            <button class="modal-close" onclick="closeModal('payslipModal')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Payslip Header -->
            <div style="text-align:center;padding:16px 0;border-bottom:2px solid var(--primary);margin-bottom:16px;">
                <div style="font-size:1.3rem;font-weight:800;color:var(--primary);">BiT</div>
                <div style="font-weight:700;font-size:1rem;">Bahir Dar Institute of Technology</div>
                <div style="color:var(--gray-600);font-size:0.85rem;">Payslip for June 2023</div>
            </div>
            <div class="grid-2" style="gap:16px;margin-bottom:16px;">
                <div>
                    <p style="font-size:0.78rem;color:var(--gray-400);margin:0;">Employee Name</p>
                    <p style="font-weight:600;margin:0;">Admasu Dejene</p>
                </div>
                <div>
                    <p style="font-size:0.78rem;color:var(--gray-400);margin:0;">Employee ID</p>
                    <p style="font-weight:600;margin:0;">EMP-101</p>
                </div>
                <div>
                    <p style="font-size:0.78rem;color:var(--gray-400);margin:0;">Department</p>
                    <p style="font-weight:600;margin:0;">Faculty of Computing</p>
                </div>
                <div>
                    <p style="font-size:0.78rem;color:var(--gray-400);margin:0;">Position</p>
                    <p style="font-weight:600;margin:0;">Lecturer</p>
                </div>
            </div>
            <table style="width:100%;font-size:0.875rem;">
                <tr style="background:var(--bg-light);">
                    <th style="padding:8px 12px;text-align:left;color:var(--primary);">Earnings</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--primary);">Amount (ETB)</th>
                </tr>
                <tr><td style="padding:8px 12px;">Basic Salary</td><td style="padding:8px 12px;text-align:right;">78,500.00</td></tr>
                <tr><td style="padding:8px 12px;">Housing Allowance</td><td style="padding:8px 12px;text-align:right;">1,000.00</td></tr>
                <tr><td style="padding:8px 12px;">Transport Allowance</td><td style="padding:8px 12px;text-align:right;">500.00</td></tr>
                <tr style="font-weight:700;background:var(--bg-light);">
                    <td style="padding:8px 12px;">Gross Salary</td>
                    <td style="padding:8px 12px;text-align:right;color:var(--primary);">80,000.00</td>
                </tr>
                <tr style="background:var(--bg-light);">
                    <th style="padding:8px 12px;text-align:left;color:var(--danger);">Deductions</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--danger);">Amount (ETB)</th>
                </tr>
                <tr><td style="padding:8px 12px;">Employee Pension (7%)</td><td style="padding:8px 12px;text-align:right;color:var(--warning);">7,200.00</td></tr>
                <tr><td style="padding:8px 12px;">Income Tax</td><td style="padding:8px 12px;text-align:right;color:var(--danger);">21,110.00</td></tr>
                <tr style="font-weight:700;background:var(--success-light);">
                    <td style="padding:10px 12px;color:var(--success);">NET PAY</td>
                    <td style="padding:10px 12px;text-align:right;color:var(--success);font-size:1.1rem;">5,210.00</td>
                </tr>
            </table>
            <div style="margin-top:12px;font-size:0.75rem;color:var(--gray-400);text-align:center;">
                Employer Pension Contribution (11%): ETB 11,000.00 — paid by BiT
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('payslipModal')">Close</button>
            <button class="btn btn-primary"><i class="fas fa-download"></i> Download PDF</button>
        </div>
    </div>
</div>

<script>
// Open payslip modal on View click
document.querySelectorAll('button[title="View Payslip"]').forEach(function(btn) {
    btn.addEventListener('click', function() { openModal('payslipModal'); });
});
</script>

<?php require_once $depth . 'includes/footer.php'; ?>

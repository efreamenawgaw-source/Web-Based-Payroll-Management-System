<?php
session_start();
$page_title = 'My Payslips';
$active_nav = 'payslips';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/header.php';

$pdo = getDB();

// Get employee record linked to this user
$emp_stmt = $pdo->prepare("
    SELECT e.emp_id, e.full_name, e.basic_salary, e.position, d.dept_name
    FROM   employees e
    JOIN   departments d ON e.dept_id = d.dept_id
    WHERE  e.user_id = ?
");
$emp_stmt->execute([$_SESSION['user_id']]);
$employee = $emp_stmt->fetch();

$payslips = [];
$view_record = null;
$ded_view    = null;

if ($employee) {
    // Load all payslips for this employee
    $ps_stmt = $pdo->prepare("
        SELECT ps.payslip_id, ps.generated_at,
               pp.period_label, pp.period_month, pp.period_year,
               pr.record_id, pr.basic_salary, pr.total_allowances,
               pr.gross_salary, pr.income_tax, pr.pension_employee,
               pr.pension_employer, pr.other_deductions, pr.net_pay,
               pr.tax_bracket,
               pr.housing, pr.transport, pr.position_allowance, pr.teaching,
               COALESCE(wd.working_days, 30) AS working_days
        FROM   payslips ps
        JOIN   payroll_records pr  ON ps.record_id = pr.record_id
        JOIN   payroll_periods pp  ON pr.period_id = pp.period_id
        LEFT JOIN working_days wd  ON wd.emp_id = ps.emp_id
            AND wd.period_month = pp.period_month
            AND wd.period_year  = pp.period_year
        WHERE  ps.emp_id = ?
        ORDER  BY pp.period_year DESC, pp.period_month DESC
    ");
    $ps_stmt->execute([$employee['emp_id']]);
    $payslips = $ps_stmt->fetchAll();

    // View specific payslip
    if (!empty($_GET['view'])) {
        foreach ($payslips as $ps) {
            if ((int)$ps['record_id'] === (int)$_GET['view']) {
                $view_record = $ps;

                // Mark as viewed
                $pdo->prepare("UPDATE payslips SET viewed_at = NOW() WHERE record_id = ? AND viewed_at IS NULL")
                    ->execute([$ps['record_id']]);

                // Load deductions breakdown
                $dv = $pdo->prepare("
                    SELECT * FROM deductions
                    WHERE emp_id = ? AND effective_month = ? AND effective_year = ?
                ");
                $dv->execute([$employee['emp_id'], $ps['period_month'], $ps['period_year']]);
                $ded_view = $dv->fetch();
                break;
            }
        }
    }
}
?>

<div class="breadcrumb">
    <a href="dashboard.php">Employee</a><span>/</span><span>My Payslips</span>
</div>

<div class="page-header">
    <h1>My Payslips</h1>
    <p>View and download your monthly payslips.</p>
</div>

<?php if (!$employee): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    Your account is not linked to an employee record. Please contact HR.
</div>
<?php elseif (empty($payslips)): ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-file-invoice"></i></div>
            <p>No payslips available yet. Payslips will appear here once Finance processes and generates them.</p>
        </div>
    </div>
</div>
<?php else: ?>

<!-- Payslip History -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-invoice" style="color:var(--primary);margin-right:8px"></i>
            Payslip History
        </h3>
        <span class="badge badge-primary"><?= count($payslips) ?> payslips</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Gross (ETB)</th>
                        <th>Tax (ETB)</th>
                        <th>Pension 7% (ETB)</th>
                        <th>Other Ded. (ETB)</th>
                        <th>Net Pay (ETB)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payslips as $ps): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ps['period_label']) ?></strong></td>
                        <td><?= number_format($ps['gross_salary'], 2) ?></td>
                        <td style="color:var(--danger);"><?= number_format($ps['income_tax'], 2) ?></td>
                        <td style="color:var(--warning);"><?= number_format($ps['pension_employee'], 2) ?></td>
                        <td><?= number_format($ps['other_deductions'], 2) ?></td>
                        <td class="text-bold text-success"><?= number_format($ps['net_pay'], 2) ?></td>
                        <td>
                            <a href="payslips.php?view=<?= $ps['record_id'] ?>"
                               class="btn btn-secondary btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer" style="font-size:0.8rem;color:var(--gray-600);">
        <i class="fas fa-info-circle" style="color:var(--info);"></i>
        Tax calculated per <strong>Revised Monthly Employment Tax Brackets 2025</strong>.
        Pension: Employee 7% + Employer 11% of basic salary.
    </div>
</div>
<?php endif; ?>

<!-- Payslip View Modal -->
<?php if ($view_record && $employee): ?>
<div class="modal-overlay active" id="payslipModal">
    <div class="modal" style="max-width:640px;">
        <div class="modal-header">
            <h3>
                <i class="fas fa-file-invoice-dollar" style="color:var(--primary);margin-right:8px"></i>
                Payslip — <?= htmlspecialchars($view_record['period_label']) ?>
            </h3>
            <a href="payslips.php" class="modal-close" style="text-decoration:none;">&times;</a>
        </div>
        <div class="modal-body" id="payslipPrint">

            <!-- Header -->
            <div style="text-align:center;padding:16px;background:var(--primary);border-radius:var(--radius);margin-bottom:16px;">
                <div style="font-size:1.6rem;font-weight:900;color:var(--white);letter-spacing:-1px;">BiT</div>
                <div style="font-weight:700;font-size:1rem;color:var(--white);">Bahir Dar Institute of Technology</div>
                <div style="font-size:0.82rem;color:rgba(255,255,255,0.80);">
                    PAYSLIP — <?= strtoupper(htmlspecialchars($view_record['period_label'])) ?>
                </div>
            </div>

            <!-- Employee Info -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;
                        background:var(--bg-light);padding:14px;border-radius:var(--radius);margin-bottom:16px;">
                <?php
                $info = [
                    ['Employee Name', $employee['full_name']],
                    ['Employee ID',   $employee['emp_id']],
                    ['Department',    $employee['dept_name']],
                    ['Position',      $employee['position']],
                    ['Working Days',  $view_record['working_days'] . ' / 30'],
                    ['Pay Period',    $view_record['period_label']],
                ];
                foreach ($info as $row): ?>
                <div>
                    <p style="font-size:0.7rem;color:var(--gray-400);margin:0;text-transform:uppercase;"><?= $row[0] ?></p>
                    <p style="font-weight:600;margin:0;font-size:0.88rem;"><?= htmlspecialchars($row[1]) ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Earnings & Deductions -->
            <table style="width:100%;font-size:0.875rem;border-collapse:collapse;margin-bottom:4px;">
                <tr style="background:var(--bg-light);">
                    <th style="padding:8px 12px;text-align:left;color:var(--primary);font-size:0.78rem;text-transform:uppercase;">Earnings</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--primary);font-size:0.78rem;text-transform:uppercase;">ETB</th>
                </tr>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Basic Salary</td>
                    <td style="padding:8px 12px;text-align:right;"><?= number_format($view_record['basic_salary'], 2) ?></td>
                </tr>
                <?php if ($view_record['housing'] > 0): ?>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Housing Allowance</td>
                    <td style="padding:8px 12px;text-align:right;"><?= number_format($view_record['housing'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($view_record['transport'] > 0): ?>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Transport Allowance</td>
                    <td style="padding:8px 12px;text-align:right;"><?= number_format($view_record['transport'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($view_record['position_allowance'] > 0): ?>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Position Allowance</td>
                    <td style="padding:8px 12px;text-align:right;"><?= number_format($view_record['position_allowance'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($view_record['teaching'] > 0): ?>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Teaching Allowance</td>
                    <td style="padding:8px 12px;text-align:right;"><?= number_format($view_record['teaching'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr style="background:var(--bg-light);font-weight:700;border-bottom:2px solid var(--accent-light);">
                    <td style="padding:9px 12px;">Gross Earnings</td>
                    <td style="padding:9px 12px;text-align:right;color:var(--success);"><?= number_format($view_record['gross_salary'], 2) ?></td>
                </tr>
                <tr style="background:var(--bg-light);">
                    <th style="padding:8px 12px;text-align:left;color:var(--danger);font-size:0.78rem;text-transform:uppercase;">Deductions</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--danger);font-size:0.78rem;text-transform:uppercase;">ETB</th>
                </tr>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Income Tax (<?= htmlspecialchars($view_record['tax_bracket'] ?? '') ?> bracket)</td>
                    <td style="padding:8px 12px;text-align:right;color:var(--danger);"><?= number_format($view_record['income_tax'], 2) ?></td>
                </tr>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Employee Pension (7% of basic)</td>
                    <td style="padding:8px 12px;text-align:right;color:var(--warning);"><?= number_format($view_record['pension_employee'], 2) ?></td>
                </tr>
                <?php if ($ded_view && $ded_view['credit_association'] > 0): ?>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Credit Association (10%)</td>
                    <td style="padding:8px 12px;text-align:right;color:var(--info);"><?= number_format($ded_view['credit_association'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($ded_view && $ded_view['renaissance_dam'] > 0): ?>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Renaissance Dam — GERD (1%)</td>
                    <td style="padding:8px 12px;text-align:right;color:var(--info);"><?= number_format($ded_view['renaissance_dam'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($ded_view && $ded_view['loan_repayment'] > 0): ?>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Loan Repayment</td>
                    <td style="padding:8px 12px;text-align:right;color:var(--warning);"><?= number_format($ded_view['loan_repayment'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($ded_view && ($ded_view['penalty'] + $ded_view['other']) > 0): ?>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Penalty / Other</td>
                    <td style="padding:8px 12px;text-align:right;color:var(--danger);"><?= number_format($ded_view['penalty'] + $ded_view['other'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr style="background:var(--success-light);font-weight:700;">
                    <td style="padding:12px;color:var(--success);font-size:1rem;">NET PAY</td>
                    <td style="padding:12px;text-align:right;color:var(--success);font-size:1.2rem;"><?= number_format($view_record['net_pay'], 2) ?></td>
                </tr>
            </table>

            <div style="margin-top:10px;padding:10px 12px;background:var(--info-light);border-radius:var(--radius);
                        font-size:0.78rem;color:var(--info);text-align:center;">
                <i class="fas fa-shield-alt"></i>
                Employer Pension (11%): <strong>ETB <?= number_format($view_record['pension_employer'], 2) ?></strong> — paid by BiT
            </div>
            <div style="margin-top:6px;font-size:0.7rem;color:var(--gray-400);text-align:center;">
                Tax: Revised Monthly Employment Tax Brackets 2025
            </div>
        </div>
        <div class="modal-footer">
            <a href="payslips.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Close
            </a>
            <button class="btn btn-primary" onclick="printPayslip()">
                <i class="fas fa-print"></i> Print / Download
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function printPayslip() {
    const content = document.getElementById('payslipPrint').innerHTML;
    const win = window.open('', '_blank', 'width=700,height=900');
    win.document.write(`
        <html><head><title>Payslip</title>
        <style>
            body { font-family:'Segoe UI',Arial,sans-serif; padding:20px; color:#263238; }
            table { width:100%; border-collapse:collapse; }
            th,td { padding:8px 12px; }
            @media print { body { padding:0; } }
        </style></head>
        <body>${content}</body></html>
    `);
    win.document.close();
    win.focus();
    win.print();
}
</script>

<?php require_once $depth . 'includes/footer.php'; ?>

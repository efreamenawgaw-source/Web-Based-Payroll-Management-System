<?php
session_start();
$page_title = 'Generate Payslips';
$active_nav = 'payslip';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/notify.php';

$pdo     = getDB();
$success = '';
$error   = '';

// â”€â”€ Load verified periods â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$periods = $pdo->query("
    SELECT pp.period_id, pp.period_label, pp.period_month, pp.period_year,
           pp.status, COUNT(pr.record_id) AS emp_count,
           SUM(pr.net_pay) AS total_net,
           COUNT(ps.payslip_id) AS payslips_generated
    FROM   payroll_periods pp
    LEFT JOIN payroll_records pr ON pp.period_id = pr.period_id
    LEFT JOIN payslips ps        ON pp.period_id = ps.period_id
    WHERE  pp.status IN ('verified','finalized')
    GROUP  BY pp.period_id
    ORDER  BY pp.period_year DESC, pp.period_month DESC
")->fetchAll();

// â”€â”€ Selected period â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$sel_period_id = (int)($_GET['period_id'] ?? ($_POST['period_id'] ?? 0));
$sel_period    = null;
$records       = [];

if ($sel_period_id) {
    $sp = $pdo->prepare("SELECT * FROM payroll_periods WHERE period_id = ?");
    $sp->execute([$sel_period_id]);
    $sel_period = $sp->fetch();

    if ($sel_period) {
        $rec_stmt = $pdo->prepare("
            SELECT pr.*,
                   e.full_name, e.email, e.phone,
                   e.basic_salary AS emp_basic,
                   d.dept_name, e.position,
                   ps.payslip_id, ps.generated_at
            FROM   payroll_records pr
            JOIN   employees e   ON pr.emp_id = e.emp_id
            JOIN   departments d ON e.dept_id = d.dept_id
            LEFT JOIN payslips ps ON ps.record_id = pr.record_id
            WHERE  pr.period_id = ?
            ORDER  BY e.full_name
        ");
        $rec_stmt->execute([$sel_period_id]);
        $records = $rec_stmt->fetchAll();
    }
}

// â”€â”€ GENERATE PAYSLIPS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $pid = (int)($_POST['period_id'] ?? 0);
    if ($pid) {
        try {
            $pdo->beginTransaction();

            // Get all records for this period
            $recs = $pdo->prepare("SELECT record_id, emp_id FROM payroll_records WHERE period_id = ?");
            $recs->execute([$pid]);
            $all_records = $recs->fetchAll();

            $generated = 0;
            foreach ($all_records as $rec) {
                // Check if payslip already exists
                $chk = $pdo->prepare("SELECT payslip_id FROM payslips WHERE record_id = ?");
                $chk->execute([$rec['record_id']]);
                if (!$chk->fetch()) {
                    $pdo->prepare("
                        INSERT INTO payslips (record_id, emp_id, period_id, generated_by)
                        VALUES (?, ?, ?, ?)
                    ")->execute([$rec['record_id'], $rec['emp_id'], $pid, $_SESSION['user_id']]);
                    $generated++;
                }
            }

            // Update period status to finalized
            $pdo->prepare("
                UPDATE payroll_periods SET status = 'finalized', finalized_at = NOW()
                WHERE period_id = ?
            ")->execute([$pid]);

            // Audit log
            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Generate Payslip', ?, ?, ?)
            ")->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                "period_id:{$pid}",
                "Generated {$generated} payslips",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $pdo->commit();
            $success = "<strong>{$generated} payslips</strong> generated successfully. Employees can now view and download their payslips.";

            // Get period label
            $pl = $pdo->prepare("SELECT period_label FROM payroll_periods WHERE period_id=?");
            $pl->execute([$pid]);
            $plabel = $pl->fetch()['period_label'] ?? "Period #{$pid}";

            // Notify each employee individually
            $emp_users = $pdo->prepare("
                SELECT e.user_id, e.full_name, pr.net_pay
                FROM   payroll_records pr
                JOIN   employees e ON pr.emp_id = e.emp_id
                WHERE  pr.period_id = ? AND e.user_id IS NOT NULL
            ");
            $emp_users->execute([$pid]);
            foreach ($emp_users->fetchAll() as $eu) {
                notify($pdo, $eu['user_id'],
                    'Payslip Available &rdquo;” ' . $plabel,
                    "Your payslip for {$plabel} is ready. Net Pay: ETB " . number_format($eu['net_pay'], 2) . ". Click to view and download.",
                    'success',
                    '/pages/employee/payslips.php');
            }

            // Notify admin and HR
            notify_role($pdo, 'admin',
                'Payslips Generated &rdquo;” ' . $plabel,
                "{$generated} payslips generated for {$plabel}. Employees have been notified.",
                'success');
            notify_role($pdo, 'hr',
                'Payslips Generated &rdquo;” ' . $plabel,
                "{$generated} payslips are now available for employees for {$plabel}.",
                'success');

            // Reload records
            $rec_stmt->execute([$pid]);
            $records = $rec_stmt->fetchAll();
            $sp->execute([$pid]);
            $sel_period = $sp->fetch();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Generation failed: ' . $e->getMessage();
        }
    }
}

// â”€â”€ VIEW SINGLE PAYSLIP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$view_record = null;
if (!empty($_GET['view'])) {
    $vr = $pdo->prepare("
        SELECT pr.*,
               e.full_name, e.email, e.phone, e.position, e.employment_date,
               d.dept_name,
               pp.period_label, pp.period_month, pp.period_year,
               ps.generated_at,
               COALESCE(wd.working_days, 30) AS working_days
        FROM   payroll_records pr
        JOIN   employees e   ON pr.emp_id = e.emp_id
        JOIN   departments d ON e.dept_id = d.dept_id
        JOIN   payroll_periods pp ON pr.period_id = pp.period_id
        LEFT JOIN payslips ps ON ps.record_id = pr.record_id
        LEFT JOIN working_days wd ON wd.emp_id = pr.emp_id
            AND wd.period_month = pp.period_month
            AND wd.period_year  = pp.period_year
        WHERE  pr.record_id = ?
    ");
    $vr->execute([(int)$_GET['view']]);
    $view_record = $vr->fetch();

    // Also fetch deductions breakdown
    $ded_view = null;
    if ($view_record) {
        $dv = $pdo->prepare("
            SELECT * FROM deductions
            WHERE emp_id = ? AND effective_month = ? AND effective_year = ?
        ");
        $dv->execute([
            $view_record['emp_id'],
            $view_record['period_month'],
            $view_record['period_year']
        ]);
        $ded_view = $dv->fetch();
    }
}

$status_badge = [
    'verified'  => 'badge-success',
    'finalized' => 'badge-primary',
];

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">Finance</a><span>/</span><span>Generate Payslips</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Generate Payslips</h1>
        <p>Generate electronic payslips for all employees after payroll verification.</p>
    </div>
    <a href="verify_payroll.php" class="btn btn-secondary">
        <i class="fas fa-check-double"></i> Verify Payroll
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- â”€â”€ Verified Periods â”€â”€ -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list" style="color:var(--primary);margin-right:8px"></i>
                Verified Periods
            </h3>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Employees</th>
                            <th>Payslips</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($periods)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:24px;">
                                No verified payrolls yet.
                                <a href="verify_payroll.php">Verify payroll first.</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($periods as $p): ?>
                        <tr style="<?= $sel_period_id === (int)$p['period_id'] ? 'background:var(--bg-light);' : '' ?>">
                            <td><strong><?= htmlspecialchars($p['period_label']) ?></strong></td>
                            <td><?= $p['emp_count'] ?></td>
                            <td>
                                <?php if ($p['payslips_generated'] > 0): ?>
                                <span class="badge badge-success"><?= $p['payslips_generated'] ?> generated</span>
                                <?php else: ?>
                                <span class="badge badge-gray">None yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $status_badge[$p['status']] ?? 'badge-gray' ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="generate_payslip.php?period_id=<?= $p['period_id'] ?>"
                                   class="btn btn-secondary btn-sm btn-icon-only" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- â”€â”€ Generate Action â”€â”€ -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-invoice-dollar" style="color:var(--primary);margin-right:8px"></i>
                <?= $sel_period ? htmlspecialchars($sel_period['period_label']) : 'Select a Period' ?>
            </h3>
        </div>
        <div class="card-body">
            <?php if ($sel_period): ?>
            <div style="margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gray-200);">
                    <span style="color:var(--gray-600);">Period</span>
                    <strong><?= htmlspecialchars($sel_period['period_label']) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gray-200);">
                    <span style="color:var(--gray-600);">Employees</span>
                    <strong><?= count($records) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gray-200);">
                    <span style="color:var(--gray-600);">Status</span>
                    <span class="badge <?= $status_badge[$sel_period['status']] ?? 'badge-gray' ?>">
                        <?= ucfirst($sel_period['status']) ?>
                    </span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;">
                    <span style="color:var(--gray-600);">Total Net Pay</span>
                    <strong style="color:var(--success);">
                        ETB <?= number_format(array_sum(array_column($records, 'net_pay')), 2) ?>
                    </strong>
                </div>
            </div>

            <?php
            $already_generated = count(array_filter($records, fn($r) => $r['payslip_id']));
            ?>

            <?php if ($already_generated === count($records) && count($records) > 0): ?>
            <div class="alert alert-success" style="margin-bottom:12px;">
                <i class="fas fa-check-circle"></i>
                All <?= $already_generated ?> payslips already generated.
            </div>
            <?php elseif ($sel_period['status'] === 'verified' || $sel_period['status'] === 'finalized'): ?>
            <form method="POST" action="">
                <input type="hidden" name="period_id" value="<?= $sel_period['period_id'] ?>">
                <button type="submit" name="generate" class="btn btn-primary w-100"
                        onclick="return confirm('Generate payslips for all <?= count($records) ?> employees?')">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Generate <?= count($records) - $already_generated ?> Payslips
                </button>
            </form>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-mouse-pointer"></i></div>
                <p>Select a verified period from the list.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- â”€â”€ Payslips List â”€â”€ -->
<?php if ($sel_period && !empty($records)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-invoice" style="color:var(--primary);margin-right:8px"></i>
            Payslips &rdquo;” <?= htmlspecialchars($sel_period['period_label']) ?>
        </h3>
        <span class="badge badge-primary"><?= count($records) ?> employees</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Gross (ETB)</th>
                        <th>Total Ded. (ETB)</th>
                        <th>Net Pay (ETB)</th>
                        <th>Payslip</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($records as $r): ?>
                    <tr>
                        <td class="text-muted"><?= $i++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($r['emp_id']) ?></small>
                        </td>
                        <td style="font-size:0.82rem;"><?= htmlspecialchars($r['dept_name']) ?></td>
                        <td><?= number_format($r['gross_salary'], 2) ?></td>
                        <td style="color:var(--danger);">
                            <?= number_format($r['income_tax'] + $r['pension_employee'] + $r['other_deductions'], 2) ?>
                        </td>
                        <td class="text-bold" style="color:var(--success);"><?= number_format($r['net_pay'], 2) ?></td>
                        <td>
                            <?php if ($r['payslip_id']): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check"></i> Generated
                            </span>
                            <?php else: ?>
                            <span class="badge badge-gray">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['payslip_id']): ?>
                            <a href="generate_payslip.php?period_id=<?= $sel_period_id ?>&view=<?= $r['record_id'] ?>"
                               class="btn btn-secondary btn-sm btn-icon-only" title="View Payslip">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- â”€â”€ Payslip Modal â”€â”€ -->
<?php if ($view_record): ?>
<div class="modal-overlay active" id="payslipModal">
    <div class="modal" style="max-width:640px;">
        <div class="modal-header">
            <h3>
                <i class="fas fa-file-invoice-dollar" style="color:var(--primary);margin-right:8px"></i>
                Payslip &rdquo;” <?= htmlspecialchars($view_record['period_label']) ?>
            </h3>
            <a href="generate_payslip.php?period_id=<?= $sel_period_id ?>"
               class="modal-close" style="text-decoration:none;">&times;</a>
        </div>
        <div class="modal-body" id="payslipPrint">

            <!-- Header -->
            <div style="text-align:center;padding:16px;background:var(--primary);border-radius:var(--radius);margin-bottom:16px;">
                <div style="font-size:1.6rem;font-weight:900;color:var(--white);letter-spacing:-1px;">BiT</div>
                <div style="font-weight:700;font-size:1rem;color:var(--white);">Bahir Dar Institute of Technology</div>
                <div style="font-size:0.82rem;color:rgba(255,255,255,0.80);">
                    PAYSLIP &rdquo;” <?= strtoupper(htmlspecialchars($view_record['period_label'])) ?>
                </div>
            </div>

            <!-- Employee Info -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;
                        background:var(--bg-light);padding:14px;border-radius:var(--radius);margin-bottom:16px;">
                <?php
                $info_rows = [
                    ['Employee Name', $view_record['full_name']],
                    ['Employee ID',   $view_record['emp_id']],
                    ['Department',    $view_record['dept_name']],
                    ['Position',      $view_record['position']],
                    ['Working Days',  $view_record['working_days'] . ' / 30'],
                    ['Pay Period',    $view_record['period_label']],
                ];
                foreach ($info_rows as $ir): ?>
                <div>
                    <p style="font-size:0.7rem;color:var(--gray-400);margin:0;text-transform:uppercase;"><?= $ir[0] ?></p>
                    <p style="font-weight:600;margin:0;font-size:0.88rem;"><?= htmlspecialchars($ir[1]) ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Earnings -->
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

                <!-- Deductions -->
                <tr style="background:var(--bg-light);">
                    <th style="padding:8px 12px;text-align:left;color:var(--danger);font-size:0.78rem;text-transform:uppercase;">Deductions</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--danger);font-size:0.78rem;text-transform:uppercase;">ETB</th>
                </tr>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">
                        Income Tax
                        <span style="font-size:0.72rem;color:var(--gray-400);">
                            (<?= htmlspecialchars($view_record['tax_bracket'] ?? '') ?> bracket)
                        </span>
                    </td>
                    <td style="padding:8px 12px;text-align:right;color:var(--danger);"><?= number_format($view_record['income_tax'], 2) ?></td>
                </tr>
                <tr style="border-bottom:1px solid var(--gray-200);">
                    <td style="padding:8px 12px;">Employee 18% of basic)</td>
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
                    <td style="padding:8px 12px;">Renaissance Dam &rdquo;” GERD (1%)</td>
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

                <!-- Net Pay -->
                <tr style="background:var(--success-light);font-weight:700;">
                    <td style="padding:12px;color:var(--success);font-size:1rem;">NET PAY</td>
                    <td style="padding:12px;text-align:right;color:var(--success);font-size:1.2rem;"><?= number_format($view_record['net_pay'], 2) ?></td>
                </tr>
            </table>

            <!-- Employer pension note -->
            <div style="margin-top:10px;padding:10px 12px;background:var(--info-light);border-radius:var(--radius);
                        font-size:0.78rem;color:var(--info);text-align:center;">
                <i class="fas fa-shield-alt"></i>
                Employer 18% of basic): <strong>ETB <?= number_format($view_record['pension_employer'], 2) ?></strong> &rdquo;” paid by BiT
            </div>
            <div style="margin-top:6px;font-size:0.7rem;color:var(--gray-400);text-align:center;">
                Generated: <?= $view_record['generated_at'] ? date('M d, Y H:i', strtotime($view_record['generated_at'])) : date('M d, Y H:i') ?>
                &nbsp;|&nbsp; Tax: Revised Monthly Employment Tax Brackets 2025
            </div>
        </div>
        <div class="modal-footer">
            <a href="generate_payslip.php?period_id=<?= $sel_period_id ?>" class="btn btn-secondary">
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
            body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; color: #263238; }
            table { width:100%; border-collapse:collapse; }
            th, td { padding: 8px 12px; }
            @media print { body { padding: 0; } }
        </style></head>
        <body>${content}</body></html>
    `);
    win.document.close();
    win.focus();
    win.print();
}
</script>

<?php require_once $depth . 'includes/footer.php'; ?>


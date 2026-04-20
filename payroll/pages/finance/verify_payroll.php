<?php
session_start();
$page_title = 'Verify Payroll';
$active_nav = 'verify';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/notify.php';

$pdo     = getDB();
$success = '';
$error   = '';

// ── Load processed periods ─────────────────────────────────
$periods = $pdo->query("
    SELECT pp.period_id, pp.period_label, pp.period_month, pp.period_year,
           pp.status, pp.processed_at,
           u.full_name AS processed_by_name,
           COUNT(pr.record_id) AS emp_count,
           SUM(pr.gross_salary) AS total_gross,
           SUM(pr.net_pay)      AS total_net
    FROM   payroll_periods pp
    LEFT JOIN users u          ON pp.processed_by = u.user_id
    LEFT JOIN payroll_records pr ON pp.period_id = pr.period_id
    WHERE  pp.status IN ('processed','verified','finalized')
    GROUP  BY pp.period_id
    ORDER  BY pp.period_year DESC, pp.period_month DESC
")->fetchAll();

// ── Selected period ────────────────────────────────────────
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
                   e.full_name, e.basic_salary AS emp_basic,
                   d.dept_name
            FROM   payroll_records pr
            JOIN   employees e  ON pr.emp_id = e.emp_id
            JOIN   departments d ON e.dept_id = d.dept_id
            WHERE  pr.period_id = ?
            ORDER  BY e.full_name
        ");
        $rec_stmt->execute([$sel_period_id]);
        $records = $rec_stmt->fetchAll();
    }
}

// ── HANDLE APPROVE ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $pid = (int)($_POST['period_id'] ?? 0);
    if ($pid) {
        try {
            $pdo->prepare("
                UPDATE payroll_periods
                SET status = 'verified', verified_by = ?, verified_at = NOW()
                WHERE period_id = ? AND status = 'processed'
            ")->execute([$_SESSION['user_id'], $pid]);

            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Verify Payroll', ?, 'Payroll approved and verified', ?)
            ")->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                "period_id:{$pid}", $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $success = 'Payroll verified and approved. '
                     . '<a href="generate_payslip.php?period_id=' . $pid . '" class="btn btn-primary btn-sm" style="margin-left:10px;">'
                     . '<i class="fas fa-file-invoice-dollar"></i> Generate Payslips</a>';

            // Get period label
            $pl = $pdo->prepare("SELECT period_label FROM payroll_periods WHERE period_id=?");
            $pl->execute([$pid]);
            $pl_row = $pl->fetch();
            $plabel = $pl_row['period_label'] ?? "Period #{$pid}";

            // Notify admin
            notify_role($pdo, 'admin',
                'Payroll Verified — ' . $plabel,
                "Finance has verified and approved payroll for {$plabel}. Payslips can now be generated.",
                'success');

            // Notify HR
            notify_role($pdo, 'hr',
                'Payroll Verified — ' . $plabel,
                "Payroll for {$plabel} has been verified. Payslips will be generated shortly.",
                'success');

            // Reload
            $sp->execute([$pid]);
            $sel_period = $pdo->prepare("SELECT * FROM payroll_periods WHERE period_id=?")->execute([$pid])
                        ? ($pdo->prepare("SELECT * FROM payroll_periods WHERE period_id=?")->execute([$pid]) ? null : null)
                        : null;
            $sp2 = $pdo->prepare("SELECT * FROM payroll_periods WHERE period_id=?");
            $sp2->execute([$pid]);
            $sel_period = $sp2->fetch();

        } catch (PDOException $e) {
            $error = 'Approval failed: ' . $e->getMessage();
        }
    }
}

// ── HANDLE REJECT ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject'])) {
    $pid    = (int)($_POST['period_id'] ?? 0);
    $reason = trim($_POST['reject_reason'] ?? '');
    if ($pid) {
        try {
            $pdo->prepare("
                UPDATE payroll_periods
                SET status = 'pending', notes = ?
                WHERE period_id = ?
            ")->execute([$reason ?: 'Rejected by Finance', $pid]);

            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target, details, ip_address)
                VALUES (?, ?, ?, 'Reject Payroll', ?, ?, ?)
            ")->execute([
                $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'],
                "period_id:{$pid}", 'Rejected: ' . ($reason ?: 'No reason given'),
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $error = 'Payroll rejected and sent back for re-processing. Reason: ' . htmlspecialchars($reason ?: 'Not specified');
            $sel_period['status'] = 'pending';

        } catch (PDOException $e) {
            $error = 'Rejection failed: ' . $e->getMessage();
        }
    }
}

// ── Grand totals ───────────────────────────────────────────
$gt = [];
if (!empty($records)) {
    $gt = [
        'gross'            => array_sum(array_column($records, 'gross_salary')),
        'income_tax'       => array_sum(array_column($records, 'income_tax')),
        'pension_emp'      => array_sum(array_column($records, 'pension_employee')),
        'pension_org'      => array_sum(array_column($records, 'pension_employer')),
        'other_deductions' => array_sum(array_column($records, 'other_deductions')),
        'net_pay'          => array_sum(array_column($records, 'net_pay')),
    ];
}

$status_badge = [
    'pending'   => 'badge-gray',
    'processed' => 'badge-warning',
    'verified'  => 'badge-success',
    'finalized' => 'badge-primary',
];

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">Finance</a><span>/</span><span>Verify Payroll</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Verify Payroll</h1>
        <p>Review processed payroll data and approve or reject before generating payslips.</p>
    </div>
    <a href="process_payroll.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Process
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <span><?= $error ?></span></div>
<?php endif; ?>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- ── Processed Periods List ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list" style="color:var(--primary);margin-right:8px"></i>
                Payroll Periods
            </h3>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Employees</th>
                            <th>Total Net Pay</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($periods)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:24px;">
                                No processed payrolls yet.
                                <a href="process_payroll.php">Process payroll first.</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($periods as $p): ?>
                        <tr style="<?= $sel_period_id === (int)$p['period_id'] ? 'background:var(--bg-light);' : '' ?>">
                            <td><strong><?= htmlspecialchars($p['period_label']) ?></strong></td>
                            <td><?= $p['emp_count'] ?></td>
                            <td class="text-bold text-success">
                                ETB <?= number_format($p['total_net'], 2) ?>
                            </td>
                            <td>
                                <span class="badge <?= $status_badge[$p['status']] ?? 'badge-gray' ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="verify_payroll.php?period_id=<?= $p['period_id'] ?>"
                                   class="btn btn-secondary btn-sm btn-icon-only" title="Review">
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

    <!-- ── Period Summary ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie" style="color:var(--primary);margin-right:8px"></i>
                <?= $sel_period ? htmlspecialchars($sel_period['period_label']) . ' — Summary' : 'Select a Period' ?>
            </h3>
            <?php if ($sel_period): ?>
            <span class="badge <?= $status_badge[$sel_period['status']] ?? 'badge-gray' ?>">
                <?= ucfirst($sel_period['status']) ?>
            </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($sel_period && !empty($gt)): ?>
            <?php
            $summary_rows = [
                ['Employees Processed', count($records),                    'var(--primary)'],
                ['Total Gross Earnings', 'ETB ' . number_format($gt['gross'], 2),       'var(--primary)'],
                ['Total Income Tax',     'ETB ' . number_format($gt['income_tax'], 2),  'var(--danger)'],
                ['Total Pension (7%)',   'ETB ' . number_format($gt['pension_emp'], 2), 'var(--warning)'],
                ['Total Pension (11%)',  'ETB ' . number_format($gt['pension_org'], 2), 'var(--info)'],
                ['Other Deductions',     'ETB ' . number_format($gt['other_deductions'], 2), 'var(--gray-600)'],
                ['Total Net Pay',        'ETB ' . number_format($gt['net_pay'], 2),     'var(--success)'],
            ];
            foreach ($summary_rows as $sr): ?>
            <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--gray-200);">
                <span style="font-size:0.875rem;color:var(--gray-600);"><?= $sr[0] ?></span>
                <span style="font-weight:700;color:<?= $sr[2] ?>;"><?= $sr[1] ?></span>
            </div>
            <?php endforeach; ?>

            <!-- Approve / Reject -->
            <?php if ($sel_period['status'] === 'processed'): ?>
            <div style="margin-top:18px;display:flex;flex-direction:column;gap:10px;">
                <form method="POST" action="">
                    <input type="hidden" name="period_id" value="<?= $sel_period['period_id'] ?>">
                    <button type="submit" name="approve" class="btn btn-success w-100"
                            onclick="return confirm('Approve and finalize this payroll?')">
                        <i class="fas fa-check-double"></i> Approve & Verify
                    </button>
                </form>
                <form method="POST" action="">
                    <input type="hidden" name="period_id" value="<?= $sel_period['period_id'] ?>">
                    <div class="form-group" style="margin-bottom:8px;">
                        <input type="text" name="reject_reason" class="form-control"
                               placeholder="Reason for rejection (optional)">
                    </div>
                    <button type="submit" name="reject" class="btn btn-danger w-100"
                            onclick="return confirm('Reject and send back for re-processing?')">
                        <i class="fas fa-times"></i> Reject — Send Back
                    </button>
                </form>
            </div>
            <?php elseif ($sel_period['status'] === 'verified'): ?>
            <div style="margin-top:18px;">
                <a href="generate_payslip.php?period_id=<?= $sel_period['period_id'] ?>"
                   class="btn btn-primary w-100">
                    <i class="fas fa-file-invoice-dollar"></i> Generate Payslips
                </a>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-mouse-pointer"></i></div>
                <p>Select a period from the list to review.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ── Detailed Records Table ── -->
<?php if ($sel_period && !empty($records)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-table" style="color:var(--primary);margin-right:8px"></i>
            Payroll Detail — <?= htmlspecialchars($sel_period['period_label']) ?>
        </h3>
        <button class="btn btn-secondary btn-sm" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Basic (ETB)</th>
                        <th>Gross (ETB)</th>
                        <th>Tax (ETB)</th>
                        <th>Pension 7% (ETB)</th>
                        <th>Other Ded. (ETB)</th>
                        <th>Net Pay (ETB)</th>
                        <th>Bracket</th>
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
                        <td><?= number_format($r['basic_salary'], 2) ?></td>
                        <td class="text-bold" style="color:var(--success);"><?= number_format($r['gross_salary'], 2) ?></td>
                        <td style="color:var(--danger);"><?= number_format($r['income_tax'], 2) ?></td>
                        <td style="color:var(--warning);"><?= number_format($r['pension_employee'], 2) ?></td>
                        <td style="color:var(--gray-600);"><?= number_format($r['other_deductions'], 2) ?></td>
                        <td class="text-bold" style="color:var(--success);font-size:1rem;"><?= number_format($r['net_pay'], 2) ?></td>
                        <td>
                            <span class="badge <?= $r['tax_bracket'] === '0%' ? 'badge-success' : 'badge-warning' ?>">
                                <?= htmlspecialchars($r['tax_bracket'] ?? '—') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--bg-light);font-weight:700;">
                        <td colspan="4" style="padding:12px 16px;color:var(--primary);">TOTALS</td>
                        <td style="padding:12px 16px;color:var(--success);"><?= number_format($gt['gross'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--danger);"><?= number_format($gt['income_tax'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--warning);"><?= number_format($gt['pension_emp'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--gray-600);"><?= number_format($gt['other_deductions'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--success);"><?= number_format($gt['net_pay'], 2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once $depth . 'includes/footer.php'; ?>

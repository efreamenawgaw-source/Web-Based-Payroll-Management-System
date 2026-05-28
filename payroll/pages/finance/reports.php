<?php
session_start();
$page_title = 'Payroll Reports';
$active_nav = 'reports';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/header.php';

$pdo = getDB();

// -- Read filter values from the GET request --
$f_period_id = (int)($_GET['period_id'] ?? 0);
$f_dept      = (int)($_GET['dept']      ?? 0);
$f_type      = trim($_GET['type']       ?? 'monthly');

// -- Load all verified/finalized payroll periods for the period selector --
$all_periods = $pdo->query("
    SELECT period_id, period_label, period_month, period_year, status
    FROM   payroll_periods
    WHERE  status IN ('verified','finalized')
    ORDER  BY period_year DESC, period_month DESC
")->fetchAll();

// -- Load active departments for the department filter --
$departments = $pdo->query("
    SELECT dept_id, dept_name FROM departments WHERE is_active=1 ORDER BY dept_name
")->fetchAll();

// -- Load payroll records for the selected period --
$sel_period  = null;
$report_rows = [];
$gt          = [];

if ($f_period_id) {
    $sp = $pdo->prepare("SELECT * FROM payroll_periods WHERE period_id = ?");
    $sp->execute([$f_period_id]);
    $sel_period = $sp->fetch();

    if ($sel_period) {
        // Build WHERE clause — always filter by period, optionally by department
        $where  = ['pr.period_id = ?'];
        $params = [$f_period_id];

        if ($f_dept) {
            $where[]  = 'e.dept_id = ?';
            $params[] = $f_dept;
        }

        $where_sql = implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT
                pr.emp_id,
                e.full_name,
                e.last_name,
                e.cbe_account_number,
                e.cbe_account_name,
                d.dept_name,
                e.position,
                pr.basic_salary,
                COALESCE(wd.working_days, 30)  AS working_days,
                pr.total_allowances,
                pr.gross_salary,
                pr.income_tax,
                pr.pension_employee,
                pr.pension_employer,
                pr.other_deductions,
                (pr.income_tax + pr.pension_employee + pr.other_deductions) AS total_deductions,
                pr.net_pay,
                pr.tax_bracket
            FROM   payroll_records pr
            JOIN   employees e   ON pr.emp_id = e.emp_id
            JOIN   departments d ON e.dept_id = d.dept_id
            LEFT JOIN working_days wd
                ON  wd.emp_id = pr.emp_id
                AND wd.period_month = ?
                AND wd.period_year  = ?
            WHERE  {$where_sql}
            ORDER  BY d.dept_name, e.full_name
        ");
        $stmt->execute(array_merge(
            [$sel_period['period_month'], $sel_period['period_year']],
            $params
        ));
        $report_rows = $stmt->fetchAll();

        // Calculate grand totals across all rows
        $gt = [
            'basic'            => array_sum(array_column($report_rows, 'basic_salary')),
            'allowances'       => array_sum(array_column($report_rows, 'total_allowances')),
            'gross'            => array_sum(array_column($report_rows, 'gross_salary')),
            'income_tax'       => array_sum(array_column($report_rows, 'income_tax')),
            'pension_emp'      => array_sum(array_column($report_rows, 'pension_employee')),
            'pension_org'      => array_sum(array_column($report_rows, 'pension_employer')),
            'other_deductions' => array_sum(array_column($report_rows, 'other_deductions')),
            'total_deductions' => array_sum(array_column($report_rows, 'total_deductions')),
            'net_pay'          => array_sum(array_column($report_rows, 'net_pay')),
        ];
    }
}

// -- Annual summary: aggregate all verified/finalized periods for the current year --
$annual = $pdo->query("
    SELECT
        pp.period_label,
        pp.period_month,
        COUNT(pr.record_id)          AS emp_count,
        SUM(pr.gross_salary)         AS total_gross,
        SUM(pr.income_tax)           AS total_tax,
        SUM(pr.pension_employee)     AS total_pension_emp,
        SUM(pr.pension_employer)     AS total_pension_org,
        SUM(pr.other_deductions)     AS total_other,
        SUM(pr.net_pay)              AS total_net
    FROM payroll_periods pp
    JOIN payroll_records pr ON pp.period_id = pr.period_id
    WHERE pp.period_year = " . date('Y') . "
    AND   pp.status IN ('verified','finalized')
    GROUP BY pp.period_id
    ORDER BY pp.period_month ASC
")->fetchAll();

// -- Department breakdown for the selected period --
$dept_breakdown = [];
if ($f_period_id) {
    $db_stmt = $pdo->prepare("
        SELECT
            d.dept_name,
            COUNT(pr.record_id)      AS emp_count,
            SUM(pr.gross_salary)     AS total_gross,
            SUM(pr.net_pay)          AS total_net,
            SUM(pr.income_tax)       AS total_tax,
            SUM(pr.pension_employee) AS total_pension
        FROM payroll_records pr
        JOIN employees e   ON pr.emp_id = e.emp_id
        JOIN departments d ON e.dept_id = d.dept_id
        WHERE pr.period_id = ?
        GROUP BY d.dept_id
        ORDER BY total_gross DESC
    ");
    $db_stmt->execute([$f_period_id]);
    $dept_breakdown = $db_stmt->fetchAll();
}
?>

<div class="breadcrumb">
    <a href="dashboard.php">Finance</a><span>/</span><span>Payroll Reports</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Payroll Reports</h1>
        <p>Generate and view financial reports for management, auditing, and CBE bank transfers.</p>
    </div>
    <button class="btn btn-secondary" onclick="window.print()">
        <i class="fas fa-print"></i> Print Report
    </button>
</div>

<!-- Filter bar: period selector and department filter -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="">
            <div class="filter-bar">
                <div class="form-group" style="margin:0;flex:1;">
                    <select name="period_id" class="form-control" onchange="this.form.submit()">
                        <option value="">&mdash; Select Period &mdash;</option>
                        <?php foreach ($all_periods as $p): ?>
                        <option value="<?= $p['period_id'] ?>"
                            <?= $f_period_id === (int)$p['period_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['period_label']) ?>
                            (<?= ucfirst($p['status']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <select name="dept" class="form-control" style="width:auto;">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['dept_id'] ?>"
                            <?= $f_dept === (int)$d['dept_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['dept_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Generate
                </button>
                <?php if ($f_period_id): ?>
                <a href="reports.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($sel_period && !empty($report_rows)): ?>

<!-- Summary stat cards: employee count, gross, tax, net pay -->
<div class="stats-grid" style="margin-bottom:24px;">
    <?php
    $cards = [
        ['Employees',     count($report_rows),                       'blue',  'fas fa-users'],
        ['Total Gross',   'ETB ' . number_format($gt['gross'], 2),   'green', 'fas fa-money-bill-wave'],
        ['Total Tax',     'ETB ' . number_format($gt['income_tax'], 2), 'red','fas fa-percent'],
        ['Total Net Pay', 'ETB ' . number_format($gt['net_pay'], 2), 'green', 'fas fa-hand-holding-usd'],
    ];
    foreach ($cards as [$label, $val, $color, $icon]): ?>
    <div class="stat-card">
        <div class="stat-icon <?= $color ?>"><i class="<?= $icon ?>"></i></div>
        <div class="stat-info">
            <p><?= $label ?></p>
            <h2 style="font-size:<?= is_numeric($val) ? '1.6rem' : '1.1rem' ?>;"><?= $val ?></h2>
            <span class="stat-change up"><?= htmlspecialchars($sel_period['period_label']) ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Main payroll report table with grouped deduction columns -->
<div class="card mb-3">
    <div class="card-header">
        <h3>
            <i class="fas fa-table" style="color:var(--primary);margin-right:8px"></i>
            Payroll Report &mdash; <?= htmlspecialchars($sel_period['period_label']) ?>
            <?= $f_dept ? '(' . htmlspecialchars(array_column($departments,'dept_name','dept_id')[$f_dept] ?? '') . ')' : '' ?>
        </h3>
        <div class="d-flex gap-2">
            <button class="btn btn-secondary btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align:middle;">#</th>
                        <th rowspan="2" style="vertical-align:middle;">Employee Name</th>
                        <th rowspan="2" style="vertical-align:middle;">Department</th>
                        <th rowspan="2" style="vertical-align:middle;">CBE Account No.</th>
                        <th rowspan="2" style="vertical-align:middle;">Basic (ETB)</th>
                        <th rowspan="2" style="vertical-align:middle;">Days</th>
                        <th rowspan="2" style="vertical-align:middle;">Allowance (ETB)</th>
                        <th rowspan="2" style="vertical-align:middle;background:var(--success-light);color:var(--success);">Gross (ETB)</th>
                        <th colspan="4" style="text-align:center;background:var(--danger-light);color:var(--danger);">Deductions (ETB)</th>
                        <th rowspan="2" style="vertical-align:middle;background:var(--danger-light);color:var(--danger);">Total Ded.</th>
                        <th rowspan="2" style="vertical-align:middle;background:var(--success-light);color:var(--success);">Net Pay (ETB)</th>
                    </tr>
                    <tr>
                        <th style="background:var(--danger-light);color:var(--danger);">Tax</th>
                        <th style="background:var(--warning-light);color:var(--warning);">Pension 11%</th>
                        <th style="background:var(--info-light);color:var(--info);">Other</th>
                        <th style="background:var(--info-light);color:var(--info);">Emp.Pension 18%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($report_rows as $r): ?>
                    <tr>
                        <td class="text-muted"><?= $i++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['full_name']) ?><?= $r['last_name'] ? ' ' . htmlspecialchars($r['last_name']) : '' ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($r['emp_id']) ?></small>
                        </td>
                        <td style="font-size:0.82rem;"><?= htmlspecialchars($r['dept_name']) ?></td>
                        <td style="font-family:monospace;font-size:0.82rem;">
                            <?= $r['cbe_account_number']
                                ? htmlspecialchars($r['cbe_account_number'])
                                : '<span class="text-muted">&mdash;</span>' ?>
                        </td>
                        <td><?= number_format($r['basic_salary'], 2) ?></td>
                        <td style="text-align:center;">
                            <!-- Badge turns yellow if the employee worked fewer than 30 days -->
                            <span class="badge <?= $r['working_days'] == 30 ? 'badge-success' : 'badge-warning' ?>">
                                <?= $r['working_days'] ?>
                            </span>
                        </td>
                        <td><?= $r['total_allowances'] > 0 ? number_format($r['total_allowances'], 2) : '&mdash;' ?></td>
                        <td class="text-bold" style="color:var(--success);"><?= number_format($r['gross_salary'], 2) ?></td>
                        <td style="color:var(--danger);"><?= number_format($r['income_tax'], 2) ?></td>
                        <td style="color:var(--warning);"><?= number_format($r['pension_employee'], 2) ?></td>
                        <td style="color:var(--info);"><?= $r['other_deductions'] > 0 ? number_format($r['other_deductions'], 2) : '&mdash;' ?></td>
                        <td style="color:var(--info);"><?= number_format($r['pension_employer'], 2) ?></td>
                        <td class="text-bold" style="color:var(--danger);"><?= number_format($r['total_deductions'], 2) ?></td>
                        <td class="text-bold" style="color:var(--success);font-size:1rem;"><?= number_format($r['net_pay'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <!-- Grand totals row -->
                    <tr style="background:var(--bg-light);font-weight:700;">
                        <td colspan="4" style="padding:12px 16px;color:var(--primary);">TOTALS (<?= count($report_rows) ?>)</td>
                        <td style="padding:12px 16px;"><?= number_format($gt['basic'], 2) ?></td>
                        <td></td>
                        <td style="padding:12px 16px;"><?= number_format($gt['allowances'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--success);"><?= number_format($gt['gross'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--danger);"><?= number_format($gt['income_tax'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--warning);"><?= number_format($gt['pension_emp'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--info);"><?= number_format($gt['other_deductions'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--info);"><?= number_format($gt['pension_org'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--danger);"><?= number_format($gt['total_deductions'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--success);"><?= number_format($gt['net_pay'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Department breakdown: progress bars showing gross pay per department -->
<?php if (!empty($dept_breakdown)): ?>
<div class="card mb-3">
    <div class="card-header">
        <h3><i class="fas fa-building" style="color:var(--primary);margin-right:8px"></i>
            Department Breakdown &mdash; <?= htmlspecialchars($sel_period['period_label']) ?>
        </h3>
    </div>
    <div class="card-body">
        <?php
        // Use the highest gross as 100% to scale the progress bars
        $max_gross = max(array_column($dept_breakdown, 'total_gross')) ?: 1;
        foreach ($dept_breakdown as $db):
            $pct = round(($db['total_gross'] / $max_gross) * 100);
        ?>
        <div style="margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;flex-wrap:wrap;gap:6px;">
                <div>
                    <span style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($db['dept_name']) ?></span>
                    <span class="badge badge-gray" style="margin-left:6px;"><?= $db['emp_count'] ?> staff</span>
                </div>
                <div style="text-align:right;font-size:0.82rem;">
                    <span style="color:var(--success);font-weight:700;">Net: ETB <?= number_format($db['total_net'], 2) ?></span>
                    <span style="color:var(--gray-400);margin-left:8px;">Gross: ETB <?= number_format($db['total_gross'], 2) ?></span>
                </div>
            </div>
            <div style="background:var(--gray-200);border-radius:20px;height:10px;">
                <div style="width:<?= $pct ?>%;background:var(--primary);height:10px;border-radius:20px;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Annual summary table: one row per processed month in the current year -->
<?php if (!empty($annual)): ?>
<?php
// Pre-calculate year totals for the export
$ann_gt_export = array_fill_keys(['gross','tax','pen_emp','pen_org','other','net'], 0);
foreach ($annual as $a) {
    $ann_gt_export['gross']   += $a['total_gross'];
    $ann_gt_export['tax']     += $a['total_tax'];
    $ann_gt_export['pen_emp'] += $a['total_pension_emp'];
    $ann_gt_export['pen_org'] += $a['total_pension_org'];
    $ann_gt_export['other']   += $a['total_other'];
    $ann_gt_export['net']     += $a['total_net'];
}
?>
<div class="card" id="annualSummaryCard">
    <div class="card-header">
        <h3><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px"></i>
            Annual Summary &mdash; <?= date('Y') ?>
        </h3>
        <div class="d-flex gap-2" style="align-items:center;">
            <span class="badge badge-primary"><?= count($annual) ?> months processed</span>
            <button class="btn btn-success btn-sm" onclick="exportAnnualToExcel()">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="annualTable">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Employees</th>
                        <th>Total Gross (ETB)</th>
                        <th>Total Tax (ETB)</th>
                        <th>Pension Emp 11% (ETB)</th>
                        <th>Pension Org 18% (ETB)</th>
                        <th>Other Ded. (ETB)</th>
                        <th>Total Net Pay (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $ann_gt = array_fill_keys(['gross','tax','pen_emp','pen_org','other','net'], 0);
                    foreach ($annual as $a):
                        $ann_gt['gross']   += $a['total_gross'];
                        $ann_gt['tax']     += $a['total_tax'];
                        $ann_gt['pen_emp'] += $a['total_pension_emp'];
                        $ann_gt['pen_org'] += $a['total_pension_org'];
                        $ann_gt['other']   += $a['total_other'];
                        $ann_gt['net']     += $a['total_net'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($a['period_label']) ?></strong></td>
                        <td><?= $a['emp_count'] ?></td>
                        <td><?= number_format($a['total_gross'], 2) ?></td>
                        <td style="color:var(--danger);"><?= number_format($a['total_tax'], 2) ?></td>
                        <td style="color:var(--warning);"><?= number_format($a['total_pension_emp'], 2) ?></td>
                        <td style="color:var(--info);"><?= number_format($a['total_pension_org'], 2) ?></td>
                        <td><?= number_format($a['total_other'], 2) ?></td>
                        <td class="text-bold" style="color:var(--success);"><?= number_format($a['total_net'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--bg-light);font-weight:700;">
                        <td style="padding:12px 16px;color:var(--primary);">YEAR TOTAL</td>
                        <td style="padding:12px 16px;"></td>
                        <td style="padding:12px 16px;"><?= number_format($ann_gt['gross'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--danger);"><?= number_format($ann_gt['tax'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--warning);"><?= number_format($ann_gt['pen_emp'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--info);"><?= number_format($ann_gt['pen_org'], 2) ?></td>
                        <td style="padding:12px 16px;"><?= number_format($ann_gt['other'], 2) ?></td>
                        <td style="padding:12px 16px;color:var(--success);"><?= number_format($ann_gt['net'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
function exportAnnualToExcel() {
    const year    = '<?= date('Y') ?>';
    const title   = 'Annual Summary — ' + year;
    const filename = 'Annual_Summary_' + year + '.xls';

    // Build XLS-compatible HTML table
    const table = document.getElementById('annualTable');

    let html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" '
             + 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
             + 'xmlns="http://www.w3.org/TR/REC-html40">'
             + '<head><meta charset="UTF-8">'
             + '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets>'
             + '<x:ExcelWorksheet><x:Name>Annual Summary</x:Name>'
             + '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>'
             + '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->'
             + '<style>'
             + 'table { border-collapse: collapse; width: 100%; }'
             + 'th, td { border: 1px solid #999; padding: 6px 10px; font-size: 11pt; }'
             + 'th { background: #1565C0; color: white; font-weight: bold; text-align: center; }'
             + '.title-row td { background: #0D47A1; color: white; font-size: 14pt; '
             + '               font-weight: bold; text-align: center; padding: 12px; }'
             + '.total-row td { background: #E3F2FD; font-weight: bold; }'
             + '</style></head><body>';

    // Title row spanning all columns
    html += '<table>'
          + '<tr class="title-row"><td colspan="8">' + title + '</td></tr>'
          + '<tr class="title-row"><td colspan="8">Bahir Dar Institute of Technology &mdash; BiT Payroll System</td></tr>'
          + '<tr><td colspan="8"></td></tr>'; // blank spacer row

    // Header row
    html += '<tr>';
    table.querySelectorAll('thead th').forEach(th => {
        html += '<th>' + th.innerText + '</th>';
    });
    html += '</tr>';

    // Data rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        html += '<tr>';
        tr.querySelectorAll('td').forEach(td => {
            // Strip commas from numbers so Excel treats them as numbers
            const val = td.innerText.trim();
            html += '<td>' + val + '</td>';
        });
        html += '</tr>';
    });

    // Totals row
    table.querySelectorAll('tfoot tr').forEach(tr => {
        html += '<tr class="total-row">';
        tr.querySelectorAll('td').forEach(td => {
            html += '<td>' + td.innerText.trim() + '</td>';
        });
        html += '</tr>';
    });

    html += '</table></body></html>';

    // Trigger download
    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

<?php else: ?>
<!-- No payrolls finalized yet for this year -->
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-chart-bar"></i></div>
            <p>No finalized payrolls yet for <?= date('Y') ?>.</p>
            <a href="process_payroll.php" class="btn btn-primary btn-sm mt-2">
                <i class="fas fa-play-circle"></i> Process Payroll
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once $depth . 'includes/footer.php'; ?>

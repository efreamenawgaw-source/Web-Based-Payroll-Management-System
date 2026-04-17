<?php
$page_title = 'Process Payroll';
$active_nav = 'process';
$depth      = '../../';

$success = '';
$error   = '';
$results = [];

// ============================================================
// Ethiopian Income Tax — Revised Monthly Employment Tax 2025
// Brackets effective 2025 (updated from old Proclamation)
//
// Monthly Taxable Income (ETB) | Rate  | Deduction
// 0        – 2,000             |  0%   |      0
// 2,001    – 4,000             | 15%   |    300
// 4,001    – 7,000             | 20%   |    500
// 7,001    – 10,000            | 25%   |    850
// 10,001   – 14,000            | 30%   |  1,350
// Over 14,000                  | 35%   |  2,050
//
// Taxable Income = Gross Salary − Employee Pension (7% of basic)
// Net Pay        = Taxable Income − Income Tax
// ============================================================
function calculateEthiopianTax(float $taxable): float {
    if ($taxable <= 2000)   return 0.00;
    if ($taxable <= 4000)   return ($taxable * 0.15) - 300.00;
    if ($taxable <= 7000)   return ($taxable * 0.20) - 500.00;
    if ($taxable <= 10000)  return ($taxable * 0.25) - 850.00;
    if ($taxable <= 14000)  return ($taxable * 0.30) - 1350.00;
    return ($taxable * 0.35) - 2050.00;
}

// Returns the bracket label and badge color for display
function getTaxBracket(float $taxable): array {
    if ($taxable <= 2000)   return ['label' => 'Exempt — 0%',  'rate' => '0%',   'color' => 'badge-success'];
    if ($taxable <= 4000)   return ['label' => 'Bracket 2 — 15%', 'rate' => '15%', 'color' => 'badge-info'];
    if ($taxable <= 7000)   return ['label' => 'Bracket 3 — 20%', 'rate' => '20%', 'color' => 'badge-primary'];
    if ($taxable <= 10000)  return ['label' => 'Bracket 4 — 25%', 'rate' => '25%', 'color' => 'badge-warning'];
    if ($taxable <= 14000)  return ['label' => 'Bracket 5 — 30%', 'rate' => '30%', 'color' => 'badge-warning'];
    return                         ['label' => 'Bracket 6 — 35%', 'rate' => '35%', 'color' => 'badge-danger'];
}

// ============================================================
// Sample employee data (in production, fetch from DB)
// ============================================================
$employees = [
    ['id'=>'EMP-101','name'=>'Admasu Dejene',    'basic'=>12500, 'housing'=>1000, 'transport'=>500,  'position'=>0,   'teaching'=>0],
    ['id'=>'EMP-102','name'=>'Bekele Abebe',     'basic'=>15200, 'housing'=>1500, 'transport'=>700,  'position'=>500, 'teaching'=>0],
    ['id'=>'EMP-103','name'=>'Chaltu Kebede',    'basic'=>9800,  'housing'=>800,  'transport'=>400,  'position'=>0,   'teaching'=>0],
    ['id'=>'EMP-104','name'=>'Dawit Solomon',    'basic'=>11000, 'housing'=>1000, 'transport'=>500,  'position'=>200, 'teaching'=>0],
    ['id'=>'EMP-105','name'=>'Eleni Tadesse',    'basic'=>13500, 'housing'=>1200, 'transport'=>600,  'position'=>300, 'teaching'=>0],
    ['id'=>'EMP-106','name'=>'Fatuma Ali',       'basic'=>12000, 'housing'=>1000, 'transport'=>500,  'position'=>0,   'teaching'=>500],
    ['id'=>'EMP-107','name'=>'Girma Haile',      'basic'=>8500,  'housing'=>600,  'transport'=>300,  'position'=>0,   'teaching'=>0],
    ['id'=>'EMP-108','name'=>'Ibrahim Yusuf',    'basic'=>22000, 'housing'=>2000, 'transport'=>1000, 'position'=>1000,'teaching'=>1000],
    ['id'=>'EMP-109','name'=>'Kidist Mekonnen',  'basic'=>8800,  'housing'=>700,  'transport'=>350,  'position'=>0,   'teaching'=>0],
    ['id'=>'EMP-110','name'=>'Lemlem Tesfaye',   'basic'=>18000, 'housing'=>1800, 'transport'=>800,  'position'=>800, 'teaching'=>500],
];

// ============================================================
// Generate dynamic payroll period options (last 12 months)
// ============================================================
$period_options = [];
for ($i = 0; $i < 12; $i++) {
    $ts = mktime(0, 0, 0, date('n') - $i, 1, date('Y'));
    $period_options[] = date('F Y', $ts);
}

// ============================================================
// Process POST — run payroll calculation
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_payroll'])) {
    $period = trim($_POST['period'] ?? '');
    if (empty($period)) {
        $error = 'Please select a payroll period.';
    } else {
        foreach ($employees as $emp) {
            $allowances  = $emp['housing'] + $emp['transport'] + $emp['position'] + $emp['teaching'];
            $gross       = $emp['basic'] + $allowances;
            $pension_emp = round($emp['basic'] * 0.07, 2);   // 7% of basic
            $pension_org = round($emp['basic'] * 0.11, 2);   // 11% of basic (employer)
            $taxable     = round($gross - $pension_emp, 2);  // taxable = gross − pension
            $tax         = round(calculateEthiopianTax($taxable), 2);
            $net         = round($taxable - $tax, 2);
            $bracket     = getTaxBracket($taxable);

            $results[] = [
                'id'          => $emp['id'],
                'name'        => $emp['name'],
                'basic'       => $emp['basic'],
                'housing'     => $emp['housing'],
                'transport'   => $emp['transport'],
                'position'    => $emp['position'],
                'teaching'    => $emp['teaching'],
                'allowances'  => $allowances,
                'gross'       => $gross,
                'pension_emp' => $pension_emp,
                'pension_org' => $pension_org,
                'taxable'     => $taxable,
                'tax'         => $tax,
                'net'         => $net,
                'bracket'     => $bracket,
            ];
        }
        $success = 'Payroll calculated successfully for <strong>' . htmlspecialchars($period) . '</strong>. '
                 . count($results) . ' employees processed. Please review and confirm.';
    }
}

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">Finance</a><span>/</span><span>Process Payroll</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Process Payroll</h1>
        <p>Calculate salaries, deductions, income tax, and pension for all active employees.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <span><?= $success ?></span>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($error) ?></span>
</div>
<?php endif; ?>

<!-- ============================================================
     PAYROLL PERIOD SELECTION
     ============================================================ -->
<div class="card mb-3">
    <div class="card-header">
        <h3>
            <i class="fas fa-calendar-alt" style="color:var(--primary);margin-right:8px"></i>
            Select Payroll Period
        </h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="form-row" style="align-items:flex-end;">
                <div class="form-group">
                    <label class="form-label">
                        Payroll Period <span style="color:var(--danger)">*</span>
                    </label>
                    <select name="period" class="form-control" required>
                        <option value="">— Select Period —</option>
                        <?php foreach ($period_options as $opt): ?>
                        <option value="<?= $opt ?>"
                            <?= (($_POST['period'] ?? '') === $opt) ? 'selected' : '' ?>>
                            <?= $opt ?>
                            <?= ($opt === date('F Y')) ? '(Current)' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Employee Filter</label>
                    <select name="filter" class="form-control">
                        <option value="all">All Active Employees (<?= count($employees) ?>)</option>
                        <option value="academic">Academic Staff Only</option>
                        <option value="admin">Administrative Staff Only</option>
                        <option value="technical">Technical Staff Only</option>
                    </select>
                </div>
                <div class="form-group" style="padding-bottom:18px;">
                    <button type="submit" name="run_payroll" class="btn btn-primary">
                        <i class="fas fa-play-circle"></i> Calculate Payroll
                    </button>
                </div>
            </div>
        </form>

        <!-- Calculation Formula Summary -->
        <div style="background:var(--bg-light);border-radius:var(--radius);padding:16px;margin-top:8px;">
            <h4 style="font-size:0.85rem;color:var(--primary);margin-bottom:12px;">
                <i class="fas fa-calculator"></i>
                Calculation Formula — Ethiopian Regulations
            </h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;font-size:0.82rem;color:var(--gray-600);">
                <div style="padding:10px;background:var(--white);border-radius:7px;border-left:3px solid var(--primary);">
                    <strong style="color:var(--primary);">① Gross Salary</strong><br>
                    Basic + Housing + Transport + Position + Teaching
                </div>
                <div style="padding:10px;background:var(--white);border-radius:7px;border-left:3px solid var(--warning);">
                    <strong style="color:var(--warning);">② Employee Pension</strong><br>
                    7% × Basic Salary
                </div>
                <div style="padding:10px;background:var(--white);border-radius:7px;border-left:3px solid var(--info);">
                    <strong style="color:var(--info);">③ Employer Pension</strong><br>
                    11% × Basic Salary (paid by BiT)
                </div>
                <div style="padding:10px;background:var(--white);border-radius:7px;border-left:3px solid var(--accent);">
                    <strong style="color:var(--accent);">④ Taxable Income</strong><br>
                    Gross − Employee Pension (7%)
                </div>
                <div style="padding:10px;background:var(--white);border-radius:7px;border-left:3px solid var(--danger);">
                    <strong style="color:var(--danger);">⑤ Income Tax</strong><br>
                    Ethiopian Tax Brackets (Proc. 1395/2025)
                </div>
                <div style="padding:10px;background:var(--white);border-radius:7px;border-left:3px solid var(--success);">
                    <strong style="color:var(--success);">⑥ Net Pay</strong><br>
                    Taxable Income − Income Tax
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     ETHIOPIAN TAX BRACKETS — Revised Monthly Employment Tax 2025
     ============================================================ -->
<div class="card mb-3">
    <div class="card-header">
        <h3>
            <i class="fas fa-percent" style="color:var(--warning);margin-right:8px"></i>
            Revised Monthly Employment Income Tax Brackets (2025)
        </h3>
        <span class="badge badge-warning">
            <i class="fas fa-gavel"></i> Updated 2025
        </span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Monthly Taxable Income (ETB)</th>
                        <th>Tax Rate</th>
                        <th>Deduction (ETB)</th>
                        <th>Tax Formula</th>
                        <th>Example: ETB 15,000 taxable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Revised 2025 brackets
                    // [bracket#, from, to_label, rate_label, rate_pct, deduction, formula, example]
                    $brackets = [
                        [1, '0',      '2,000',  '0% (Exempt)', 0.00,  '0.00',    'Tax = 0',                              '0.00 (exempt)'],
                        [2, '2,001',  '4,000',  '15%',        0.15,  '300.00',  'Tax = (Income × 0.15) − 300',          '—'],
                        [3, '4,001',  '7,000',  '20%',        0.20,  '500.00',  'Tax = (Income × 0.20) − 500',          '—'],
                        [4, '7,001',  '10,000', '25%',        0.25,  '850.00',  'Tax = (Income × 0.25) − 850',          '—'],
                        [5, '10,001', '14,000', '30%',        0.30,  '1,350.00','Tax = (Income × 0.30) − 1,350',        '—'],
                        [6, '14,001', 'Above',  '35%',        0.35,  '2,050.00','Tax = (Income × 0.35) − 2,050',        '(15,000 × 0.35) − 2,050 = 3,200'],
                    ];

                    $badge_colors = [
                        1 => 'badge-success',
                        2 => 'badge-info',
                        3 => 'badge-primary',
                        4 => 'badge-warning',
                        5 => 'badge-warning',
                        6 => 'badge-danger',
                    ];

                    foreach ($brackets as $b):
                        $isHighlight = ($b[0] === 6); // highlight bracket 6 for the example
                    ?>
                    <tr <?= $isHighlight ? 'style="background:var(--warning-light);"' : '' ?>>
                        <td>
                            <span class="badge <?= $badge_colors[$b[0]] ?>">
                                <?= $b[0] ?>
                            </span>
                        </td>
                        <td>
                            <strong>ETB <?= $b[1] ?></strong>
                            <?= $b[2] !== 'Above' ? ' — ETB ' . $b[2] : ' &amp; above' ?>
                        </td>
                        <td>
                            <span class="badge <?= $badge_colors[$b[0]] ?>">
                                <?= $b[2] ?>
                            </span>
                        </td>
                        <td>ETB <?= $b[5] ?></td>
                        <td style="font-family:monospace;font-size:0.8rem;color:var(--gray-600);">
                            <?= $b[6] ?>
                        </td>
                        <td style="font-size:0.82rem;
                            <?= $isHighlight ? 'font-weight:700;color:var(--warning);' : 'color:var(--gray-400);' ?>">
                            <?= $b[7] ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Key change callout -->
        <div style="padding:12px 18px;background:var(--success-light);border-top:1px solid var(--gray-200);
                    font-size:0.82rem;color:var(--success);display:flex;align-items:flex-start;gap:10px;">
            <i class="fas fa-arrow-up-right-dots" style="margin-top:2px;flex-shrink:0;"></i>
            <span>
                <strong>Key 2025 Update:</strong>
                The exempt threshold has been raised from ETB 600 to <strong>ETB 2,000/month</strong>.
                The 10% bracket has been removed. Rates now start at 15% for income above ETB 2,000.
                Taxable Income = Gross Salary − Employee Pension (7% of basic salary).
            </span>
        </div>

        <div style="padding:10px 18px;background:var(--info-light);border-top:1px solid var(--gray-200);
                    font-size:0.8rem;color:var(--info);display:flex;align-items:center;gap:8px;">
            <i class="fas fa-info-circle"></i>
            <span>
                Source: <strong>Revised Monthly Employment Tax Brackets — Ethiopia, 2025</strong>.
                Pension contributions: Employee <strong>7%</strong> + Employer <strong>11%</strong> of basic salary.
            </span>
        </div>
    </div>
</div>

<?php if (!empty($results)): ?>
<!-- ============================================================
     PAYROLL CALCULATION RESULTS
     ============================================================ -->
<div class="card">
    <div class="card-header">
        <h3>
            <i class="fas fa-table" style="color:var(--success);margin-right:8px"></i>
            Payroll Results — <?= htmlspecialchars($_POST['period'] ?? '') ?>
        </h3>
        <div class="d-flex gap-2">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="period" value="<?= htmlspecialchars($_POST['period'] ?? '') ?>">
                <button type="submit" name="confirm_payroll" class="btn btn-success btn-sm">
                    <i class="fas fa-check"></i> Confirm & Save
                </button>
            </form>
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
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Basic (ETB)</th>
                        <th>Allowances (ETB)</th>
                        <th>Gross (ETB)</th>
                        <th>Pension 7% (ETB)</th>
                        <th>Taxable (ETB)</th>
                        <th>Tax Bracket</th>
                        <th>Income Tax (ETB)</th>
                        <th>Employer 11% (ETB)</th>
                        <th>Net Pay (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_gross       = 0;
                    $total_allowances  = 0;
                    $total_pension_emp = 0;
                    $total_pension_org = 0;
                    $total_taxable     = 0;
                    $total_tax         = 0;
                    $total_net         = 0;

                    foreach ($results as $r):
                        $total_gross       += $r['gross'];
                        $total_allowances  += $r['allowances'];
                        $total_pension_emp += $r['pension_emp'];
                        $total_pension_org += $r['pension_org'];
                        $total_taxable     += $r['taxable'];
                        $total_tax         += $r['tax'];
                        $total_net         += $r['net'];
                    ?>
                    <tr>
                        <td>
                            <span class="badge badge-primary"><?= htmlspecialchars($r['id']) ?></span>
                        </td>
                        <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                        <td><?= number_format($r['basic'], 2) ?></td>
                        <td>
                            <?= number_format($r['allowances'], 2) ?>
                            <!-- Allowance breakdown tooltip -->
                            <span title="Housing: <?= number_format($r['housing'],2) ?> | Transport: <?= number_format($r['transport'],2) ?> | Position: <?= number_format($r['position'],2) ?> | Teaching: <?= number_format($r['teaching'],2) ?>"
                                  style="cursor:help;color:var(--gray-400);font-size:0.75rem;margin-left:3px;">
                                <i class="fas fa-info-circle"></i>
                            </span>
                        </td>
                        <td class="text-bold"><?= number_format($r['gross'], 2) ?></td>
                        <td style="color:var(--warning);">
                            <?= number_format($r['pension_emp'], 2) ?>
                        </td>
                        <td><?= number_format($r['taxable'], 2) ?></td>
                        <td>
                            <span class="badge <?= $r['bracket']['color'] ?>" style="font-size:0.68rem;">
                                <?= $r['bracket']['rate'] ?>
                            </span>
                        </td>
                        <td style="color:var(--danger);">
                            <?= number_format($r['tax'], 2) ?>
                        </td>
                        <td style="color:var(--info);">
                            <?= number_format($r['pension_org'], 2) ?>
                        </td>
                        <td class="text-bold text-success">
                            <?= number_format($r['net'], 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--bg-light);font-weight:700;font-size:0.88rem;">
                        <td colspan="2" style="padding:12px 16px;color:var(--primary);">
                            TOTALS (<?= count($results) ?> employees)
                        </td>
                        <td style="padding:12px 16px;"></td>
                        <td style="padding:12px 16px;"><?= number_format($total_allowances, 2) ?></td>
                        <td style="padding:12px 16px;"><?= number_format($total_gross, 2) ?></td>
                        <td style="padding:12px 16px;color:var(--warning);"><?= number_format($total_pension_emp, 2) ?></td>
                        <td style="padding:12px 16px;"><?= number_format($total_taxable, 2) ?></td>
                        <td style="padding:12px 16px;"></td>
                        <td style="padding:12px 16px;color:var(--danger);"><?= number_format($total_tax, 2) ?></td>
                        <td style="padding:12px 16px;color:var(--info);"><?= number_format($total_pension_org, 2) ?></td>
                        <td style="padding:12px 16px;color:var(--success);"><?= number_format($total_net, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Summary Footer -->
    <div class="card-footer">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;">
            <div style="text-align:center;padding:10px;background:var(--bg-light);border-radius:var(--radius);">
                <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;">Total Gross</p>
                <p style="font-size:1.1rem;font-weight:700;color:var(--primary);margin:0;">
                    ETB <?= number_format($total_gross, 2) ?>
                </p>
            </div>
            <div style="text-align:center;padding:10px;background:var(--warning-light);border-radius:var(--radius);">
                <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;">Employee Pension (7%)</p>
                <p style="font-size:1.1rem;font-weight:700;color:var(--warning);margin:0;">
                    ETB <?= number_format($total_pension_emp, 2) ?>
                </p>
            </div>
            <div style="text-align:center;padding:10px;background:var(--info-light);border-radius:var(--radius);">
                <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;">Employer Pension (11%)</p>
                <p style="font-size:1.1rem;font-weight:700;color:var(--info);margin:0;">
                    ETB <?= number_format($total_pension_org, 2) ?>
                </p>
            </div>
            <div style="text-align:center;padding:10px;background:var(--danger-light);border-radius:var(--radius);">
                <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;">Total Income Tax</p>
                <p style="font-size:1.1rem;font-weight:700;color:var(--danger);margin:0;">
                    ETB <?= number_format($total_tax, 2) ?>
                </p>
            </div>
            <div style="text-align:center;padding:10px;background:var(--success-light);border-radius:var(--radius);">
                <p style="font-size:0.72rem;color:var(--gray-400);margin:0;text-transform:uppercase;">Total Net Pay</p>
                <p style="font-size:1.1rem;font-weight:700;color:var(--success);margin:0;">
                    ETB <?= number_format($total_net, 2) ?>
                </p>
            </div>
        </div>

        <div style="margin-top:14px;padding:10px 14px;background:var(--info-light);border-radius:var(--radius);font-size:0.8rem;color:var(--info);display:flex;align-items:center;gap:8px;">
            <i class="fas fa-gavel"></i>
            <span>
                Income tax calculated per <strong>Revised Monthly Employment Tax Brackets — Ethiopia 2025</strong>
                (exempt up to ETB 2,000; rates 15%–35% above that).
                Pension per <strong>Pension Proclamation (Updated 2025)</strong>:
                Employee <strong>7%</strong> + Employer <strong>11%</strong> of basic salary.
            </span>
        </div>
    </div>
</div>

<!-- Allowance Breakdown Detail -->
<div class="card mt-3">
    <div class="card-header">
        <h3>
            <i class="fas fa-hand-holding-usd" style="color:var(--success);margin-right:8px"></i>
            Allowance Breakdown Detail
        </h3>
        <span class="text-muted" style="font-size:0.8rem;">Hover the ℹ icon in the main table for quick view</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Basic (ETB)</th>
                        <th>Housing (ETB)</th>
                        <th>Transport (ETB)</th>
                        <th>Position (ETB)</th>
                        <th>Teaching (ETB)</th>
                        <th>Total Allow. (ETB)</th>
                        <th>Gross (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($r['id']) ?></span></td>
                        <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                        <td><?= number_format($r['basic'], 2) ?></td>
                        <td><?= number_format($r['housing'], 2) ?></td>
                        <td><?= number_format($r['transport'], 2) ?></td>
                        <td><?= number_format($r['position'], 2) ?></td>
                        <td><?= number_format($r['teaching'], 2) ?></td>
                        <td class="text-bold"><?= number_format($r['allowances'], 2) ?></td>
                        <td class="text-bold text-primary"><?= number_format($r['gross'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once $depth . 'includes/footer.php'; ?>

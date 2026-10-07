<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

date_default_timezone_set('Asia/Manila');

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$fromDate = DateTime::createFromFormat('Y-m-d', $from);
$toDate   = DateTime::createFromFormat('Y-m-d', $to);

if (!$fromDate || $fromDate->format('Y-m-d') !== $from) {
    $from = date('Y-m-01');
    $fromDate = new DateTime($from);
}

if (!$toDate || $toDate->format('Y-m-d') !== $to) {
    $to = date('Y-m-d');
    $toDate = new DateTime($to);
}

if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$fromDT = $from . ' 00:00:00';
$toDT = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';

$department = $_GET['department'] ?? 'overall';
$allowedDepartments = ['overall', 'store', 'wallet', 'computer_shop', 'pisowifi'];
if (!in_array($department, $allowedDepartments, true)) {
    $department = 'overall';
}

/*
|--------------------------------------------------------------------------
| STORE SALES
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(total), 0) AS sales,
        COALESCE(SUM(total_cost), 0) AS cost,
        COALESCE(SUM(total_profit), 0) AS profit,
        COUNT(*) AS transactions
    FROM sales
    WHERE sale_date >= ?
      AND sale_date < ?
");
$stmt->execute([$fromDT, $toDT]);
$sales = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'sales' => 0,
    'cost' => 0,
    'profit' => 0,
    'transactions' => 0
];

/*
|--------------------------------------------------------------------------
| WALLET SERVICE INCOME
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(service_fee), 0) AS fees,
        COUNT(*) AS transactions,
        COALESCE(SUM(
            CASE
                WHEN transaction_type = 'cash_in' THEN wallet_amount
                ELSE 0
            END
        ), 0) AS cash_in,
        COALESCE(SUM(
            CASE
                WHEN transaction_type = 'cash_out' THEN wallet_amount
                ELSE 0
            END
        ), 0) AS cash_out
    FROM wallet_transactions
    WHERE transaction_date >= ?
      AND transaction_date < ?
");
$stmt->execute([$fromDT, $toDT]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'fees' => 0,
    'transactions' => 0,
    'cash_in' => 0,
    'cash_out' => 0
];

/*
|--------------------------------------------------------------------------
| STORE EXPENSES
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) AS expenses
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
");
$stmt->execute([$from, $to]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['expenses' => 0];

/*
|--------------------------------------------------------------------------
| COMPUTER SHOP / PISONET HARVESTS
|--------------------------------------------------------------------------
|
| Pisonets are harvested monthly. The report therefore uses harvest_month
| rather than harvested_at when deciding which period the collection belongs
| to. The selected date range is converted to its first-of-month boundaries.
|--------------------------------------------------------------------------
*/
$pisonet = [
    'gross' => 0,
    'transactions' => 0,
    'units' => 0
];
$pisonetRows = [];
$pisonetSummary = [];

$fromMonth = date('Y-m-01', strtotime($from));
$toMonth = date('Y-m-01', strtotime($to));

try {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(h.amount), 0) AS gross,
            COUNT(*) AS transactions,
            COUNT(DISTINCT h.unit_id) AS units
        FROM pisonet_harvests h
        WHERE h.harvest_month >= ?
          AND h.harvest_month <= ?
    ");
    $stmt->execute([$fromMonth, $toMonth]);
    $pisonetTotals = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pisonetTotals) {
        $pisonet['gross'] = (float)$pisonetTotals['gross'];
        $pisonet['transactions'] = (int)$pisonetTotals['transactions'];
        $pisonet['units'] = (int)$pisonetTotals['units'];
    }

    $stmt = $pdo->prepare("
        SELECT
            h.id,
            h.harvest_month,
            h.amount,
            h.remarks,
            h.harvested_at,
            u.unit_name,
            COALESCE(NULLIF(us.full_name, ''), 'System User') AS recorded_by
        FROM pisonet_harvests h
        INNER JOIN pisonet_units u
            ON u.id = h.unit_id
        LEFT JOIN users us
            ON us.id = h.user_id
        WHERE h.harvest_month >= ?
          AND h.harvest_month <= ?
        ORDER BY h.harvest_month DESC, h.harvested_at DESC, u.id ASC
    ");
    $stmt->execute([$fromMonth, $toMonth]);
    $pisonetRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            u.unit_name,
            COALESCE(SUM(h.amount), 0) AS amount,
            COUNT(h.id) AS records
        FROM pisonet_units u
        INNER JOIN pisonet_harvests h
            ON h.unit_id = u.id
        WHERE h.harvest_month >= ?
          AND h.harvest_month <= ?
        GROUP BY u.id, u.unit_name
        ORDER BY amount DESC, u.unit_name ASC
    ");
    $stmt->execute([$fromMonth, $toMonth]);
    $pisonetSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Keep Reports working even if the Pisonet tables are not available yet.
    $pisonet = [
        'gross' => 0,
        'transactions' => 0,
        'units' => 0
    ];
    $pisonetRows = [];
    $pisonetSummary = [];
}

/*
|--------------------------------------------------------------------------
| PISOWIFI MONTHLY EARNINGS
|--------------------------------------------------------------------------
| PisoWiFi records are stored by earning_month/earning_year.
| The selected date range is therefore compared by month.
|--------------------------------------------------------------------------
*/
$pisowifi = [
    'gross' => 0,
    'expenses' => 0,
    'net' => 0,
    'transactions' => 0,
    'boxes' => 0
];
$pisowifiRows = [];
$pisowifiSummary = [];

try {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(e.gross_earnings), 0) AS gross,
            COALESCE(SUM(e.expenses), 0) AS expenses,
            COUNT(*) AS transactions,
            COUNT(DISTINCT e.pisowifi_id) AS boxes
        FROM pisowifi_earnings e
        WHERE STR_TO_DATE(CONCAT(e.earning_year, '-', LPAD(e.earning_month, 2, '0'), '-01'), '%Y-%m-%d') >= ?
          AND STR_TO_DATE(CONCAT(e.earning_year, '-', LPAD(e.earning_month, 2, '0'), '-01'), '%Y-%m-%d') <= ?
    ");
    $stmt->execute([$fromMonth, $toMonth]);
    $pisowifiTotals = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pisowifiTotals) {
        $pisowifi['gross'] = (float)$pisowifiTotals['gross'];
        $pisowifi['expenses'] = (float)$pisowifiTotals['expenses'];
        $pisowifi['transactions'] = (int)$pisowifiTotals['transactions'];
        $pisowifi['boxes'] = (int)$pisowifiTotals['boxes'];
        $pisowifi['net'] = $pisowifi['gross'] - $pisowifi['expenses'];
    }

    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.pisowifi_id,
            e.earning_month,
            e.earning_year,
            e.gross_earnings,
            e.expenses,
            (e.gross_earnings - e.expenses) AS net_earnings,
            e.notes,
            p.box_name,
            p.location
        FROM pisowifi_earnings e
        INNER JOIN pisowifi p
            ON p.id = e.pisowifi_id
        WHERE STR_TO_DATE(CONCAT(e.earning_year, '-', LPAD(e.earning_month, 2, '0'), '-01'), '%Y-%m-%d') >= ?
          AND STR_TO_DATE(CONCAT(e.earning_year, '-', LPAD(e.earning_month, 2, '0'), '-01'), '%Y-%m-%d') <= ?
        ORDER BY e.earning_year DESC, e.earning_month DESC, p.box_name ASC
    ");
    $stmt->execute([$fromMonth, $toMonth]);
    $pisowifiRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.box_name,
            COALESCE(SUM(e.gross_earnings), 0) AS gross,
            COALESCE(SUM(e.expenses), 0) AS expenses,
            COUNT(e.id) AS records
        FROM pisowifi p
        INNER JOIN pisowifi_earnings e
            ON e.pisowifi_id = p.id
        WHERE STR_TO_DATE(CONCAT(e.earning_year, '-', LPAD(e.earning_month, 2, '0'), '-01'), '%Y-%m-%d') >= ?
          AND STR_TO_DATE(CONCAT(e.earning_year, '-', LPAD(e.earning_month, 2, '0'), '-01'), '%Y-%m-%d') <= ?
        GROUP BY p.id, p.box_name
        ORDER BY gross DESC, p.box_name ASC
    ");
    $stmt->execute([$fromMonth, $toMonth]);
    $pisowifiSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pisowifi = [
        'gross' => 0,
        'expenses' => 0,
        'net' => 0,
        'transactions' => 0,
        'boxes' => 0
    ];
    $pisowifiRows = [];
    $pisowifiSummary = [];
}

/*
|--------------------------------------------------------------------------
| OVERALL NET INCOME
|--------------------------------------------------------------------------
|
| Overall Gross Income = Store Gross Profit
|                     + Wallet Service Income
|                     + Computer Shop / Pisonet Harvests
|                     + PisoWiFi Gross Income
|
| Overall Net Income = Overall Gross Income
|                   - Store Expenses
|                   - PisoWiFi Expenses
|--------------------------------------------------------------------------
*/
$overallGross =
    (float)$sales['profit']
    + (float)$wallet['fees']
    + (float)$pisonet['gross']
    + (float)$pisowifi['gross'];

$overallExpenses =
    (float)$expense['expenses']
    + (float)$pisowifi['expenses'];

$overallNet =
    $overallGross
    - $overallExpenses;

$departmentInfo = [
    'overall' => [
        'label' => 'All Departments',
        'short' => 'Overall',
        'gross' => $overallGross,
        'expenses' => $overallExpenses,
        'net' => $overallNet,
        'activity' => (int)$sales['transactions'] + (int)$wallet['transactions'] + (int)$pisonet['transactions'] + (int)$pisowifi['transactions'],
        'activityLabel' => 'combined transactions / records'
    ],
    'store' => [
        'label' => 'Store Sales / POS',
        'short' => 'Store Sales',
        'gross' => (float)$sales['profit'],
        'expenses' => (float)$expense['expenses'],
        'net' => (float)$sales['profit'] - (float)$expense['expenses'],
        'activity' => (int)$sales['transactions'],
        'activityLabel' => 'sales transactions'
    ],
    'wallet' => [
        'label' => 'GCash / Maya',
        'short' => 'GCash / Maya',
        'gross' => (float)$wallet['fees'],
        'expenses' => 0,
        'net' => (float)$wallet['fees'],
        'activity' => (int)$wallet['transactions'],
        'activityLabel' => 'wallet transactions'
    ],
    'computer_shop' => [
        'label' => 'Computer Shop / Pisonet',
        'short' => 'Computer Shop',
        'gross' => (float)$pisonet['gross'],
        'expenses' => 0,
        'net' => (float)$pisonet['gross'],
        'activity' => (int)$pisonet['transactions'],
        'activityLabel' => 'harvest records'
    ],
    'pisowifi' => [
        'label' => 'PisoWiFi',
        'short' => 'PisoWiFi',
        'gross' => (float)$pisowifi['gross'],
        'expenses' => (float)$pisowifi['expenses'],
        'net' => (float)$pisowifi['net'],
        'activity' => (int)$pisowifi['transactions'],
        'activityLabel' => 'monthly records'
    ]
];

$selectedDepartment = $departmentInfo[$department];

/*
|--------------------------------------------------------------------------
| SALES LIST
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        sale_number,
        sale_date,
        total,
        total_cost,
        total_profit,
        payment_method
    FROM sales
    WHERE sale_date >= ?
      AND sale_date < ?
    ORDER BY sale_date DESC
");
$stmt->execute([$fromDT, $toDT]);
$saleRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TOP SELLING PRODUCTS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        si.product_name,
        SUM(si.quantity) AS qty,
        SUM(si.subtotal) AS sales,
        SUM(si.profit) AS profit
    FROM sale_items si
    INNER JOIN sales s
        ON s.id = si.sale_id
    WHERE s.sale_date >= ?
      AND s.sale_date < ?
    GROUP BY si.product_id, si.product_name
    ORDER BY qty DESC
    LIMIT 20
");
$stmt->execute([$fromDT, $toDT]);
$topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| WALLET TRANSACTIONS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        transaction_number,
        transaction_date,
        provider,
        transaction_type,
        wallet_amount,
        service_fee,
        total_received,
        total_paid
    FROM wallet_transactions
    WHERE transaction_date >= ?
      AND transaction_date < ?
    ORDER BY transaction_date DESC
");
$stmt->execute([$fromDT, $toDT]);
$walletRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports Overview - Arnold &amp; Jackie's Store</title>

    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .reports-page {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
        }

        .reports-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
        }

        .reports-heading {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .reports-heading-mark {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #2563eb;
            font-size: 18px;
            font-weight: 800;
        }

        .reports-title {
            margin: 0;
            font-size: 25px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -.03em;
        }

        .reports-subtitle {
            margin: 4px 0 0 42px;
            font-size: 12px;
            color: #7b8494;
        }

        .reports-toolbar {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }

        .report-control {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .report-control label {
            font-size: 10px;
            font-weight: 800;
            color: #9aa2ae;
            text-transform: uppercase;
            letter-spacing: .45px;
        }

        .report-control input,
        .report-control select {
            height: 38px;
            min-width: 138px;
            padding: 0 11px;
            border: 1px solid #e3e7ed;
            border-radius: 9px;
            background: #fff;
            color: #273244;
            font: inherit;
            font-size: 12px;
            outline: 0;
        }

        .report-control select {
            min-width: 195px;
        }

        .report-control input:focus,
        .report-control select:focus {
            border-color: #93b4ff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
        }

        .report-generate {
            height: 38px;
            padding: 0 15px;
            border: 0;
            border-radius: 9px;
            background: #2563eb;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .report-generate:hover {
            background: #1d4ed8;
        }

        .report-tabs {
            display: flex;
            align-items: center;
            gap: 22px;
            height: 46px;
            border-bottom: 1px solid #e8ebf0;
            margin-bottom: 18px;
            overflow-x: auto;
        }

        .report-tab {
            position: relative;
            height: 46px;
            display: inline-flex;
            align-items: center;
            color: #8a919d;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .report-tab:hover {
            color: #334155;
        }

        .report-tab.active {
            color: #2563eb;
        }

        .report-tab.active::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: -1px;
            height: 2px;
            background: #2563eb;
            border-radius: 2px 2px 0 0;
        }

        .reports-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 295px;
            gap: 16px;
            align-items: start;
        }

        .reports-main {
            min-width: 0;
        }

        .reports-side {
            min-width: 0;
        }

        .report-card {
            background: #fff;
            border: 1px solid #e7ebf0;
            border-radius: 14px;
            box-shadow: 0 7px 24px rgba(15, 23, 42, .035);
            overflow: hidden;
        }

        .report-card+.report-card {
            margin-top: 16px;
        }

        .report-card-header {
            min-height: 54px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid #edf0f4;
        }

        .report-card-title {
            font-size: 14px;
            font-weight: 800;
            color: #171f2d;
        }

        .report-card-subtitle {
            margin-top: 3px;
            font-size: 10px;
            color: #9aa2ae;
        }

        .header-badge {
            padding: 6px 9px;
            border: 1px solid #e8ebef;
            border-radius: 7px;
            background: #fbfcfe;
            color: #6b7280;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .chart-card-body {
            padding: 11px 14px 13px;
        }

        .chart-wrap {
            height: 285px;
        }

        .under-chart-grid {
            display: grid;
            grid-template-columns: minmax(0, .95fr) minmax(0, 1.4fr);
            gap: 16px;
            margin-top: 16px;
        }

        .mini-panel {
            padding: 15px 16px;
        }

        .mini-panel-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 12px;
        }

        .mini-panel-title {
            font-size: 13px;
            font-weight: 800;
            color: #171f2d;
        }

        .mini-panel-meta {
            font-size: 10px;
            color: #9aa2ae;
        }

        .donut-row {
            display: grid;
            grid-template-columns: 155px minmax(0, 1fr);
            gap: 15px;
            align-items: center;
        }

        .donut-wrap {
            position: relative;
            width: 155px;
            height: 155px;
        }

        .donut-center {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            flex-direction: column;
        }

        .donut-center strong {
            color: #171f2d;
            font-size: 16px;
        }

        .donut-center span {
            color: #9aa2ae;
            font-size: 9px;
            margin-top: 2px;
        }

        .mix-list {
            display: grid;
            gap: 9px;
        }

        .mix-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 11px;
        }

        .mix-left {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
        }

        .mix-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #2563eb;
            flex: 0 0 7px;
        }

        .mix-name {
            color: #475569;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mix-value {
            color: #172033;
            font-weight: 800;
            white-space: nowrap;
        }

        .top-products-table {
            width: 100%;
            border-collapse: collapse;
        }

        .top-products-table th {
            padding: 8px 8px;
            text-align: left;
            color: #8b94a3;
            background: #fbfcfe;
            border-bottom: 1px solid #eef1f4;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        .top-products-table td {
            padding: 9px 8px;
            border-bottom: 1px solid #f0f2f5;
            font-size: 11px;
            color: #475569;
        }

        .top-products-table tr:last-child td {
            border-bottom: 0;
        }

        .product-name {
            font-weight: 700;
            color: #172033;
        }

        .money {
            font-weight: 800;
            white-space: nowrap;
        }

        .blue {
            color: #20335c;
        }

        .green {
            color: #16a34a;
        }

        .muted-cell {
            color: #9aa2ae;
            font-size: 9px;
            margin-top: 2px;
        }

        .side-section+.side-section {
            margin-top: 16px;
        }

        .side-card-body {
            padding: 15px 16px;
        }

        .side-kicker {
            color: #8c94a0;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .side-value {
            font-size: 25px;
            font-weight: 800;
            color: #172033;
            letter-spacing: -.03em;
            margin-top: 5px;
        }

        .side-caption {
            color: #9aa2ae;
            font-size: 9px;
            margin-top: 2px;
        }

        .rank-list {
            display: grid;
            gap: 0;
            margin-top: 8px;
        }

        .rank-item {
            display: grid;
            grid-template-columns: 25px minmax(0, 1fr) auto;
            gap: 8px;
            align-items: center;
            padding: 10px 0;
            border-top: 1px solid #f0f2f5;
        }

        .rank-item:first-child {
            border-top: 0;
        }

        .rank-number {
            width: 23px;
            height: 23px;
            border-radius: 7px;
            background: #f1f5f9;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 800;
        }

        .rank-name {
            color: #334155;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.25;
        }

        .rank-sub {
            color: #a0a7b2;
            font-size: 9px;
            margin-top: 1px;
        }

        .rank-value {
            color: #172033;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .net-box {
            padding: 14px 16px;
        }

        .net-big {
            font-size: 22px;
            font-weight: 800;
            color: #16a34a;
            margin-top: 4px;
        }

        .net-rows {
            margin-top: 12px;
        }

        .net-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            border-top: 1px solid #f0f2f5;
            font-size: 10px;
        }

        .net-row:first-child {
            border-top: 0;
        }

        .net-row span:first-child {
            color: #7b8494;
        }

        .net-row span:last-child {
            color: #172033;
            font-weight: 800;
            white-space: nowrap;
        }

        .net-row.total span:first-child,
        .net-row.total span:last-child {
            color: #15803d;
        }

        .department-card-body {
            padding: 0;
        }

        .department-table {
            width: 100%;
            border-collapse: collapse;
        }

        .department-table th {
            padding: 10px 12px;
            text-align: left;
            background: #fbfcfe;
            border-bottom: 1px solid #edf0f4;
            color: #8b94a3;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        .department-table td {
            padding: 11px 12px;
            border-bottom: 1px solid #f0f2f5;
            color: #475569;
            font-size: 11px;
        }

        .department-table tr:last-child td {
            border-bottom: 0;
        }

        .department-table .selected {
            background: #f7faff;
        }

        .department-table .total td {
            background: #fbfcfe;
            font-weight: 800;
        }

        .dept-name {
            font-weight: 700;
            color: #172033;
        }

        .dept-note {
            padding: 10px 12px;
            border-top: 1px solid #edf0f4;
            background: #fbfcfe;
            color: #8b94a3;
            font-size: 9px;
            line-height: 1.5;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-table th {
            padding: 10px 12px;
            background: #fbfcfe;
            color: #8b94a3;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .35px;
            text-align: left;
        }

        .activity-table td {
            padding: 10px 12px;
            border-top: 1px solid #eef1f4;
            font-size: 11px;
            color: #475569;
        }

        .activity-table tr:hover td {
            background: #fbfdff;
        }

        .empty-state {
            padding: 28px 15px;
            text-align: center;
            color: #98a1ad;
            font-size: 11px;
        }

        .side-chart-wrap {
            height: 165px;
        }

        @media (max-width: 1200px) {
            .reports-layout {
                grid-template-columns: 1fr;
            }

            .reports-side {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 16px;
            }

            .side-section+.side-section {
                margin-top: 0;
            }
        }

        @media (max-width: 900px) {
            .reports-header {
                flex-direction: column;
            }

            .reports-toolbar {
                width: 100%;
            }

            .under-chart-grid {
                grid-template-columns: 1fr;
            }

            .reports-side {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 620px) {
            .reports-toolbar {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .report-control,
            .report-control input,
            .report-control select,
            .report-generate {
                width: 100%;
                min-width: 0;
            }

            .report-generate {
                grid-column: 1 / -1;
            }

            .reports-side {
                grid-template-columns: 1fr;
            }

            .donut-row {
                grid-template-columns: 1fr;
                justify-items: center;
            }

            .mix-list {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php
    $basePath = '../';
    $activeMenu = 'reports';
    $currentSection = 'reports';
    require_once $basePath . 'includes/sidebar.php';
    ?>

    <div class="main">
        <?php require_once $basePath . 'includes/topbar.php'; ?>

        <main class="content">
            <div class="reports-page">

                <div class="reports-header">
                    <div>
                        <div class="reports-heading">
                            <span class="reports-heading-mark">⌁</span>
                            <h1 class="reports-title">Reports Overview</h1>
                        </div>
                        <p class="reports-subtitle">Financial performance across your store departments.</p>
                    </div>

                    <form method="get" class="reports-toolbar">
                        <input type="hidden" name="department" value="<?= htmlspecialchars($department) ?>">
                        <div class="report-control">
                            <label for="reportFrom">From</label>
                            <input id="reportFrom" type="date" name="from" value="<?= htmlspecialchars($from) ?>">
                        </div>
                        <div class="report-control">
                            <label for="reportTo">To</label>
                            <input id="reportTo" type="date" name="to" value="<?= htmlspecialchars($to) ?>">
                        </div>
                        <div class="report-control">
                            <label for="reportDepartment">Department</label>
                            <select id="reportDepartment" name="department">
                                <?php foreach ($departmentInfo as $key => $info): ?>
                                    <option value="<?= htmlspecialchars($key) ?>" <?= $department === $key ? 'selected' : '' ?>><?= htmlspecialchars($info['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="report-generate" type="submit">Generate</button>
                    </form>
                </div>

                <nav class="report-tabs" aria-label="Report departments">
                    <?php
                    $tabs = [
                        'overall' => 'All',
                        'store' => 'Store',
                        'wallet' => 'GCash / Maya',
                        'computer_shop' => 'Computer Shop',
                        'pisowifi' => 'PisoWiFi'
                    ];
                    foreach ($tabs as $key => $label):
                        $url = '?from=' . urlencode($from) . '&to=' . urlencode($to) . '&department=' . urlencode($key);
                    ?>
                        <a class="report-tab <?= $department === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($label) ?></a>
                    <?php endforeach; ?>
                </nav>

                <div class="reports-layout">
                    <div class="reports-main">

                        <section class="report-card">
                            <div class="report-card-header">
                                <div>
                                    <div class="report-card-title">Income Overview</div>
                                    <div class="report-card-subtitle">Gross income by department for <?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?></div>
                                </div>
                                <div class="header-badge"><?= htmlspecialchars($selectedDepartment['short']) ?></div>
                            </div>
                            <div class="chart-card-body">
                                <div class="chart-wrap"><canvas id="incomeChart"></canvas></div>
                            </div>
                        </section>

                        <div class="under-chart-grid">
                            <section class="report-card">
                                <div class="mini-panel">
                                    <div class="mini-panel-head">
                                        <div class="mini-panel-title">Income mix</div>
                                        <div class="mini-panel-meta">Gross income</div>
                                    </div>
                                    <div class="donut-row">
                                        <div class="donut-wrap">
                                            <canvas id="mixChart"></canvas>
                                            <div class="donut-center">
                                                <strong>₱<?= number_format($overallGross, 0) ?></strong>
                                                <span>total gross</span>
                                            </div>
                                        </div>
                                        <div class="mix-list">
                                            <?php
                                            $mixColors = ['#2563eb', '#8b5cf6', '#06b6d4', '#f59e0b'];
                                            $mixIndex = 0;
                                            foreach (['store', 'wallet', 'computer_shop', 'pisowifi'] as $key):
                                                $value = (float)$departmentInfo[$key]['gross'];
                                                $pct = $overallGross > 0 ? ($value / $overallGross) * 100 : 0;
                                            ?>
                                                <div class="mix-row">
                                                    <div class="mix-left"><span class="mix-dot" style="background:<?= $mixColors[$mixIndex] ?>"></span><span class="mix-name"><?= htmlspecialchars($departmentInfo[$key]['short']) ?></span></div>
                                                    <span class="mix-value"><?= number_format($pct, 1) ?>%</span>
                                                </div>
                                            <?php $mixIndex++;
                                            endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="report-card">
                                <div class="mini-panel">
                                    <div class="mini-panel-head">
                                        <div class="mini-panel-title">Top selling products</div>
                                        <div class="mini-panel-meta">Selected period</div>
                                    </div>
                                    <div class="table-wrap">
                                        <table class="top-products-table">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Qty</th>
                                                    <th>Sales</th>
                                                    <th>Profit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($topProducts): ?>
                                                    <?php foreach (array_slice($topProducts, 0, 6) as $row): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="product-name"><?= htmlspecialchars($row['product_name']) ?></div>
                                                            </td>
                                                            <td><?= number_format((float)$row['qty'], 3) ?></td>
                                                            <td class="money">₱<?= number_format((float)$row['sales'], 2) ?></td>
                                                            <td class="money green">₱<?= number_format((float)$row['profit'], 2) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="4" class="empty-state">No product sales for this period.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <section class="report-card">
                            <div class="report-card-header">
                                <div>
                                    <div class="report-card-title">Department Performance</div>
                                    <div class="report-card-subtitle">Compare gross income, expenses and net income across every department.</div>
                                </div>
                            </div>
                            <div class="department-card-body">
                                <div class="table-wrap">
                                    <table class="department-table">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Activity</th>
                                                <th>Gross Income</th>
                                                <th>Expenses</th>
                                                <th>Net Income</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (['store', 'wallet', 'computer_shop', 'pisowifi'] as $key): $info = $departmentInfo[$key]; ?>
                                                <tr class="<?= $department === $key ? 'selected' : '' ?>">
                                                    <td>
                                                        <div class="dept-name"><?= htmlspecialchars($info['label']) ?></div>
                                                    </td>
                                                    <td><?= number_format((int)$info['activity']) ?></td>
                                                    <td class="money blue">₱<?= number_format((float)$info['gross'], 2) ?></td>
                                                    <td class="money">₱<?= number_format((float)$info['expenses'], 2) ?></td>
                                                    <td class="money green">₱<?= number_format((float)$info['net'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="total">
                                                <td>Overall</td>
                                                <td><?= number_format((int)$departmentInfo['overall']['activity']) ?></td>
                                                <td class="money blue">₱<?= number_format($overallGross, 2) ?></td>
                                                <td class="money">₱<?= number_format($overallExpenses, 2) ?></td>
                                                <td class="money green">₱<?= number_format($overallNet, 2) ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="dept-note">Store gross income follows the existing Store Gross Profit calculation (sales less cost of goods). GCash / Maya gross is service fees. Computer Shop gross is Pisonet harvests. PisoWiFi gross is monthly gross earnings before PisoWiFi expenses.</div>
                            </div>
                        </section>

                        <?php if ($department === 'overall' || $department === 'store'): ?>
                            <section class="report-card">
                                <div class="report-card-header">
                                    <div>
                                        <div class="report-card-title">Sales Activity</div>
                                        <div class="report-card-subtitle">Regular POS transactions in the selected period.</div>
                                    </div>
                                    <div class="header-badge"><?= number_format((int)$sales['transactions']) ?> transactions</div>
                                </div>
                                <div class="table-wrap">
                                    <table class="activity-table">
                                        <thead>
                                            <tr>
                                                <th>Sale #</th>
                                                <th>Date</th>
                                                <th>Payment</th>
                                                <th>Sales</th>
                                                <th>Cost</th>
                                                <th>Profit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($saleRows): ?>
                                                <?php foreach (array_slice($saleRows, 0, 12) as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['sale_number']) ?></td>
                                                        <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($row['sale_date']))) ?></td>
                                                        <td><?= htmlspecialchars(strtoupper($row['payment_method'])) ?></td>
                                                        <td class="money">₱<?= number_format((float)$row['total'], 2) ?></td>
                                                        <td class="money">₱<?= number_format((float)$row['total_cost'], 2) ?></td>
                                                        <td class="money green">₱<?= number_format((float)$row['total_profit'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="empty-state">No sales for this period.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if ($department === 'overall' || $department === 'wallet'): ?>
                            <section class="report-card">
                                <div class="report-card-header">
                                    <div>
                                        <div class="report-card-title">GCash / Maya Activity</div>
                                        <div class="report-card-subtitle">Wallet transactions and service fees.</div>
                                    </div>
                                    <div class="header-badge">₱<?= number_format((float)$wallet['fees'], 2) ?> fees</div>
                                </div>
                                <div class="table-wrap">
                                    <table class="activity-table">
                                        <thead>
                                            <tr>
                                                <th>Transaction #</th>
                                                <th>Date</th>
                                                <th>Provider</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Fee</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($walletRows): ?>
                                                <?php foreach (array_slice($walletRows, 0, 12) as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['transaction_number']) ?></td>
                                                        <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($row['transaction_date']))) ?></td>
                                                        <td><?= htmlspecialchars(strtoupper($row['provider'])) ?></td>
                                                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['transaction_type']))) ?></td>
                                                        <td class="money">₱<?= number_format((float)$row['wallet_amount'], 2) ?></td>
                                                        <td class="money green">₱<?= number_format((float)$row['service_fee'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="empty-state">No GCash / Maya transactions for this period.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if ($department === 'overall' || $department === 'computer_shop'): ?>
                            <section class="report-card">
                                <div class="report-card-header">
                                    <div>
                                        <div class="report-card-title">Computer Shop / Pisonet</div>
                                        <div class="report-card-subtitle">Monthly harvest records by machine.</div>
                                    </div>
                                    <div class="header-badge">₱<?= number_format((float)$pisonet['gross'], 2) ?> gross</div>
                                </div>
                                <div class="table-wrap">
                                    <table class="activity-table">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th>Pisonet</th>
                                                <th>Amount</th>
                                                <th>Collected</th>
                                                <th>Recorded By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($pisonetRows): ?>
                                                <?php foreach (array_slice($pisonetRows, 0, 12) as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars(date('F Y', strtotime($row['harvest_month']))) ?></td>
                                                        <td><?= htmlspecialchars($row['unit_name']) ?></td>
                                                        <td class="money blue">₱<?= number_format((float)$row['amount'], 2) ?></td>
                                                        <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($row['harvested_at']))) ?></td>
                                                        <td><?= htmlspecialchars($row['recorded_by']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="empty-state">No Pisonet harvests for this period.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if ($department === 'overall' || $department === 'pisowifi'): ?>
                            <section class="report-card">
                                <div class="report-card-header">
                                    <div>
                                        <div class="report-card-title">PisoWiFi Earnings</div>
                                        <div class="report-card-subtitle">Monthly gross earnings, expenses and net income.</div>
                                    </div>
                                    <div class="header-badge">₱<?= number_format((float)$pisowifi['net'], 2) ?> net</div>
                                </div>
                                <div class="table-wrap">
                                    <table class="activity-table">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th>Box</th>
                                                <th>Gross</th>
                                                <th>Expenses</th>
                                                <th>Net</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($pisowifiRows): ?>
                                                <?php foreach (array_slice($pisowifiRows, 0, 12) as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars(date('F Y', mktime(0, 0, 0, (int)$row['earning_month'], 1, (int)$row['earning_year']))) ?></td>
                                                        <td>
                                                            <div class="dept-name"><?= htmlspecialchars($row['box_name']) ?></div>
                                                            <div class="muted-cell"><?= htmlspecialchars($row['location']) ?></div>
                                                        </td>
                                                        <td class="money blue">₱<?= number_format((float)$row['gross_earnings'], 2) ?></td>
                                                        <td class="money">₱<?= number_format((float)$row['expenses'], 2) ?></td>
                                                        <td class="money green">₱<?= number_format((float)$row['net_earnings'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="empty-state">No PisoWiFi monthly records for this period.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        <?php endif; ?>

                    </div>

                    <aside class="reports-side">
                        <section class="report-card side-section">
                            <div class="side-card-body">
                                <div class="side-kicker"><?= htmlspecialchars($selectedDepartment['label']) ?></div>
                                <div class="side-value">₱<?= number_format((float)$selectedDepartment['gross'], 2) ?></div>
                                <div class="side-caption">Gross income for the selected period</div>
                                <div class="side-chart-wrap"><canvas id="sideIncomeChart"></canvas></div>
                            </div>
                        </section>

                        <section class="report-card side-section">
                            <div class="report-card-header">
                                <div>
                                    <div class="report-card-title">Best performing departments</div>
                                    <div class="report-card-subtitle">Ranked by gross income</div>
                                </div>
                            </div>
                            <div class="side-card-body">
                                <div class="rank-list">
                                    <?php
                                    $ranked = [];
                                    foreach (['store', 'wallet', 'computer_shop', 'pisowifi'] as $key) {
                                        $ranked[] = ['key' => $key, 'info' => $departmentInfo[$key]];
                                    }
                                    usort($ranked, function ($a, $b) {
                                        return $b['info']['gross'] <=> $a['info']['gross'];
                                    });
                                    foreach ($ranked as $i => $item):
                                    ?>
                                        <div class="rank-item">
                                            <div class="rank-number"><?= $i + 1 ?></div>
                                            <div>
                                                <div class="rank-name"><?= htmlspecialchars($item['info']['short']) ?></div>
                                                <div class="rank-sub"><?= number_format((int)$item['info']['activity']) ?> <?= htmlspecialchars($item['info']['activityLabel']) ?></div>
                                            </div>
                                            <div class="rank-value">₱<?= number_format((float)$item['info']['gross'], 2) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </section>

                        <section class="report-card side-section">
                            <div class="report-card-header">
                                <div>
                                    <div class="report-card-title">Overall net income</div>
                                    <div class="report-card-subtitle">All departments for this period</div>
                                </div>
                            </div>
                            <div class="net-box">
                                <div class="side-kicker">Net income</div>
                                <div class="net-big">₱<?= number_format($overallNet, 2) ?></div>
                                <div class="net-rows">
                                    <div class="net-row"><span>Gross income</span><span>₱<?= number_format($overallGross, 2) ?></span></div>
                                    <div class="net-row"><span>Store expenses</span><span>− ₱<?= number_format((float)$expense['expenses'], 2) ?></span></div>
                                    <div class="net-row"><span>PisoWiFi expenses</span><span>− ₱<?= number_format((float)$pisowifi['expenses'], 2) ?></span></div>
                                    <div class="net-row total"><span>Net income</span><span>₱<?= number_format($overallNet, 2) ?></span></div>
                                </div>
                            </div>
                        </section>
                    </aside>
                </div>

            </div>
        </main>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
        (function() {
            const deptLabels = [
                <?= json_encode($departmentInfo['store']['short']) ?>,
                <?= json_encode($departmentInfo['wallet']['short']) ?>,
                <?= json_encode($departmentInfo['computer_shop']['short']) ?>,
                <?= json_encode($departmentInfo['pisowifi']['short']) ?>
            ];
            const deptValues = [
                <?= json_encode((float)$departmentInfo['store']['gross']) ?>,
                <?= json_encode((float)$departmentInfo['wallet']['gross']) ?>,
                <?= json_encode((float)$departmentInfo['computer_shop']['gross']) ?>,
                <?= json_encode((float)$departmentInfo['pisowifi']['gross']) ?>
            ];

            const incomeCanvas = document.getElementById('incomeChart');
            if (incomeCanvas) {
                new Chart(incomeCanvas, {
                    type: 'bar',
                    data: {
                        labels: deptLabels,
                        datasets: [{
                            label: 'Gross Income',
                            data: deptValues,
                            borderRadius: 7,
                            maxBarThickness: 44,
                            backgroundColor: '#2563eb'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 900,
                            easing: 'easeOutQuart'
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: ctx => '₱' + Number(ctx.raw).toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#8b94a3',
                                    font: {
                                        size: 10,
                                        weight: '600'
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#edf0f4'
                                },
                                ticks: {
                                    color: '#9aa2ae',
                                    font: {
                                        size: 9
                                    },
                                    callback: value => '₱' + Number(value).toLocaleString()
                                }
                            }
                        }
                    }
                });
            }

            const mixCanvas = document.getElementById('mixChart');
            if (mixCanvas) {
                new Chart(mixCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: deptLabels,
                        datasets: [{
                            data: deptValues,
                            backgroundColor: ['#2563eb', '#8b5cf6', '#06b6d4', '#f59e0b'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: ctx => ' ₱' + Number(ctx.raw).toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })
                                }
                            }
                        }
                    }
                });
            }

            const sideCanvas = document.getElementById('sideIncomeChart');
            if (sideCanvas) {
                new Chart(sideCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['Selected', 'Other'],
                        datasets: [{
                            data: [<?= json_encode((float)$selectedDepartment['gross']) ?>, <?= json_encode(max(0, $overallGross - (float)$selectedDepartment['gross'])) ?>],
                            backgroundColor: ['#2563eb', '#e7eefb'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: false
                            }
                        }
                    }
                });
            }
        })();
    </script>
</body>

</html>
<?php

require_once "config/database.php";
require_once "config/auth.php";

require_login();

/*
|--------------------------------------------------------------------------
| UNIVERSAL ADMIN SHELL
|--------------------------------------------------------------------------
*/

$basePath = '';
$activeMenu = 'dashboard';
$currentSection = 'dashboard';

$today = date('Y-m-d');
$monthStart = date('Y-m-01');


/*
|--------------------------------------------------------------------------
| TODAY'S SALES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(total), 0) AS sales,
        COALESCE(SUM(total_cost), 0) AS cost,
        COALESCE(SUM(total_profit), 0) AS profit,
        COUNT(*) AS transactions
    FROM sales
    WHERE DATE(sale_date) = ?
");

$stmt->execute([$today]);

$salesToday = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| TODAY'S WALLET
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(service_fee), 0) AS fees,
        COUNT(*) AS transactions,

        COALESCE(SUM(
            CASE
                WHEN transaction_type = 'cash_in'
                THEN wallet_amount
                ELSE 0
            END
        ), 0) AS cash_in,

        COALESCE(SUM(
            CASE
                WHEN transaction_type = 'cash_out'
                THEN wallet_amount
                ELSE 0
            END
        ), 0) AS cash_out

    FROM wallet_transactions

    WHERE DATE(transaction_date) = ?
");

$stmt->execute([$today]);

$walletToday = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| TODAY'S EXPENSES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
    WHERE expense_date = ?
");

$stmt->execute([$today]);

$expenseToday = (float)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TODAY NET INCOME
|--------------------------------------------------------------------------
*/

$netToday =
    (float)$salesToday['profit']
    + (float)$walletToday['fees']
    - $expenseToday;


/*
|--------------------------------------------------------------------------
| MONTH-TO-DATE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(total), 0) AS sales,
        COALESCE(SUM(total_cost), 0) AS cost,
        COALESCE(SUM(total_profit), 0) AS profit

    FROM sales

    WHERE sale_date >= ?
");

$stmt->execute([
    $monthStart . ' 00:00:00'
]);

$month = $stmt->fetch();


$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(service_fee), 0)

    FROM wallet_transactions

    WHERE transaction_date >= ?
");

$stmt->execute([
    $monthStart . ' 00:00:00'
]);

$monthWalletIncome = (float)$stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)

    FROM expenses

    WHERE expense_date >= ?
");

$stmt->execute([
    $monthStart
]);

$monthExpenses = (float)$stmt->fetchColumn();


$monthNetIncome =
    (float)$month['profit']
    + $monthWalletIncome
    - $monthExpenses;


/*
|--------------------------------------------------------------------------
| INVENTORY
|--------------------------------------------------------------------------
*/

$inventoryValue = (float)$pdo->query("
    SELECT COALESCE(SUM(stock * cost_price), 0)

    FROM products

    WHERE is_active = 1
")->fetchColumn();


$productCount = (int)$pdo->query("
    SELECT COUNT(*)

    FROM products

    WHERE is_active = 1
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| LOW STOCK
|--------------------------------------------------------------------------
*/

$lowStock = $pdo->query("
    SELECT
        id,
        product_name,
        stock,
        reorder_level,
        unit

    FROM products

    WHERE is_active = 1
      AND stock <= reorder_level

    ORDER BY
        stock ASC,
        product_name ASC

    LIMIT 8
")->fetchAll();


/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$notifications = [];

try {

    $notificationStmt = $pdo->query("
        SELECT
            id,
            product_name,
            stock,
            reorder_level,
            unit

        FROM products

        WHERE is_active = 1
          AND stock <= reorder_level

        ORDER BY

            CASE
                WHEN stock <= 0 THEN 1
                ELSE 2
            END,

            stock ASC,
            product_name ASC

        LIMIT 20
    ");

    $notifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {

    $notifications = [];
}

$notificationCount = count($notifications);


/*
|--------------------------------------------------------------------------
| RECENT SALES
|--------------------------------------------------------------------------
*/

$recentSales = $pdo->query("
    SELECT
        sale_number,
        sale_date,
        total,
        payment_method

    FROM sales

    ORDER BY sale_date DESC

    LIMIT 8
")->fetchAll();


/*
|--------------------------------------------------------------------------
| LAST 7 DAYS SALES / PROFIT
|--------------------------------------------------------------------------
*/

$chartSales = [];

for ($i = 6; $i >= 0; $i--) {

    $date = date(
        'Y-m-d',
        strtotime("-$i days")
    );

    $chartSales[$date] = [
        'label' => date(
            'M d',
            strtotime($date)
        ),

        'sales' => 0,

        'profit' => 0
    ];
}


$stmt = $pdo->prepare("
    SELECT
        DATE(sale_date) AS sale_day,
        COALESCE(SUM(total), 0) AS sales,
        COALESCE(SUM(total_profit), 0) AS profit

    FROM sales

    WHERE sale_date >= ?

    GROUP BY DATE(sale_date)

    ORDER BY sale_day
");


$stmt->execute([
    date(
        'Y-m-d 00:00:00',
        strtotime('-6 days')
    )
]);


foreach ($stmt->fetchAll() as $row) {

    $date = $row['sale_day'];

    if (isset($chartSales[$date])) {

        $chartSales[$date]['sales'] =
            (float)$row['sales'];

        $chartSales[$date]['profit'] =
            (float)$row['profit'];
    }
}


/*
|--------------------------------------------------------------------------
| PAYMENT METHOD BREAKDOWN
|--------------------------------------------------------------------------
*/

$paymentData = [
    'Cash' => 0,
    'GCash' => 0,
    'Maya' => 0
];


$stmt = $pdo->prepare("
    SELECT
        LOWER(payment_method) AS payment_method,
        COALESCE(SUM(total), 0) AS total

    FROM sales

    WHERE sale_date >= ?

    GROUP BY payment_method
");


$stmt->execute([
    date(
        'Y-m-d 00:00:00',
        strtotime('-6 days')
    )
]);


foreach ($stmt->fetchAll() as $row) {

    $method = strtolower(
        trim($row['payment_method'])
    );

    if ($method === 'cash') {

        $paymentData['Cash'] =
            (float)$row['total'];
    } elseif ($method === 'gcash') {

        $paymentData['GCash'] =
            (float)$row['total'];
    } elseif ($method === 'maya') {

        $paymentData['Maya'] =
            (float)$row['total'];
    }
}

?>

<!doctype html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Dashboard - Arnold & Jackie's Store
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


    <!-- Universal Admin CSS -->

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">


    <!-- Chart.js -->

    <script
        src="https://cdn.jsdelivr.net/npm/chart.js">
    </script>


    <style>
        /*
        |--------------------------------------------------------------------------
        | DASHBOARD ONLY
        |--------------------------------------------------------------------------
        |
        | Keep this section intentionally small.
        | Global UI belongs in admin.css.
        |
        */

        .dashboard-hero {
            position: relative;

            padding: 24px 26px;

            margin-bottom: 22px;

            border-radius: 18px;

            background:
                linear-gradient(135deg,
                    #ffffff 0%,
                    #f8fbff 100%);

            border: 1px solid #e7edf5;

            box-shadow:
                0 5px 20px rgba(15, 23, 42, .045);

            overflow: hidden;
        }


        .dashboard-hero::after {
            content: "";

            position: absolute;

            width: 180px;
            height: 180px;

            right: -60px;
            top: -90px;

            border-radius: 50%;

            background:
                rgba(37, 99, 235, .055);

            pointer-events: none;
        }


        .dashboard-hero-content {
            position: relative;
            z-index: 1;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;
        }


        .dashboard-eyebrow {
            display: flex;

            align-items: center;

            gap: 7px;

            margin-bottom: 7px;

            color: #64748b;

            font-size: 11px;
            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .08em;
        }


        .dashboard-status-dot {
            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: #22c55e;

            box-shadow:
                0 0 0 4px rgba(34, 197, 94, .10);
        }


        .dashboard-date {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 8px 11px;

            color: #64748b;

            background: #fff;

            border: 1px solid #e5eaf0;

            border-radius: 9px;

            font-size: 11px;

            white-space: nowrap;
        }


        /*
        |--------------------------------------------------------------------------
        | KPI
        |--------------------------------------------------------------------------
        */

        .dashboard-kpi {
            min-height: 150px;
        }


        .dashboard-kpi .stat-value {
            padding-right: 48px;
        }


        .dashboard-kpi .stat-icon {
            top: 18px;
            right: 18px;
        }


        /*
        |--------------------------------------------------------------------------
        | CHART
        |--------------------------------------------------------------------------
        */

        .dashboard-chart {
            height: 315px;
        }


        .chart-header-action {
            color: #94a3b8;

            font-size: 11px;
        }


        /*
        |--------------------------------------------------------------------------
        | PAYMENT LEGEND
        |--------------------------------------------------------------------------
        */

        .payment-summary {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 8px;

            margin-top: 14px;
        }


        .payment-summary-item {
            padding: 9px;

            background: #f8fafc;

            border: 1px solid #eef2f7;

            border-radius: 9px;

            text-align: center;
        }


        .payment-summary-label {
            color: #94a3b8;

            font-size: 9px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .05em;
        }


        .payment-summary-value {
            margin-top: 3px;

            color: #334155;

            font-size: 11px;

            font-weight: 750;
        }


        /*
        |--------------------------------------------------------------------------
        | QUICK ACTIONS
        |--------------------------------------------------------------------------
        */

        .quick-action {
            min-height: 76px;

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .quick-action-icon {
            width: 38px;
            height: 38px;

            display: flex;

            align-items: center;
            justify-content: center;

            flex: 0 0 38px;

            border-radius: 10px;

            color: #2563eb;

            background: #eff6ff;

            font-size: 16px;
        }


        .quick-action:nth-child(2) .quick-action-icon {
            color: #16a34a;
            background: #f0fdf4;
        }


        .quick-action:nth-child(3) .quick-action-icon {
            color: #0891b2;
            background: #ecfeff;
        }


        .quick-action:nth-child(4) .quick-action-icon {
            color: #d97706;
            background: #fffbeb;
        }


        /*
        |--------------------------------------------------------------------------
        | PAYMENT BADGES
        |--------------------------------------------------------------------------
        */

        .payment-badge {
            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding: 5px 8px;

            border-radius: 7px;

            font-size: 10px;

            font-weight: 750;
        }


        .payment-cash {
            color: #166534;
            background: #dcfce7;
        }


        .payment-gcash {
            color: #1d4ed8;
            background: #dbeafe;
        }


        .payment-maya {
            color: #047857;
            background: #d1fae5;
        }


        /*
        |--------------------------------------------------------------------------
        | LOW STOCK
        |--------------------------------------------------------------------------
        */

        .stock-alert-icon {
            width: 34px;
            height: 34px;

            display: flex;

            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            color: #d97706;

            background: #fffbeb;

            border-radius: 9px;

            font-size: 14px;
        }


        .stock-item {
            gap: 10px;
        }


        .stock-main {
            display: flex;

            align-items: center;

            gap: 10px;

            min-width: 0;

            flex: 1;
        }


        .stock-info {
            min-width: 0;
        }


        .stock-name {
            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;
        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY DASHBOARD
        |--------------------------------------------------------------------------
        */

        .dashboard-empty {
            padding: 30px 15px;

            text-align: center;

            color: #94a3b8;
        }


        .dashboard-empty i {
            display: block;

            margin-bottom: 8px;

            font-size: 28px;
        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 768px) {

            .dashboard-hero {
                padding: 19px;
            }


            .dashboard-hero-content {
                align-items: flex-start;

                flex-direction: column;
            }


            .dashboard-date {
                width: 100%;

                justify-content: center;
            }


            .dashboard-chart {
                height: 270px;
            }

        }


        @media (max-width: 576px) {

            .payment-summary {
                grid-template-columns: 1fr;
            }


            .dashboard-chart {
                height: 240px;
            }

        }
    </style>

</head>


<body>


    <!-- =========================================================
         UNIVERSAL SIDEBAR
    ========================================================== -->

    <?php require_once $basePath . 'includes/sidebar.php'; ?>


    <div class="main">


        <!-- =====================================================
             UNIVERSAL TOPBAR
        ====================================================== -->

        <?php require_once $basePath . 'includes/topbar.php'; ?>


        <main class="content">


            <!-- =====================================================
                 HERO
            ====================================================== -->

            <section class="dashboard-hero">

                <div class="dashboard-hero-content">

                    <div>

                        <div class="dashboard-eyebrow">

                            <span class="dashboard-status-dot"></span>

                            Store Overview

                        </div>


                        <h1 class="page-title">
                            Executive Dashboard
                        </h1>


                        <p class="page-subtitle mb-0">
                            Monitor your store's sales,
                            inventory, wallet transactions,
                            and daily performance.
                        </p>

                    </div>


                    <div class="dashboard-date">

                        <i class="bi bi-calendar3"></i>

                        <?= date('l, F d, Y') ?>

                    </div>

                </div>

            </section>


            <!-- =====================================================
                 PRIMARY KPIs
            ====================================================== -->

            <div class="row g-3 mb-4">


                <!-- SALES -->

                <div class="col-xl-3 col-md-6">

                    <div class="stat-card dashboard-kpi">

                        <div class="stat-icon">

                            <i class="bi bi-cart3"></i>

                        </div>


                        <div class="stat-label">
                            Today's Sales
                        </div>


                        <div class="stat-value">

                            ₱<?= number_format(
                                    $salesToday['sales'],
                                    2
                                ) ?>

                        </div>


                        <div class="stat-small">

                            <i class="bi bi-receipt me-1"></i>

                            <?= number_format(
                                $salesToday['transactions']
                            ) ?>

                            transaction<?=

                                        $salesToday['transactions'] == 1
                                            ? ''
                                            : 's'

                                        ?>

                        </div>

                    </div>

                </div>


                <!-- COST -->

                <div class="col-xl-3 col-md-6">

                    <div class="stat-card dashboard-kpi">

                        <div class="stat-icon warning">

                            <i class="bi bi-box-seam"></i>

                        </div>


                        <div class="stat-label">
                            Cost of Goods
                        </div>


                        <div class="stat-value">

                            ₱<?= number_format(
                                    $salesToday['cost'],
                                    2
                                ) ?>

                        </div>


                        <div class="stat-small">
                            Product cost for today's sales
                        </div>

                    </div>

                </div>


                <!-- PROFIT -->

                <div class="col-xl-3 col-md-6">

                    <div class="stat-card dashboard-kpi">

                        <div class="stat-icon success">

                            <i class="bi bi-graph-up-arrow"></i>

                        </div>


                        <div class="stat-label">
                            Gross Profit
                        </div>


                        <div class="stat-value">

                            ₱<?= number_format(
                                    $salesToday['profit'],
                                    2
                                ) ?>

                        </div>


                        <div class="stat-small">
                            Sales minus cost of goods
                        </div>

                    </div>

                </div>


                <!-- NET -->

                <div class="col-xl-3 col-md-6">

                    <div class="stat-card dashboard-kpi">

                        <div class="stat-icon purple">

                            <i class="bi bi-wallet2"></i>

                        </div>


                        <div class="stat-label">
                            Net Income
                        </div>


                        <div class="stat-value">

                            ₱<?= number_format(
                                    $netToday,
                                    2
                                ) ?>

                        </div>


                        <div class="stat-small">
                            Profit + wallet income − expenses
                        </div>

                    </div>

                </div>


            </div>


            <!-- =====================================================
                 SECONDARY KPIs
            ====================================================== -->

            <div class="row g-3 mb-4">


                <!-- WALLET -->

                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="stat-icon info">

                            <i class="bi bi-phone"></i>

                        </div>


                        <div class="stat-label">
                            Wallet Service Income
                        </div>


                        <div class="stat-value">

                            ₱<?= number_format(
                                    $walletToday['fees'],
                                    2
                                ) ?>

                        </div>


                        <div class="stat-small">

                            <?= number_format(
                                $walletToday['transactions']
                            ) ?>

                            wallet transaction<?=

                                                $walletToday['transactions'] == 1
                                                    ? ''
                                                    : 's'

                                                ?>

                        </div>

                    </div>

                </div>


                <!-- EXPENSE -->

                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="stat-icon danger">

                            <i class="bi bi-receipt"></i>

                        </div>


                        <div class="stat-label">
                            Today's Expenses
                        </div>


                        <div class="stat-value">

                            ₱<?= number_format(
                                    $expenseToday,
                                    2
                                ) ?>

                        </div>


                        <div class="stat-small">
                            Recorded store expenses
                        </div>

                    </div>

                </div>


                <!-- INVENTORY -->

                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="stat-icon">

                            <i class="bi bi-boxes"></i>

                        </div>


                        <div class="stat-label">
                            Inventory Value
                        </div>


                        <div class="stat-value">

                            ₱<?= number_format(
                                    $inventoryValue,
                                    2
                                ) ?>

                        </div>


                        <div class="stat-small">

                            <?= number_format(
                                $productCount
                            ) ?>

                            active products

                        </div>

                    </div>

                </div>


                <!-- MONTH -->

                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="stat-icon success">

                            <i class="bi bi-calendar-month"></i>

                        </div>


                        <div class="stat-label">
                            Month Net Income
                        </div>


                        <div class="stat-value">

                            ₱<?= number_format(
                                    $monthNetIncome,
                                    2
                                ) ?>

                        </div>


                        <div class="stat-small">
                            Month-to-date
                        </div>

                    </div>

                </div>


            </div>


            <!-- =====================================================
                 CHARTS
            ====================================================== -->

            <div class="row g-3 mb-4">


                <!-- SALES CHART -->

                <div class="col-xl-8">

                    <div class="section-card h-100">

                        <div class="section-header">

                            <div>

                                <div class="section-title">

                                    <i class="bi bi-graph-up me-1"></i>

                                    Sales & Gross Profit

                                </div>


                                <div class="section-subtitle">
                                    Performance over the last 7 days
                                </div>

                            </div>


                            <span class="chart-header-action">
                                Last 7 days
                            </span>

                        </div>


                        <div class="section-body">

                            <div class="dashboard-chart">

                                <canvas id="salesChart"></canvas>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- PAYMENT CHART -->

                <div class="col-xl-4">

                    <div class="section-card h-100">

                        <div class="section-header">

                            <div>

                                <div class="section-title">

                                    <i class="bi bi-pie-chart me-1"></i>

                                    Sales by Payment

                                </div>


                                <div class="section-subtitle">
                                    Last 7 days
                                </div>

                            </div>

                        </div>


                        <div class="section-body">

                            <div
                                class="dashboard-chart"
                                style="height:250px;">

                                <canvas id="paymentChart"></canvas>

                            </div>


                            <div class="payment-summary">


                                <div class="payment-summary-item">

                                    <div class="payment-summary-label">
                                        Cash
                                    </div>

                                    <div class="payment-summary-value">

                                        ₱<?= number_format(
                                                $paymentData['Cash'],
                                                2
                                            ) ?>

                                    </div>

                                </div>


                                <div class="payment-summary-item">

                                    <div class="payment-summary-label">
                                        GCash
                                    </div>

                                    <div class="payment-summary-value">

                                        ₱<?= number_format(
                                                $paymentData['GCash'],
                                                2
                                            ) ?>

                                    </div>

                                </div>


                                <div class="payment-summary-item">

                                    <div class="payment-summary-label">
                                        Maya
                                    </div>

                                    <div class="payment-summary-value">

                                        ₱<?= number_format(
                                                $paymentData['Maya'],
                                                2
                                            ) ?>

                                    </div>

                                </div>


                            </div>

                        </div>

                    </div>

                </div>


            </div>


            <!-- =====================================================
                 QUICK ACTIONS
            ====================================================== -->

            <div class="mb-4">


                <div class="d-flex align-items-center justify-content-between mb-3">

                    <div>

                        <div class="section-title">
                            Quick Actions
                        </div>

                        <div class="section-subtitle">
                            Common store operations
                        </div>

                    </div>

                </div>


                <div class="row g-3">


                    <!-- POS -->

                    <div class="col-xl-3 col-md-6">

                        <a
                            href="pos/"
                            class="quick-action">

                            <div class="quick-action-icon">

                                <i class="bi bi-cart-plus"></i>

                            </div>


                            <div>

                                <div class="quick-action-title">
                                    New Sale
                                </div>

                                <div class="quick-action-description">
                                    Start a POS transaction
                                </div>

                            </div>

                        </a>

                    </div>


                    <!-- PRODUCT -->

                    <div class="col-xl-3 col-md-6">

                        <a
                            href="products/index.php"
                            class="quick-action">

                            <div class="quick-action-icon">

                                <i class="bi bi-box-seam"></i>

                            </div>


                            <div>

                                <div class="quick-action-title">
                                    Add Product
                                </div>

                                <div class="quick-action-description">
                                    Add a new inventory item
                                </div>

                            </div>

                        </a>

                    </div>


                    <!-- WALLET -->

                    <div class="col-xl-3 col-md-6">

                        <a
                            href="wallet/index.php"
                            class="quick-action">

                            <div class="quick-action-icon">

                                <i class="bi bi-phone"></i>

                            </div>


                            <div>

                                <div class="quick-action-title">
                                    Wallet Transaction
                                </div>

                                <div class="quick-action-description">
                                    GCash or Maya cash-in/out
                                </div>

                            </div>

                        </a>

                    </div>


                    <!-- EXPENSE -->

                    <div class="col-xl-3 col-md-6">

                        <a
                            href="expenses/index.php"
                            class="quick-action">

                            <div class="quick-action-icon">

                                <i class="bi bi-cash-stack"></i>

                            </div>


                            <div>

                                <div class="quick-action-title">
                                    Add Expense
                                </div>

                                <div class="quick-action-description">
                                    Record a store expense
                                </div>

                            </div>

                        </a>

                    </div>


                </div>

            </div>


            <!-- =====================================================
                 RECENT SALES + LOW STOCK
            ====================================================== -->

            <div class="row g-3">


                <!-- =================================================
                     RECENT SALES
                ================================================== -->

                <div class="col-xl-8">

                    <div class="section-card">

                        <div class="section-header">

                            <div>

                                <div class="section-title">

                                    <i class="bi bi-clock-history me-1"></i>

                                    Recent Sales

                                </div>


                                <div class="section-subtitle">
                                    Latest POS transactions
                                </div>

                            </div>


                            <a
                                href="reports/"
                                class="btn btn-sm btn-outline-secondary">

                                View All

                                <i class="bi bi-arrow-right"></i>

                            </a>

                        </div>


                        <div class="table-responsive">

                            <table class="table dashboard-table">

                                <thead>

                                    <tr>

                                        <th>
                                            Sale #
                                        </th>

                                        <th>
                                            Date
                                        </th>

                                        <th>
                                            Payment
                                        </th>

                                        <th class="text-end">
                                            Amount
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                    <?php foreach (
                                        $recentSales
                                        as $sale
                                    ): ?>


                                        <?php

                                        $paymentMethod =
                                            strtolower(
                                                trim(
                                                    $sale['payment_method']
                                                )
                                            );

                                        $paymentClass =
                                            'payment-cash';

                                        $paymentIcon =
                                            'bi-cash';

                                        if (
                                            $paymentMethod === 'gcash'
                                        ) {

                                            $paymentClass =
                                                'payment-gcash';

                                            $paymentIcon =
                                                'bi-phone';
                                        } elseif (
                                            $paymentMethod === 'maya'
                                        ) {

                                            $paymentClass =
                                                'payment-maya';

                                            $paymentIcon =
                                                'bi-phone';
                                        }

                                        ?>


                                        <tr>


                                            <td>

                                                <span class="sale-number">

                                                    <?= htmlspecialchars(
                                                        $sale['sale_number']
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td>

                                                <span class="text-secondary">

                                                    <?= date(
                                                        'M d, Y',
                                                        strtotime(
                                                            $sale['sale_date']
                                                        )
                                                    ) ?>

                                                </span>

                                                <div class="text-muted small">

                                                    <?= date(
                                                        'h:i A',
                                                        strtotime(
                                                            $sale['sale_date']
                                                        )
                                                    ) ?>

                                                </div>

                                            </td>


                                            <td>

                                                <span
                                                    class="payment-badge <?= $paymentClass ?>">

                                                    <i
                                                        class="bi <?= $paymentIcon ?>">
                                                    </i>

                                                    <?= htmlspecialchars(
                                                        strtoupper(
                                                            $sale['payment_method']
                                                        )
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td class="text-end">

                                                <span class="amount">

                                                    ₱<?= number_format(
                                                            $sale['total'],
                                                            2
                                                        ) ?>

                                                </span>

                                            </td>


                                        </tr>


                                    <?php endforeach; ?>


                                    <?php if (!$recentSales): ?>


                                        <tr>

                                            <td
                                                colspan="4"
                                                class="p-0">

                                                <div class="dashboard-empty">

                                                    <i class="bi bi-receipt"></i>

                                                    No sales recorded yet.

                                                </div>

                                            </td>

                                        </tr>


                                    <?php endif; ?>


                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     LOW STOCK
                ================================================== -->

                <div class="col-xl-4">

                    <div class="section-card h-100">


                        <div class="section-header">

                            <div>

                                <div class="section-title">

                                    <i class="bi bi-exclamation-triangle me-1"></i>

                                    Low Stock

                                </div>


                                <div class="section-subtitle">
                                    Products that need attention
                                </div>

                            </div>


                            <a
                                href="products/"
                                class="btn btn-sm btn-outline-secondary">

                                Inventory

                            </a>

                        </div>


                        <div class="section-body">


                            <?php foreach (
                                $lowStock
                                as $product
                            ): ?>


                                <div class="stock-item">


                                    <div class="stock-main">


                                        <div class="stock-alert-icon">

                                            <i class="bi bi-box"></i>

                                        </div>


                                        <div class="stock-info">


                                            <div class="stock-name">

                                                <?= htmlspecialchars(
                                                    $product['product_name']
                                                ) ?>

                                            </div>


                                            <div class="stock-level">

                                                <?=
                                                rtrim(
                                                    rtrim(
                                                        number_format(
                                                            $product['stock'],
                                                            3
                                                        ),
                                                        '0'
                                                    ),
                                                    '.'
                                                )
                                                ?>

                                                <?= htmlspecialchars(
                                                    $product['unit']
                                                ) ?>

                                                <span class="mx-1">
                                                    /
                                                </span>

                                                reorder

                                                <?=
                                                rtrim(
                                                    rtrim(
                                                        number_format(
                                                            $product['reorder_level'],
                                                            3
                                                        ),
                                                        '0'
                                                    ),
                                                    '.'
                                                )
                                                ?>

                                            </div>


                                        </div>

                                    </div>


                                    <?php if ($product['stock'] <= 0): ?>

                                        <span class="badge badge-danger">
                                            OUT
                                        </span>

                                    <?php else: ?>

                                        <span class="low-badge">
                                            LOW
                                        </span>

                                    <?php endif; ?>


                                </div>


                            <?php endforeach; ?>


                            <?php if (!$lowStock): ?>


                                <div class="dashboard-empty">

                                    <i class="bi bi-check-circle"></i>

                                    <div class="fw-700 text-dark">
                                        Stock looks good
                                    </div>

                                    <div class="small mt-1">
                                        No products currently need restocking.
                                    </div>

                                </div>


                            <?php endif; ?>


                        </div>

                    </div>

                </div>


            </div>


        </main>

    </div>


    <!-- =========================================================
         BOOTSTRAP
    ========================================================== -->

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>


    <!-- =========================================================
         UNIVERSAL ADMIN JS
    ========================================================== -->

    <script
        src="<?= $basePath ?>assets/js/admin.js">
    </script>


    <!-- =========================================================
         DASHBOARD CHARTS
    ========================================================== -->

    <script>
        /*
        |--------------------------------------------------------------------------
        | CHART DATA
        |--------------------------------------------------------------------------
        */

        const salesLabels =
            <?= json_encode(
                array_column(
                    $chartSales,
                    'label'
                )
            ) ?>;


        const salesValues =
            <?= json_encode(
                array_column(
                    $chartSales,
                    'sales'
                )
            ) ?>;


        const profitValues =
            <?= json_encode(
                array_column(
                    $chartSales,
                    'profit'
                )
            ) ?>;


        /*
        |--------------------------------------------------------------------------
        | CHART COLORS
        |--------------------------------------------------------------------------
        */

        const chartPrimary =
            '#2563eb';

        const chartPrimarySoft =
            'rgba(37, 99, 235, .10)';

        const chartSuccess =
            '#16a34a';

        const chartSuccessSoft =
            'rgba(22, 163, 74, .08)';


        /*
        |--------------------------------------------------------------------------
        | SALES & PROFIT CHART
        |--------------------------------------------------------------------------
        */

        const salesChartCanvas =
            document.getElementById('salesChart');


        if (salesChartCanvas) {

            const salesContext =
                salesChartCanvas.getContext('2d');


            const salesGradient =
                salesContext.createLinearGradient(
                    0,
                    0,
                    0,
                    300
                );

            salesGradient.addColorStop(
                0,
                'rgba(37, 99, 235, .20)'
            );

            salesGradient.addColorStop(
                1,
                'rgba(37, 99, 235, 0)'
            );


            new Chart(
                salesChartCanvas, {

                    type: 'line',

                    data: {

                        labels: salesLabels,

                        datasets: [

                            {
                                label: 'Sales',

                                data: salesValues,

                                borderColor: chartPrimary,

                                backgroundColor: salesGradient,

                                borderWidth: 2.5,

                                tension: .4,

                                fill: true,

                                pointRadius: 3,

                                pointHoverRadius: 6,

                                pointBackgroundColor: '#ffffff',

                                pointBorderWidth: 2,

                                pointBorderColor: chartPrimary
                            },


                            {
                                label: 'Gross Profit',

                                data: profitValues,

                                borderColor: chartSuccess,

                                backgroundColor: chartSuccessSoft,

                                borderWidth: 2.5,

                                tension: .4,

                                fill: false,

                                pointRadius: 3,

                                pointHoverRadius: 6,

                                pointBackgroundColor: '#ffffff',

                                pointBorderWidth: 2,

                                pointBorderColor: chartSuccess
                            }

                        ]

                    },


                    options: {

                        responsive: true,

                        maintainAspectRatio: false,

                        interaction: {

                            intersect: false,

                            mode: 'index'

                        },


                        animation: {

                            duration: 1100,

                            easing: 'easeOutQuart'

                        },


                        plugins: {

                            legend: {

                                position: 'bottom',

                                labels: {

                                    usePointStyle: true,

                                    pointStyle: 'circle',

                                    padding: 18,

                                    color: '#64748b',

                                    font: {

                                        size: 11,

                                        weight: '600'

                                    }

                                }

                            },


                            tooltip: {

                                backgroundColor: '#172033',

                                titleColor: '#ffffff',

                                bodyColor: '#e2e8f0',

                                padding: 11,

                                cornerRadius: 9,

                                displayColors: true,

                                callbacks: {

                                    label: function(context) {

                                        return (
                                            ' ' +
                                            context.dataset.label +
                                            ': ₱' +
                                            Number(
                                                context.raw
                                            ).toLocaleString(
                                                undefined, {
                                                    minimumFractionDigits: 2
                                                }
                                            )
                                        );

                                    }

                                }

                            }

                        },


                        scales: {

                            x: {

                                grid: {

                                    display: false

                                },

                                border: {

                                    display: false

                                },

                                ticks: {

                                    color: '#94a3b8',

                                    font: {

                                        size: 10

                                    }

                                }

                            },


                            y: {

                                beginAtZero: true,

                                border: {

                                    display: false

                                },

                                grid: {

                                    color: 'rgba(148, 163, 184, .12)'

                                },

                                ticks: {

                                    color: '#94a3b8',

                                    font: {

                                        size: 10

                                    },

                                    callback: function(value) {

                                        return '₱' +
                                            Number(
                                                value
                                            ).toLocaleString();

                                    }

                                }

                            }

                        }

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | PAYMENT CHART
        |--------------------------------------------------------------------------
        */

        const paymentChartCanvas =
            document.getElementById('paymentChart');


        if (paymentChartCanvas) {

            new Chart(
                paymentChartCanvas, {

                    type: 'doughnut',

                    data: {

                        labels: [
                            'Cash',
                            'GCash',
                            'Maya'
                        ],

                        datasets: [

                            {

                                data: [

                                    <?= $paymentData['Cash'] ?>,

                                    <?= $paymentData['GCash'] ?>,

                                    <?= $paymentData['Maya'] ?>

                                ],

                                backgroundColor: [

                                    '#2563eb',

                                    '#0891b2',

                                    '#16a34a'

                                ],

                                borderWidth: 0,

                                hoverOffset: 5

                            }

                        ]

                    },


                    options: {

                        responsive: true,

                        maintainAspectRatio: false,

                        cutout: '70%',


                        animation: {

                            duration: 1100,

                            easing: 'easeOutQuart',

                            animateRotate: true,

                            animateScale: true

                        },


                        plugins: {

                            legend: {

                                display: false

                            },


                            tooltip: {

                                backgroundColor: '#172033',

                                titleColor: '#ffffff',

                                bodyColor: '#e2e8f0',

                                padding: 10,

                                cornerRadius: 9,

                                callbacks: {

                                    label: function(context) {

                                        return (
                                            ' ' +
                                            context.label +
                                            ': ₱' +
                                            Number(
                                                context.raw
                                            ).toLocaleString(
                                                undefined, {
                                                    minimumFractionDigits: 2
                                                }
                                            )
                                        );

                                    }

                                }

                            }

                        }

                    }

                }
            );

        }
    </script>


</body>

</html>
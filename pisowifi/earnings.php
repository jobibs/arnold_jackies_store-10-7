<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

$basePath = '../';
$activeMenu = 'pisowifi';

$notifications = [];


/*
|--------------------------------------------------------------------------
| LOW STOCK NOTIFICATIONS
|--------------------------------------------------------------------------
*/

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


$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD PISOWIFI BOX
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        box_name,
        location,
        description,
        status
    FROM pisowifi
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$box = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$box) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| DEFAULT MONTH
|--------------------------------------------------------------------------
*/

$selectedMonth = isset($_GET['month'])
    ? (int)$_GET['month']
    : (int)date('n');

$selectedYear = isset($_GET['year'])
    ? (int)$_GET['year']
    : (int)date('Y');


if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int)date('n');
}

if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = (int)date('Y');
}


/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

$grossEarnings = '';
$expenses = '';
$notes = '';

$error = '';
$success = '';


/*
|--------------------------------------------------------------------------
| LOAD EXISTING RECORD
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        gross_earnings,
        expenses,
        notes
    FROM pisowifi_earnings
    WHERE pisowifi_id = ?
      AND earning_month = ?
      AND earning_year = ?
    LIMIT 1
");

$stmt->execute([
    $id,
    $selectedMonth,
    $selectedYear
]);

$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {

    $grossEarnings = $existing['gross_earnings'];
    $expenses = $existing['expenses'];
    $notes = $existing['notes'] ?? '';
}


/*
|--------------------------------------------------------------------------
| SAVE MONTHLY EARNINGS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selectedMonth = (int)($_POST['earning_month'] ?? 0);
    $selectedYear = (int)($_POST['earning_year'] ?? 0);

    $grossInput = trim($_POST['gross_earnings'] ?? '');
    $expensesInput = trim($_POST['expenses'] ?? '');
    $notesInput = trim($_POST['notes'] ?? '');

    $grossEarnings = $grossInput;
    $expenses = $expensesInput;
    $notes = $notesInput;


    if ($selectedMonth < 1 || $selectedMonth > 12) {

        $error = 'Please select a valid month.';
    } elseif ($selectedYear < 2000 || $selectedYear > 2100) {

        $error = 'Please enter a valid year.';
    } elseif (
        $grossInput === '' ||
        !is_numeric($grossInput) ||
        (float)$grossInput < 0
    ) {

        $error = 'Gross earnings must be a valid amount greater than or equal to zero.';
    } elseif (
        $expensesInput === '' ||
        !is_numeric($expensesInput) ||
        (float)$expensesInput < 0
    ) {

        $error = 'Expenses must be a valid amount greater than or equal to zero.';
    } else {

        $grossValue = round((float)$grossInput, 2);
        $expenseValue = round((float)$expensesInput, 2);


        try {

            /*
            |--------------------------------------------------------------------------
            | INSERT OR UPDATE
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO pisowifi_earnings
                (
                    pisowifi_id,
                    earning_month,
                    earning_year,
                    gross_earnings,
                    expenses,
                    notes
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
                ON DUPLICATE KEY UPDATE

                    gross_earnings = VALUES(gross_earnings),
                    expenses = VALUES(expenses),
                    notes = VALUES(notes)
            ");

            $stmt->execute([
                $id,
                $selectedMonth,
                $selectedYear,
                $grossValue,
                $expenseValue,
                $notesInput !== '' ? $notesInput : null
            ]);


            $success = 'Monthly earnings successfully saved.';

            $grossEarnings = number_format($grossValue, 2, '.', '');
            $expenses = number_format($expenseValue, 2, '.', '');
        } catch (PDOException $e) {

            $error = 'Unable to save the monthly earnings. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT NET
|--------------------------------------------------------------------------
*/

$grossValue = is_numeric($grossEarnings)
    ? (float)$grossEarnings
    : 0;

$expenseValue = is_numeric($expenses)
    ? (float)$expenses
    : 0;

$netEarnings = $grossValue - $expenseValue;


/*
|--------------------------------------------------------------------------
| ALL MONTHLY RECORDS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        earning_month,
        earning_year,
        gross_earnings,
        expenses,
        (gross_earnings - expenses) AS net_earnings
    FROM pisowifi_earnings
    WHERE pisowifi_id = ?
    ORDER BY
        earning_year DESC,
        earning_month DESC
");

$stmt->execute([$id]);

$earningHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>
        <?= htmlspecialchars($box['box_name']) ?> Earnings - Arnold & Jackie's Store
    </title>

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">

    <style>
        .earnings-page {
            width: 100%;
        }

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
        }

        .page-title {
            margin: 0;
            font-size: 25px;
            font-weight: 700;
            color: #172033;
        }

        .page-subtitle {
            margin-top: 5px;
            font-size: 13px;
            color: #6b7280;
        }

        .back-link {
            color: #374151;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .back-link:hover {
            color: #0d6efd;
        }

        .box-info {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 17px 20px;
            margin-bottom: 18px;
        }

        .box-info-name {
            font-size: 15px;
            font-weight: 700;
            color: #172033;
        }

        .box-info-location {
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
        }

        .earnings-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.5fr);
            gap: 18px;
            align-items: start;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .card-header {
            padding: 17px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-title {
            font-size: 15px;
            font-weight: 700;
            color: #172033;
        }

        .card-subtitle {
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
        }

        .card-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 7px;
            font-size: 12px;
            font-weight: 700;
            color: #374151;
        }

        .form-control {
            width: 100%;
            height: 42px;
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            color: #172033;
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }

        textarea.form-control {
            height: 90px;
            resize: vertical;
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .10);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 9px 15px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            width: 100%;
            background: #0d6efd;
            border: 1px solid #0d6efd;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0b5ed7;
        }

        .error-box,
        .success-box {
            margin-bottom: 17px;
            padding: 11px 13px;
            border-radius: 8px;
            font-size: 13px;
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .success-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
        }

        .net-preview {
            margin-top: 17px;
            padding: 15px;
            border-radius: 9px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .net-preview-label {
            font-size: 11px;
            color: #15803d;
            margin-bottom: 4px;
        }

        .net-preview-value {
            font-size: 22px;
            font-weight: 700;
            color: #16a34a;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th {
            padding: 11px 13px;
            background: #f8fafc;
            color: #6b7280;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .history-table td {
            padding: 13px;
            border-top: 1px solid #eef0f3;
            font-size: 13px;
            color: #374151;
        }

        .history-table td.amount {
            font-weight: 600;
        }

        .history-table td.net {
            color: #16a34a;
            font-weight: 700;
        }

        .empty-history {
            padding: 35px 20px;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
        }

        @media (max-width: 900px) {

            .earnings-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 650px) {

            .page-header {
                flex-direction: column;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .card-body {
                padding: 17px;
            }

        }
    </style>

</head>

<body>

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="main">

        <?php require_once $basePath . 'includes/topbar.php'; ?>

        <main class="content">

            <div class="earnings-page">

                <div class="page-header">

                    <div>

                        <h1 class="page-title">
                            <?= htmlspecialchars($box['box_name']) ?>
                        </h1>

                        <div class="page-subtitle">
                            Monthly earnings and expense records
                        </div>

                    </div>

                    <a
                        href="index.php"
                        class="back-link">
                        ← Back to PisoWiFi
                    </a>

                </div>


                <div class="box-info">

                    <div class="box-info-name">
                        📡 <?= htmlspecialchars($box['box_name']) ?>
                    </div>

                    <div class="box-info-location">
                        Location: <?= htmlspecialchars($box['location']) ?>
                    </div>

                </div>


                <div class="earnings-grid">


                    <!-- ENTRY FORM -->

                    <div class="card">

                        <div class="card-header">

                            <div class="card-title">
                                Monthly Earnings
                            </div>

                            <div class="card-subtitle">
                                Add or update a monthly record.
                            </div>

                        </div>


                        <div class="card-body">

                            <?php if ($error): ?>

                                <div class="error-box">
                                    <?= htmlspecialchars($error) ?>
                                </div>

                            <?php endif; ?>


                            <?php if ($success): ?>

                                <div class="success-box">
                                    <?= htmlspecialchars($success) ?>
                                </div>

                            <?php endif; ?>


                            <form
                                method="POST"
                                autocomplete="off">

                                <div class="form-row">

                                    <div class="form-group">

                                        <label class="form-label">
                                            Month
                                        </label>

                                        <select
                                            name="earning_month"
                                            class="form-control"
                                            onchange="this.form.submit()">

                                            <?php for ($month = 1; $month <= 12; $month++): ?>

                                                <option
                                                    value="<?= $month ?>"
                                                    <?= $selectedMonth === $month ? 'selected' : '' ?>>

                                                    <?= date('F', mktime(0, 0, 0, $month, 1)) ?>

                                                </option>

                                            <?php endfor; ?>

                                        </select>

                                    </div>


                                    <div class="form-group">

                                        <label class="form-label">
                                            Year
                                        </label>

                                        <input
                                            type="number"
                                            name="earning_year"
                                            class="form-control"
                                            value="<?= htmlspecialchars($selectedYear) ?>"
                                            min="2000"
                                            max="2100"
                                            required>

                                    </div>

                                </div>


                                <div class="form-group">

                                    <label class="form-label">
                                        Gross Earnings
                                    </label>

                                    <input
                                        type="number"
                                        name="gross_earnings"
                                        class="form-control"
                                        value="<?= htmlspecialchars($grossEarnings) ?>"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                        required>

                                </div>


                                <div class="form-group">

                                    <label class="form-label">
                                        Expenses
                                    </label>

                                    <input
                                        type="number"
                                        name="expenses"
                                        class="form-control"
                                        value="<?= htmlspecialchars($expenses) ?>"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                        required>

                                </div>


                                <div class="form-group">

                                    <label class="form-label">
                                        Notes
                                    </label>

                                    <textarea
                                        name="notes"
                                        class="form-control"
                                        placeholder="Optional notes..."><?= htmlspecialchars($notes) ?></textarea>

                                </div>


                                <div class="net-preview">

                                    <div class="net-preview-label">
                                        NET EARNINGS
                                    </div>

                                    <div class="net-preview-value">
                                        ₱<?= number_format($netEarnings, 2) ?>
                                    </div>

                                </div>


                                <div style="margin-top:17px;">

                                    <button
                                        type="submit"
                                        class="btn btn-primary">
                                        Save Monthly Earnings
                                    </button>

                                </div>

                            </form>

                        </div>

                    </div>


                    <!-- HISTORY -->

                    <div class="card">

                        <div class="card-header">

                            <div class="card-title">
                                Earnings History
                            </div>

                            <div class="card-subtitle">
                                Previous monthly records
                            </div>

                        </div>


                        <?php if ($earningHistory): ?>

                            <div style="overflow-x:auto;">

                                <table class="history-table">

                                    <thead>

                                        <tr>

                                            <th>
                                                Month
                                            </th>

                                            <th>
                                                Gross
                                            </th>

                                            <th>
                                                Expenses
                                            </th>

                                            <th>
                                                Net
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($earningHistory as $row): ?>

                                            <tr>

                                                <td>

                                                    <?= date(
                                                        'F Y',
                                                        mktime(
                                                            0,
                                                            0,
                                                            0,
                                                            (int)$row['earning_month'],
                                                            1,
                                                            (int)$row['earning_year']
                                                        )
                                                    ) ?>

                                                </td>

                                                <td class="amount">

                                                    ₱<?= number_format(
                                                            $row['gross_earnings'],
                                                            2
                                                        ) ?>

                                                </td>

                                                <td class="amount">

                                                    ₱<?= number_format(
                                                            $row['expenses'],
                                                            2
                                                        ) ?>

                                                </td>

                                                <td class="net">

                                                    ₱<?= number_format(
                                                            $row['net_earnings'],
                                                            2
                                                        ) ?>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php else: ?>

                            <div class="empty-history">
                                No monthly earnings have been recorded yet.
                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </main>

    </div>

    <script src="<?= $basePath ?>assets/js/admin.js"></script>

</body>

</html>
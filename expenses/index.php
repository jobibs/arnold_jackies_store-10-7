<?php

require_once "../config/database.php";
require_once "../config/auth.php";

require_login();

/*
|--------------------------------------------------------------------------
| Universal Admin Layout
|--------------------------------------------------------------------------
*/

$basePath = '../';
$activeMenu = 'expenses';
$currentSection = 'expenses';


/*
|--------------------------------------------------------------------------
| Add Expense
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['expense_name'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $date = $_POST['expense_date'] ?? date('Y-m-d');
    $remarks = trim($_POST['remarks'] ?? '');


    if ($name !== '' && $amount > 0) {

        $stmt = $pdo->prepare("
            INSERT INTO expenses
            (
                expense_name,
                amount,
                expense_date,
                remarks
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $name,
            $amount,
            $date,
            $remarks ?: null
        ]);

        header("Location: index.php?saved=1");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Recent Expenses
|--------------------------------------------------------------------------
*/

$rows = $pdo->query("
    SELECT *
    FROM expenses
    ORDER BY
        expense_date DESC,
        id DESC
    LIMIT 100
")->fetchAll();


/*
|--------------------------------------------------------------------------
| Inventory Notifications
|--------------------------------------------------------------------------
|
| Used by the universal topbar notification dropdown.
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

?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Expenses - Arnold & Jackie's Store
    </title>


    <!--
    |--------------------------------------------------------------------------
    | Bootstrap
    |--------------------------------------------------------------------------
    -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!--
    |--------------------------------------------------------------------------
    | Universal Admin Design
    |--------------------------------------------------------------------------
    -->

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">


    <style>
        /*
        |--------------------------------------------------------------------------
        | Expenses Page
        |--------------------------------------------------------------------------
        */

        .expense-card {
            background: #ffffff;
            border: 1px solid #e8ebf0;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, .04);
            overflow: hidden;
        }


        .expense-card-header {
            padding: 20px 22px;
            border-bottom: 1px solid #edf0f4;
        }


        .expense-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #172033;
            margin-bottom: 3px;
        }


        .expense-card-subtitle {
            font-size: 12px;
            color: #8b94a3;
        }


        .expense-card-body {
            padding: 22px;
        }


        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }


        .form-control {
            min-height: 42px;
            border-color: #dfe4ea;
            font-size: 13px;
            border-radius: 8px;
        }


        .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .10);
        }


        .expense-save-btn {
            min-height: 42px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }


        .expense-table-wrapper {
            overflow-x: auto;
        }


        .expense-table {
            margin: 0;
            font-size: 13px;
        }


        .expense-table thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .25px;
            padding: 13px 16px;
            white-space: nowrap;
        }


        .expense-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf0f4;
            color: #374151;
            vertical-align: middle;
        }


        .expense-table tbody tr:last-child td {
            border-bottom: 0;
        }


        .expense-table tbody tr:hover {
            background: #fafbfc;
        }


        .expense-name {
            font-weight: 600;
            color: #172033;
        }


        .expense-date {
            color: #6b7280;
            white-space: nowrap;
        }


        .expense-amount {
            font-weight: 700;
            color: #dc3545;
            white-space: nowrap;
        }


        .expense-remarks {
            color: #7b8494;
        }


        /*
        |--------------------------------------------------------------------------
        | Delete Action
        |--------------------------------------------------------------------------
        */

        .expense-action-form {
            margin: 0;
        }


        .expense-delete-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            min-height: 32px;
            padding: 5px 10px;
            border: 1px solid #fecaca;
            border-radius: 7px;
            background: #fff1f2;
            color: #dc2626;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition:
                background .15s ease,
                border-color .15s ease,
                color .15s ease,
                transform .15s ease;
        }


        .expense-delete-btn:hover {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #b91c1c;
        }


        .expense-delete-btn:active {
            transform: translateY(1px);
        }


        .expense-alert {
            border-radius: 10px;
            font-size: 13px;
            opacity: 1;
            max-height: 100px;
            overflow: hidden;
            transition:
                opacity .3s ease,
                max-height .3s ease,
                margin .3s ease,
                padding .3s ease,
                border-width .3s ease;
        }

        .expense-alert.expense-alert-hide {
            opacity: 0;
            max-height: 0;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            border-width: 0;
        }


        .empty-expenses {
            padding: 45px 20px;
            text-align: center;
            color: #8b94a3;
            font-size: 12px;
        }


        .empty-expenses strong {
            display: block;
            color: #273142;
            font-size: 14px;
            margin-bottom: 4px;
        }


        .empty-expenses-icon {
            width: 45px;
            height: 45px;
            margin: 0 auto 10px;
            border-radius: 50%;
            background: #fff1f2;
            color: #dc3545;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
        }


        @media (max-width: 767px) {

            .expense-card-header {
                padding: 17px;
            }


            .expense-card-body {
                padding: 16px;
            }

        }
    </style>

</head>


<body>


    <!--
    |--------------------------------------------------------------------------
    | Universal Sidebar
    |--------------------------------------------------------------------------
    -->

    <?php require_once $basePath . 'includes/sidebar.php'; ?>


    <!--
    |--------------------------------------------------------------------------
    | Main Content
    |--------------------------------------------------------------------------
    -->

    <main class="main">


        <!--
        |--------------------------------------------------------------------------
        | Universal Topbar
        |--------------------------------------------------------------------------
        -->

        <?php require_once $basePath . 'includes/topbar.php'; ?>


        <!--
        |--------------------------------------------------------------------------
        | Page Content
        |--------------------------------------------------------------------------
        -->

        <div class="content">


            <!--
            |--------------------------------------------------------------------------
            | Page Header
            |--------------------------------------------------------------------------
            -->

            <div class="d-flex justify-content-between align-items-start mb-4">

                <div>

                    <div class="page-title">
                        Expenses
                    </div>

                    <div class="page-subtitle">
                        Record and monitor the store's expenses.
                    </div>

                </div>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Success Message
            |--------------------------------------------------------------------------
            -->

            <?php if (isset($_GET['saved'])): ?>

                <div
                    class="alert alert-success expense-alert mb-4"
                    role="alert">

                    Expense saved successfully.

                </div>

            <?php endif; ?>


            <?php if (isset($_GET['deleted'])): ?>

                <div
                    class="alert alert-success expense-alert mb-4"
                    role="alert">

                    Expense deleted successfully.

                </div>

            <?php endif; ?>


            <!--
            |--------------------------------------------------------------------------
            | Add Expense
            |--------------------------------------------------------------------------
            -->

            <div class="expense-card mb-4">

                <div class="expense-card-header">

                    <div class="expense-card-title">
                        Add Expense
                    </div>

                    <div class="expense-card-subtitle">
                        Enter the expense details below.
                    </div>

                </div>


                <div class="expense-card-body">

                    <form method="post">

                        <div class="row g-3">


                            <!--
                            |--------------------------------------------------------------------------
                            | Expense Name
                            |--------------------------------------------------------------------------
                            -->

                            <div class="col-lg-4 col-md-6">

                                <label
                                    for="expense_name"
                                    class="form-label">

                                    Expense Name

                                </label>

                                <input
                                    type="text"
                                    name="expense_name"
                                    id="expense_name"
                                    class="form-control"
                                    placeholder="e.g. Electricity"
                                    required>

                            </div>


                            <!--
                            |--------------------------------------------------------------------------
                            | Amount
                            |--------------------------------------------------------------------------
                            -->

                            <div class="col-lg-3 col-md-6">

                                <label
                                    for="amount"
                                    class="form-label">

                                    Amount

                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        ₱
                                    </span>

                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        name="amount"
                                        id="amount"
                                        class="form-control"
                                        placeholder="0.00"
                                        required>

                                </div>

                            </div>


                            <!--
                            |--------------------------------------------------------------------------
                            | Expense Date
                            |--------------------------------------------------------------------------
                            -->

                            <div class="col-lg-3 col-md-6">

                                <label
                                    for="expense_date"
                                    class="form-label">

                                    Expense Date

                                </label>

                                <input
                                    type="date"
                                    name="expense_date"
                                    id="expense_date"
                                    class="form-control"
                                    value="<?= date('Y-m-d') ?>"
                                    required>

                            </div>


                            <!--
                            |--------------------------------------------------------------------------
                            | Save Button
                            |--------------------------------------------------------------------------
                            -->

                            <div class="col-lg-2 col-md-6 d-flex align-items-end">

                                <button
                                    type="submit"
                                    class="btn btn-dark w-100 expense-save-btn">

                                    Save Expense

                                </button>

                            </div>


                            <!--
                            |--------------------------------------------------------------------------
                            | Remarks
                            |--------------------------------------------------------------------------
                            -->

                            <div class="col-12">

                                <label
                                    for="remarks"
                                    class="form-label">

                                    Remarks

                                    <span
                                        style="
                                            font-size:10px;
                                            color:#9ca3af;
                                            font-weight:400;
                                        ">

                                        (optional)

                                    </span>

                                </label>

                                <input
                                    type="text"
                                    name="remarks"
                                    id="remarks"
                                    class="form-control"
                                    placeholder="Optional notes about this expense">

                            </div>


                        </div>

                    </form>

                </div>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Recent Expenses
            |--------------------------------------------------------------------------
            -->

            <div class="expense-card">

                <div class="expense-card-header">

                    <div class="expense-card-title">
                        Recent Expenses
                    </div>

                    <div class="expense-card-subtitle">
                        The latest 100 recorded expenses.
                    </div>

                </div>


                <div class="expense-table-wrapper">

                    <?php if (!empty($rows)): ?>

                        <table class="table expense-table align-middle">

                            <thead>

                                <tr>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Expense
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Remarks
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($rows as $r): ?>

                                    <tr>

                                        <!-- Date -->

                                        <td>

                                            <span class="expense-date">

                                                <?= htmlspecialchars(
                                                    $r['expense_date']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- Expense -->

                                        <td>

                                            <span class="expense-name">

                                                <?= htmlspecialchars(
                                                    $r['expense_name']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- Amount -->

                                        <td>

                                            <span class="expense-amount">

                                                ₱<?= number_format(
                                                        (float)$r['amount'],
                                                        2
                                                    ) ?>

                                            </span>

                                        </td>


                                        <!-- Remarks -->

                                        <td>

                                            <span class="expense-remarks">

                                                <?= htmlspecialchars(
                                                    $r['remarks'] ?? ''
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- Action -->

                                        <td>

                                            <form
                                                method="post"
                                                action="delete.php"
                                                class="expense-action-form"
                                                onsubmit="return confirm('Are you sure you want to delete this expense? This action cannot be undone.');">

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int)$r['id'] ?>">

                                                <button
                                                    type="submit"
                                                    class="expense-delete-btn">

                                                    Delete

                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    <?php else: ?>


                        <!--
                        |--------------------------------------------------------------------------
                        | Empty State
                        |--------------------------------------------------------------------------
                        -->

                        <div class="empty-expenses">

                            <div class="empty-expenses-icon">
                                ₱
                            </div>

                            <strong>
                                No expenses recorded
                            </strong>

                            Expenses you add will appear here.

                        </div>

                    <?php endif; ?>

                </div>

            </div>


        </div>

    </main>


    <!--
    |--------------------------------------------------------------------------
    | Bootstrap JS
    |--------------------------------------------------------------------------
    -->

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    <!--
    |--------------------------------------------------------------------------
    | Universal Admin JS
    |--------------------------------------------------------------------------
    -->

    <script
        src="<?= $basePath ?>assets/js/admin.js"></script>

    <script>
        /*
    |--------------------------------------------------------------------------
    | Auto-hide Expense Alerts
    |--------------------------------------------------------------------------
    */

        document.addEventListener('DOMContentLoaded', function() {

            const alerts = document.querySelectorAll('.expense-alert');

            alerts.forEach(function(alert) {

                setTimeout(function() {

                    alert.classList.add('expense-alert-hide');

                    setTimeout(function() {

                        if (alert && alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }

                    }, 350);

                }, 5000);

            });

        });
    </script>


</body>

</html>
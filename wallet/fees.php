<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

/*
|--------------------------------------------------------------------------
| Universal Admin Layout
|--------------------------------------------------------------------------
*/

$basePath = '../';
$activeMenu = 'wallet';

$error = '';

/*
|--------------------------------------------------------------------------
| Add Fee Rule
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $provider = $_POST['provider'] ?? '';
    $type = $_POST['transaction_type'] ?? '';

    $min = (float)($_POST['min_amount'] ?? 0);

    $max = $_POST['max_amount'] !== ''
        ? (float)$_POST['max_amount']
        : null;

    $fee = (float)($_POST['fee'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | Validate
    |--------------------------------------------------------------------------
    */

    if (
        !in_array($provider, ['gcash', 'maya'], true) ||
        !in_array($type, ['cash_in', 'cash_out'], true)
    ) {

        $error = 'Invalid provider or transaction type.';
    } elseif (
        $min < 0 ||
        ($max !== null && $max < $min) ||
        $fee < 0
    ) {

        $error = 'Please check the amount range and fee.';
    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Insert Fee Rule
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO wallet_fee_rules
                (
                    provider,
                    transaction_type,
                    min_amount,
                    max_amount,
                    fee
                )
                VALUES
                (
                    ?, ?, ?, ?, ?
                )
            ");

            $stmt->execute([
                $provider,
                $type,
                $min,
                $max,
                $fee
            ]);

            header('Location: fees.php?saved=1');
            exit;
        } catch (PDOException $e) {

            $error = $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Deactivate Fee Rule
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {

    $id = (int)$_GET['delete'];

    if ($id > 0) {

        try {

            $stmt = $pdo->prepare("
                UPDATE wallet_fee_rules
                SET is_active = 0
                WHERE id = ?
            ");

            $stmt->execute([$id]);
        } catch (PDOException $e) {

            $error = 'Unable to deactivate the fee rule.';
        }
    }

    header('Location: fees.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Active Fee Rules
|--------------------------------------------------------------------------
*/

$rules = [];

try {

    $rules = $pdo->query("
        SELECT *
        FROM wallet_fee_rules
        WHERE is_active = 1
        ORDER BY
            provider,
            transaction_type,
            min_amount
    ")->fetchAll();
} catch (PDOException $e) {

    $error = 'Unable to load wallet fee rules.';
}


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

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>Wallet Fee Settings</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Universal Admin Design -->
    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">

    <style>
        /*
        |--------------------------------------------------------------------------
        | Wallet Fee Settings Page
        |--------------------------------------------------------------------------
        */

        .fee-card {
            background: #ffffff;
            border: 1px solid #e8ebf0;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, .04);
            overflow: hidden;
        }

        .fee-card-header {
            padding: 20px 22px;
            border-bottom: 1px solid #edf0f4;
        }

        .fee-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #172033;
            margin-bottom: 3px;
        }

        .fee-card-subtitle {
            font-size: 12px;
            color: #8b94a3;
        }

        .fee-card-body {
            padding: 22px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            min-height: 42px;
            border-color: #dfe4ea;
            font-size: 13px;
            border-radius: 8px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .10);
        }

        .fee-add-button {
            min-height: 42px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .fee-table-wrapper {
            overflow-x: auto;
        }

        .fee-table {
            margin: 0;
            font-size: 13px;
        }

        .fee-table thead th {
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

        .fee-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf0f4;
            color: #374151;
            vertical-align: middle;
        }

        .fee-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .fee-table tbody tr:hover {
            background: #fafbfc;
        }

        .provider-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 700;
        }

        .provider-gcash {
            background: #e8f8ef;
            color: #15803d;
        }

        .provider-maya {
            background: #eef2ff;
            color: #4338ca;
        }

        .transaction-type {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
        }

        .amount-range {
            font-size: 12px;
            color: #4b5563;
            white-space: nowrap;
        }

        .fee-amount {
            font-size: 13px;
            font-weight: 700;
            color: #172033;
            white-space: nowrap;
        }

        .deactivate-button {
            font-size: 11px;
            font-weight: 600;
            border-radius: 7px;
            white-space: nowrap;
        }

        .empty-rules {
            padding: 45px 20px;
            text-align: center;
            color: #8b94a3;
            font-size: 12px;
        }

        .empty-rules strong {
            display: block;
            color: #273142;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .empty-rules-icon {
            width: 45px;
            height: 45px;
            margin: 0 auto 10px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .fee-alert {
            border-radius: 10px;
            font-size: 13px;
        }

        .page-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-button {
            min-height: 40px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        @media (max-width: 991px) {

            .fee-card-body {
                padding: 18px;
            }

        }

        @media (max-width: 767px) {

            .page-actions {
                margin-top: 12px;
            }

            .fee-card-header {
                padding: 17px;
            }

            .fee-card-body {
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
                        Wallet Fee Settings
                    </div>

                    <div class="page-subtitle">
                        Set different service fees by provider, transaction type, and amount range.
                    </div>

                </div>


                <div class="page-actions">

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary back-button">
                        Back
                    </a>

                </div>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Error Message
            |--------------------------------------------------------------------------
            -->

            <?php if ($error): ?>

                <div
                    class="alert alert-danger fee-alert mb-4"
                    role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <!--
            |--------------------------------------------------------------------------
            | Success Message
            |--------------------------------------------------------------------------
            -->

            <?php if (isset($_GET['saved'])): ?>

                <div
                    class="alert alert-success fee-alert mb-4"
                    role="alert">
                    Fee rule added successfully.
                </div>

            <?php endif; ?>


            <!--
            |--------------------------------------------------------------------------
            | Add Fee Rule
            |--------------------------------------------------------------------------
            -->

            <div class="fee-card mb-4">

                <div class="fee-card-header">

                    <div class="fee-card-title">
                        Add Fee Rule
                    </div>

                    <div class="fee-card-subtitle">
                        Create a service fee based on the provider, transaction type, and wallet amount.
                    </div>

                </div>


                <div class="fee-card-body">

                    <form method="post">

                        <div class="row g-3">


                            <!--
                            |--------------------------------------------------------------------------
                            | Provider
                            |--------------------------------------------------------------------------
                            -->

                            <div class="col-xl-2 col-md-6">

                                <label
                                    for="provider"
                                    class="form-label">
                                    Provider
                                </label>

                                <select
                                    name="provider"
                                    id="provider"
                                    class="form-select"
                                    required>

                                    <option value="gcash">
                                        GCash
                                    </option>

                                    <option value="maya">
                                        Maya
                                    </option>

                                </select>

                            </div>


                            <!--
                            |--------------------------------------------------------------------------
                            | Transaction Type
                            |--------------------------------------------------------------------------
                            -->

                            <div class="col-xl-2 col-md-6">

                                <label
                                    for="transaction_type"
                                    class="form-label">
                                    Type
                                </label>

                                <select
                                    name="transaction_type"
                                    id="transaction_type"
                                    class="form-select"
                                    required>

                                    <option value="cash_in">
                                        Cash In
                                    </option>

                                    <option value="cash_out">
                                        Cash Out
                                    </option>

                                </select>

                            </div>


                            <!--
                            |--------------------------------------------------------------------------
                            | Minimum Amount
                            |--------------------------------------------------------------------------
                            -->

                            <div class="col-xl-2 col-md-4">

                                <label
                                    for="min_amount"
                                    class="form-label">
                                    Minimum
                                </label>

                                <input
                                    type="number"
                                    name="min_amount"
                                    id="min_amount"
                                    min="0"
                                    step="0.01"
                                    value="0"
                                    class="form-control"
                                    required>

                            </div>


                            <!--
                            |--------------------------------------------------------------------------
                            | Maximum Amount
                            |--------------------------------------------------------------------------
                            -->

                            <div class="col-xl-2 col-md-4">

                                <label
                                    for="max_amount"
                                    class="form-label">
                                    Maximum
                                </label>

                                <input
                                    type="number"
                                    name="max_amount"
                                    id="max_amount"
                                    min="0"
                                    step="0.01"
                                    placeholder="No limit"
                                    class="form-control">

                            </div>


                            <!--
                            |--------------------------------------------------------------------------
                            | Fee
                            |--------------------------------------------------------------------------
                            -->

                            <div class="col-xl-2 col-md-4">

                                <label
                                    for="fee"
                                    class="form-label">
                                    Fee / Markup
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        ₱
                                    </span>

                                    <input
                                        type="number"
                                        name="fee"
                                        id="fee"
                                        min="0"
                                        step="0.01"
                                        value="0"
                                        class="form-control"
                                        required>

                                </div>

                            </div>


                            <!--
                            |--------------------------------------------------------------------------
                            | Add Button
                            |--------------------------------------------------------------------------
                            -->

                            <div class="col-xl-2 col-md-12 d-flex align-items-end">

                                <button
                                    type="submit"
                                    class="btn btn-dark w-100 fee-add-button">
                                    Add Rule
                                </button>

                            </div>


                        </div>

                    </form>

                </div>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Active Fee Rules
            |--------------------------------------------------------------------------
            -->

            <div class="fee-card">

                <div class="fee-card-header">

                    <div class="fee-card-title">
                        Active Fee Rules
                    </div>

                    <div class="fee-card-subtitle">
                        These rules are used automatically when creating GCash or Maya transactions.
                    </div>

                </div>


                <div class="fee-table-wrapper">

                    <?php if (!empty($rules)): ?>

                        <table class="table fee-table align-middle">

                            <thead>

                                <tr>

                                    <th>
                                        Provider
                                    </th>

                                    <th>
                                        Type
                                    </th>

                                    <th>
                                        Amount Range
                                    </th>

                                    <th>
                                        Fee
                                    </th>

                                    <th class="text-end">
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($rules as $r): ?>

                                    <tr>


                                        <!--
                                        |--------------------------------------------------------------------------
                                        | Provider
                                        |--------------------------------------------------------------------------
                                        -->

                                        <td>

                                            <?php if ($r['provider'] === 'gcash'): ?>

                                                <span class="provider-badge provider-gcash">
                                                    GCASH
                                                </span>

                                            <?php else: ?>

                                                <span class="provider-badge provider-maya">
                                                    MAYA
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!--
                                        |--------------------------------------------------------------------------
                                        | Transaction Type
                                        |--------------------------------------------------------------------------
                                        -->

                                        <td>

                                            <span class="transaction-type">

                                                <?= htmlspecialchars(
                                                    ucwords(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $r['transaction_type']
                                                        )
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <!--
                                        |--------------------------------------------------------------------------
                                        | Amount Range
                                        |--------------------------------------------------------------------------
                                        -->

                                        <td>

                                            <span class="amount-range">

                                                ₱<?= number_format(
                                                        (float)$r['min_amount'],
                                                        2
                                                    ) ?>

                                                –

                                                <?=
                                                $r['max_amount'] === null
                                                    ? 'No limit'
                                                    : '₱' . number_format(
                                                        (float)$r['max_amount'],
                                                        2
                                                    )
                                                ?>

                                            </span>

                                        </td>


                                        <!--
                                        |--------------------------------------------------------------------------
                                        | Fee
                                        |--------------------------------------------------------------------------
                                        -->

                                        <td>

                                            <span class="fee-amount">

                                                ₱<?= number_format(
                                                        (float)$r['fee'],
                                                        2
                                                    ) ?>

                                            </span>

                                        </td>


                                        <!--
                                        |--------------------------------------------------------------------------
                                        | Action
                                        |--------------------------------------------------------------------------
                                        -->

                                        <td class="text-end">

                                            <a
                                                href="?delete=<?= (int)$r['id'] ?>"
                                                class="btn btn-sm btn-outline-danger deactivate-button"
                                                onclick="return confirm('Deactivate this fee rule?')">
                                                Deactivate
                                            </a>

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

                        <div class="empty-rules">

                            <div class="empty-rules-icon">
                                ₱
                            </div>

                            <strong>
                                No active fee rules
                            </strong>

                            Add a fee rule above to start calculating
                            GCash and Maya service fees automatically.

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


</body>

</html>
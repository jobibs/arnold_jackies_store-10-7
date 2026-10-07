<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

$basePath = '../';
$activeMenu = 'inventory';
$currentSection = 'inventory';

$productId = (int)($_GET['id'] ?? $_POST['product_id'] ?? 0);

if ($productId <= 0) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Product
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        product_code,
        product_name,
        stock,
        unit,
        cost_price,
        selling_price
    FROM products
    WHERE id = ?
      AND is_active = 1
    LIMIT 1
");

$stmt->execute([$productId]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Inventory Notifications
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
| Form Processing
|--------------------------------------------------------------------------
*/

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $addStock = trim($_POST['add_stock'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validate
    |--------------------------------------------------------------------------
    */

    if ($addStock === '') {

        $error = 'Please enter the quantity to add.';
    } elseif (!is_numeric($addStock)) {

        $error = 'Please enter a valid number.';
    } elseif ((float)$addStock <= 0) {

        $error = 'The quantity must be greater than zero.';
    } else {

        $addStock = (float)$addStock;

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Lock Product Row
            |--------------------------------------------------------------------------
            */

            $lockStmt = $pdo->prepare("
                SELECT
                    stock
                FROM products
                WHERE id = ?
                  AND is_active = 1
                FOR UPDATE
            ");

            $lockStmt->execute([$productId]);

            $currentProduct = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentProduct) {

                throw new Exception(
                    'The product could not be found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Calculate New Stock
            |--------------------------------------------------------------------------
            */

            $currentStock = (float)$currentProduct['stock'];

            $newStock = $currentStock + $addStock;


            /*
            |--------------------------------------------------------------------------
            | Update Stock
            |--------------------------------------------------------------------------
            */

            $updateStmt = $pdo->prepare("
                UPDATE products
                SET stock = ?
                WHERE id = ?
            ");

            $updateStmt->execute([
                $newStock,
                $productId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Return To Inventory
            |--------------------------------------------------------------------------
            */

            header('Location: index.php');
            exit;
        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Unable to add stock. Please try again.';
        }
    }
}

?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>
        Add Stock - Arnold & Jackie's Store
    </title>


    <!-- =========================================================
         UNIVERSAL ADMIN CSS
         ========================================================= -->

    <link
        href="<?= $basePath ?>assets/css/admin.css"
        rel="stylesheet">


    <style>
        /* =========================================================
           PAGE
           ========================================================= */

        .stock-page {
            width: 100%;
            display: flex;
            justify-content: center;
        }


        /* =========================================================
           CARD
           ========================================================= */

        .stock-card {
            width: 100%;
            max-width: 560px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 13px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .03);
        }


        .stock-card-header {
            padding: 20px 22px;
            border-bottom: 1px solid #edf0f4;
        }


        .stock-title {
            margin: 0 0 5px;
            font-size: 20px;
            font-weight: 700;
            color: #172033;
        }


        .stock-subtitle {
            margin: 0;
            font-size: 12px;
            color: #7b8494;
        }


        .stock-card-body {
            padding: 22px;
        }


        /* =========================================================
           PRODUCT INFO
           ========================================================= */

        .stock-product-info {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }


        .stock-product-name {
            font-size: 15px;
            font-weight: 700;
            color: #202938;
            margin-bottom: 4px;
        }


        .stock-product-code {
            font-family: monospace;
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 12px;
        }


        .stock-current {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }


        .stock-current-label {
            font-size: 12px;
            color: #6b7280;
        }


        .stock-current-value {
            font-size: 18px;
            font-weight: 700;
            color: #172033;
        }


        /* =========================================================
           FORM
           ========================================================= */

        .stock-form-group {
            margin-bottom: 18px;
        }


        .stock-label {
            display: block;
            margin-bottom: 7px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
        }


        .stock-input-wrapper {
            display: flex;
            align-items: stretch;
        }


        .stock-input {
            width: 100%;
            height: 43px;
            padding: 9px 12px;
            border: 1px solid #d9dee7;
            border-radius: 8px 0 0 8px;
            background: #ffffff;
            color: #172033;
            font-size: 14px;
            outline: none;
        }


        .stock-input:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .10);
        }


        .stock-unit {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 80px;
            padding: 0 12px;
            background: #f8fafc;
            border: 1px solid #d9dee7;
            border-left: none;
            border-radius: 0 8px 8px 0;
            color: #6b7280;
            font-size: 12px;
            font-weight: 600;
        }


        .stock-help {
            margin-top: 7px;
            font-size: 11px;
            color: #8b94a3;
        }


        /* =========================================================
           PREVIEW
           ========================================================= */

        .stock-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 20px;
            padding: 14px 15px;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 9px;
        }


        .stock-preview-label {
            font-size: 12px;
            color: #4b5563;
        }


        .stock-preview-value {
            font-size: 17px;
            font-weight: 700;
            color: #2563eb;
        }


        /* =========================================================
           ERROR
           ========================================================= */

        .stock-error {
            margin-bottom: 18px;
            padding: 11px 13px;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 8px;
            color: #b02a37;
            font-size: 12px;
        }


        /* =========================================================
           BUTTONS
           ========================================================= */

        .stock-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 22px;
        }


        .stock-button {
            min-height: 39px;
            padding: 0 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: .15s;
        }


        .stock-button-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            border: 1px solid #d9dee7;
            color: #4b5563;
        }


        .stock-button-cancel:hover {
            background: #f8fafc;
            color: #111827;
        }


        .stock-button-save {
            border: 1px solid #198754;
            background: #198754;
            color: #ffffff;
        }


        .stock-button-save:hover {
            background: #157347;
            border-color: #157347;
        }


        /* =========================================================
           MOBILE
           ========================================================= */

        @media (max-width: 600px) {

            .stock-card-body {
                padding: 17px;
            }

            .stock-card-header {
                padding: 17px;
            }

            .stock-actions {
                flex-direction: column-reverse;
            }

            .stock-button {
                width: 100%;
            }

        }
    </style>

</head>


<body>


    <!-- =========================================================
         UNIVERSAL SIDEBAR
         ========================================================= -->

    <?php require_once $basePath . 'includes/sidebar.php'; ?>


    <div class="main">


        <!-- =====================================================
             UNIVERSAL TOPBAR
             ===================================================== -->

        <?php require_once $basePath . 'includes/topbar.php'; ?>


        <main class="content">


            <div class="stock-page">

                <div class="stock-card">


                    <!-- =================================================
                         HEADER
                         ================================================= -->

                    <div class="stock-card-header">

                        <h1 class="stock-title">
                            Add Stock
                        </h1>

                        <p class="stock-subtitle">
                            Increase the current inventory quantity.
                        </p>

                    </div>


                    <!-- =================================================
                         BODY
                         ================================================= -->

                    <div class="stock-card-body">


                        <?php if ($error !== ''): ?>

                            <div class="stock-error">

                                <?= htmlspecialchars($error) ?>

                            </div>

                        <?php endif; ?>


                        <!-- PRODUCT -->

                        <div class="stock-product-info">

                            <div class="stock-product-name">

                                <?= htmlspecialchars(
                                    $product['product_name']
                                ) ?>

                            </div>


                            <?php if (!empty($product['product_code'])): ?>

                                <div class="stock-product-code">

                                    <?= htmlspecialchars(
                                        $product['product_code']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <div class="stock-current">

                                <span class="stock-current-label">
                                    Current Stock
                                </span>

                                <span class="stock-current-value">

                                    <?= rtrim(
                                        rtrim(
                                            number_format(
                                                $product['stock'],
                                                3
                                            ),
                                            '0'
                                        ),
                                        '.'
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $product['unit']
                                    ) ?>

                                </span>

                            </div>

                        </div>


                        <!-- FORM -->

                        <form
                            method="post"
                            action="add_stock.php?id=<?= (int)$product['id'] ?>">


                            <input
                                type="hidden"
                                name="product_id"
                                value="<?= (int)$product['id'] ?>">


                            <div class="stock-form-group">

                                <label
                                    for="add_stock"
                                    class="stock-label">

                                    Quantity to Add

                                </label>


                                <div class="stock-input-wrapper">

                                    <input
                                        type="number"
                                        id="add_stock"
                                        name="add_stock"
                                        class="stock-input"
                                        min="0.001"
                                        step="0.001"
                                        inputmode="decimal"
                                        placeholder="0.000"
                                        required
                                        autofocus>


                                    <div class="stock-unit">

                                        <?= htmlspecialchars(
                                            $product['unit']
                                        ) ?>

                                    </div>

                                </div>


                                <div class="stock-help">

                                    Enter the quantity received or added to inventory.

                                    Decimal quantities are allowed.

                                </div>

                            </div>


                            <!-- PREVIEW -->

                            <div class="stock-preview">

                                <span class="stock-preview-label">
                                    New Stock
                                </span>

                                <span
                                    class="stock-preview-value"
                                    id="newStockPreview">

                                    <?= rtrim(
                                        rtrim(
                                            number_format(
                                                $product['stock'],
                                                3
                                            ),
                                            '0'
                                        ),
                                        '.'
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $product['unit']
                                    ) ?>

                                </span>

                            </div>


                            <!-- BUTTONS -->

                            <div class="stock-actions">

                                <a
                                    href="index.php"
                                    class="stock-button stock-button-cancel">

                                    Cancel

                                </a>


                                <button
                                    type="submit"
                                    class="stock-button stock-button-save">

                                    + Add Stock

                                </button>

                            </div>


                        </form>


                    </div>

                </div>

            </div>


        </main>

    </div>


    <!-- =========================================================
         LIVE STOCK PREVIEW
         ========================================================= -->

    <script>
        const stockInput =
            document.getElementById('add_stock');

        const newStockPreview =
            document.getElementById('newStockPreview');

        const currentStock =
            <?= (float)$product['stock'] ?>;

        const stockUnit =
            <?= json_encode($product['unit']) ?>;


        function formatStock(value) {

            return Number(value)
                .toFixed(3)
                .replace(/\.?0+$/, '');

        }


        stockInput.addEventListener('input', function() {

            const addStock =
                parseFloat(this.value) || 0;

            const newStock =
                currentStock + addStock;

            newStockPreview.textContent =
                formatStock(newStock) +
                ' ' +
                stockUnit;

        });
    </script>


    <!-- =========================================================
         UNIVERSAL ADMIN JS
         ========================================================= -->

    <script
        src="<?= $basePath ?>assets/js/admin.js"></script>


</body>

</html>
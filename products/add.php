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
$activeMenu = 'inventory';
$currentSection = 'inventory';

/*
|--------------------------------------------------------------------------
| Get Categories
|--------------------------------------------------------------------------
*/
$categories = [];

try {

    $categoryStmt = $pdo->query("
        SELECT
            id,
            category_name
        FROM categories
        ORDER BY category_name
    ");

    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {

    $categories = [];
}

/*
|--------------------------------------------------------------------------
| Form Error
|--------------------------------------------------------------------------
*/
$error = '';

/*
|--------------------------------------------------------------------------
| Detect AJAX Request
|--------------------------------------------------------------------------
*/
$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);

/*
|--------------------------------------------------------------------------
| Previously Selected Category
|--------------------------------------------------------------------------
*/
$selectedCategoryId = isset($_POST['category_id'])
    ? (int)$_POST['category_id']
    : 0;

/*
|--------------------------------------------------------------------------
| Add Product
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code = trim($_POST['product_code'] ?? '');
    $name = trim($_POST['product_name'] ?? '');

    $categoryId = !empty($_POST['category_id'])
        ? (int)$_POST['category_id']
        : null;

    $cost = (float)($_POST['cost_price'] ?? 0);
    $selling = (float)($_POST['selling_price'] ?? 0);
    $stock = (float)($_POST['stock'] ?? 0);
    $reorder = (float)($_POST['reorder_level'] ?? 0);

    $unit = trim($_POST['unit'] ?? 'pc');


    /*
    |--------------------------------------------------------------------------
    | Validate Product Name
    |--------------------------------------------------------------------------
    */
    if ($name === '') {

        $error = 'Product name is required.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Prices and Quantities
    |--------------------------------------------------------------------------
    */ elseif (
        $cost < 0 ||
        $selling < 0 ||
        $stock < 0 ||
        $reorder < 0
    ) {

        $error = 'Prices and quantities cannot be negative.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Selling Price
    |--------------------------------------------------------------------------
    */ elseif ($selling < $cost) {

        $error = 'Selling price is lower than cost price. Please check the prices.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Unit
    |--------------------------------------------------------------------------
    */ elseif ($unit === '') {

        $error = 'Unit is required.';
    }


    /*
    |--------------------------------------------------------------------------
    | Save Product
    |--------------------------------------------------------------------------
    */ else {

        try {

            /*
            | Start Transaction
            | This keeps the product and its initial stock movement together.
            */
            $pdo->beginTransaction();


            /*
            | Insert Product
            */
            $stmt = $pdo->prepare("
                INSERT INTO products
                    (
                        product_code,
                        product_name,
                        category_id,
                        cost_price,
                        selling_price,
                        stock,
                        reorder_level,
                        unit
                    )
                VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
            ");

            $stmt->execute([
                $code !== '' ? $code : null,
                $name,
                $categoryId,
                $cost,
                $selling,
                $stock,
                $reorder,
                $unit
            ]);


            /*
            | Get Newly Created Product ID
            */
            $productId = (int)$pdo->lastInsertId();


            /*
            | Record Initial Stock Movement
            */
            if ($stock > 0) {

                $move = $pdo->prepare("
                    INSERT INTO stock_movements
                        (
                            product_id,
                            movement_type,
                            quantity,
                            remarks
                        )
                    VALUES
                        (
                            ?,
                            'stock_in',
                            ?,
                            'Initial stock'
                        )
                ");

                $move->execute([
                    $productId,
                    $stock
                ]);
            }


            /*
            | Commit Transaction
            */
            $pdo->commit();


            /*
            | AJAX Response
            */
            if ($isAjax) {

                header('Content-Type: application/json; charset=utf-8');

                echo json_encode([
                    'success' => true,
                    'message' => 'Product added successfully.',
                    'product_id' => $productId
                ]);

                exit;
            }


            /*
            | Normal Browser Redirect
            */
            header('Location: index.php?added=1');
            exit;
        } catch (PDOException $e) {

            /*
            | Rollback When Needed
            */
            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            /*
            | Handle Duplicate Product Code
            */
            if ($e->getCode() === '23000') {

                $error = 'Product code already exists.';
            } else {

                $error = 'Unable to save product.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| AJAX Error Response
|--------------------------------------------------------------------------
*/
if ($isAjax && $error !== '') {

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => false,
        'message' => $error
    ]);

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

?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>
        Add Product - Arnold & Jackie's Store
    </title>


    <!-- BOOTSTRAP -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- UNIVERSAL ADMIN CSS -->
    <link
        href="<?= $basePath ?>assets/css/admin.css"
        rel="stylesheet">


    <!-- PAGE-SPECIFIC CSS -->
    <style>
        /* =========================================================
           ADD PRODUCT PAGE
           ========================================================= */

        .product-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .product-page-title {
            font-size: 25px;
            font-weight: 700;
            margin: 0 0 4px;
            color: #172033;
        }

        .product-page-subtitle {
            color: #7b8494;
            font-size: 13px;
            margin: 0;
        }

        .product-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .product-header-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            background: #ffffff;
            color: #374151;
            border: 1px solid #d9dee7;
            transition: .15s;
        }

        .product-header-button:hover {
            background: #f8fafc;
            color: #111827;
        }

        .product-form-card {
            width: 100%;
            max-width: 850px;
            margin-left: auto;
            margin-right: auto;
        }

        .product-form-header {
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f4;
        }

        .product-form-title {
            font-size: 15px;
            font-weight: 700;
            color: #202938;
            margin: 0;
        }

        .product-form-subtitle {
            font-size: 11px;
            color: #8b94a3;
            margin-top: 3px;
        }

        .product-form-body {
            padding: 22px 20px;
        }

        .product-field {
            margin-bottom: 0;
        }

        .product-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 7px;
        }

        .product-label-required::after {
            content: " *";
            color: #dc3545;
        }

        .product-label-optional {
            font-weight: 400;
            color: #8b94a3;
        }

        .product-input,
        .product-select {
            width: 100%;
            height: 40px;
            padding: 8px 11px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #ffffff;
            color: #172033;
            font-size: 13px;
            outline: none;
            transition:
                border-color .15s ease,
                box-shadow .15s ease,
                background-color .15s ease;
            box-sizing: border-box;
        }

        .product-input:hover,
        .product-select:hover {
            border-color: #cbd5e1;
        }

        .product-input:focus,
        .product-select:focus {
            border-color: #86b7fe;
            background: #ffffff;
            box-shadow:
                0 0 0 3px rgba(13, 110, 253, .10);
        }

        .product-select {
            cursor: pointer;
        }

        /* FIX: Remove the hover spinner arrows on number inputs */
        .product-input[type="number"] {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .product-input[type="number"]::-webkit-outer-spin-button,
        .product-input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .product-input-group {
            display: flex;
            align-items: stretch;
            width: 100%;
        }

        .product-input-prefix {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 10px;
            border: 1px solid #d9dee7;
            border-right: none;
            border-radius: 8px 0 0 8px;
            background: #f8fafc;
            color: #6b7280;
            font-size: 13px;
            font-weight: 600;
            box-sizing: border-box;
        }

        .product-input-group .product-input {
            border-radius: 0 8px 8px 0;
        }

        .product-input-group .product-input-prefix+.product-input {
            width: 100%;
        }

        .product-category-group {
            display: flex;
            align-items: stretch;
            gap: 8px;
        }

        .product-category-group .product-select {
            flex: 1;
        }

        .product-add-category {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 80px;
            height: 40px;
            padding: 0 12px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #ffffff;
            color: #374151;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition:
                background-color .15s ease,
                color .15s ease,
                border-color .15s ease;
        }

        .product-add-category:hover {
            background: #f8fafc;
            color: #111827;
            border-color: #cbd5e1;
        }

        .product-profit-input {
            background: #f8fafc;
            color: #198754;
            font-weight: 600;
        }

        .product-help {
            margin-top: 6px;
            font-size: 11px;
            color: #8b94a3;
        }

        .product-error {
            width: 100%;
            max-width: 850px;
            margin: 0 auto 12px;
            padding: 11px 13px;
            border: 1px solid #fecaca;
            border-radius: 9px;
            background: #fff1f2;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 600;
        }

        .product-form-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #edf0f4;
        }

        .product-save-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 18px;
            border: none;
            border-radius: 8px;
            background: #0d6efd;
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color .15s ease;
        }

        /* FIX: removed transform: translateY(-1px) so the button no longer jumps on hover */
        .product-save-button:hover {
            background: #0b5ed7;
        }

        .product-cancel-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 18px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #ffffff;
            color: #374151;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition:
                background-color .15s ease,
                color .15s ease,
                border-color .15s ease;
        }

        .product-cancel-button:hover {
            background: #f8fafc;
            color: #111827;
            border-color: #cbd5e1;
        }

        /* FIX: Stop the form card from moving/resizing when the mouse is over it.
           admin.css likely has a generic hover rule (e.g. .card:hover) that
           applies transform/scale. These overrides neutralize it for this page. */
        .product-form-card,
        .product-form-card:hover,
        .product-form-header,
        .product-form-header:hover,
        .product-form-body,
        .product-form-body:hover,
        .product-field,
        .product-field:hover,
        .product-input,
        .product-input:hover,
        .product-input:focus,
        .product-input-group,
        .product-input-group:hover,
        .product-input-prefix,
        .product-input-prefix:hover {
            transform: none !important;
            translate: none !important;
            scale: none !important;
            animation: none !important;
        }

        .product-form-card {
            transition: none !important;
        }

        @media (max-width: 768px) {

            .product-page-header {
                flex-direction: column;
            }

            .product-header-actions {
                width: 100%;
            }

            .product-header-button {
                width: 100%;
            }

            .product-form-body {
                padding: 18px 16px;
            }

            .product-category-group {
                flex-direction: column;
            }

            .product-add-category {
                width: 100%;
            }

            .product-form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .product-save-button,
            .product-cancel-button {
                width: 100%;
            }

        }
    </style>

</head>


<body>


    <!-- UNIVERSAL SIDEBAR -->
    <?php require_once $basePath . 'includes/sidebar.php'; ?>


    <div class="main">


        <!-- UNIVERSAL TOPBAR -->
        <?php require_once $basePath . 'includes/topbar.php'; ?>


        <main class="content">


            <!-- PAGE HEADER -->
            <div class="product-page-header">

                <div>

                    <h1 class="product-page-title">
                        Add Product
                    </h1>

                    <p class="product-page-subtitle">
                        Add a new product to your store inventory.
                    </p>

                </div>


                <div class="product-header-actions">

                    <a
                        href="index.php"
                        class="product-header-button">
                        ← Back to Products
                    </a>

                </div>

            </div>


            <!-- ERROR MESSAGE -->
            <?php if ($error): ?>

                <div class="product-error">

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- PRODUCT FORM -->
            <div class="product-form-card">


                <div class="product-form-header">

                    <div class="product-form-title">
                        Product Information
                    </div>

                    <div class="product-form-subtitle">
                        Enter the product details, pricing and initial stock.
                    </div>

                </div>


                <div class="product-form-body">


                    <form
                        method="post"
                        action="add.php">


                        <div class="row g-3">


                            <!-- PRODUCT CODE -->
                            <div class="col-md-4">

                                <div class="product-field">

                                    <label
                                        for="product_code"
                                        class="product-label">

                                        Product Code

                                        <span class="product-label-optional">
                                            (optional)
                                        </span>

                                    </label>


                                    <input
                                        type="text"
                                        id="product_code"
                                        name="product_code"
                                        value="<?= htmlspecialchars(
                                                    $_POST['product_code'] ?? '',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                        class="product-input"
                                        placeholder="e.g. COKE15"
                                        autocomplete="off">

                                </div>

                            </div>


                            <!-- PRODUCT NAME -->
                            <div class="col-md-8">

                                <div class="product-field">

                                    <label
                                        for="product_name"
                                        class="product-label product-label-required">

                                        Product Name

                                    </label>


                                    <input
                                        type="text"
                                        id="product_name"
                                        name="product_name"
                                        value="<?= htmlspecialchars(
                                                    $_POST['product_name'] ?? '',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                        class="product-input"
                                        required
                                        placeholder="e.g. Coca-Cola 1.5L">

                                </div>

                            </div>


                            <!-- CATEGORY -->
                            <div class="col-md-6">

                                <div class="product-field">

                                    <label
                                        for="category_id"
                                        class="product-label">

                                        Category

                                    </label>


                                    <div class="product-category-group">

                                        <select
                                            name="category_id"
                                            id="category_id"
                                            class="product-select">

                                            <option value="">
                                                Select Category
                                            </option>


                                            <?php foreach ($categories as $c): ?>

                                                <?php
                                                $categoryId = (int)$c['id'];
                                                $isSelected = (
                                                    $selectedCategoryId === $categoryId
                                                );
                                                ?>

                                                <option
                                                    value="<?= $categoryId ?>"
                                                    <?= $isSelected ? 'selected' : '' ?>>

                                                    <?= htmlspecialchars(
                                                        $c['category_name'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>


                                        <a
                                            href="../categories/add.php"
                                            class="product-add-category">

                                            + Add

                                        </a>

                                    </div>

                                </div>

                            </div>


                            <!-- UNIT -->
                            <div class="col-md-6">

                                <div class="product-field">

                                    <label
                                        for="unit"
                                        class="product-label product-label-required">

                                        Unit

                                    </label>


                                    <input
                                        type="text"
                                        id="unit"
                                        name="unit"
                                        value="<?= htmlspecialchars(
                                                    $_POST['unit'] ?? 'pc',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                        class="product-input"
                                        required
                                        placeholder="pc">


                                    <div class="product-help">
                                        Examples: pc, pack, box, bottle, kg.
                                    </div>

                                </div>

                            </div>


                            <!-- COST PRICE -->
                            <div class="col-md-4">

                                <div class="product-field">

                                    <label
                                        for="cost"
                                        class="product-label product-label-required">

                                        Cost Price

                                    </label>


                                    <div class="product-input-group">

                                        <span class="product-input-prefix">
                                            ₱
                                        </span>


                                        <input
                                            name="cost_price"
                                            id="cost"
                                            type="number"
                                            inputmode="decimal"
                                            step="0.01"
                                            min="0"
                                            value="<?= htmlspecialchars(
                                                        $_POST['cost_price'] ?? '0',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                            class="product-input"
                                            required>

                                    </div>

                                </div>

                            </div>


                            <!-- SELLING PRICE -->
                            <div class="col-md-4">

                                <div class="product-field">

                                    <label
                                        for="selling"
                                        class="product-label product-label-required">

                                        Selling Price

                                    </label>


                                    <div class="product-input-group">

                                        <span class="product-input-prefix">
                                            ₱
                                        </span>


                                        <input
                                            name="selling_price"
                                            id="selling"
                                            type="number"
                                            inputmode="decimal"
                                            step="0.01"
                                            min="0"
                                            value="<?= htmlspecialchars(
                                                        $_POST['selling_price'] ?? '0',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                            class="product-input"
                                            required>

                                    </div>

                                </div>

                            </div>


                            <!-- PROFIT -->
                            <div class="col-md-4">

                                <div class="product-field">

                                    <label
                                        for="profit"
                                        class="product-label">

                                        Profit / Unit

                                    </label>


                                    <div class="product-input-group">

                                        <span class="product-input-prefix">
                                            ₱
                                        </span>


                                        <input
                                            id="profit"
                                            type="text"
                                            class="product-input product-profit-input"
                                            value="0.00"
                                            readonly>

                                    </div>

                                </div>

                            </div>


                            <!-- INITIAL STOCK -->
                            <div class="col-md-6">

                                <div class="product-field">

                                    <label
                                        for="stock"
                                        class="product-label product-label-required">

                                        Initial Stock

                                    </label>


                                    <input
                                        name="stock"
                                        id="stock"
                                        type="number"
                                        inputmode="decimal"
                                        step="0.001"
                                        min="0"
                                        value="<?= htmlspecialchars(
                                                    $_POST['stock'] ?? '0',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                        class="product-input"
                                        required>


                                    <div class="product-help">
                                        Decimal quantities are allowed.
                                    </div>

                                </div>

                            </div>


                            <!-- REORDER LEVEL -->
                            <div class="col-md-6">

                                <div class="product-field">

                                    <label
                                        for="reorder_level"
                                        class="product-label">

                                        Reorder Level

                                    </label>


                                    <input
                                        name="reorder_level"
                                        id="reorder_level"
                                        type="number"
                                        inputmode="decimal"
                                        step="0.001"
                                        min="0"
                                        value="<?= htmlspecialchars(
                                                    $_POST['reorder_level'] ?? '0',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                        class="product-input">


                                    <div class="product-help">
                                        The system will show a low-stock notification
                                        when stock reaches this level.
                                    </div>

                                </div>

                            </div>


                        </div>


                        <!-- FORM ACTIONS -->
                        <div class="product-form-actions">

                            <button
                                type="submit"
                                class="product-save-button">

                                Save Product

                            </button>


                            <a
                                href="index.php"
                                class="product-cancel-button">

                                Cancel

                            </a>

                        </div>


                    </form>


                </div>

            </div>


        </main>

    </div>


    <!-- BOOTSTRAP JS -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>


    <!-- UNIVERSAL ADMIN JS -->
    <script
        src="<?= $basePath ?>assets/js/admin.js">
    </script>


    <!-- PROFIT CALCULATOR + NUMBER INPUT FIXES -->
    <script>
        function updateProfit() {

            const costInput = document.getElementById('cost');
            const sellingInput = document.getElementById('selling');
            const profitInput = document.getElementById('profit');

            if (!costInput || !sellingInput || !profitInput) {
                return;
            }

            const cost = parseFloat(costInput.value) || 0;
            const selling = parseFloat(sellingInput.value) || 0;

            profitInput.value = (selling - cost).toFixed(2);
        }

        const costInput = document.getElementById('cost');
        const sellingInput = document.getElementById('selling');

        if (costInput) {
            costInput.addEventListener('input', updateProfit);
        }

        if (sellingInput) {
            sellingInput.addEventListener('input', updateProfit);
        }

        updateProfit();


        // FIX: Prevent the mouse wheel from changing number input values
        document.querySelectorAll('input[type="number"]').forEach(function(input) {
            input.addEventListener('wheel', function() {
                this.blur();
            }, {
                passive: true
            });
        });
    </script>


</body>

</html>
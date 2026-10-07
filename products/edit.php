<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

$basePath = '../';
$activeMenu = 'inventory';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ?
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([$id]);

$product = $stmt->fetch();

if (!$product) {
    exit('Product not found.');
}

$categories = $pdo->query("
    SELECT id, category_name
    FROM categories
    ORDER BY category_name
")->fetchAll();

$error = '';

/*
|--------------------------------------------------------------------------
| Update Product
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
    $reorder = (float)($_POST['reorder_level'] ?? 0);

    $unit = trim($_POST['unit'] ?? 'pc');

    if ($name === '') {

        $error = 'Product name is required.';
    } elseif ($cost < 0 || $selling < 0 || $reorder < 0) {

        $error = 'Values cannot be negative.';
    } elseif ($selling < $cost) {

        $error = 'Selling price is lower than cost price. Please check the prices.';
    } else {

        try {

            $update = $pdo->prepare("
                UPDATE products
                SET
                    product_code = ?,
                    product_name = ?,
                    category_id = ?,
                    cost_price = ?,
                    selling_price = ?,
                    reorder_level = ?,
                    unit = ?
                WHERE id = ?
            ");

            $update->execute([
                $code !== '' ? $code : null,
                $name,
                $categoryId,
                $cost,
                $selling,
                $reorder,
                $unit,
                $id
            ]);

            header('Location: index.php?updated=1');
            exit;
        } catch (PDOException $e) {

            $error = $e->getCode() === '23000'
                ? 'Product code already exists.'
                : 'Unable to update product.';
        }
    }
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
| Current Values
|--------------------------------------------------------------------------
|
| If validation fails, display the values submitted by the user.
| Otherwise, display the original product values.
|
*/

$formCode = $_POST['product_code']
    ?? ($product['product_code'] ?? '');

$formName = $_POST['product_name']
    ?? ($product['product_name'] ?? '');

$formCategoryId = $_POST['category_id']
    ?? ($product['category_id'] ?? '');

$formUnit = $_POST['unit']
    ?? ($product['unit'] ?? 'pc');

$formReorderLevel = $_POST['reorder_level']
    ?? ($product['reorder_level'] ?? 0);

$formCostPrice = $_POST['cost_price']
    ?? ($product['cost_price'] ?? 0);

$formSellingPrice = $_POST['selling_price']
    ?? ($product['selling_price'] ?? 0);

?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Edit Product | Arnold & Jackie's Store</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">

    <style>
        .product-form-card {
            background: #ffffff;
            border: 1px solid #e7ebf0;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .product-form-header {
            padding: 20px 24px;
            border-bottom: 1px solid #edf0f4;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .product-form-header h2 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: #172033;
        }

        .product-form-header p {
            margin: 4px 0 0;
            font-size: 12px;
            color: #8b94a3;
        }

        .product-form-body {
            padding: 25px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 7px;
        }

        .form-control,
        .form-select {
            min-height: 42px;
            border-color: #dfe4ea;
            border-radius: 8px;
            font-size: 13px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, .08);
        }

        .form-control[readonly] {
            background: #f8fafc;
            color: #6b7280;
        }

        .form-section-title {
            font-size: 13px;
            font-weight: 700;
            color: #273142;
            margin-bottom: 16px;
        }

        .form-section {
            padding-bottom: 24px;
            margin-bottom: 24px;
            border-bottom: 1px solid #edf0f4;
        }

        .form-section:last-of-type {
            border-bottom: 0;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .price-preview {
            margin-top: 7px;
            font-size: 11px;
            color: #8b94a3;
        }

        .profit-positive {
            color: #16a34a;
            font-weight: 600;
        }

        .profit-zero {
            color: #6b7280;
            font-weight: 600;
        }

        .product-actions {
            padding-top: 22px;
            border-top: 1px solid #edf0f4;
            display: flex;
            gap: 8px;
        }

        .product-actions .btn {
            min-height: 40px;
            border-radius: 8px;
            font-size: 13px;
            padding: 0 18px;
        }

        .current-stock-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            padding: 11px 13px;
            font-size: 13px;
            color: #374151;
            min-height: 42px;
            display: flex;
            align-items: center;
        }

        .current-stock-label {
            font-size: 11px;
            color: #8b94a3;
            margin-right: 7px;
        }

        .alert {
            border-radius: 9px;
            font-size: 13px;
        }

        @media (max-width: 767px) {

            .product-form-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .product-form-body {
                padding: 18px;
            }

            .product-actions {
                flex-direction: column;
            }

            .product-actions .btn {
                width: 100%;
            }
        }
    </style>

</head>

<body>

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="main">

        <?php require_once $basePath . 'includes/topbar.php'; ?>

        <main class="content">

            <div class="d-flex justify-content-between align-items-center mb-1">

                <div>

                    <div class="page-title">
                        Edit Product
                    </div>

                    <div class="page-subtitle">
                        Update product information, pricing and inventory settings.
                    </div>

                </div>

                <a
                    href="index.php"
                    class="btn btn-outline-secondary btn-sm">
                    ← Back to Products
                </a>

            </div>

            <div class="product-form-card">

                <div class="product-form-header">

                    <div>

                        <h2>
                            Product Information
                        </h2>

                        <p>
                            Modify the details of this product below.
                        </p>

                    </div>

                </div>

                <div class="product-form-body">

                    <?php if ($error): ?>

                        <div class="alert alert-danger mb-4">
                            <?= htmlspecialchars($error) ?>
                        </div>

                    <?php endif; ?>


                    <form method="post">

                        <input
                            type="hidden"
                            name="id"
                            value="<?= $id ?>">


                        <!-- Product Details -->

                        <div class="form-section">

                            <div class="form-section-title">
                                Product Details
                            </div>

                            <div class="row g-3">

                                <div class="col-md-4">

                                    <label class="form-label">
                                        Product Code
                                    </label>

                                    <input
                                        type="text"
                                        name="product_code"
                                        class="form-control"
                                        value="<?= htmlspecialchars($formCode) ?>"
                                        placeholder="Optional">

                                </div>


                                <div class="col-md-8">

                                    <label class="form-label">
                                        Product Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        name="product_name"
                                        class="form-control"
                                        value="<?= htmlspecialchars($formName) ?>"
                                        placeholder="Enter product name"
                                        required>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Category
                                    </label>

                                    <select
                                        name="category_id"
                                        class="form-select">

                                        <option value="">
                                            -- Select Category --
                                        </option>

                                        <?php foreach ($categories as $c): ?>

                                            <option
                                                value="<?= $c['id'] ?>"
                                                <?= (string)$formCategoryId === (string)$c['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['category_name']) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-md-3">

                                    <label class="form-label">
                                        Unit
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        name="unit"
                                        class="form-control"
                                        value="<?= htmlspecialchars($formUnit) ?>"
                                        placeholder="pc"
                                        required>

                                </div>


                                <div class="col-md-3">

                                    <label class="form-label">
                                        Reorder Level
                                    </label>

                                    <input
                                        type="number"
                                        name="reorder_level"
                                        step="0.001"
                                        min="0"
                                        class="form-control"
                                        value="<?= htmlspecialchars($formReorderLevel) ?>">

                                    <div class="price-preview">
                                        Low-stock notification level
                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- Pricing -->

                        <div class="form-section">

                            <div class="form-section-title">
                                Pricing
                            </div>

                            <div class="row g-3">

                                <div class="col-md-4">

                                    <label class="form-label">
                                        Cost Price
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        id="cost_price"
                                        type="number"
                                        name="cost_price"
                                        step="0.01"
                                        min="0"
                                        class="form-control"
                                        value="<?= htmlspecialchars($formCostPrice) ?>"
                                        required>

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        Selling Price
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        id="selling_price"
                                        type="number"
                                        name="selling_price"
                                        step="0.01"
                                        min="0"
                                        class="form-control"
                                        value="<?= htmlspecialchars($formSellingPrice) ?>"
                                        required>

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        Profit per Unit
                                    </label>

                                    <div
                                        id="profit_display"
                                        class="current-stock-box">
                                        ₱0.00
                                    </div>

                                    <div
                                        id="profit_description"
                                        class="price-preview">
                                        Selling price minus cost price
                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- Current Inventory -->

                        <div class="form-section">

                            <div class="form-section-title">
                                Current Inventory
                            </div>

                            <div class="row g-3">

                                <div class="col-md-4">

                                    <label class="form-label">
                                        Current Stock
                                    </label>

                                    <div class="current-stock-box">

                                        <span class="current-stock-label">
                                            Stock:
                                        </span>

                                        <strong>
                                            <?= rtrim(
                                                rtrim(
                                                    number_format(
                                                        (float)$product['stock'],
                                                        3
                                                    ),
                                                    '0'
                                                ),
                                                '.'
                                            ) ?>

                                            <?= htmlspecialchars($product['unit']) ?>
                                        </strong>

                                    </div>

                                </div>

                                <div class="col-md-8">

                                    <div class="price-preview mt-4">

                                        To change the current stock quantity,
                                        use the inventory stock-in or stock-out
                                        functions instead of editing the product
                                        details.

                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- Actions -->

                        <div class="product-actions">

                            <button
                                type="submit"
                                class="btn btn-primary">
                                Save Changes
                            </button>

                            <a
                                href="index.php"
                                class="btn btn-outline-secondary">
                                Cancel
                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </main>

    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="<?= $basePath ?>assets/js/admin.js"></script>


    <script>
        function updateProfit() {

            const costInput = document.getElementById('cost_price');
            const sellingInput = document.getElementById('selling_price');

            const profitDisplay = document.getElementById('profit_display');
            const profitDescription = document.getElementById('profit_description');

            if (!costInput || !sellingInput || !profitDisplay) {
                return;
            }

            const cost = parseFloat(costInput.value) || 0;
            const selling = parseFloat(sellingInput.value) || 0;

            const profit = selling - cost;

            profitDisplay.textContent =
                '₱' + profit.toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

            profitDisplay.classList.remove(
                'profit-positive',
                'profit-zero'
            );

            if (profit > 0) {

                profitDisplay.classList.add('profit-positive');

                profitDescription.textContent =
                    'Estimated profit per unit';

            } else {

                profitDisplay.classList.add('profit-zero');

                if (profit === 0) {

                    profitDescription.textContent =
                        'No profit';

                } else {

                    profitDescription.textContent =
                        'Selling price is below cost price';

                }
            }
        }


        document.addEventListener('DOMContentLoaded', function() {

            const costInput =
                document.getElementById('cost_price');

            const sellingInput =
                document.getElementById('selling_price');

            if (costInput) {
                costInput.addEventListener('input', updateProfit);
            }

            if (sellingInput) {
                sellingInput.addEventListener('input', updateProfit);
            }

            updateProfit();

        });
    </script>

</body>

</html>
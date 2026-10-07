<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

/*
|--------------------------------------------------------------------------
| UNIVERSAL ADMIN DESIGN
|--------------------------------------------------------------------------
*/

$basePath = '../';
$activeMenu = 'pos';
$currentSection = 'pos';

/*
|--------------------------------------------------------------------------
| PRODUCTS
|--------------------------------------------------------------------------
*/

$products = $pdo->query("
    SELECT
        id,
        product_name,
        product_code,
        selling_price,
        stock,
        unit
    FROM products
    WHERE is_active = 1
      AND stock > 0
    ORDER BY product_name
")->fetchAll();

/*
|--------------------------------------------------------------------------
| LOW STOCK NOTIFICATIONS
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
        New Sale - Arnold & Jackie's Store
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">

    <style>
        /* =========================================================
           POS LAYOUT
        ========================================================= */

        .pos-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 390px;
            gap: 18px;
            align-items: start;
        }

        .section-card {
            background: #fff;
            border: 1px solid #e8ebf0;
            border-radius: 14px;
            box-shadow: 0 2px 7px rgba(0, 0, 0, .035);
            overflow: hidden;
        }

        .section-header {
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 15px;
            font-weight: 700;
            margin: 0;
        }

        .section-subtitle {
            font-size: 12px;
            color: #8b94a3;
            margin-top: 3px;
        }

        .section-body {
            padding: 20px;
        }


        /* =========================================================
           SEARCH
        ========================================================= */

        .search-wrapper {
            position: relative;
            margin-bottom: 18px;
        }

        .search-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa2af;
            width: 17px;
            height: 17px;
            pointer-events: none;
        }

        #search {
            width: 100%;
            height: 44px;
            border: 1px solid #dfe3e8;
            border-radius: 9px;
            padding: 0 14px 0 42px;
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #172033;
            outline: none;
            background: #fff;
        }

        #search:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .08);
        }


        /* =========================================================
           PRODUCT GRID
        ========================================================= */

        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .product-item {
            min-width: 0;
        }

        .product-card {
            cursor: pointer;
            border: 1px solid #e8ebf0;
            border-radius: 11px;
            padding: 15px;
            min-height: 130px;
            background: #fff;
            transition: .15s;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 5px 15px rgba(15, 23, 42, .06);
            transform: translateY(-1px);
        }

        .product-name {
            font-size: 13px;
            font-weight: 600;
            line-height: 1.35;
            color: #202938;
        }

        .product-code {
            color: #8b94a3;
            font-size: 10px;
            margin-top: 4px;
        }

        .product-price {
            font-size: 16px;
            font-weight: 700;
            margin-top: auto;
            padding-top: 12px;
            color: #182033;
        }

        .stock-label {
            font-size: 11px;
            margin-top: 5px;
        }

        .stock-good {
            color: #16a34a;
        }

        .stock-low {
            color: #f59e0b;
        }


        /* =========================================================
           CART
        ========================================================= */

        .cart-panel {
            position: sticky;
            top: 20px;
        }

        .cart-body {
            padding: 0 20px 20px;
        }

        #cart {
            max-height: 350px;
            overflow-y: auto;
        }

        .empty-cart {
            text-align: center;
            padding: 45px 15px;
            color: #8b94a3;
        }

        .empty-cart-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #9aa2af;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-row {
            padding: 14px 0;
            border-bottom: 1px solid #f0f2f5;
        }

        .cart-product-name {
            font-size: 12px;
            font-weight: 600;
            color: #202938;
            padding-right: 8px;
        }

        .remove-btn {
            border: 0;
            background: transparent;
            color: #dc3545;
            font-size: 10px;
            padding: 0;
            cursor: pointer;
        }

        .remove-btn:hover {
            text-decoration: underline;
        }

        .cart-meta {
            font-size: 10px;
            color: #8b94a3;
            margin-top: 4px;
        }

        .qty-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 9px;
        }

        .qty-input {
            width: 85px;
            height: 34px;
            border: 1px solid #dfe3e8;
            border-radius: 7px;
            font-size: 12px;
            padding: 5px 8px;
            outline: none;
        }

        .qty-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .06);
        }

        .line-total {
            margin-left: auto;
            font-size: 12px;
            font-weight: 700;
            color: #182033;
        }


        /* =========================================================
           CART SUMMARY
        ========================================================= */

        .cart-summary {
            border-top: 1px solid #edf0f4;
            margin-top: 5px;
            padding-top: 18px;
        }

        .total-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .total-label {
            font-size: 12px;
            color: #7b8494;
        }

        .total-value {
            font-size: 25px;
            font-weight: 700;
            color: #182033;
        }

        .form-label-modern {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .modern-input,
        .modern-select {
            width: 100%;
            height: 42px;
            border: 1px solid #dfe3e8;
            border-radius: 8px;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #172033;
            padding: 0 12px;
            background: #fff;
            outline: none;
        }

        .modern-input:focus,
        .modern-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .06);
        }

        .change-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #edf0f4;
            border-radius: 8px;
            padding: 11px 13px;
            margin-top: 12px;
        }

        .change-label {
            font-size: 11px;
            color: #7b8494;
        }

        .change-value {
            font-size: 14px;
            font-weight: 700;
            color: #182033;
        }


        /* =========================================================
           BUTTONS
        ========================================================= */

        .checkout-btn {
            height: 44px;
            border: 0;
            border-radius: 8px;
            background: #0d6efd;
            color: white;
            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: 600;
            width: 100%;
            margin-top: 14px;
            cursor: pointer;
            transition: .15s;
        }

        .checkout-btn:hover {
            background: #0b5ed7;
        }

        .checkout-btn:disabled {
            opacity: .65;
            cursor: not-allowed;
        }

        .clear-btn {
            height: 40px;
            border: 1px solid #dfe3e8;
            border-radius: 8px;
            background: white;
            color: #4b5563;
            font-family: Arial, sans-serif;
            font-size: 11px;
            font-weight: 600;
            width: 100%;
            margin-top: 8px;
            cursor: pointer;
        }

        .clear-btn:hover {
            background: #f8fafc;
        }


        /* =========================================================
           MESSAGE
        ========================================================= */

        #message {
            margin-bottom: 18px;
        }

        .modern-alert {
            border-radius: 9px;
            font-size: 12px;
            padding: 12px 14px;
            border: 1px solid transparent;
        }

        .alert-warning {
            background: #fff8e6;
            border-color: #ffe8a3;
            color: #8a6500;
        }

        .alert-danger {
            background: #fff1f2;
            border-color: #fecdd3;
            color: #b4232f;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1200px) {

            .product-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

        }

        @media (max-width: 991px) {

            .pos-grid {
                grid-template-columns: 1fr;
            }

            .cart-panel {
                position: static;
            }

        }

        @media (max-width: 768px) {

            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 576px) {

            .product-grid {
                grid-template-columns: 1fr 1fr;
                gap: 9px;
            }

            .product-card {
                min-height: 120px;
                padding: 12px;
            }

            .product-name {
                font-size: 12px;
            }

            .product-price {
                font-size: 14px;
            }

            .total-value {
                font-size: 21px;
            }

        }
    </style>

</head>

<body>


    <?php

    /*
|--------------------------------------------------------------------------
| UNIVERSAL SIDEBAR
|--------------------------------------------------------------------------
*/

    require_once $basePath . 'includes/sidebar.php';

    ?>


    <div class="main">


        <?php

        /*
    |--------------------------------------------------------------------------
    | UNIVERSAL TOPBAR
    |--------------------------------------------------------------------------
    */

        require_once $basePath . 'includes/topbar.php';

        ?>


        <!-- =========================================================
         PAGE CONTENT
    ========================================================== -->

        <main class="content">


            <div class="page-title">
                New Sale
            </div>

            <div class="page-subtitle">
                Select products and process a new store transaction.
            </div>


            <!-- MESSAGE -->

            <div id="message"></div>


            <!-- =====================================================
             POS GRID
        ====================================================== -->

            <div class="pos-grid">


                <!-- =================================================
                 PRODUCTS
            ================================================== -->

                <section class="section-card">


                    <div class="section-header">

                        <div>

                            <h2 class="section-title">
                                Products
                            </h2>

                            <div class="section-subtitle">

                                <?= count($products) ?>

                                product<?= count($products) == 1 ? '' : 's' ?>

                                available

                            </div>

                        </div>

                    </div>


                    <div class="section-body">


                        <!-- SEARCH -->

                        <div class="search-wrapper">

                            <svg
                                class="search-icon"
                                viewBox="0 0 24 24"
                                fill="none">

                                <circle
                                    cx="11"
                                    cy="11"
                                    r="7"
                                    stroke="currentColor"
                                    stroke-width="1.8" />

                                <path
                                    d="M16.5 16.5L21 21"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round" />

                            </svg>


                            <input
                                id="search"
                                type="text"
                                placeholder="Search product by name or code..."
                                autocomplete="off">

                        </div>


                        <!-- PRODUCT GRID -->

                        <div
                            class="product-grid"
                            id="productList">


                            <?php foreach ($products as $p): ?>

                                <?php

                                $stock = (float)$p['stock'];

                                $stockClass =
                                    $stock <= 5
                                    ? 'stock-low'
                                    : 'stock-good';

                                ?>


                                <div
                                    class="product-item"
                                    data-name="<?= htmlspecialchars(
                                                    strtolower(
                                                        $p['product_name'] . ' ' .
                                                            $p['product_code']
                                                    )
                                                ) ?>">


                                    <div
                                        class="product-card"
                                        onclick='addProduct(<?= json_encode(
                                                                [
                                                                    "id" =>
                                                                    (int)$p["id"],

                                                                    "name" =>
                                                                    $p["product_name"],

                                                                    "price" =>
                                                                    (float)$p["selling_price"],

                                                                    "stock" =>
                                                                    $stock,

                                                                    "unit" =>
                                                                    $p["unit"]
                                                                ],
                                                                JSON_HEX_TAG |
                                                                    JSON_HEX_APOS |
                                                                    JSON_HEX_QUOT |
                                                                    JSON_HEX_AMP
                                                            ) ?>)'>


                                        <div class="product-name">

                                            <?= htmlspecialchars(
                                                $p['product_name']
                                            ) ?>

                                        </div>


                                        <?php if (!empty($p['product_code'])): ?>

                                            <div class="product-code">

                                                <?= htmlspecialchars(
                                                    $p['product_code']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>


                                        <div class="product-price">

                                            ₱<?= number_format(
                                                    $p['selling_price'],
                                                    2
                                                ) ?>

                                        </div>


                                        <div
                                            class="
                                            stock-label
                                            <?= $stockClass ?>
                                        ">

                                            Stock:

                                            <?= rtrim(
                                                rtrim(
                                                    number_format(
                                                        $stock,
                                                        3
                                                    ),
                                                    '0'
                                                ),
                                                '.'
                                            ) ?>

                                            <?= htmlspecialchars(
                                                $p['unit']
                                            ) ?>

                                        </div>


                                    </div>

                                </div>

                            <?php endforeach; ?>


                            <?php if (!$products): ?>

                                <div
                                    style="grid-column:1/-1;"
                                    class="empty-cart">

                                    <div class="empty-cart-icon">

                                        <svg
                                            width="20"
                                            height="20"
                                            viewBox="0 0 24 24"
                                            fill="none">

                                            <path
                                                d="M4 5H20V19H4V5Z"
                                                stroke="currentColor"
                                                stroke-width="1.6" />

                                            <path
                                                d="M8 9H16M8 13H16"
                                                stroke="currentColor"
                                                stroke-width="1.6"
                                                stroke-linecap="round" />

                                        </svg>

                                    </div>

                                    <strong>
                                        No products available
                                    </strong>

                                    <div style="margin-top:4px;">
                                        No products with available stock.
                                    </div>

                                </div>

                            <?php endif; ?>


                        </div>

                    </div>

                </section>


                <!-- =================================================
                 CURRENT SALE / CART
            ================================================== -->

                <section class="section-card cart-panel">


                    <div class="section-header">

                        <div>

                            <h2 class="section-title">
                                Current Sale
                            </h2>

                            <div class="section-subtitle">
                                Review items before completing the transaction.
                            </div>

                        </div>

                    </div>


                    <div class="cart-body">


                        <div id="cart"></div>


                        <!-- CART SUMMARY -->

                        <div class="cart-summary">


                            <div class="total-row">

                                <div class="total-label">
                                    Total
                                </div>

                                <div class="total-value">

                                    ₱<span id="total">
                                        0.00
                                    </span>

                                </div>

                            </div>


                            <!-- PAYMENT METHOD -->

                            <div style="margin-top:20px;">

                                <label class="form-label-modern">
                                    Payment Method
                                </label>

                                <select
                                    id="payment_method"
                                    class="modern-select">

                                    <option value="cash">
                                        Cash
                                    </option>

                                    <option value="gcash">
                                        GCash
                                    </option>

                                    <option value="maya">
                                        Maya
                                    </option>

                                </select>

                            </div>


                            <!-- AMOUNT RECEIVED -->

                            <div style="margin-top:14px;">

                                <label class="form-label-modern">
                                    Amount Received
                                </label>

                                <input
                                    id="amount_received"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="modern-input"
                                    value="0">

                            </div>


                            <!-- CHANGE -->

                            <div class="change-box">

                                <span class="change-label">
                                    Change
                                </span>

                                <span class="change-value">

                                    ₱<span id="change">
                                        0.00
                                    </span>

                                </span>

                            </div>


                            <!-- COMPLETE SALE -->

                            <button
                                id="checkoutBtn"
                                class="checkout-btn"
                                onclick="checkout()">

                                Complete Sale

                            </button>


                            <!-- CLEAR CART -->

                            <button
                                class="clear-btn"
                                onclick="clearCart()">

                                Clear Cart

                            </button>


                        </div>

                    </div>

                </section>


            </div>

        </main>

    </div>


    <!-- =========================================================
     BOOTSTRAP
========================================================= -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    <!-- =========================================================
     UNIVERSAL ADMIN JAVASCRIPT
========================================================= -->

    <script src="<?= $basePath ?>assets/js/admin.js"></script>


    <!-- =========================================================
     POS JAVASCRIPT
========================================================= -->

    <script>
        const cart = [];


        /*
        |--------------------------------------------------------------------------
        | ADD PRODUCT
        |--------------------------------------------------------------------------
        */

        function addProduct(p) {

            const existing = cart.find(
                x => x.id === p.id
            );

            if (existing) {

                if (existing.qty < existing.stock) {
                    existing.qty++;
                }

            } else {

                cart.push({
                    ...p,
                    qty: 1
                });

            }

            renderCart();
        }


        /*
        |--------------------------------------------------------------------------
        | CHANGE QUANTITY
        |--------------------------------------------------------------------------
        */

        function changeQty(id, value) {

            const item = cart.find(
                x => x.id === id
            );

            if (!item) {
                return;
            }

            let qty = parseFloat(value);

            if (
                !Number.isFinite(qty) ||
                qty <= 0
            ) {

                qty = 1;

            }

            if (qty > item.stock) {

                qty = item.stock;

            }

            item.qty = qty;

            renderCart();
        }


        /*
        |--------------------------------------------------------------------------
        | REMOVE ITEM
        |--------------------------------------------------------------------------
        */

        function removeItem(id) {

            const index = cart.findIndex(
                x => x.id === id
            );

            if (index >= 0) {

                cart.splice(index, 1);

            }

            renderCart();
        }


        /*
        |--------------------------------------------------------------------------
        | RENDER CART
        |--------------------------------------------------------------------------
        */

        function renderCart() {

            const el =
                document.getElementById('cart');


            if (!cart.length) {

                el.innerHTML = `

            <div class="empty-cart">

                <div class="empty-cart-icon">

                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none">

                        <path
                            d="M5 7H19L18 20H6L5 7Z"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linejoin="round"/>

                        <path
                            d="M9 7C9 4 15 4 15 7"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linecap="round"/>

                    </svg>

                </div>


                <div
                    style="
                    font-size:12px;
                    font-weight:600;
                    color:#6b7280;
                    ">

                    Cart is empty

                </div>


                <div
                    style="
                    font-size:10px;
                    margin-top:4px;
                    ">

                    Select a product to begin.

                </div>

            </div>

        `;

            } else {

                el.innerHTML = cart.map(
                    item => `

            <div class="cart-row">

                <div
                    style="
                    display:flex;
                    justify-content:space-between;
                    align-items:flex-start;
                    ">

                    <div class="cart-product-name">

                        ${escapeHtml(item.name)}

                    </div>


                    <button
                        class="remove-btn"
                        onclick="removeItem(${item.id})">

                        Remove

                    </button>

                </div>


                <div class="cart-meta">

                    ₱${item.price.toFixed(2)}

                    /

                    ${escapeHtml(item.unit)}

                    · Max ${item.stock}

                </div>


                <div class="qty-row">

                    <input
                        class="qty-input"
                        type="number"
                        min="0.001"
                        max="${item.stock}"
                        step="0.001"
                        value="${item.qty}"
                        onchange="
                            changeQty(
                                ${item.id},
                                this.value
                            )
                        ">


                    <span class="line-total">

                        ₱${(
                            item.qty *
                            item.price
                        ).toFixed(2)}

                    </span>

                </div>

            </div>

        `
                ).join('');

            }


            const total =
                cart.reduce(
                    (sum, item) =>
                    sum +
                    item.qty *
                    item.price,
                    0
                );


            document.getElementById(
                    'total'
                ).textContent =
                total.toFixed(2);


            updateChange();
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE CHANGE
        |--------------------------------------------------------------------------
        */

        function updateChange() {

            const total =
                cart.reduce(
                    (sum, item) =>
                    sum +
                    item.qty *
                    item.price,
                    0
                );


            const received =
                parseFloat(
                    document.getElementById(
                        'amount_received'
                    ).value
                ) || 0;


            document.getElementById(
                    'change'
                ).textContent =
                Math.max(
                    received - total,
                    0
                ).toFixed(2);
        }


        /*
        |--------------------------------------------------------------------------
        | AMOUNT RECEIVED
        |--------------------------------------------------------------------------
        */

        document
            .getElementById(
                'amount_received'
            )
            .addEventListener(
                'input',
                updateChange
            );


        /*
        |--------------------------------------------------------------------------
        | PAYMENT METHOD
        |--------------------------------------------------------------------------
        */

        document
            .getElementById(
                'payment_method'
            )
            .addEventListener(
                'change',
                function() {

                    const total =
                        cart.reduce(
                            (sum, item) =>
                            sum +
                            item.qty *
                            item.price,
                            0
                        );


                    document.getElementById(
                            'amount_received'
                        ).value =
                        total.toFixed(2);


                    updateChange();

                }
            );


        /*
        |--------------------------------------------------------------------------
        | PRODUCT SEARCH
        |--------------------------------------------------------------------------
        */

        document
            .getElementById('search')
            .addEventListener(
                'input',
                function() {

                    const q =
                        this.value
                        .toLowerCase()
                        .trim();


                    document
                        .querySelectorAll(
                            '.product-item'
                        )
                        .forEach(
                            el => {

                                el.style.display =
                                    el.dataset.name.includes(q) ?
                                    '' :
                                    'none';

                            }
                        );

                }
            );


        /*
        |--------------------------------------------------------------------------
        | CHECKOUT
        |--------------------------------------------------------------------------
        */

        async function checkout() {

            const message =
                document.getElementById(
                    'message'
                );


            if (!cart.length) {

                message.innerHTML = `

            <div class="
                modern-alert
                alert-warning
            ">

                Please add at least one product.

            </div>

        `;

                return;
            }


            const total =
                cart.reduce(
                    (sum, item) =>
                    sum +
                    item.qty *
                    item.price,
                    0
                );


            const payment =
                document.getElementById(
                    'payment_method'
                ).value;


            const received =
                parseFloat(
                    document.getElementById(
                        'amount_received'
                    ).value
                ) || 0;


            if (received < total) {

                message.innerHTML = `

            <div class="
                modern-alert
                alert-danger
            ">

                Amount received is less than the total.

            </div>

        `;

                return;
            }


            const btn =
                document.getElementById(
                    'checkoutBtn'
                );


            btn.disabled = true;

            btn.textContent =
                'Saving...';


            try {

                const response =
                    await fetch(
                        'checkout.php', {
                            method: 'POST',

                            headers: {
                                'Content-Type': 'application/json'
                            },

                            body: JSON.stringify({

                                payment_method: payment,

                                amount_received: received,

                                items: cart.map(
                                    x => ({
                                        product_id: x.id,

                                        quantity: x.qty
                                    })
                                )

                            })

                        }
                    );


                const data =
                    await response.json();


                if (!data.success) {

                    throw new Error(
                        data.message ||
                        'Unable to save sale.'
                    );

                }


                window.location.href =
                    'receipt.php?id=' +
                    encodeURIComponent(
                        data.sale_id
                    );


            } catch (e) {

                message.innerHTML = `

            <div class="
                modern-alert
                alert-danger
            ">

                ${escapeHtml(e.message)}

            </div>

        `;


                btn.disabled = false;

                btn.textContent =
                    'Complete Sale';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | CLEAR CART
        |--------------------------------------------------------------------------
        */

        function clearCart() {

            cart.length = 0;


            document.getElementById(
                'amount_received'
            ).value = '0';


            document.getElementById(
                'message'
            ).innerHTML = '';


            renderCart();
        }


        /*
        |--------------------------------------------------------------------------
        | ESCAPE HTML
        |--------------------------------------------------------------------------
        */

        function escapeHtml(value) {

            return String(value).replace(
                /[&<>"']/g,
                m =>
                ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                } [m])
            );

        }


        /*
        |--------------------------------------------------------------------------
        | INITIAL CART
        |--------------------------------------------------------------------------
        */

        renderCart();
    </script>

</body>

</html>
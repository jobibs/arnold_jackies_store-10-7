<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    exit('Invalid receipt.');
}


/*
|--------------------------------------------------------------------------
| LOAD SALE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.*,
        u.full_name
    FROM sales s
    LEFT JOIN users u
        ON u.id = s.user_id
    WHERE s.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    exit('Sale not found.');
}


/*
|--------------------------------------------------------------------------
| LOAD SALE ITEMS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        *
    FROM sale_items
    WHERE sale_id = ?
    ORDER BY id ASC
");

$stmt->execute([$id]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function money($amount)
{
    return '₱' . number_format((float)$amount, 2);
}


function cleanQty($quantity)
{
    return rtrim(
        rtrim(
            number_format((float)$quantity, 3),
            '0'
        ),
        '.'
    );
}


function paymentLabel($payment)
{
    $payment = strtolower(trim((string)$payment));

    return match ($payment) {
        'gcash' => 'GCASH',
        'maya'  => 'MAYA',
        'cash'  => 'CASH',
        default => strtoupper($payment)
    };
}


$cashier = trim((string)($sale['full_name'] ?? ''));

if ($cashier === '') {
    $cashier = 'System';
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
        Receipt <?= htmlspecialchars($sale['sale_number']) ?>
    </title>


    <style>
        /* =========================================================
           GLOBAL
        ========================================================= */

        * {
            box-sizing: border-box;
        }


        html,
        body {
            margin: 0;
            padding: 0;
        }


        body {

            background: #eeeeee;

            font-family:
                Tahoma,
                Arial,
                Helvetica,
                sans-serif;

            color: #000000;

            font-size: 14px;

            line-height: 1.45;
        }


        /* =========================================================
           RECEIPT
        ========================================================= */

        .receipt {

            width: 80mm;

            max-width: 80mm;

            margin: 20px auto;

            padding:
                4mm 3.5mm 6mm;

            background: #ffffff;

            color: #000000;
        }


        /* =========================================================
           HEADER
        ========================================================= */

        .store-name {

            margin: 0;

            text-align: center;

            font-family:
                Tahoma,
                Arial,
                Helvetica,
                sans-serif;

            font-size: 20px;

            font-weight: 900;

            line-height: 1.15;

            letter-spacing: 0;
        }


        .receipt-title {

            margin-top: 4px;

            text-align: center;

            font-size: 13px;

            font-weight: 700;

            line-height: 1.2;

            letter-spacing: .3px;
        }


        .divider {

            border: 0;

            border-top:
                1px dashed #000000;

            margin:
                9px 0;
        }


        /* =========================================================
           SALE INFORMATION
        ========================================================= */

        .info-row {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 8px;

            margin: 4px 0;

            font-size: 13px;

            line-height: 1.4;
        }


        .info-label {

            flex: 0 0 auto;

            font-weight: 700;

            white-space: nowrap;
        }


        .info-value {

            flex: 1;

            min-width: 0;

            text-align: right;

            font-weight: 400;

            overflow-wrap: anywhere;
        }


        /* =========================================================
           ITEM TABLE
        ========================================================= */

        .item-header {

            display: grid;

            grid-template-columns:
                7mm minmax(0, 1fr) 19mm 19mm;

            column-gap: 1mm;

            align-items: end;

            padding-bottom: 4px;

            font-size: 9px;

            font-weight: 900;

            line-height: 1.1;

            text-transform: uppercase;
        }


        .item-header-qty {

            text-align: left;
        }


        .item-header-description {

            text-align: left;
        }


        .item-header-price {

            text-align: right;
        }


        .item-header-subtotal {

            text-align: right;
        }


        .item-row {

            display: grid;

            grid-template-columns:
                7mm minmax(0, 1fr) 19mm 19mm;

            column-gap: 1mm;

            align-items: start;

            margin:
                7px 0;

            font-size: 12px;

            line-height: 1.35;
        }


        .item-qty {

            text-align: left;

            font-weight: 700;

            overflow-wrap: anywhere;
        }


        .item-description {

            min-width: 0;

            text-align: left;

            font-size: 13px;

            font-weight: 700;

            line-height: 1.3;

            overflow-wrap: anywhere;

            word-break: break-word;
        }


        .item-unit-price {

            text-align: right;

            white-space: nowrap;

            font-size: 12px;

            font-weight: 600;
        }


        .item-subtotal {

            text-align: right;

            white-space: nowrap;

            font-size: 12px;

            font-weight: 800;
        }


        /* =========================================================
           TOTAL
        ========================================================= */

        .total-row {

            display: flex;

            justify-content: space-between;

            align-items: baseline;

            gap: 10px;

            margin:
                7px 0;
        }


        .total-label {

            font-size: 18px;

            font-weight: 900;
        }


        .total-value {

            font-size: 18px;

            font-weight: 900;

            white-space: nowrap;
        }


        /* =========================================================
           PAYMENT
        ========================================================= */

        .payment-section {

            margin-top: 4px;
        }


        .payment-row {

            display: flex;

            justify-content: space-between;

            align-items: baseline;

            gap: 10px;

            margin:
                5px 0;

            font-size: 14px;

            line-height: 1.4;
        }


        .payment-row span:first-child {

            font-weight: 700;
        }


        .payment-row span:last-child {

            text-align: right;

            white-space: nowrap;

            font-weight: 500;
        }


        .change-row {

            margin-top: 7px;
        }


        .change-row span:first-child,
        .change-row span:last-child {

            font-weight: 900;
        }


        /* =========================================================
           FOOTER
        ========================================================= */

        .footer {

            margin-top: 12px;

            text-align: center;
        }


        .thank-you {

            font-size: 13px;

            font-weight: 900;

            line-height: 1.35;
        }


        .footer-small {

            margin-top: 5px;

            font-size: 10px;

            font-weight: 400;

            line-height: 1.3;
        }


        /* =========================================================
           SCREEN ACTION BUTTONS
        ========================================================= */

        .print-actions {

            width: 80mm;

            max-width: 80mm;

            margin:
                0 auto 20px;

            display: flex;

            gap: 6px;
        }


        .print-actions button,
        .print-actions a {

            flex: 1;

            min-width: 0;

            min-height: 40px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding:
                8px 10px;

            border:
                1px solid #cfd4dc;

            border-radius: 7px;

            background: #ffffff;

            color: #111111;

            font-family:
                Tahoma,
                Arial,
                Helvetica,
                sans-serif;

            font-size: 12px;

            font-weight: 700;

            text-decoration: none;

            cursor: pointer;
        }


        .print-actions button:hover,
        .print-actions a:hover {

            background: #f4f4f4;
        }


        /* =========================================================
           PRINT
        ========================================================= */

        @media print {

            @page {

                size: 80mm auto;

                margin: 0;
            }


            html,
            body {

                width: 80mm;

                max-width: 80mm;

                margin: 0;

                padding: 0;

                background: #ffffff;
            }


            body {

                font-family:
                    Tahoma,
                    Arial,
                    Helvetica,
                    sans-serif;

                font-size: 14px;

                color: #000000;
            }


            .receipt {

                width: 80mm;

                max-width: 80mm;

                margin: 0;

                padding:
                    4mm 3.5mm 6mm;

                background: #ffffff;
            }


            .print-actions {

                display: none !important;
            }


            * {

                -webkit-print-color-adjust: exact;

                print-color-adjust: exact;
            }
        }


        /* =========================================================
           SMALL SCREEN
        ========================================================= */

        @media screen and (max-width: 420px) {

            body {

                background: #eeeeee;
            }


            .receipt {

                width: 100%;

                max-width: 100%;

                margin: 0 auto;

                padding:
                    18px 14px 20px;
            }


            .print-actions {

                width: 100%;

                max-width: 100%;

                padding:
                    0 10px 15px;
            }
        }
    </style>

</head>


<body>


    <!-- =========================================================
         RECEIPT
    ========================================================== -->

    <div class="receipt">


        <!-- =====================================================
             STORE HEADER
        ====================================================== -->

        <div class="store-name">

            ARNOLD &amp; JACKIE'S STORE

        </div>


        <div class="receipt-title">

            SALES RECEIPT

        </div>


        <hr class="divider">


        <!-- =====================================================
             SALE INFORMATION
        ====================================================== -->

        <div class="info-row">

            <span class="info-label">
                Receipt:
            </span>

            <span class="info-value">

                <?= htmlspecialchars(
                    $sale['sale_number'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Date:
            </span>

            <span class="info-value">

                <?= date(
                    'M d, Y h:i A',
                    strtotime($sale['sale_date'])
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Cashier:
            </span>

            <span class="info-value">

                <?= htmlspecialchars(
                    $cashier,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>


        <hr class="divider">


        <!-- =====================================================
             ITEM HEADER
        ====================================================== -->

        <div class="item-header">

            <div class="item-header-qty">
                QTY
            </div>

            <div class="item-header-description">
                DESCRIPTION
            </div>

            <div class="item-header-price">
                UNIT PRICE
            </div>

            <div class="item-header-subtotal">
                SUBTOTAL
            </div>

        </div>


        <!-- =====================================================
             ITEMS
        ====================================================== -->

        <?php if ($items): ?>


            <?php foreach ($items as $item): ?>


                <div class="item-row">


                    <!-- QTY -->

                    <div class="item-qty">

                        <?= cleanQty(
                            $item['quantity']
                        ) ?>

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="item-description">

                        <?= htmlspecialchars(
                            $item['product_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>


                    <!-- UNIT PRICE -->

                    <div class="item-unit-price">

                        <?= money(
                            $item['selling_price']
                        ) ?>

                    </div>


                    <!-- SUBTOTAL -->

                    <div class="item-subtotal">

                        <?= money(
                            $item['subtotal']
                        ) ?>

                    </div>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div
                style="
                    text-align: center;
                    padding: 8px 0;
                    font-weight: 700;
                    font-size: 13px;
                ">

                NO ITEMS

            </div>


        <?php endif; ?>


        <hr class="divider">


        <!-- =====================================================
             TOTAL
        ====================================================== -->

        <div class="total-row">

            <span class="total-label">
                TOTAL
            </span>

            <span class="total-value">

                <?= money(
                    $sale['total']
                ) ?>

            </span>

        </div>


        <hr class="divider">


        <!-- =====================================================
             PAYMENT
        ====================================================== -->

        <div class="payment-section">


            <div class="payment-row">

                <span>
                    Payment
                </span>

                <span>

                    <?= paymentLabel(
                        $sale['payment_method']
                    ) ?>

                </span>

            </div>


            <div class="payment-row">

                <span>
                    Received
                </span>

                <span>

                    <?= money(
                        $sale['amount_received']
                    ) ?>

                </span>

            </div>


            <div class="payment-row change-row">

                <span>
                    Change
                </span>

                <span>

                    <?= money(
                        $sale['change_amount']
                    ) ?>

                </span>

            </div>


        </div>


        <hr class="divider">


        <!-- =====================================================
             FOOTER
        ====================================================== -->

        <div class="footer">


            <div class="thank-you">

                THANK YOU FOR SHOPPING WITH US!

            </div>


            <div class="footer-small">

                Please come again.

            </div>


        </div>


    </div>


    <!-- =========================================================
         SCREEN ACTIONS
    ========================================================== -->

    <div class="print-actions">


        <button
            type="button"
            onclick="window.print()">
            Print
        </button>


        <a href="index.php">
            New Sale
        </a>


        <a href="../dashboard.php">
            Dashboard
        </a>


    </div>


</body>

</html>
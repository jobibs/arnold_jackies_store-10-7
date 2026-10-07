<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT wt.*, u.full_name FROM wallet_transactions wt LEFT JOIN users u ON u.id=wt.user_id WHERE wt.id=?");
$stmt->execute([$id]);
$t = $stmt->fetch();
if (!$t) exit('Transaction not found.');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wallet Receipt</title>
    <style>
        body {
            font-family: Arial;
            background: #eee;
            padding: 20px
        }

        .receipt {
            width: 360px;
            max-width: 100%;
            margin: auto;
            background: #fff;
            padding: 20px;
            box-sizing: border-box
        }

        .center {
            text-align: center
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0
        }

        hr {
            border: 0;
            border-top: 1px dashed #999
        }

        .buttons {
            max-width: 360px;
            margin: 15px auto;
            display: flex;
            gap: 8px
        }

        button,
        a {
            flex: 1;
            padding: 10px;
            border: 1px solid #333;
            background: #fff;
            color: #000;
            text-decoration: none;
            text-align: center
        }

        @media print {
            body {
                background: #fff;
                padding: 0
            }

            .buttons {
                display: none
            }

            .receipt {
                width: 100%
            }
        }
    </style>
</head>

<body>
    <div class="receipt">
        <div class="center">
            <h3 style="margin:0">ARNOLD & JACKIE'S STORE</h3><small><?= strtoupper($t['provider']) ?> TRANSACTION</small>
        </div>
        <hr>
        <div class="row"><span>Transaction #</span><strong><?= htmlspecialchars($t['transaction_number']) ?></strong></div>
        <div class="row"><span>Date</span><span><?= date('M d, Y h:i A', strtotime($t['transaction_date'])) ?></span></div>
        <div class="row"><span>Type</span><span><?= strtoupper(str_replace('_', ' ', $t['transaction_type'])) ?></span></div>
        <?php if ($t['customer_name']): ?><div class="row"><span>Customer</span><span><?= htmlspecialchars($t['customer_name']) ?></span></div><?php endif; ?>
        <?php if ($t['reference_number']): ?><div class="row"><span>Reference</span><span><?= htmlspecialchars($t['reference_number']) ?></span></div><?php endif; ?>
        <hr>
        <div class="row"><span>Wallet Amount</span><strong>₱<?= number_format($t['wallet_amount'], 2) ?></strong></div>
        <div class="row"><span>Service Fee</span><strong>₱<?= number_format($t['service_fee'], 2) ?></strong></div>
        <?php if ($t['transaction_type'] === 'cash_in'): ?>
            <div class="row"><strong>Customer Pays</strong><strong>₱<?= number_format($t['total_received'], 2) ?></strong></div>
        <?php else: ?>
            <div class="row"><strong>Store Releases</strong><strong>₱<?= number_format($t['total_paid'], 2) ?></strong></div>
        <?php endif; ?>
        <hr>
        <div class="center"><small>Thank you!</small></div>
    </div>
    <div class="buttons"><button onclick="window.print()">Print</button><a href="transaction.php">New Transaction</a><a href="index.php">Wallet</a></div>
</body>

</html>
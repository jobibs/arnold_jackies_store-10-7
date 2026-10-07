<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

$error = '';
$success = '';

$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);

function getFee(PDO $pdo, string $provider, string $type, float $amount): float
{
    $stmt = $pdo->prepare("
        SELECT fee
        FROM wallet_fee_rules
        WHERE provider = ?
          AND transaction_type = ?
          AND is_active = 1
          AND min_amount <= ?
          AND (max_amount IS NULL OR max_amount >= ?)
        ORDER BY min_amount DESC
        LIMIT 1
    ");

    $stmt->execute([
        $provider,
        $type,
        $amount,
        $amount
    ]);

    $row = $stmt->fetch();

    return $row ? (float)$row['fee'] : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $provider =
        $_POST['provider'] ?? '';

    $type =
        $_POST['transaction_type'] ?? '';

    $customer =
        trim($_POST['customer_name'] ?? '');

    $reference =
        trim($_POST['reference_number'] ?? '');

    $amount =
        (float)($_POST['wallet_amount'] ?? 0);

    $remarks =
        trim($_POST['remarks'] ?? '');

    if (
        !in_array($provider, ['gcash', 'maya'], true) ||
        !in_array($type, ['cash_in', 'cash_out'], true)
    ) {

        $error =
            'Please select a valid provider and transaction type.';
    } elseif ($amount <= 0) {

        $error =
            'Transaction amount must be greater than zero.';
    } else {

        try {

            $fee =
                getFee(
                    $pdo,
                    $provider,
                    $type,
                    $amount
                );

            if ($type === 'cash_in') {

                // Customer gives the store the wallet amount + service fee.
                $totalReceived =
                    $amount + $fee;

                $totalPaid = 0;
            } else {

                // Store gives customer the wallet amount; customer pays fee separately.
                $totalReceived =
                    $fee;

                $totalPaid =
                    $amount;
            }

            $transactionNumber =
                'WAL-' .
                date('YmdHis') .
                '-' .
                random_int(100, 999);

            $stmt = $pdo->prepare("
                INSERT INTO wallet_transactions
                (
                    transaction_number,
                    provider,
                    transaction_type,
                    customer_name,
                    reference_number,
                    wallet_amount,
                    service_fee,
                    total_received,
                    total_paid,
                    remarks,
                    user_id
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $transactionNumber,
                $provider,
                $type,
                $customer !== ''
                    ? $customer
                    : null,
                $reference !== ''
                    ? $reference
                    : null,
                $amount,
                $fee,
                $totalReceived,
                $totalPaid,
                $remarks !== ''
                    ? $remarks
                    : null,
                $_SESSION['user_id']
            ]);

            $transactionId =
                (int)$pdo->lastInsertId();

            if ($isAjax) {

                header(
                    'Content-Type: application/json; charset=utf-8'
                );

                echo json_encode([
                    'success' => true,
                    'message' =>
                    'Wallet transaction saved successfully.',
                    'transaction_id' =>
                    $transactionId,
                    'transaction_number' =>
                    $transactionNumber
                ]);

                exit;
            }

            header(
                'Location: receipt.php?id=' .
                    $transactionId
            );

            exit;
        } catch (Throwable $e) {

            $error =
                $e->getMessage();
        }
    }

    if ($isAjax) {

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo json_encode([
            'success' => false,
            'message' =>
            $error !== ''
                ? $error
                : 'Unable to save the transaction.'
        ]);

        exit;
    }
}
?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        New Wallet Transaction
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

</head>

<body class="bg-light">

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-3">

            <div>
                <h2>
                    New GCash / Maya Transaction
                </h2>

                <small class="text-muted">
                    The fee is pulled automatically from your fee settings.
                </small>
            </div>

            <a
                href="index.php"
                class="btn btn-outline-secondary">
                Back
            </a>

        </div>

        <?php if ($error): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <div class="row">

            <div class="col-lg-7">

                <div class="card border-0 shadow-sm">

                    <div class="card-body p-4">

                        <form method="post">

                            <div class="row g-3">

                                <div class="col-md-6">

                                    <label class="form-label">
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

                                <div class="col-md-6">

                                    <label class="form-label">
                                        Transaction Type
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

                                <div class="col-md-6">

                                    <label class="form-label">
                                        Customer Name
                                        <small class="text-muted">
                                            (optional)
                                        </small>
                                    </label>

                                    <input
                                        name="customer_name"
                                        class="form-control">

                                </div>

                                <div class="col-md-6">

                                    <label class="form-label">
                                        Reference Number
                                        <small class="text-muted">
                                            (optional)
                                        </small>
                                    </label>

                                    <input
                                        name="reference_number"
                                        class="form-control">

                                </div>

                                <div class="col-md-6">

                                    <label class="form-label">
                                        Wallet Amount
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            ₱
                                        </span>

                                        <input
                                            name="wallet_amount"
                                            id="wallet_amount"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            class="form-control"
                                            required>

                                    </div>

                                </div>

                                <div class="col-md-6">

                                    <label class="form-label">
                                        Service Fee
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            ₱
                                        </span>

                                        <input
                                            id="fee"
                                            class="form-control"
                                            readonly>

                                    </div>

                                </div>

                                <div class="col-12">

                                    <div class="alert alert-light border mb-0">

                                        <div class="d-flex justify-content-between">

                                            <span id="flowLabel">
                                                Customer pays store
                                            </span>

                                            <strong>
                                                ₱<span id="cashFlow">0.00</span>
                                            </strong>

                                        </div>

                                        <small
                                            id="flowNote"
                                            class="text-muted">
                                            Wallet amount + service fee.
                                        </small>

                                    </div>

                                </div>

                                <div class="col-12">

                                    <label class="form-label">
                                        Remarks
                                        <small class="text-muted">
                                            (optional)
                                        </small>
                                    </label>

                                    <input
                                        name="remarks"
                                        class="form-control">

                                </div>

                            </div>

                            <button
                                class="btn btn-dark mt-4">
                                Save Transaction
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script>
        async function calculateFee() {

            const provider =
                document.getElementById('provider').value;

            const type =
                document.getElementById('transaction_type').value;

            const amount =
                parseFloat(
                    document.getElementById('wallet_amount').value
                ) || 0;

            if (!amount) {

                document.getElementById('fee').value =
                    '0.00';

                document.getElementById('cashFlow').textContent =
                    '0.00';

                return;
            }

            const url =
                'fee_api.php?provider=' +
                encodeURIComponent(provider) +
                '&transaction_type=' +
                encodeURIComponent(type) +
                '&amount=' +
                encodeURIComponent(amount);

            const r =
                await fetch(url);

            const d =
                await r.json();

            const fee =
                Number(d.fee || 0);

            document.getElementById('fee').value =
                fee.toFixed(2);

            if (type === 'cash_in') {

                document.getElementById('flowLabel').textContent =
                    'Customer pays store';

                document.getElementById('cashFlow').textContent =
                    (amount + fee).toFixed(2);

                document.getElementById('flowNote').textContent =
                    'Wallet amount + service fee.';

            } else {

                document.getElementById('flowLabel').textContent =
                    'Store gives customer';

                document.getElementById('cashFlow').textContent =
                    amount.toFixed(2);

                document.getElementById('flowNote').textContent =
                    'Customer pays the service fee separately.';
            }
        }

        ['provider', 'transaction_type', 'wallet_amount']
        .forEach(id => {

            document
                .getElementById(id)
                .addEventListener(
                    'input',
                    calculateFee
                );

            document
                .getElementById(id)
                .addEventListener(
                    'change',
                    calculateFee
                );

        });

        calculateFee();
    </script>

</body>

</html>
<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];
$paymentMethod = $input['payment_method'] ?? 'cash';
$amountReceived = (float)($input['amount_received'] ?? 0);

if (!in_array($paymentMethod, ['cash', 'gcash', 'maya'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method.']);
    exit;
}

if (!is_array($items) || count($items) === 0) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $cleanItems = [];
    $subtotal = 0;
    $totalCost = 0;
    $totalProfit = 0;

    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $quantity = (float)($item['quantity'] ?? 0);

        if ($productId <= 0 || $quantity <= 0) {
            throw new Exception('Invalid product or quantity.');
        }

        // Lock the product row until the transaction is complete.
        $stmt = $pdo->prepare("SELECT id, product_name, cost_price, selling_price, stock
            FROM products WHERE id=? AND is_active=1 FOR UPDATE");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new Exception('One of the selected products no longer exists.');
        }

        if ((float)$product['stock'] < $quantity) {
            throw new Exception('Not enough stock for: ' . $product['product_name'] .
                '. Available: ' . rtrim(rtrim(number_format($product['stock'], 3), '0'), '.'));
        }

        $lineSales = round((float)$product['selling_price'] * $quantity, 2);
        $lineCost = round((float)$product['cost_price'] * $quantity, 2);
        $lineProfit = round($lineSales - $lineCost, 2);

        $subtotal += $lineSales;
        $totalCost += $lineCost;
        $totalProfit += $lineProfit;

        $cleanItems[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $lineSales,
            'cost' => $lineCost,
            'profit' => $lineProfit
        ];
    }

    $subtotal = round($subtotal, 2);
    $totalCost = round($totalCost, 2);
    $totalProfit = round($totalProfit, 2);

    if ($amountReceived < $subtotal) {
        throw new Exception('Amount received is less than the sale total.');
    }

    $change = round($amountReceived - $subtotal, 2);
    $saleNumber = 'SALE-' . date('YmdHis') . '-' . random_int(100, 999);

    $sale = $pdo->prepare("INSERT INTO sales
        (sale_number, subtotal, discount, total, total_cost, total_profit,
         payment_method, amount_received, change_amount, user_id)
        VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?)");
    $sale->execute([
        $saleNumber,
        $subtotal,
        $subtotal,
        $totalCost,
        $totalProfit,
        $paymentMethod,
        $amountReceived,
        $change,
        $_SESSION['user_id']
    ]);

    $saleId = $pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO sale_items
        (sale_id, product_id, product_name, quantity, cost_price,
         selling_price, subtotal, profit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $stockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id=?");

    $movementStmt = $pdo->prepare("INSERT INTO stock_movements
        (product_id, movement_type, quantity, reference_id, remarks)
        VALUES (?, 'sale', ?, ?, ?)");

    foreach ($cleanItems as $line) {
        $p = $line['product'];

        $itemStmt->execute([
            $saleId,
            $p['id'],
            $p['product_name'],
            $line['quantity'],
            $p['cost_price'],
            $p['selling_price'],
            $line['subtotal'],
            $line['profit']
        ]);

        $stockStmt->execute([$line['quantity'], $p['id']]);

        $movementStmt->execute([
            $p['id'],
            -$line['quantity'],
            $saleId,
            'Sale ' . $saleNumber
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'sale_id' => (int)$saleId,
        'sale_number' => $saleNumber
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

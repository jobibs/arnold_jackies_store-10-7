<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_login();
header('Content-Type: application/json');

$provider = $_GET['provider'] ?? '';
$type = $_GET['transaction_type'] ?? '';
$amount = (float)($_GET['amount'] ?? 0);

$stmt = $pdo->prepare("SELECT fee FROM wallet_fee_rules
    WHERE provider=? AND transaction_type=? AND is_active=1
    AND min_amount <= ?
    AND (max_amount IS NULL OR max_amount >= ?)
    ORDER BY min_amount DESC LIMIT 1");
$stmt->execute([$provider, $type, $amount, $amount]);
$row = $stmt->fetch();

echo json_encode(['fee' => $row ? (float)$row['fee'] : 0]);

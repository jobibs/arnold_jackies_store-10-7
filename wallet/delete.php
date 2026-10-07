<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    DELETE FROM wallet_transactions
    WHERE id = ?
");

$stmt->execute([$id]);

header('Location: index.php?deleted=1');
exit;

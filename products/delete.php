<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE products SET is_active=0 WHERE id=?");
    $stmt->execute([$id]);
}
header('Location: index.php');
exit;

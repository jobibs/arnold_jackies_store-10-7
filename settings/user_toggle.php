<?php

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$userId = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($userId <= 0 || !in_array($action, ['activate', 'deactivate'], true)) {
    header('Location: users.php');
    exit;
}

/*
 * Prevent the currently logged-in administrator
 * from deactivating their own account.
 */
if ($userId === (int)$_SESSION['user_id'] && $action === 'deactivate') {
    header('Location: users.php?error=self_deactivate');
    exit;
}

$newStatus = ($action === 'activate') ? 1 : 0;

$stmt = $pdo->prepare("
    UPDATE users
    SET is_active = ?
    WHERE id = ?
");

$stmt->execute([
    $newStatus,
    $userId
]);

header('Location: users.php?status=' . $action);
exit;

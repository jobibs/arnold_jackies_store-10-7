<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/database.php";

function require_login(): void
{
    global $pdo;

    if (empty($_SESSION['user_id'])) {
        header('Location: /arnold_jackies_store/login.php');
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, full_name, role, is_active
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int)$user['is_active'] !== 1) {
        session_unset();
        session_destroy();

        header('Location: /arnold_jackies_store/login.php');
        exit;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
}

function require_admin(): void
{
    require_login();

    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Access denied. Administrator access is required.');
    }
}

function require_roles(array $allowedRoles): void
{
    require_login();

    $currentRole = $_SESSION['role'] ?? '';

    if (!in_array($currentRole, $allowedRoles, true)) {
        http_response_code(403);
        exit('Access denied. You do not have permission to access this page.');
    }
}

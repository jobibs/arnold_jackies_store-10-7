<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$status = strtolower(trim($_POST['status'] ?? ''));

if (!$id || $id < 1) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid PisoWiFi ID.'
    ]);
    exit;
}

if (!in_array($status, ['active', 'inactive'], true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid PisoWiFi status.'
    ]);
    exit;
}

try {
    $find = $pdo->prepare("
        SELECT id, box_name
        FROM pisowifi
        WHERE id = ?
        LIMIT 1
    ");
    $find->execute([$id]);
    $box = $find->fetch(PDO::FETCH_ASSOC);

    if (!$box) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'PisoWiFi box was not found.'
        ]);
        exit;
    }

    $update = $pdo->prepare("
        UPDATE pisowifi
        SET status = ?
        WHERE id = ?
    ");
    $update->execute([$status, $id]);

    $label = $status === 'active' ? 'Active' : 'Inactive';

    echo json_encode([
        'success' => true,
        'message' => $box['box_name'] . ' status updated to ' . $label . '.',
        'status' => $status,
        'label' => $label
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update the PisoWiFi status. Please try again.'
    ]);
}

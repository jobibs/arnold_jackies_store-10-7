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
        'message' => 'Invalid Pisonet unit.'
    ]);
    exit;
}

if (!in_array($status, ['working', 'maintenance', 'offline'], true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid Pisonet status.'
    ]);
    exit;
}

try {

    $stmt = $pdo->prepare("SELECT id, unit_name FROM pisonet_units WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Pisonet unit was not found.'
        ]);
        exit;
    }

    $update = $pdo->prepare("UPDATE pisonet_units SET status = ? WHERE id = ?");
    $update->execute([$status, $id]);

    $labels = [
        'working' => 'Working',
        'maintenance' => 'Maintenance',
        'offline' => 'Offline'
    ];

    echo json_encode([
        'success' => true,
        'message' => $unit['unit_name'] . ' status updated to ' . $labels[$status] . '.',
        'status' => $status
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to update the Pisonet status. Please try again.'
    ]);
}

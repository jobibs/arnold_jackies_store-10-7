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
$boxName = trim($_POST['box_name'] ?? '');

if (!$id || $id < 1) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid PisoWiFi ID.'
    ]);
    exit;
}

if ($boxName === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'PisoWiFi name is required.'
    ]);
    exit;
}

if (mb_strlen($boxName) > 100) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'PisoWiFi name cannot exceed 100 characters.'
    ]);
    exit;
}

try {
    $find = $pdo->prepare("SELECT id FROM pisowifi WHERE id = ? LIMIT 1");
    $find->execute([$id]);

    if (!$find->fetchColumn()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'PisoWiFi box was not found.'
        ]);
        exit;
    }

    $duplicate = $pdo->prepare("
        SELECT id
        FROM pisowifi
        WHERE LOWER(box_name) = LOWER(?)
          AND id <> ?
        LIMIT 1
    ");
    $duplicate->execute([$boxName, $id]);

    if ($duplicate->fetchColumn()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Another PisoWiFi box already uses that name.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE pisowifi
        SET box_name = ?
        WHERE id = ?
    ");
    $stmt->execute([$boxName, $id]);

    echo json_encode([
        'success' => true,
        'message' => 'PisoWiFi box renamed successfully.',
        'box_name' => $boxName
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to rename the PisoWiFi box. Please try again.'
    ]);
}

<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$boxName = trim($_POST['box_name'] ?? '');
$location = trim($_POST['location'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = $_POST['status'] ?? 'active';

if ($boxName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Box name is required.']);
    exit;
}

if ($location === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Location is required.']);
    exit;
}

if (!in_array($status, ['active', 'inactive'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid PisoWiFi status.']);
    exit;
}

if (
    mb_strlen($boxName) > 100 ||
    mb_strlen($location) > 255 ||
    mb_strlen($description) > 500
) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'One or more fields exceed the allowed length.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id
        FROM pisowifi
        WHERE LOWER(box_name) = LOWER(?)
        LIMIT 1
    ");
    $stmt->execute([$boxName]);

    if ($stmt->fetchColumn()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'A PisoWiFi box with that name already exists.'
        ]);
        exit;
    }

    $insert = $pdo->prepare("
        INSERT INTO pisowifi
            (box_name, location, description, status)
        VALUES
            (?, ?, ?, ?)
    ");
    $insert->execute([
        $boxName,
        $location,
        $description !== '' ? $description : null,
        $status
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'PisoWiFi box added successfully.',
        'id' => (int)$pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save the PisoWiFi box. Please try again.'
    ]);
}

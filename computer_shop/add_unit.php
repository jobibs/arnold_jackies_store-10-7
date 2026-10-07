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

$unitName = trim($_POST['unit_name'] ?? '');
$status = trim($_POST['status'] ?? 'working');

if ($unitName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Pisonet name is required.']);
    exit;
}

if (mb_strlen($unitName) > 50) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Pisonet name must not exceed 50 characters.']);
    exit;
}

if (!in_array($status, ['working', 'maintenance', 'offline'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid Pisonet status.']);
    exit;
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pisonet_units (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            unit_name VARCHAR(50) NOT NULL,
            status ENUM('working','maintenance','offline') NOT NULL DEFAULT 'working',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pisonet_unit_name (unit_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $duplicateStmt = $pdo->prepare("SELECT id FROM pisonet_units WHERE LOWER(unit_name) = LOWER(?) LIMIT 1");
    $duplicateStmt->execute([$unitName]);

    if ($duplicateStmt->fetchColumn()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'That Pisonet name is already in use. Please choose another name.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO pisonet_units (unit_name, status) VALUES (?, ?)");
    $stmt->execute([$unitName, $status]);

    echo json_encode([
        'success' => true,
        'message' => $unitName . ' was added successfully.',
        'id' => (int)$pdo->lastInsertId(),
        'unit_name' => $unitName
    ]);

} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'That Pisonet name is already in use. Please choose another name.'
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to add the Pisonet. Please try again.'
    ]);
}

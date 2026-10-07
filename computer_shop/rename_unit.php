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
$unitName = trim($_POST['unit_name'] ?? '');

if (!$id || $id < 1) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid Pisonet unit.'
    ]);
    exit;
}

if ($unitName === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Pisonet name is required.'
    ]);
    exit;
}

if (mb_strlen($unitName) > 50) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Pisonet name must not exceed 50 characters.'
    ]);
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

    $unitStmt = $pdo->prepare("SELECT id, unit_name FROM pisonet_units WHERE id = ? LIMIT 1");
    $unitStmt->execute([$id]);
    $unit = $unitStmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Pisonet unit was not found.'
        ]);
        exit;
    }

    if (strcasecmp($unit['unit_name'], $unitName) === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Pisonet name is already set to ' . $unitName . '.',
            'unit_name' => $unitName
        ]);
        exit;
    }

    $duplicateStmt = $pdo->prepare("
        SELECT id
        FROM pisonet_units
        WHERE LOWER(unit_name) = LOWER(?)
          AND id <> ?
        LIMIT 1
    ");
    $duplicateStmt->execute([$unitName, $id]);

    if ($duplicateStmt->fetchColumn()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'That Pisonet name is already in use. Please choose another name.'
        ]);
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE pisonet_units SET unit_name = ? WHERE id = ?");
    $updateStmt->execute([$unitName, $id]);

    echo json_encode([
        'success' => true,
        'message' => $unit['unit_name'] . ' was renamed to ' . $unitName . '.',
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
        'message' => 'Unable to rename the Pisonet. Please try again.'
    ]);
}

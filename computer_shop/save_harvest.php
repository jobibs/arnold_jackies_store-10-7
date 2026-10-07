<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$unitId = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);
$harvestMonth = trim($_POST['harvest_month'] ?? '');
$amountRaw = trim($_POST['amount'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (!$unitId || $unitId < 1) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid Pisonet unit.'
    ]);
    exit;
}

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $harvestMonth)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please select a valid harvest month.'
    ]);
    exit;
}

if ($amountRaw === '' || !is_numeric($amountRaw)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Enter a valid harvest amount.'
    ]);
    exit;
}

$amount = round((float)$amountRaw, 2);

if ($amount <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Harvest amount must be greater than ₱0.00.'
    ]);
    exit;
}

if ($amount > 9999999999.99) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Harvest amount is too large.'
    ]);
    exit;
}

if (mb_strlen($remarks) > 500) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Remarks must not exceed 500 characters.'
    ]);
    exit;
}

$harvestMonthDate = $harvestMonth . '-01';

try {

    /* Ensure the module tables exist even if this endpoint is hit directly. */
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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pisonet_harvests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            unit_id INT UNSIGNED NOT NULL,
            harvest_month DATE NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            remarks VARCHAR(500) NULL,
            user_id INT UNSIGNED NULL,
            harvested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pisonet_unit_month (unit_id, harvest_month),
            KEY idx_pisonet_harvest_month (harvest_month),
            KEY idx_pisonet_harvest_user (user_id),
            CONSTRAINT fk_pisonet_harvest_unit
                FOREIGN KEY (unit_id) REFERENCES pisonet_units(id)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $unitStmt = $pdo->prepare("SELECT id, unit_name FROM pisonet_units WHERE id = ? LIMIT 1");
    $unitStmt->execute([$unitId]);
    $unit = $unitStmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Pisonet unit was not found.'
        ]);
        exit;
    }

    $existingStmt = $pdo->prepare("SELECT id FROM pisonet_harvests WHERE unit_id = ? AND harvest_month = ? LIMIT 1");
    $existingStmt->execute([$unitId, $harvestMonthDate]);
    $existingId = $existingStmt->fetchColumn();

    if ($existingId) {

        $updateStmt = $pdo->prepare("
            UPDATE pisonet_harvests
            SET amount = ?,
                remarks = ?,
                user_id = ?,
                harvested_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $updateStmt->execute([
            number_format($amount, 2, '.', ''),
            $remarks !== '' ? $remarks : null,
            $userId ?: null,
            (int)$existingId
        ]);

        echo json_encode([
            'success' => true,
            'message' => $unit['unit_name'] . ' harvest updated successfully.',
            'id' => (int)$existingId
        ]);
        exit;
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO pisonet_harvests (
            unit_id,
            harvest_month,
            amount,
            remarks,
            user_id
        ) VALUES (?, ?, ?, ?, ?)
    ");

    $insertStmt->execute([
        $unitId,
        $harvestMonthDate,
        number_format($amount, 2, '.', ''),
        $remarks !== '' ? $remarks : null,
        $userId ?: null
    ]);

    echo json_encode([
        'success' => true,
        'message' => $unit['unit_name'] . ' harvest recorded successfully.',
        'id' => (int)$pdo->lastInsertId()
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save the Pisonet harvest. Please try again.'
    ]);
}

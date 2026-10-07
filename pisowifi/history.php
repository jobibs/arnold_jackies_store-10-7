<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid PisoWiFi ID.'
    ]);
    exit;
}

try {
    $box = $pdo->prepare("SELECT id, box_name FROM pisowifi WHERE id = ? LIMIT 1");
    $box->execute([$id]);
    $boxRow = $box->fetch(PDO::FETCH_ASSOC);

    if (!$boxRow) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'PisoWiFi box was not found.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            earning_month,
            earning_year,
            gross_earnings,
            expenses
        FROM pisowifi_earnings
        WHERE pisowifi_id = ?
        ORDER BY earning_year DESC, earning_month DESC, id DESC
    ");
    $stmt->execute([$id]);

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'box_name' => $boxRow['box_name'],
        'records' => $records
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load PisoWiFi earnings history.'
    ]);
}

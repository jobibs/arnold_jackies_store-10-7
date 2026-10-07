<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid Pisonet ID.']);
    exit;
}

try {
    $unitStmt = $pdo->prepare("SELECT id, unit_name FROM pisonet_units WHERE id = ? LIMIT 1");
    $unitStmt->execute([$id]);
    $unit = $unitStmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pisonet was not found.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            h.id,
            h.unit_id,
            h.harvest_month,
            h.amount,
            h.remarks,
            h.harvested_at,
            u.unit_name,
            COALESCE(NULLIF(us.full_name, ''), 'System User') AS recorded_by
        FROM pisonet_harvests h
        INNER JOIN pisonet_units u ON u.id = h.unit_id
        LEFT JOIN users us ON us.id = h.user_id
        WHERE h.unit_id = ?
        ORDER BY h.harvest_month DESC, h.harvested_at DESC, h.id DESC
    ");

    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'unit_name' => $unit['unit_name'],
        'records' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load Pisonet harvest history.']);
}

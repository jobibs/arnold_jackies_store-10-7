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

if (!$id || $id < 1) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid harvest record ID.'
    ]);
    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT
            h.id,
            h.amount,
            h.harvest_month,
            u.unit_name
        FROM pisonet_harvests h
        INNER JOIN pisonet_units u
            ON u.id = h.unit_id
        WHERE h.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $harvest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$harvest) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Harvest record was not found.'
        ]);
        exit;
    }

    $deleteStmt = $pdo->prepare('DELETE FROM pisonet_harvests WHERE id = ?');
    $deleteStmt->execute([$id]);

    if ($deleteStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Harvest record was not found or has already been deleted.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => $harvest['unit_name'] . ' · ' . date('F Y', strtotime($harvest['harvest_month'])) . ' harvest was deleted successfully.'
    ]);

} catch (PDOException $e) {

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to delete the harvest record. Please try again.'
    ]);
}

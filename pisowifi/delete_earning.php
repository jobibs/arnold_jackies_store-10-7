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
        'message' => 'Invalid earnings record ID.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.earning_month,
            e.earning_year,
            p.box_name
        FROM pisowifi_earnings e
        INNER JOIN pisowifi p ON p.id = e.pisowifi_id
        WHERE e.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'The earnings record was not found.'
        ]);
        exit;
    }

    $delete = $pdo->prepare("DELETE FROM pisowifi_earnings WHERE id = ?");
    $delete->execute([$id]);

    if ($delete->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'The earnings record could not be deleted.'
        ]);
        exit;
    }

    $monthName = date(
        'F',
        mktime(0, 0, 0, (int)$record['earning_month'], 1)
    );

    echo json_encode([
        'success' => true,
        'message' => sprintf(
            '%s %s earnings for %s were deleted successfully.',
            $monthName,
            $record['earning_year'],
            $record['box_name']
        )
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to delete the earnings record. Please try again.'
    ]);
}

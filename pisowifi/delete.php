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

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid PisoWiFi ID.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $check = $pdo->prepare("SELECT id, box_name FROM pisowifi WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    $box = $check->fetch(PDO::FETCH_ASSOC);

    if (!$box) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'PisoWiFi box was not found.']);
        exit;
    }

    $earnings = $pdo->prepare("DELETE FROM pisowifi_earnings WHERE pisowifi_id = ?");
    $earnings->execute([$id]);

    $delete = $pdo->prepare("DELETE FROM pisowifi WHERE id = ?");
    $delete->execute([$id]);

    if ($delete->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'PisoWiFi box could not be deleted.']);
        exit;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'PisoWiFi box "' . $box['box_name'] . '" and its earnings history were deleted successfully.'
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to delete the PisoWiFi box. Please try again.'
    ]);
}

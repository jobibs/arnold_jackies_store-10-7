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
        'message' => 'Invalid Pisonet ID.'
    ]);
    exit;
}

try {

    $pdo->beginTransaction();

    $unitStmt = $pdo->prepare('SELECT unit_name FROM pisonet_units WHERE id = ? LIMIT 1');
    $unitStmt->execute([$id]);
    $unit = $unitStmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Pisonet was not found.'
        ]);
        exit;
    }

    // The user explicitly requested that deleting a Pisonet may also
    // remove its harvest history. Delete the history first so this
    // works even when the database foreign key is not configured.
    $historyStmt = $pdo->prepare('DELETE FROM pisonet_harvests WHERE unit_id = ?');
    $historyStmt->execute([$id]);
    $deletedHistory = $historyStmt->rowCount();

    $deleteStmt = $pdo->prepare('DELETE FROM pisonet_units WHERE id = ?');
    $deleteStmt->execute([$id]);

    if ($deleteStmt->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Pisonet was not found or has already been deleted.'
        ]);
        exit;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $unit['unit_name'] . ' was deleted successfully' . ($deletedHistory > 0 ? ', including ' . $deletedHistory . ' harvest record' . ($deletedHistory === 1 ? '' : 's') . '.' : '.')
    ]);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to delete the Pisonet and its harvest history. Please try again.'
    ]);
}

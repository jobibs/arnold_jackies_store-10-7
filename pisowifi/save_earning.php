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

$boxId = filter_input(INPUT_POST, 'pisowifi_id', FILTER_VALIDATE_INT);
$earningMonth = filter_input(INPUT_POST, 'earning_month', FILTER_VALIDATE_INT);
$earningYear = filter_input(INPUT_POST, 'earning_year', FILTER_VALIDATE_INT);
$grossRaw = trim($_POST['gross_earnings'] ?? '');
$expensesRaw = trim($_POST['expenses'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$boxId || $boxId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid PisoWiFi ID.']);
    exit;
}

if ($earningMonth === false || $earningMonth < 1 || $earningMonth > 12) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please select a valid month.']);
    exit;
}

if ($earningYear === false || $earningYear < 2000 || $earningYear > 2100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid year.']);
    exit;
}

if ($grossRaw === '' || !is_numeric($grossRaw) || (float)$grossRaw < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Gross earnings must be a valid amount greater than or equal to zero.']);
    exit;
}

if ($expensesRaw === '' || !is_numeric($expensesRaw) || (float)$expensesRaw < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Expenses must be a valid amount greater than or equal to zero.']);
    exit;
}

if (mb_strlen($notes) > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Notes cannot exceed 500 characters.']);
    exit;
}

try {
    $boxStmt = $pdo->prepare("
        SELECT id, box_name
        FROM pisowifi
        WHERE id = ?
        LIMIT 1
    ");
    $boxStmt->execute([$boxId]);
    $box = $boxStmt->fetch(PDO::FETCH_ASSOC);

    if (!$box) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'PisoWiFi box was not found.']);
        exit;
    }

    $gross = round((float)$grossRaw, 2);
    $expenses = round((float)$expensesRaw, 2);

    $stmt = $pdo->prepare("
        INSERT INTO pisowifi_earnings
            (pisowifi_id, earning_month, earning_year, gross_earnings, expenses, notes)
        VALUES
            (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            gross_earnings = VALUES(gross_earnings),
            expenses = VALUES(expenses),
            notes = VALUES(notes)
    ");

    $stmt->execute([
        $boxId,
        $earningMonth,
        $earningYear,
        $gross,
        $expenses,
        $notes !== '' ? $notes : null
    ]);

    $monthLabel = date('F Y', mktime(0, 0, 0, $earningMonth, 1, $earningYear));

    echo json_encode([
        'success' => true,
        'message' => $box['box_name'] . ' earnings for ' . $monthLabel . ' were saved successfully.',
        'gross_earnings' => $gross,
        'expenses' => $expenses,
        'net_earnings' => round($gross - $expenses, 2)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save the monthly earnings. Please try again.'
    ]);
}

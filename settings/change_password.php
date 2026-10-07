<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();


/*
|--------------------------------------------------------------------------
| AJAX Detection
|--------------------------------------------------------------------------
*/
$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);


/*
|--------------------------------------------------------------------------
| JSON Response
|--------------------------------------------------------------------------
*/
function respond(
    bool $success,
    string $message
): void {

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/
$currentUserRole = strtolower(
    trim($_SESSION['role'] ?? '')
);

$currentUserId = (int)(
    $_SESSION['user_id'] ?? 0
);


/*
|--------------------------------------------------------------------------
| Admin Check
|--------------------------------------------------------------------------
*/
if ($currentUserRole !== 'admin') {

    if ($isAjax) {

        respond(
            false,
            'Only an administrator can change user passwords.'
        );
    }

    header('Location: users.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| POST Only
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    if ($isAjax) {

        respond(
            false,
            'Invalid request.'
        );
    }

    header('Location: users.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Data
|--------------------------------------------------------------------------
*/
$userId = (int)(
    $_POST['user_id'] ?? 0
);

$password = $_POST['password'] ?? '';

$confirmPassword =
    $_POST['confirm_password'] ?? '';


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/
if ($userId <= 0) {

    respond(
        false,
        'Invalid user account.'
    );
}


if ($password === '') {

    respond(
        false,
        'Password is required.'
    );
}


if (strlen($password) < 6) {

    respond(
        false,
        'Password must be at least 6 characters.'
    );
}


if ($password !== $confirmPassword) {

    respond(
        false,
        'Passwords do not match.'
    );
}


/*
|--------------------------------------------------------------------------
| Check User Exists
|--------------------------------------------------------------------------
*/
try {

    $check = $pdo->prepare("
        SELECT
            id,
            username,
            full_name
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $check->execute([
        $userId
    ]);

    $user = $check->fetch(PDO::FETCH_ASSOC);


    if (!$user) {

        respond(
            false,
            'User account not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Hash Password
    |--------------------------------------------------------------------------
    */
    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    if ($passwordHash === false) {

        respond(
            false,
            'Unable to secure the new password.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Password
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        UPDATE users
        SET password_hash = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $passwordHash,
        $userId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */
    respond(
        true,
        'Password changed successfully.'
    );
} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | Development Error Logging
    |--------------------------------------------------------------------------
    */
    error_log(
        'Change Password Error: ' .
            $e->getMessage()
    );


    respond(
        false,
        'Unable to change the password. Please check your database configuration.'
    );
}

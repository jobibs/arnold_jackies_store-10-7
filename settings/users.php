<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

/*
|--------------------------------------------------------------------------
| Universal Admin Layout
|--------------------------------------------------------------------------
*/

$basePath = '../';
$activeMenu = 'settings';
$currentSection = 'settings';

/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$currentUserId = isset($_SESSION['user_id'])
    ? (int) $_SESSION['user_id']
    : 0;

$currentUserRole = strtolower(
    trim($_SESSION['role'] ?? 'staff')
);

$currentUserName =
    $_SESSION['full_name'] ?? 'System User';

/*
|--------------------------------------------------------------------------
| Admin Access
|--------------------------------------------------------------------------
*/

$isAdmin =
    ($currentUserRole === 'admin');

/*
|--------------------------------------------------------------------------
| AJAX Detection
|--------------------------------------------------------------------------
*/

$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower(
        trim($_SERVER['HTTP_X_REQUESTED_WITH'])
    ) === 'xmlhttprequest'
);

/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $success,
    string $message,
    array $extra = []
): void {

    while (
        ob_get_level() > 0
    ) {
        ob_end_clean();
    }

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Start Output Buffer
|--------------------------------------------------------------------------
*/

ob_start();

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| HANDLE POST ACTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $action =
        $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | ADD USER
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_user') {

        if (!$isAdmin) {

            $error =
                'Only an administrator can create user accounts.';
        } else {

            $fullName =
                trim(
                    $_POST['full_name'] ?? ''
                );

            $username =
                trim(
                    $_POST['username'] ?? ''
                );

            $role =
                strtolower(
                    trim(
                        $_POST['role'] ?? 'staff'
                    )
                );

            $password =
                $_POST['password'] ?? '';

            $confirmPassword =
                $_POST['confirm_password'] ?? '';


            if ($fullName === '') {

                $error =
                    'Full name is required.';
            } elseif ($username === '') {

                $error =
                    'Username is required.';
            } elseif (
                !preg_match(
                    '/^[A-Za-z0-9._-]{3,50}$/',
                    $username
                )
            ) {

                $error =
                    'Username must be 3-50 characters and may only contain letters, numbers, dots, underscores or hyphens.';
            } elseif (
                !in_array(
                    $role,
                    [
                        'admin',
                        'cashier',
                        'staff'
                    ],
                    true
                )
            ) {

                $error =
                    'Invalid user role selected.';
            } elseif ($password === '') {

                $error =
                    'Password is required.';
            } elseif (
                strlen($password) < 6
            ) {

                $error =
                    'Password must be at least 6 characters.';
            } elseif (
                $password !==
                $confirmPassword
            ) {

                $error =
                    'Passwords do not match.';
            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Duplicate Username
                    |--------------------------------------------------------------------------
                    */

                    $check =
                        $pdo->prepare("
                            SELECT id
                            FROM users
                            WHERE username = ?
                            LIMIT 1
                        ");

                    $check->execute([
                        $username
                    ]);

                    if ($check->fetch()) {

                        $error =
                            'Username already exists.';
                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Hash Password
                        |--------------------------------------------------------------------------
                        */

                        $passwordHash =
                            password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                        if (
                            $passwordHash === false
                        ) {

                            $error =
                                'Unable to securely create the password.';
                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | Insert
                            |--------------------------------------------------------------------------
                            */

                            $stmt =
                                $pdo->prepare("
                                    INSERT INTO users (
                                        username,
                                        password,
                                        full_name,
                                        role,
                                        is_active
                                    )
                                    VALUES (
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        1
                                    )
                                ");

                            $stmt->execute([
                                $username,
                                $passwordHash,
                                $fullName,
                                $role
                            ]);

                            $newUserId =
                                (int)
                                $pdo->lastInsertId();


                            if ($isAjax) {

                                jsonResponse(
                                    true,
                                    'User account created successfully.',
                                    [
                                        'user_id' =>
                                        $newUserId
                                    ]
                                );
                            }

                            $success =
                                'User account created successfully.';
                        }
                    }
                } catch (
                    PDOException $e
                ) {

                    error_log(
                        'Add user error: ' .
                            $e->getMessage()
                    );

                    $error =
                        $e->getCode() === '23000'
                        ? 'Username already exists.'
                        : 'Unable to create the user account.';
                }
            }
        }


        if (
            $isAjax &&
            $error !== ''
        ) {

            jsonResponse(
                false,
                $error
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT USER
    |--------------------------------------------------------------------------
    */ elseif (
        $action === 'edit_user'
    ) {

        if (!$isAdmin) {

            $error =
                'Only an administrator can edit user accounts.';
        } else {

            $userId =
                (int)(
                    $_POST['user_id'] ?? 0
                );

            $fullName =
                trim(
                    $_POST['full_name'] ?? ''
                );

            $username =
                trim(
                    $_POST['username'] ?? ''
                );

            $role =
                strtolower(
                    trim(
                        $_POST['role'] ?? 'staff'
                    )
                );


            if (
                $userId <= 0
            ) {

                $error =
                    'Invalid user account.';
            } elseif (
                $fullName === ''
            ) {

                $error =
                    'Full name is required.';
            } elseif (
                $username === ''
            ) {

                $error =
                    'Username is required.';
            } elseif (
                !preg_match(
                    '/^[A-Za-z0-9._-]{3,50}$/',
                    $username
                )
            ) {

                $error =
                    'Username must be 3-50 characters and may only contain letters, numbers, dots, underscores or hyphens.';
            } elseif (
                !in_array(
                    $role,
                    [
                        'admin',
                        'cashier',
                        'staff'
                    ],
                    true
                )
            ) {

                $error =
                    'Invalid user role selected.';
            } elseif (
                $userId ===
                $currentUserId &&
                $role !== 'admin'
            ) {

                $error =
                    'You cannot remove the administrator role from your own account.';
            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Check User
                    |--------------------------------------------------------------------------
                    */

                    $checkUser =
                        $pdo->prepare("
                            SELECT id
                            FROM users
                            WHERE id = ?
                            LIMIT 1
                        ");

                    $checkUser->execute([
                        $userId
                    ]);

                    if (
                        !$checkUser->fetch()
                    ) {

                        $error =
                            'The selected user account does not exist.';
                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Duplicate Username
                        |--------------------------------------------------------------------------
                        */

                        $check =
                            $pdo->prepare("
                                SELECT id
                                FROM users
                                WHERE username = ?
                                  AND id <> ?
                                LIMIT 1
                            ");

                        $check->execute([
                            $username,
                            $userId
                        ]);

                        if (
                            $check->fetch()
                        ) {

                            $error =
                                'Username already exists.';
                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | Update User
                            |--------------------------------------------------------------------------
                            */

                            $stmt =
                                $pdo->prepare("
                                    UPDATE users
                                    SET
                                        username = ?,
                                        full_name = ?,
                                        role = ?
                                    WHERE id = ?
                                ");

                            $stmt->execute([
                                $username,
                                $fullName,
                                $role,
                                $userId
                            ]);


                            /*
                            |--------------------------------------------------------------------------
                            | Current Session
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $userId ===
                                $currentUserId
                            ) {

                                $_SESSION['full_name'] =
                                    $fullName;

                                $_SESSION['role'] =
                                    $role;

                                $currentUserName =
                                    $fullName;

                                $currentUserRole =
                                    $role;

                                $isAdmin =
                                    (
                                        $currentUserRole
                                        ===
                                        'admin'
                                    );
                            }


                            if ($isAjax) {

                                jsonResponse(
                                    true,
                                    'User account updated successfully.'
                                );
                            }

                            $success =
                                'User account updated successfully.';
                        }
                    }
                } catch (
                    PDOException $e
                ) {

                    error_log(
                        'Edit user error: ' .
                            $e->getMessage()
                    );

                    $error =
                        $e->getCode() === '23000'
                        ? 'Username already exists.'
                        : 'Unable to update the user account.';
                }
            }
        }


        if (
            $isAjax &&
            $error !== ''
        ) {

            jsonResponse(
                false,
                $error
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CHANGE PASSWORD
    |--------------------------------------------------------------------------
    */ elseif (
        $action === 'change_password'
    ) {

        if (!$isAdmin) {

            $error =
                'Only an administrator can change another user\'s password.';
        } else {

            $userId =
                (int)(
                    $_POST['user_id'] ?? 0
                );

            $password =
                $_POST['password'] ?? '';

            $confirmPassword =
                $_POST['confirm_password'] ?? '';


            if (
                $userId <= 0
            ) {

                $error =
                    'Invalid user account.';
            } elseif (
                $password === ''
            ) {

                $error =
                    'New password is required.';
            } elseif (
                strlen($password) < 6
            ) {

                $error =
                    'Password must be at least 6 characters.';
            } elseif (
                $password !==
                $confirmPassword
            ) {

                $error =
                    'Passwords do not match.';
            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Check User
                    |--------------------------------------------------------------------------
                    */

                    $checkUser =
                        $pdo->prepare("
                            SELECT
                                id,
                                username,
                                full_name
                            FROM users
                            WHERE id = ?
                            LIMIT 1
                        ");

                    $checkUser->execute([
                        $userId
                    ]);

                    $targetUser =
                        $checkUser->fetch(
                            PDO::FETCH_ASSOC
                        );


                    if (
                        !$targetUser
                    ) {

                        $error =
                            'The selected user account does not exist.';
                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Hash
                        |--------------------------------------------------------------------------
                        */

                        $passwordHash =
                            password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );


                        if (
                            $passwordHash === false
                        ) {

                            $error =
                                'Unable to securely generate the new password.';
                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | IMPORTANT
                            | Actual database column = password
                            |--------------------------------------------------------------------------
                            */

                            $stmt =
                                $pdo->prepare("
                                    UPDATE users
                                    SET password = ?
                                    WHERE id = ?
                                ");

                            $stmt->execute([
                                $passwordHash,
                                $userId
                            ]);


                            /*
                            |--------------------------------------------------------------------------
                            | Verify
                            |--------------------------------------------------------------------------
                            */

                            $verify =
                                $pdo->prepare("
                                    SELECT password
                                    FROM users
                                    WHERE id = ?
                                    LIMIT 1
                                ");

                            $verify->execute([
                                $userId
                            ]);

                            $updatedUser =
                                $verify->fetch(
                                    PDO::FETCH_ASSOC
                                );


                            if (
                                !$updatedUser ||
                                empty($updatedUser['password'])
                            ) {

                                $error =
                                    'The password was not saved correctly.';
                            } elseif (
                                !password_verify(
                                    $password,
                                    $updatedUser['password']
                                )
                            ) {

                                $error =
                                    'The new password could not be verified after saving.';
                            } else {

                                if ($isAjax) {

                                    jsonResponse(
                                        true,
                                        'Password changed successfully.'
                                    );
                                }

                                $success =
                                    'Password changed successfully.';
                            }
                        }
                    }
                } catch (
                    PDOException $e
                ) {

                    error_log(
                        'Change password error: ' .
                            $e->getMessage()
                    );

                    $error =
                        'Database error: ' .
                        $e->getMessage();
                }
            }
        }


        if (
            $isAjax &&
            $error !== ''
        ) {

            jsonResponse(
                false,
                $error
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */ elseif (
        $action === 'toggle_status'
    ) {

        if (!$isAdmin) {

            $error =
                'Only an administrator can change user account status.';
        } else {

            $userId =
                (int)(
                    $_POST['user_id'] ?? 0
                );


            if (
                $userId <= 0
            ) {

                $error =
                    'Invalid user account.';
            } elseif (
                $userId ===
                $currentUserId
            ) {

                $error =
                    'You cannot deactivate your own account.';
            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Get Current Status
                    |--------------------------------------------------------------------------
                    */

                    $stmt =
                        $pdo->prepare("
                            SELECT
                                id,
                                full_name,
                                username,
                                is_active
                            FROM users
                            WHERE id = ?
                            LIMIT 1
                        ");

                    $stmt->execute([
                        $userId
                    ]);

                    $targetUser =
                        $stmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                    if (
                        !$targetUser
                    ) {

                        $error =
                            'The selected user account does not exist.';
                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Toggle
                        |--------------------------------------------------------------------------
                        */

                        $currentStatus =
                            (int)(
                                $targetUser['is_active']
                                ?? 0
                            );

                        $newStatus =
                            $currentStatus === 1
                            ? 0
                            : 1;


                        /*
                        |--------------------------------------------------------------------------
                        | Update
                        |--------------------------------------------------------------------------
                        */

                        $update =
                            $pdo->prepare("
                                UPDATE users
                                SET is_active = ?
                                WHERE id = ?
                            ");

                        $update->execute([
                            $newStatus,
                            $userId
                        ]);


                        /*
                        |--------------------------------------------------------------------------
                        | Verify
                        |--------------------------------------------------------------------------
                        */

                        $verify =
                            $pdo->prepare("
                                SELECT is_active
                                FROM users
                                WHERE id = ?
                                LIMIT 1
                            ");

                        $verify->execute([
                            $userId
                        ]);

                        $verifiedStatus =
                            $verify->fetchColumn();


                        if (
                            $verifiedStatus === false
                        ) {

                            $error =
                                'The account status could not be verified.';
                        } else {

                            $verifiedStatus =
                                (int)$verifiedStatus;


                            $message =
                                $verifiedStatus === 1
                                ? 'User account activated successfully.'
                                : 'User account deactivated successfully.';


                            if ($isAjax) {

                                jsonResponse(
                                    true,
                                    $message,
                                    [
                                        'user_id' =>
                                        $userId,

                                        'is_active' =>
                                        $verifiedStatus,

                                        'full_name' =>
                                        $targetUser['full_name']
                                    ]
                                );
                            }

                            $success =
                                $message;
                        }
                    }
                } catch (
                    PDOException $e
                ) {

                    error_log(
                        'Toggle status error: ' .
                            $e->getMessage()
                    );

                    $error =
                        'Unable to change the user account status.';
                }
            }
        }


        if (
            $isAjax &&
            $error !== ''
        ) {

            jsonResponse(
                false,
                $error
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UNKNOWN ACTION
    |--------------------------------------------------------------------------
    */ else {

        if ($isAjax) {

            jsonResponse(
                false,
                'Invalid request.'
            );
        }

        $error =
            'Invalid request.';
    }
}


/*
|--------------------------------------------------------------------------
| GET USERS
|--------------------------------------------------------------------------
*/

$users = [];

try {

    $stmt =
        $pdo->query("
            SELECT
                id,
                username,
                full_name,
                role,
                is_active
            FROM users
            ORDER BY
                CASE
                    WHEN role = 'admin' THEN 1
                    WHEN role = 'cashier' THEN 2
                    ELSE 3
                END,
                full_name ASC
        ");

    $users =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
} catch (
    PDOException $e
) {

    error_log(
        'Load users error: ' .
            $e->getMessage()
    );

    if (!$error) {

        $error =
            'Unable to load user accounts.';
    }
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalUsers =
    count($users);

$adminCount = 0;
$cashierCount = 0;
$staffCount = 0;

foreach (
    $users as $user
) {

    $role =
        strtolower(
            trim(
                $user['role'] ?? 'staff'
            )
        );

    if (
        $role === 'admin'
    ) {

        $adminCount++;
    } elseif (
        $role === 'cashier'
    ) {

        $cashierCount++;
    } else {

        $staffCount++;
    }
}


/*
|--------------------------------------------------------------------------
| LOW STOCK NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$notifications = [];

try {

    $notificationStmt =
        $pdo->query("
            SELECT
                id,
                product_name,
                stock,
                reorder_level,
                unit
            FROM products
            WHERE is_active = 1
              AND stock <= reorder_level
            ORDER BY
                CASE
                    WHEN stock <= 0 THEN 1
                    ELSE 2
                END,
                stock ASC,
                product_name ASC
            LIMIT 20
        ");

    $notifications =
        $notificationStmt->fetchAll(
            PDO::FETCH_ASSOC
        );
} catch (
    PDOException $e
) {

    $notifications = [];
}

$notificationCount =
    count($notifications);

?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>
        User Accounts - Arnold & Jackie's Store
    </title>


    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">


    <style>
        /* =========================================================
           PAGE
           ========================================================= */

        .users-page {
            width: 100%;
        }


        /* =========================================================
           HEADER
           ========================================================= */

        .users-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;

            gap: 20px;

            margin-bottom: 24px;
        }

        .users-title {
            margin: 0 0 5px;

            color: #172033;

            font-size: 25px;
            font-weight: 750;

            line-height: 1.2;

            letter-spacing: -.025em;
        }

        .users-subtitle {
            margin: 0;

            color: #7b8494;

            font-size: 13px;
            line-height: 1.6;
        }

        .users-header-actions {
            display: flex;
            align-items: center;

            gap: 9px;

            flex-wrap: wrap;
        }

        .users-header-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 39px;

            padding: 0 14px;

            border: 1px solid #dce3eb;
            border-radius: 9px;

            background: #ffffff;
            color: #475569;

            text-decoration: none;

            font-size: 12px;
            font-weight: 700;

            cursor: pointer;

            transition:
                background-color .16s ease,
                border-color .16s ease,
                color .16s ease,
                transform .16s ease;
        }

        .users-header-button:hover {
            background: #f8fafc;

            border-color: #cfd8e4;

            color: #111827;

            transform:
                translateY(-1px);
        }

        .users-header-button-primary {
            border-color: #2563eb;

            background: #2563eb;
            color: #ffffff;

            box-shadow:
                0 6px 16px rgba(37, 99, 235, .15);
        }

        .users-header-button-primary:hover {
            border-color: #1d4ed8;

            background: #1d4ed8;
            color: #ffffff;
        }


        /* =========================================================
           SUMMARY
           ========================================================= */

        .users-summary {
            display: grid;

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 13px;

            margin-bottom: 18px;
        }

        .users-summary-card {
            display: flex;
            align-items: center;

            gap: 12px;

            min-height: 76px;

            padding: 15px;

            border: 1px solid #e5eaf0;
            border-radius: 13px;

            background: #ffffff;

            box-shadow:
                0 5px 18px rgba(15, 23, 42, .025);
        }

        .users-summary-icon {
            width: 38px;
            height: 38px;

            flex: 0 0 38px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 10px;

            font-size: 15px;
            font-weight: 800;
        }

        .users-summary-icon-blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .users-summary-icon-purple {
            background: #f5f3ff;
            color: #7c3aed;
        }

        .users-summary-icon-orange {
            background: #fff7ed;
            color: #ea580c;
        }

        .users-summary-icon-green {
            background: #ecfdf5;
            color: #16a34a;
        }

        .users-summary-label {
            margin: 0 0 2px;

            color: #8b94a3;

            font-size: 9px;
            font-weight: 800;

            letter-spacing: .06em;

            text-transform: uppercase;
        }

        .users-summary-value {
            margin: 0;

            color: #202938;

            font-size: 19px;
            font-weight: 750;

            line-height: 1.2;
        }


        /* =========================================================
           TABLE
           ========================================================= */

        .users-card {
            overflow: hidden;

            border: 1px solid #e5eaf0;
            border-radius: 15px;

            background: #ffffff;

            box-shadow:
                0 7px 24px rgba(15, 23, 42, .025);
        }

        .users-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 15px;

            padding: 17px 18px;

            border-bottom:
                1px solid #edf0f4;
        }

        .users-card-title {
            margin: 0;

            color: #202938;

            font-size: 14px;
            font-weight: 750;
        }

        .users-card-subtitle {
            margin: 3px 0 0;

            color: #8b94a3;

            font-size: 10px;
        }

        .users-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 27px;

            padding: 0 9px;

            border-radius: 8px;

            background: #eff6ff;
            color: #2563eb;

            font-size: 10px;
            font-weight: 800;
        }

        .users-table-wrapper {
            width: 100%;

            overflow-x: auto;
        }

        .users-table {
            width: 100%;

            min-width: 900px;

            border-collapse: collapse;
        }

        .users-table th {
            padding: 12px 16px;

            background: #f8fafc;

            border-bottom:
                1px solid #e5e7eb;

            color: #717b8b;

            font-size: 9px;
            font-weight: 800;

            letter-spacing: .06em;

            text-transform: uppercase;

            text-align: left;

            white-space: nowrap;
        }

        .users-table td {
            padding: 14px 16px;

            border-bottom:
                1px solid #f0f2f5;

            color: #273142;

            font-size: 11px;

            vertical-align: middle;
        }

        .users-table tbody tr:last-child td {
            border-bottom: none;
        }

        .users-table tbody tr {
            transition:
                background-color .15s ease;
        }

        .users-table tbody tr:hover {
            background: #fbfdff;
        }


        /* =========================================================
           USER
           ========================================================= */

        .user-cell {
            display: flex;
            align-items: center;

            gap: 11px;

            min-width: 210px;
        }

        .user-avatar {
            width: 34px;
            height: 34px;

            flex: 0 0 34px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 10px;

            background: #eff6ff;
            color: #2563eb;

            font-size: 11px;
            font-weight: 800;

            text-transform: uppercase;
        }

        .user-name {
            margin: 0 0 2px;

            color: #202938;

            font-size: 12px;
            font-weight: 750;
        }

        .user-username {
            margin: 0;

            color: #8b94a3;

            font-size: 10px;
        }


        /* =========================================================
           CURRENT USER
           ========================================================= */

        .current-user-badge {
            display: inline-flex;
            align-items: center;

            gap: 5px;

            margin-left: 6px;

            min-height: 19px;

            padding: 0 7px;

            border:
                1px solid #dbeafe;

            border-radius: 999px;

            background: #f8fbff;
            color: #2563eb;

            font-size: 8px;
            font-weight: 800;

            text-transform: uppercase;
        }

        .current-user-dot {
            width: 5px;
            height: 5px;

            border-radius: 50%;

            background: #22c55e;
        }


        /* =========================================================
           ROLE BADGES
           ========================================================= */

        .user-role-badge {
            display: inline-flex;
            align-items: center;

            min-height: 22px;

            padding: 0 8px;

            border-radius: 999px;

            font-size: 9px;
            font-weight: 800;

            letter-spacing: .04em;

            text-transform: uppercase;
        }

        .user-role-admin {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .user-role-cashier {
            background: #fff7ed;
            color: #c2410c;
        }

        .user-role-staff {
            background: #ecfdf5;
            color: #15803d;
        }


        /* =========================================================
           STATUS BADGES
           ========================================================= */

        .user-status-badge {
            display: inline-flex;
            align-items: center;

            gap: 5px;

            min-height: 22px;

            padding: 0 8px;

            border-radius: 999px;

            font-size: 9px;
            font-weight: 800;
        }

        .user-status-active {
            background: #ecfdf5;
            color: #15803d;
        }

        .user-status-inactive {
            background: #fef2f2;
            color: #b91c1c;
        }

        .user-status-dot {
            width: 5px;
            height: 5px;

            border-radius: 50%;

            background: currentColor;
        }


        /* =========================================================
           ACTIONS
           ========================================================= */

        .user-actions {
            display: flex;
            align-items: center;

            gap: 6px;

            flex-wrap: wrap;
        }

        .user-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 30px;

            padding: 0 9px;

            border-radius: 7px;

            font-size: 10px;
            font-weight: 700;

            cursor: pointer;

            white-space: nowrap;

            transition:
                background-color .15s ease,
                color .15s ease,
                border-color .15s ease,
                transform .15s ease,
                opacity .15s ease;
        }

        .user-action-button:hover:not(:disabled) {
            transform:
                translateY(-1px);
        }

        .user-action-button:disabled {
            cursor: wait;
            opacity: .65;
        }

        .user-action-edit {
            border:
                1px solid #dbeafe;

            background: #eff6ff;
            color: #2563eb;
        }

        .user-action-edit:hover {
            border-color: #bfdbfe;

            background: #dbeafe;

            color: #1d4ed8;
        }

        .user-action-password {
            border:
                1px solid #e2e8f0;

            background: #f8fafc;
            color: #475569;
        }

        .user-action-password:hover {
            border-color: #cbd5e1;

            background: #f1f5f9;

            color: #1f2937;
        }

        .user-action-deactivate {
            border:
                1px solid #fecaca;

            background: #fff5f5;
            color: #dc2626;
        }

        .user-action-deactivate:hover {
            border-color: #fca5a5;

            background: #fee2e2;

            color: #b91c1c;
        }

        .user-action-activate {
            border:
                1px solid #bbf7d0;

            background: #f0fdf4;
            color: #16a34a;
        }

        .user-action-activate:hover {
            border-color: #86efac;

            background: #dcfce7;

            color: #15803d;
        }

        .user-action-current {
            border:
                1px solid #e2e8f0;

            background: #f8fafc;
            color: #94a3b8;

            cursor: not-allowed;
        }


        /* =========================================================
           EMPTY
           ========================================================= */

        .users-empty {
            padding: 45px 20px !important;

            text-align: center;

            color: #8b94a3 !important;
        }

        .users-empty-icon {
            width: 44px;
            height: 44px;

            margin: 0 auto 10px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #f1f5f9;
            color: #94a3b8;

            font-size: 18px;
        }


        /* =========================================================
           MODAL
           ========================================================= */

        body.users-modal-open {
            overflow: hidden;
        }

        .users-modal-backdrop {
            position: fixed;
            inset: 0;

            z-index: 1080;

            display: flex;

            align-items: center;
            justify-content: center;

            padding: 20px;

            background:
                rgba(15, 23, 42, .58);

            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);

            opacity: 0;

            visibility: hidden;

            pointer-events: none;

            transition:
                opacity .22s ease,
                visibility 0s linear .22s;
        }

        .users-modal-backdrop.is-open {
            opacity: 1;

            visibility: visible;

            pointer-events: auto;

            transition:
                opacity .22s ease,
                visibility 0s linear 0s;
        }

        .users-modal {
            position: relative;

            width: min(560px, 100%);

            max-height:
                calc(100vh - 40px);

            display: flex;
            flex-direction: column;

            overflow: hidden;

            border:
                1px solid rgba(226, 232, 240, .9);

            border-radius: 18px;

            background: #ffffff;

            box-shadow:
                0 28px 80px rgba(15, 23, 42, .22);

            transform:
                translate3d(0, 14px, 0) scale(.975);

            opacity: .96;

            transition:
                transform .26s cubic-bezier(.22, 1, .36, 1),
                opacity .18s ease;
        }

        .users-modal-backdrop.is-open .users-modal {
            transform:
                translate3d(0, 0, 0) scale(1);

            opacity: 1;
        }

        .users-modal-header {
            display: flex;

            align-items: flex-start;
            justify-content: space-between;

            gap: 15px;

            padding:
                20px 22px 16px;

            border-bottom:
                1px solid #edf0f4;
        }

        .users-modal-eyebrow {
            margin-bottom: 5px;

            color: #64748b;

            font-size: 9px;
            font-weight: 800;

            letter-spacing: .08em;

            text-transform: uppercase;
        }

        .users-modal-title {
            margin: 0;

            color: #111827;

            font-size: 19px;
            font-weight: 750;

            letter-spacing: -.02em;
        }

        .users-modal-subtitle {
            margin: 5px 0 0;

            color: #64748b;

            font-size: 11px;

            line-height: 1.5;
        }

        .users-modal-close {
            width: 34px;
            height: 34px;

            flex: 0 0 34px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            border:
                1px solid #e2e8f0;

            border-radius: 9px;

            background: #f8fafc;
            color: #475569;

            font-size: 19px;

            cursor: pointer;

            transition:
                background-color .15s ease,
                color .15s ease,
                transform .15s ease;
        }

        .users-modal-close:hover {
            background: #f1f5f9;

            color: #111827;

            transform:
                translateY(-1px);
        }

        .users-modal-body {
            overflow-y: auto;

            padding:
                21px 22px 22px;
        }


        /* =========================================================
           FORM
           ========================================================= */

        .users-form {
            margin: 0;
        }

        .users-form-row {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 15px;
        }

        .users-form-group {
            min-width: 0;

            margin-bottom: 15px;
        }

        .users-form-full {
            grid-column:
                1 / -1;
        }

        .users-form-label {
            display: block;

            margin-bottom: 6px;

            color: #334155;

            font-size: 10px;
            font-weight: 800;
        }

        .users-form-label-required::after {
            content: " *";

            color: #ef4444;
        }

        .users-form-input,
        .users-form-select {
            width: 100%;
            height: 42px;

            box-sizing: border-box;

            padding: 0 12px;

            border:
                1px solid #dbe2ea;

            border-radius: 9px;

            outline: none;

            background: #ffffff;
            color: #172033;

            font: inherit;

            font-size: 12px;
            font-weight: 500;

            transition:
                border-color .16s ease,
                box-shadow .16s ease,
                background-color .16s ease;
        }

        .users-form-input:hover,
        .users-form-select:hover {
            border-color:
                #cbd5e1;
        }

        .users-form-input:focus,
        .users-form-select:focus {
            border-color:
                #2563eb;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, .10);
        }

        .users-form-input::placeholder {
            color: #a1aab8;
        }

        .users-password-wrap {
            position: relative;
        }

        .users-password-wrap .users-form-input {
            padding-right: 60px;
        }

        .users-password-toggle {
            position: absolute;

            top: 50%;
            right: 9px;

            transform:
                translateY(-50%);

            min-height: 26px;

            padding: 0 7px;

            border: none;

            border-radius: 6px;

            background: transparent;
            color: #64748b;

            font-size: 9px;
            font-weight: 700;

            cursor: pointer;
        }

        .users-password-toggle:hover {
            background:
                #f1f5f9;

            color:
                #334155;
        }

        .users-form-help {
            margin-top: 5px;

            color: #94a3b8;

            font-size: 9px;

            line-height: 1.45;
        }


        /* =========================================================
           ERROR
           ========================================================= */

        .users-modal-error {
            display: none;

            margin-bottom: 16px;

            padding: 10px 12px;

            border:
                1px solid #fecaca;

            border-radius: 9px;

            background: #fff1f2;
            color: #b91c1c;

            font-size: 10px;
            font-weight: 650;

            line-height: 1.45;
        }

        .users-modal-error.is-visible {
            display: block;

            animation:
                usersErrorIn .18s ease both;
        }

        @keyframes usersErrorIn {

            from {
                opacity: 0;
                transform:
                    translateY(-4px);
            }

            to {
                opacity: 1;
                transform:
                    translateY(0);
            }

        }


        /* =========================================================
           MODAL ACTIONS
           ========================================================= */

        .users-modal-actions {
            display: flex;

            align-items: center;

            justify-content: flex-end;

            gap: 8px;

            margin-top: 5px;

            padding-top: 16px;

            border-top:
                1px solid #edf0f4;
        }

        .users-modal-button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            min-height: 39px;

            padding: 0 15px;

            border-radius: 9px;

            font: inherit;

            font-size: 11px;
            font-weight: 750;

            cursor: pointer;

            transition:
                background-color .15s ease,
                border-color .15s ease,
                color .15s ease,
                transform .15s ease,
                box-shadow .15s ease;
        }

        .users-modal-button:hover:not(:disabled) {
            transform:
                translateY(-1px);
        }

        .users-modal-button-secondary {
            border:
                1px solid #dbe2ea;

            background: #ffffff;
            color: #475569;
        }

        .users-modal-button-secondary:hover {
            border-color:
                #cbd5e1;

            background:
                #f8fafc;

            color:
                #111827;
        }

        .users-modal-button-primary {
            min-width: 108px;

            border:
                1px solid #2563eb;

            background: #2563eb;
            color: #ffffff;

            box-shadow:
                0 5px 14px rgba(37, 99, 235, .14);
        }

        .users-modal-button-primary:hover {
            border-color:
                #1d4ed8;

            background:
                #1d4ed8;

            box-shadow:
                0 7px 18px rgba(37, 99, 235, .18);
        }

        .users-modal-button-primary:disabled {
            opacity: .6;

            cursor: wait;

            transform: none;

            box-shadow: none;
        }


        /* =========================================================
           CONFIRM MODAL
           ========================================================= */

        .status-confirm-icon {
            width: 46px;
            height: 46px;

            margin-bottom: 14px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 14px;

            background: #fff7ed;
            color: #ea580c;

            font-size: 18px;
            font-weight: 900;
        }

        .status-confirm-text {
            margin: 0;

            color: #64748b;

            font-size: 11px;

            line-height: 1.55;
        }

        .status-confirm-name {
            color: #172033;
            font-weight: 800;
        }


        /* =========================================================
           TOAST
           ========================================================= */

        .users-toast-container {
            position: fixed;

            top: 22px;
            right: 22px;

            z-index: 99999;

            display: flex;
            flex-direction: column;

            gap: 10px;

            pointer-events: none;
        }

        .users-toast {
            width:
                min(370px,
                    calc(100vw - 32px));

            display: flex;

            align-items: center;

            gap: 11px;

            padding: 12px 14px;

            border:
                1px solid #bbf7d0;

            border-radius: 13px;

            background: #ffffff;

            box-shadow:
                0 16px 42px rgba(15, 23, 42, .13);

            opacity: 0;

            transform:
                translate3d(24px, -8px, 0);

            transition:
                transform .32s cubic-bezier(.22, 1, .36, 1),
                opacity .22s ease;

            pointer-events: auto;
        }

        .users-toast.is-visible {
            opacity: 1;

            transform:
                translate3d(0, 0, 0);
        }

        .users-toast.is-closing {
            opacity: 0;

            transform:
                translate3d(20px, -6px, 0);
        }

        .users-toast-icon {
            width: 32px;
            height: 32px;

            flex: 0 0 32px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #ecfdf5;
            color: #16a34a;

            font-size: 15px;
            font-weight: 900;
        }

        .users-toast-content {
            min-width: 0;
        }

        .users-toast-title {
            margin-bottom: 2px;

            color: #166534;

            font-size: 11px;
            font-weight: 800;
        }

        .users-toast-message {
            color: #64748b;

            font-size: 10px;

            line-height: 1.45;
        }


        /* =========================================================
           RESPONSIVE
           ========================================================= */

        @media (max-width: 1050px) {

            .users-summary {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 700px) {

            .users-header {
                flex-direction: column;
            }

            .users-header-actions {
                width: 100%;
            }

            .users-header-button {
                flex: 1;
            }

            .users-summary {
                grid-template-columns: 1fr;
            }

            .users-form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .users-form-full {
                grid-column: auto;
            }

        }

        @media (max-width: 520px) {

            .users-title {
                font-size: 22px;
            }

            .users-card-header {
                align-items:
                    flex-start;
            }

            .users-modal-backdrop {
                align-items:
                    flex-end;

                padding: 0;
            }

            .users-modal {
                width: 100%;

                max-height:
                    94vh;

                border-radius:
                    19px 19px 0 0;

                transform:
                    translate3d(0, 24px, 0);
            }

            .users-modal-backdrop.is-open .users-modal {
                transform:
                    translate3d(0, 0, 0);
            }

            .users-modal-header,
            .users-modal-body {
                padding-left: 18px;
                padding-right: 18px;
            }

            .users-modal-actions {
                flex-direction:
                    column-reverse;

                align-items:
                    stretch;
            }

            .users-modal-button {
                width: 100%;
            }

            .users-toast-container {
                top: 14px;

                left: 14px;
                right: 14px;
            }

            .users-toast {
                width: 100%;
            }

        }
    </style>

</head>


<body>


    <?php

    /*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

    require_once
        $basePath .
        'includes/sidebar.php';

    ?>


    <div class="main">


        <?php

        /*
|--------------------------------------------------------------------------
| TOPBAR
|--------------------------------------------------------------------------
*/

        require_once
            $basePath .
            'includes/topbar.php';

        ?>


        <main class="content">


            <div class="users-page">


                <!-- =========================================================
         HEADER
         ========================================================= -->

                <div class="users-header">


                    <div>

                        <h1 class="users-title">
                            User Accounts
                        </h1>

                        <p class="users-subtitle">
                            Manage the people who can access Arnold & Jackie's Store.
                        </p>

                    </div>


                    <div class="users-header-actions">


                        <a
                            href="index.php"
                            class="users-header-button">

                            ← Settings

                        </a>


                        <?php if ($isAdmin): ?>

                            <button
                                type="button"
                                class="
                        users-header-button
                        users-header-button-primary
                    "
                                id="openAddUserModal">

                                + Add User

                            </button>

                        <?php endif; ?>


                    </div>


                </div>


                <!-- =========================================================
         SUMMARY
         ========================================================= -->

                <div class="users-summary">


                    <div class="users-summary-card">

                        <div
                            class="
                    users-summary-icon
                    users-summary-icon-blue
                ">

                            👥

                        </div>

                        <div>

                            <p class="users-summary-label">
                                Total Users
                            </p>

                            <p class="users-summary-value">

                                <?= number_format(
                                    $totalUsers
                                ) ?>

                            </p>

                        </div>

                    </div>


                    <div class="users-summary-card">

                        <div
                            class="
                    users-summary-icon
                    users-summary-icon-purple
                ">

                            🛡

                        </div>

                        <div>

                            <p class="users-summary-label">
                                Administrators
                            </p>

                            <p class="users-summary-value">

                                <?= number_format(
                                    $adminCount
                                ) ?>

                            </p>

                        </div>

                    </div>


                    <div class="users-summary-card">

                        <div
                            class="
                    users-summary-icon
                    users-summary-icon-orange
                ">

                            💳

                        </div>

                        <div>

                            <p class="users-summary-label">
                                Cashiers
                            </p>

                            <p class="users-summary-value">

                                <?= number_format(
                                    $cashierCount
                                ) ?>

                            </p>

                        </div>

                    </div>


                    <div class="users-summary-card">

                        <div
                            class="
                    users-summary-icon
                    users-summary-icon-green
                ">

                            👤

                        </div>

                        <div>

                            <p class="users-summary-label">
                                Staff
                            </p>

                            <p class="users-summary-value">

                                <?= number_format(
                                    $staffCount
                                ) ?>

                            </p>

                        </div>

                    </div>


                </div>


                <!-- =========================================================
         USER TABLE
         ========================================================= -->

                <div class="users-card">


                    <div class="users-card-header">


                        <div>

                            <p class="users-card-title">
                                System Users
                            </p>

                            <p class="users-card-subtitle">
                                Accounts currently registered in the system.
                            </p>

                        </div>


                        <div class="users-count">

                            <?= number_format(
                                $totalUsers
                            ) ?>

                            <?= $totalUsers === 1
                                ? 'User'
                                : 'Users'
                            ?>

                        </div>


                    </div>


                    <div class="users-table-wrapper">


                        <table
                            class="users-table">


                            <thead>

                                <tr>

                                    <th>
                                        User
                                    </th>

                                    <th>
                                        Username
                                    </th>

                                    <th>
                                        Role
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $users
                                    as $user
                                ): ?>


                                    <?php

                                    $userId =
                                        (int)$user['id'];

                                    $fullName =
                                        trim(
                                            $user['full_name']
                                                ?? 'User'
                                        );

                                    $username =
                                        $user['username']
                                        ?? '';

                                    $role =
                                        strtolower(
                                            trim(
                                                $user['role']
                                                    ?? 'staff'
                                            )
                                        );

                                    $isActive =
                                        (int)(
                                            $user['is_active']
                                            ?? 0
                                        ) === 1;

                                    $isCurrentUser =
                                        $userId ===
                                        $currentUserId;

                                    $initial =
                                        strtoupper(
                                            substr(
                                                $fullName,
                                                0,
                                                1
                                            )
                                        );

                                    ?>


                                    <tr
                                        data-user-row="<?= $userId ?>">


                                        <!-- USER -->

                                        <td>


                                            <div
                                                class="user-cell">


                                                <div
                                                    class="user-avatar">

                                                    <?= htmlspecialchars(
                                                        $initial,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </div>


                                                <div>


                                                    <p
                                                        class="user-name">

                                                        <?= htmlspecialchars(
                                                            $fullName,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>


                                                        <?php if (
                                                            $isCurrentUser
                                                        ): ?>

                                                            <span
                                                                class="
                                                    current-user-badge
                                                ">

                                                                <span
                                                                    class="
                                                        current-user-dot
                                                    ">
                                                                </span>

                                                                You

                                                            </span>

                                                        <?php endif; ?>


                                                    </p>


                                                    <p
                                                        class="user-username">

                                                        Account ID:
                                                        <?= $userId ?>

                                                    </p>


                                                </div>


                                            </div>


                                        </td>


                                        <!-- USERNAME -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $username,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </td>


                                        <!-- ROLE -->

                                        <td>


                                            <?php if (
                                                $role === 'admin'
                                            ): ?>

                                                <span
                                                    class="
                                        user-role-badge
                                        user-role-admin
                                    ">

                                                    Administrator

                                                </span>


                                            <?php elseif (
                                                $role === 'cashier'
                                            ): ?>

                                                <span
                                                    class="
                                        user-role-badge
                                        user-role-cashier
                                    ">

                                                    Cashier

                                                </span>


                                            <?php else: ?>

                                                <span
                                                    class="
                                        user-role-badge
                                        user-role-staff
                                    ">

                                                    Staff

                                                </span>

                                            <?php endif; ?>


                                        </td>


                                        <!-- STATUS -->

                                        <td>


                                            <span
                                                class="
                                    user-status-badge
                                    <?= $isActive
                                        ? 'user-status-active'
                                        : 'user-status-inactive'
                                    ?>
                                "
                                                data-status-badge="<?= $userId ?>">


                                                <span
                                                    class="
                                        user-status-dot
                                    ">
                                                </span>


                                                <span
                                                    data-status-text="<?= $userId ?>">

                                                    <?= $isActive
                                                        ? 'Active'
                                                        : 'Inactive'
                                                    ?>

                                                </span>


                                            </span>


                                        </td>


                                        <!-- ACTIONS -->

                                        <td>


                                            <?php if (
                                                $isAdmin
                                            ): ?>


                                                <div
                                                    class="user-actions">


                                                    <!-- EDIT -->

                                                    <button
                                                        type="button"
                                                        class="
                                            user-action-button
                                            user-action-edit
                                        "
                                                        data-action="edit"
                                                        data-user-id="<?= $userId ?>"
                                                        data-full-name="<?= htmlspecialchars(
                                                                            $fullName,
                                                                            ENT_QUOTES,
                                                                            'UTF-8'
                                                                        ) ?>"
                                                        data-username="<?= htmlspecialchars(
                                                                            $username,
                                                                            ENT_QUOTES,
                                                                            'UTF-8'
                                                                        ) ?>"
                                                        data-role="<?= htmlspecialchars(
                                                                        $role,
                                                                        ENT_QUOTES,
                                                                        'UTF-8'
                                                                    ) ?>">

                                                        Edit

                                                    </button>


                                                    <!-- PASSWORD -->

                                                    <button
                                                        type="button"
                                                        class="
                                            user-action-button
                                            user-action-password
                                        "
                                                        data-action="password"
                                                        data-user-id="<?= $userId ?>"
                                                        data-full-name="<?= htmlspecialchars(
                                                                            $fullName,
                                                                            ENT_QUOTES,
                                                                            'UTF-8'
                                                                        ) ?>">

                                                        Password

                                                    </button>


                                                    <!-- CURRENT ACCOUNT -->

                                                    <?php if (
                                                        $isCurrentUser
                                                    ): ?>

                                                        <button
                                                            type="button"
                                                            class="
                                                user-action-button
                                                user-action-current
                                            "
                                                            disabled>

                                                            Current Account

                                                        </button>


                                                        <!-- DEACTIVATE -->

                                                    <?php elseif (
                                                        $isActive
                                                    ): ?>

                                                        <button
                                                            type="button"
                                                            class="
                                                user-action-button
                                                user-action-deactivate
                                            "
                                                            data-action="toggle-status"
                                                            data-user-id="<?= $userId ?>"
                                                            data-user-name="<?= htmlspecialchars(
                                                                                $fullName,
                                                                                ENT_QUOTES,
                                                                                'UTF-8'
                                                                            ) ?>"
                                                            data-active="1">

                                                            Deactivate

                                                        </button>


                                                        <!-- ACTIVATE -->

                                                    <?php else: ?>

                                                        <button
                                                            type="button"
                                                            class="
                                                user-action-button
                                                user-action-activate
                                            "
                                                            data-action="toggle-status"
                                                            data-user-id="<?= $userId ?>"
                                                            data-user-name="<?= htmlspecialchars(
                                                                                $fullName,
                                                                                ENT_QUOTES,
                                                                                'UTF-8'
                                                                            ) ?>"
                                                            data-active="0">

                                                            Activate

                                                        </button>

                                                    <?php endif; ?>


                                                </div>


                                            <?php else: ?>


                                                <span
                                                    style="
                                        color:#94a3b8;
                                        font-size:10px;
                                        font-weight:600;
                                    ">

                                                    Admin only

                                                </span>


                                            <?php endif; ?>


                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                                <?php if (
                                    !$users
                                ): ?>


                                    <tr>

                                        <td
                                            colspan="5"
                                            class="users-empty">

                                            <div
                                                class="users-empty-icon">

                                                👤

                                            </div>


                                            <strong>
                                                No user accounts found
                                            </strong>


                                            <div>
                                                There are currently no users in the system.
                                            </div>


                                        </td>

                                    </tr>


                                <?php endif; ?>


                            </tbody>


                        </table>


                    </div>


                </div>


                <!-- =========================================================
         ACCESS STATUS
         ========================================================= -->

                <div
                    style="
            display:grid;
            grid-template-columns:minmax(0,1fr) auto;
            align-items:center;
            gap:18px;
            margin-top:18px;
            padding:15px 17px;
            border:1px solid #e5eaf0;
            border-radius:13px;
            background:#ffffff;
        ">


                    <div>

                        <div
                            style="
                    margin-bottom:3px;
                    color:#253044;
                    font-size:11px;
                    font-weight:750;
                ">

                            Access management

                        </div>


                        <div
                            style="
                    color:#7f8998;
                    font-size:10px;
                    line-height:1.55;
                ">

                            <?= $isAdmin
                                ? 'You are signed in as an administrator and can manage system user accounts.'
                                : 'Your account can use the system, but only administrators can manage user accounts.'
                            ?>

                        </div>

                    </div>


                    <?php if (
                        $notificationCount > 0
                    ): ?>

                        <a
                            href="<?= $basePath ?>products/"
                            style="
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    gap:7px;
                    min-height:32px;
                    padding:0 10px;
                    border:1px solid #fed7aa;
                    border-radius:9px;
                    background:#fffaf5;
                    color:#c2410c;
                    text-decoration:none;
                    font-size:9px;
                    font-weight:750;
                    white-space:nowrap;
                ">

                            ●

                            <?= number_format(
                                $notificationCount
                            ) ?>

                            low-stock alert<?= $notificationCount === 1 ? '' : 's' ?>

                        </a>

                    <?php endif; ?>


                </div>


            </div>


        </main>


    </div>


    <!-- =========================================================
     ADD / EDIT USER MODAL
     ========================================================= -->

    <div
        class="users-modal-backdrop"
        id="userModal"
        aria-hidden="true">


        <section
            class="users-modal"
            role="dialog"
            aria-modal="true">


            <div
                class="users-modal-header">


                <div>

                    <div
                        class="users-modal-eyebrow">

                        Access Management

                    </div>


                    <h2
                        class="users-modal-title"
                        id="userModalTitle">

                        Add User

                    </h2>


                    <p
                        class="users-modal-subtitle"
                        id="userModalSubtitle">

                        Create a new account for someone who will use the store system.

                    </p>


                </div>


                <button
                    type="button"
                    class="users-modal-close"
                    id="closeUserModal">

                    ×

                </button>


            </div>


            <div
                class="users-modal-body">


                <div
                    class="users-modal-error"
                    id="userModalError">
                </div>


                <form
                    method="post"
                    action="users.php"
                    id="userForm"
                    class="users-form">


                    <input
                        type="hidden"
                        name="action"
                        id="userFormAction"
                        value="add_user">


                    <input
                        type="hidden"
                        name="user_id"
                        id="userFormUserId"
                        value="">


                    <div
                        class="users-form-row">


                        <!-- FULL NAME -->

                        <div
                            class="
                            users-form-group
                            users-form-full
                        ">

                            <label
                                for="userFullName"
                                class="
                                users-form-label
                                users-form-label-required
                            ">

                                Full Name

                            </label>


                            <input
                                type="text"
                                id="userFullName"
                                name="full_name"
                                class="users-form-input"
                                maxlength="100"
                                required
                                placeholder="e.g. Juan Dela Cruz">

                        </div>


                        <!-- USERNAME -->

                        <div
                            class="users-form-group">

                            <label
                                for="userUsername"
                                class="
                                users-form-label
                                users-form-label-required
                            ">

                                Username

                            </label>


                            <input
                                type="text"
                                id="userUsername"
                                name="username"
                                class="users-form-input"
                                maxlength="50"
                                required
                                placeholder="e.g. juan.staff">


                            <div
                                class="users-form-help">

                                Letters, numbers, dots, underscores and hyphens.

                            </div>

                        </div>


                        <!-- ROLE -->

                        <div
                            class="users-form-group">

                            <label
                                for="userRole"
                                class="
                                users-form-label
                                users-form-label-required
                            ">

                                Role

                            </label>


                            <select
                                id="userRole"
                                name="role"
                                class="users-form-select"
                                required>

                                <option value="staff">
                                    Staff
                                </option>

                                <option value="cashier">
                                    Cashier
                                </option>

                                <option value="admin">
                                    Administrator
                                </option>

                            </select>

                        </div>


                        <!-- PASSWORD -->

                        <div
                            class="users-form-group"
                            id="userPasswordGroup">


                            <label
                                for="userPassword"
                                class="
                                users-form-label
                                users-form-label-required
                            ">

                                Password

                            </label>


                            <div
                                class="users-password-wrap">


                                <input
                                    type="password"
                                    id="userPassword"
                                    name="password"
                                    class="users-form-input"
                                    minlength="6"
                                    autocomplete="new-password"
                                    placeholder="Enter password">


                                <button
                                    type="button"
                                    class="users-password-toggle"
                                    data-target="userPassword">

                                    Show

                                </button>


                            </div>


                            <div
                                class="users-form-help">

                                Minimum of 6 characters.

                            </div>


                        </div>


                        <!-- CONFIRM PASSWORD -->

                        <div
                            class="users-form-group"
                            id="userConfirmPasswordGroup">


                            <label
                                for="userConfirmPassword"
                                class="
                                users-form-label
                                users-form-label-required
                            ">

                                Confirm Password

                            </label>


                            <div
                                class="users-password-wrap">


                                <input
                                    type="password"
                                    id="userConfirmPassword"
                                    name="confirm_password"
                                    class="users-form-input"
                                    minlength="6"
                                    autocomplete="new-password"
                                    placeholder="Repeat password">


                                <button
                                    type="button"
                                    class="users-password-toggle"
                                    data-target="userConfirmPassword">

                                    Show

                                </button>


                            </div>


                        </div>


                    </div>


                    <div
                        class="users-modal-actions">


                        <button
                            type="button"
                            class="
                            users-modal-button
                            users-modal-button-secondary
                        "
                            id="cancelUserModal">

                            Cancel

                        </button>


                        <button
                            type="submit"
                            class="
                            users-modal-button
                            users-modal-button-primary
                        "
                            id="saveUserButton">

                            Create User

                        </button>


                    </div>


                </form>


            </div>


        </section>


    </div>


    <!-- =========================================================
     PASSWORD MODAL
     ========================================================= -->

    <div
        class="users-modal-backdrop"
        id="passwordModal"
        aria-hidden="true">


        <section
            class="users-modal"
            role="dialog"
            aria-modal="true">


            <div
                class="users-modal-header">


                <div>

                    <div
                        class="users-modal-eyebrow">

                        Account Management

                    </div>


                    <h2
                        class="users-modal-title">

                        Change Password

                    </h2>


                    <p
                        class="users-modal-subtitle"
                        id="passwordModalSubtitle">

                        Set a new password for this account.

                    </p>


                </div>


                <button
                    type="button"
                    class="users-modal-close"
                    id="closePasswordModal">

                    ×

                </button>


            </div>


            <div
                class="users-modal-body">


                <div
                    class="users-modal-error"
                    id="passwordModalError">
                </div>


                <form
                    method="post"
                    action="users.php"
                    id="passwordForm"
                    class="users-form">


                    <input
                        type="hidden"
                        name="action"
                        value="change_password">


                    <input
                        type="hidden"
                        name="user_id"
                        id="passwordUserId">


                    <div
                        class="users-form-group">


                        <label
                            for="newPassword"
                            class="
                            users-form-label
                            users-form-label-required
                        ">

                            New Password

                        </label>


                        <div
                            class="users-password-wrap">


                            <input
                                type="password"
                                id="newPassword"
                                name="password"
                                class="users-form-input"
                                minlength="6"
                                autocomplete="new-password"
                                required
                                placeholder="Enter new password">


                            <button
                                type="button"
                                class="users-password-toggle"
                                data-target="newPassword">

                                Show

                            </button>


                        </div>


                        <div
                            class="users-form-help">

                            Minimum of 6 characters.

                        </div>


                    </div>


                    <div
                        class="users-form-group">


                        <label
                            for="newConfirmPassword"
                            class="
                            users-form-label
                            users-form-label-required
                        ">

                            Confirm Password

                        </label>


                        <div
                            class="users-password-wrap">


                            <input
                                type="password"
                                id="newConfirmPassword"
                                name="confirm_password"
                                class="users-form-input"
                                minlength="6"
                                autocomplete="new-password"
                                required
                                placeholder="Repeat new password">


                            <button
                                type="button"
                                class="users-password-toggle"
                                data-target="newConfirmPassword">

                                Show

                            </button>


                        </div>


                    </div>


                    <div
                        class="users-modal-actions">


                        <button
                            type="button"
                            class="
                            users-modal-button
                            users-modal-button-secondary
                        "
                            id="cancelPasswordModal">

                            Cancel

                        </button>


                        <button
                            type="submit"
                            class="
                            users-modal-button
                            users-modal-button-primary
                        "
                            id="savePasswordButton">

                            Change Password

                        </button>


                    </div>


                </form>


            </div>


        </section>


    </div>


    <!-- =========================================================
     STATUS CONFIRMATION MODAL
     ========================================================= -->

    <div
        class="users-modal-backdrop"
        id="statusConfirmModal"
        aria-hidden="true">


        <section
            class="users-modal"
            role="dialog"
            aria-modal="true">


            <div
                class="users-modal-header">


                <div>

                    <div
                        class="users-modal-eyebrow">

                        Account Status

                    </div>


                    <h2
                        class="users-modal-title"
                        id="statusConfirmTitle">

                        Deactivate Account

                    </h2>


                    <p
                        class="users-modal-subtitle">

                        Confirm the account status change.

                    </p>


                </div>


                <button
                    type="button"
                    class="users-modal-close"
                    id="closeStatusConfirm">

                    ×

                </button>


            </div>


            <div
                class="users-modal-body">


                <div
                    class="status-confirm-icon"
                    id="statusConfirmIcon">

                    !

                </div>


                <p
                    class="status-confirm-text"
                    id="statusConfirmText">

                    Are you sure you want to change this account's status?

                </p>


                <div
                    class="users-modal-actions">


                    <button
                        type="button"
                        class="
                        users-modal-button
                        users-modal-button-secondary
                    "
                        id="cancelStatusConfirm">

                        Cancel

                    </button>


                    <button
                        type="button"
                        class="
                        users-modal-button
                        users-modal-button-primary
                    "
                        id="confirmStatusButton">

                        Confirm

                    </button>


                </div>


            </div>


        </section>


    </div>


    <!-- =========================================================
     TOAST
     ========================================================= -->

    <div
        class="users-toast-container"
        id="usersToastContainer"
        aria-live="polite"
        aria-atomic="true">
    </div>


    <!-- =========================================================
     UNIVERSAL ADMIN JS
     ========================================================= -->

    <script
        src="<?= $basePath ?>assets/js/admin.js">
    </script>


    <script>
        (() => {

            /*
            |--------------------------------------------------------------------------
            | ELEMENTS
            |--------------------------------------------------------------------------
            */

            const userModal =
                document.getElementById(
                    'userModal'
                );

            const passwordModal =
                document.getElementById(
                    'passwordModal'
                );

            const statusConfirmModal =
                document.getElementById(
                    'statusConfirmModal'
                );

            const usersToastContainer =
                document.getElementById(
                    'usersToastContainer'
                );


            const userForm =
                document.getElementById(
                    'userForm'
                );

            const passwordForm =
                document.getElementById(
                    'passwordForm'
                );


            const openAddUserButton =
                document.getElementById(
                    'openAddUserModal'
                );


            const closeUserButton =
                document.getElementById(
                    'closeUserModal'
                );

            const cancelUserButton =
                document.getElementById(
                    'cancelUserModal'
                );


            const closePasswordButton =
                document.getElementById(
                    'closePasswordModal'
                );

            const cancelPasswordButton =
                document.getElementById(
                    'cancelPasswordModal'
                );


            const closeStatusConfirm =
                document.getElementById(
                    'closeStatusConfirm'
                );

            const cancelStatusConfirm =
                document.getElementById(
                    'cancelStatusConfirm'
                );

            const confirmStatusButton =
                document.getElementById(
                    'confirmStatusButton'
                );


            const userFormAction =
                document.getElementById(
                    'userFormAction'
                );

            const userFormUserId =
                document.getElementById(
                    'userFormUserId'
                );


            const userModalTitle =
                document.getElementById(
                    'userModalTitle'
                );

            const userModalSubtitle =
                document.getElementById(
                    'userModalSubtitle'
                );


            const userFullName =
                document.getElementById(
                    'userFullName'
                );

            const userUsername =
                document.getElementById(
                    'userUsername'
                );

            const userRole =
                document.getElementById(
                    'userRole'
                );

            const userPassword =
                document.getElementById(
                    'userPassword'
                );

            const userConfirmPassword =
                document.getElementById(
                    'userConfirmPassword'
                );


            const userPasswordGroup =
                document.getElementById(
                    'userPasswordGroup'
                );

            const userConfirmPasswordGroup =
                document.getElementById(
                    'userConfirmPasswordGroup'
                );


            const saveUserButton =
                document.getElementById(
                    'saveUserButton'
                );


            const userModalError =
                document.getElementById(
                    'userModalError'
                );


            const passwordUserId =
                document.getElementById(
                    'passwordUserId'
                );


            const passwordModalSubtitle =
                document.getElementById(
                    'passwordModalSubtitle'
                );

            const passwordModalError =
                document.getElementById(
                    'passwordModalError'
                );


            const newPassword =
                document.getElementById(
                    'newPassword'
                );

            const newConfirmPassword =
                document.getElementById(
                    'newConfirmPassword'
                );


            const savePasswordButton =
                document.getElementById(
                    'savePasswordButton'
                );


            const statusConfirmTitle =
                document.getElementById(
                    'statusConfirmTitle'
                );

            const statusConfirmText =
                document.getElementById(
                    'statusConfirmText'
                );

            const statusConfirmIcon =
                document.getElementById(
                    'statusConfirmIcon'
                );


            let statusTargetButton =
                null;

            let lastFocusedElement =
                null;


            /*
            |--------------------------------------------------------------------------
            | ERROR
            |--------------------------------------------------------------------------
            */

            function showError(
                box,
                message
            ) {

                box.textContent =
                    message;

                box.classList.add(
                    'is-visible'
                );
            }


            function clearError(
                box
            ) {

                box.textContent =
                    '';

                box.classList.remove(
                    'is-visible'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | BODY LOCK
            |--------------------------------------------------------------------------
            */

            function updateBodyLock() {

                const anyOpen =
                    document.querySelector(
                        '.users-modal-backdrop.is-open'
                    );

                document.body.classList.toggle(
                    'users-modal-open',
                    !!anyOpen
                );
            }


            /*
            |--------------------------------------------------------------------------
            | OPEN MODAL
            |--------------------------------------------------------------------------
            */

            function openModal(
                modal
            ) {

                lastFocusedElement =
                    document.activeElement;

                modal.classList.add(
                    'is-open'
                );

                modal.setAttribute(
                    'aria-hidden',
                    'false'
                );

                updateBodyLock();
            }


            /*
            |--------------------------------------------------------------------------
            | CLOSE MODAL
            |--------------------------------------------------------------------------
            */

            function closeModal(
                modal
            ) {

                modal.classList.remove(
                    'is-open'
                );

                modal.setAttribute(
                    'aria-hidden',
                    'true'
                );

                updateBodyLock();


                if (
                    lastFocusedElement &&
                    document.contains(
                        lastFocusedElement
                    )
                ) {

                    lastFocusedElement.focus();
                }
            }


            /*
            |--------------------------------------------------------------------------
            | SUCCESS TOAST
            |--------------------------------------------------------------------------
            */

            function showSuccessToast(
                message
            ) {

                /*
                |--------------------------------------------------------------------------
                | Use Universal Toast
                |--------------------------------------------------------------------------
                */

                if (
                    typeof window.showToast ===
                    'function'
                ) {

                    window.showToast(
                        message,
                        'success'
                    );

                    return;
                }


                if (
                    !usersToastContainer
                ) {

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Create Local Toast
                |--------------------------------------------------------------------------
                */

                const toast =
                    document.createElement(
                        'div'
                    );

                toast.className =
                    'users-toast';


                toast.innerHTML = `
            <div class="users-toast-icon">
                ✓
            </div>

            <div class="users-toast-content">

                <div class="users-toast-title">
                    Success
                </div>

                <div class="users-toast-message"></div>

            </div>
        `;


                toast.querySelector(
                        '.users-toast-message'
                    ).textContent =
                    message;


                usersToastContainer.appendChild(
                    toast
                );


                requestAnimationFrame(() => {

                    requestAnimationFrame(() => {

                        toast.classList.add(
                            'is-visible'
                        );

                    });

                });


                setTimeout(() => {

                    toast.classList.remove(
                        'is-visible'
                    );

                    toast.classList.add(
                        'is-closing'
                    );


                    setTimeout(() => {

                        toast.remove();

                    }, 300);

                }, 3000);

            }


            /*
            |--------------------------------------------------------------------------
            | ERROR TOAST
            |--------------------------------------------------------------------------
            */

            function showErrorToast(
                message
            ) {

                if (
                    typeof window.showToast ===
                    'function'
                ) {

                    window.showToast(
                        message,
                        'error'
                    );

                    return;
                }


                if (
                    !usersToastContainer
                ) {

                    return;
                }


                const toast =
                    document.createElement(
                        'div'
                    );

                toast.className =
                    'users-toast';

                toast.style.borderColor =
                    '#fecaca';


                toast.innerHTML = `
            <div
                class="users-toast-icon"
                style="
                    background:#fef2f2;
                    color:#dc2626;
                ">

                !

            </div>

            <div
                class="users-toast-content">

                <div
                    class="users-toast-title"
                    style="color:#b91c1c;">

                    Error

                </div>

                <div
                    class="users-toast-message">
                </div>

            </div>
        `;


                toast.querySelector(
                        '.users-toast-message'
                    ).textContent =
                    message;


                usersToastContainer.appendChild(
                    toast
                );


                requestAnimationFrame(() => {

                    requestAnimationFrame(() => {

                        toast.classList.add(
                            'is-visible'
                        );

                    });

                });


                setTimeout(() => {

                    toast.classList.remove(
                        'is-visible'
                    );

                    toast.classList.add(
                        'is-closing'
                    );


                    setTimeout(() => {

                        toast.remove();

                    }, 300);

                }, 3500);

            }


            /*
            |--------------------------------------------------------------------------
            | READ JSON
            |--------------------------------------------------------------------------
            */

            async function readJson(
                response
            ) {

                const text =
                    await response.text();


                console.log(
                    'Server response:',
                    text
                );


                if (
                    !text.trim()
                ) {

                    throw new Error(
                        'The server returned an empty response.'
                    );
                }


                try {

                    return JSON.parse(
                        text
                    );

                } catch (
                    error
                ) {

                    throw new Error(
                        'The server returned an invalid response.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | OPEN ADD USER
            |--------------------------------------------------------------------------
            */

            if (
                openAddUserButton
            ) {

                openAddUserButton.addEventListener(
                    'click',
                    () => {

                        clearError(
                            userModalError
                        );

                        userForm.reset();

                        userFormAction.value =
                            'add_user';

                        userFormUserId.value =
                            '';

                        userModalTitle.textContent =
                            'Add User';

                        userModalSubtitle.textContent =
                            'Create a new account for someone who will use the store system.';

                        userPasswordGroup.style.display =
                            '';

                        userConfirmPasswordGroup.style.display =
                            '';

                        userPassword.required =
                            true;

                        userConfirmPassword.required =
                            true;

                        saveUserButton.textContent =
                            'Create User';


                        openModal(
                            userModal
                        );


                        requestAnimationFrame(
                            () => {

                                userFullName.focus();

                            }
                        );

                    }
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CLOSE USER MODAL
            |--------------------------------------------------------------------------
            */

            closeUserButton.addEventListener(
                'click',
                () => {

                    closeModal(
                        userModal
                    );

                }
            );


            cancelUserButton.addEventListener(
                'click',
                () => {

                    closeModal(
                        userModal
                    );

                }
            );


            /*
            |--------------------------------------------------------------------------
            | CLOSE PASSWORD MODAL
            |--------------------------------------------------------------------------
            */

            closePasswordButton.addEventListener(
                'click',
                () => {

                    closeModal(
                        passwordModal
                    );

                }
            );


            cancelPasswordButton.addEventListener(
                'click',
                () => {

                    closeModal(
                        passwordModal
                    );

                }
            );


            /*
            |--------------------------------------------------------------------------
            | OPEN EDIT
            |--------------------------------------------------------------------------
            */

            document
                .querySelectorAll(
                    '[data-action="edit"]'
                )
                .forEach(
                    button => {

                        button.addEventListener(
                            'click',
                            () => {

                                clearError(
                                    userModalError
                                );

                                userForm.reset();

                                userFormAction.value =
                                    'edit_user';

                                userFormUserId.value =
                                    button.dataset.userId;

                                userModalTitle.textContent =
                                    'Edit User';

                                userModalSubtitle.textContent =
                                    'Update the account name, username and access role.';

                                userFullName.value =
                                    button.dataset.fullName ||
                                    '';

                                userUsername.value =
                                    button.dataset.username ||
                                    '';

                                userRole.value =
                                    button.dataset.role ||
                                    'staff';


                                userPasswordGroup.style.display =
                                    'none';

                                userConfirmPasswordGroup.style.display =
                                    'none';


                                userPassword.required =
                                    false;

                                userConfirmPassword.required =
                                    false;


                                saveUserButton.textContent =
                                    'Save Changes';


                                openModal(
                                    userModal
                                );


                                requestAnimationFrame(
                                    () => {

                                        userFullName.focus();

                                    }
                                );

                            }
                        );

                    }
                );


            /*
            |--------------------------------------------------------------------------
            | OPEN PASSWORD
            |--------------------------------------------------------------------------
            */

            document
                .querySelectorAll(
                    '[data-action="password"]'
                )
                .forEach(
                    button => {

                        button.addEventListener(
                            'click',
                            () => {

                                clearError(
                                    passwordModalError
                                );

                                passwordForm.reset();

                                passwordUserId.value =
                                    button.dataset.userId ||
                                    '';

                                passwordModalSubtitle.textContent =
                                    'Set a new password for ' +
                                    (
                                        button.dataset.fullName ||
                                        'this account'
                                    ) +
                                    '.';


                                openModal(
                                    passwordModal
                                );


                                requestAnimationFrame(
                                    () => {

                                        newPassword.focus();

                                    }
                                );

                            }
                        );

                    }
                );


            /*
            |--------------------------------------------------------------------------
            | PASSWORD SHOW / HIDE
            |--------------------------------------------------------------------------
            */

            document
                .querySelectorAll(
                    '.users-password-toggle'
                )
                .forEach(
                    button => {

                        button.addEventListener(
                            'click',
                            () => {

                                const input =
                                    document.getElementById(
                                        button.dataset.target
                                    );


                                if (
                                    !input
                                ) {

                                    return;
                                }


                                if (
                                    input.type ===
                                    'password'
                                ) {

                                    input.type =
                                        'text';

                                    button.textContent =
                                        'Hide';

                                } else {

                                    input.type =
                                        'password';

                                    button.textContent =
                                        'Show';
                                }

                            }
                        );

                    }
                );


            /*
            |--------------------------------------------------------------------------
            | USER FORM
            |--------------------------------------------------------------------------
            */

            userForm.addEventListener(
                'submit',
                async event => {

                    event.preventDefault();

                    clearError(
                        userModalError
                    );


                    if (
                        !userForm.reportValidity()
                    ) {

                        return;
                    }


                    const isEdit =
                        userFormAction.value ===
                        'edit_user';


                    if (
                        !isEdit &&
                        userPassword.value !==
                        userConfirmPassword.value
                    ) {

                        showError(
                            userModalError,
                            'Passwords do not match.'
                        );

                        return;
                    }


                    const originalText =
                        saveUserButton.innerHTML;


                    saveUserButton.disabled =
                        true;


                    saveUserButton.innerHTML =
                        isEdit ?
                        'Saving...' :
                        'Creating...';


                    try {

                        const response =
                            await fetch(
                                'users.php', {
                                    method: 'POST',

                                    body: new FormData(
                                        userForm
                                    ),

                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',

                                        'Accept': 'application/json'
                                    },

                                    credentials: 'same-origin'
                                }
                            );


                        const data =
                            await readJson(
                                response
                            );


                        if (
                            !data.success
                        ) {

                            throw new Error(
                                data.message ||
                                'Unable to save the user account.'
                            );
                        }


                        closeModal(
                            userModal
                        );


                        showSuccessToast(
                            data.message ||
                            'User account saved successfully.'
                        );


                        setTimeout(
                            () => {

                                window.location.reload();

                            },
                            900
                        );


                    } catch (
                        error
                    ) {

                        console.error(
                            'User save error:',
                            error
                        );


                        showError(
                            userModalError,
                            error.message ||
                            'Something went wrong.'
                        );

                    } finally {

                        saveUserButton.disabled =
                            false;

                        saveUserButton.innerHTML =
                            originalText;
                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | PASSWORD FORM
            |--------------------------------------------------------------------------
            */

            passwordForm.addEventListener(
                'submit',
                async event => {

                    event.preventDefault();

                    clearError(
                        passwordModalError
                    );


                    if (
                        !passwordForm.reportValidity()
                    ) {

                        return;
                    }


                    if (
                        newPassword.value !==
                        newConfirmPassword.value
                    ) {

                        showError(
                            passwordModalError,
                            'Passwords do not match.'
                        );

                        return;
                    }


                    if (
                        newPassword.value.length < 6
                    ) {

                        showError(
                            passwordModalError,
                            'Password must be at least 6 characters.'
                        );

                        return;
                    }


                    const originalText =
                        savePasswordButton.innerHTML;


                    savePasswordButton.disabled =
                        true;


                    savePasswordButton.innerHTML =
                        'Updating...';


                    try {

                        const response =
                            await fetch(
                                'users.php', {
                                    method: 'POST',

                                    body: new FormData(
                                        passwordForm
                                    ),

                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',

                                        'Accept': 'application/json'
                                    },

                                    credentials: 'same-origin'
                                }
                            );


                        const data =
                            await readJson(
                                response
                            );


                        if (
                            !data.success
                        ) {

                            throw new Error(
                                data.message ||
                                'Unable to change the password.'
                            );
                        }


                        newPassword.value =
                            '';

                        newConfirmPassword.value =
                            '';


                        closeModal(
                            passwordModal
                        );


                        showSuccessToast(
                            data.message ||
                            'Password changed successfully.'
                        );


                    } catch (
                        error
                    ) {

                        console.error(
                            'Password error:',
                            error
                        );


                        showError(
                            passwordModalError,
                            error.message ||
                            'Unable to change the password.'
                        );

                    } finally {

                        savePasswordButton.disabled =
                            false;

                        savePasswordButton.innerHTML =
                            originalText;
                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | OPEN STATUS CONFIRMATION
            |--------------------------------------------------------------------------
            */

            document
                .querySelectorAll(
                    '[data-action="toggle-status"]'
                )
                .forEach(
                    button => {

                        button.addEventListener(
                            'click',
                            () => {

                                statusTargetButton =
                                    button;


                                const currentlyActive =
                                    button.dataset.active ===
                                    '1';


                                const userName =
                                    button.dataset.userName ||
                                    'this account';


                                if (
                                    currentlyActive
                                ) {

                                    statusConfirmTitle.textContent =
                                        'Deactivate Account';

                                    statusConfirmIcon.textContent =
                                        '!';

                                    statusConfirmText.innerHTML =
                                        'Are you sure you want to deactivate <span class="status-confirm-name"></span>?';

                                    confirmStatusButton.textContent =
                                        'Deactivate';

                                    confirmStatusButton.style.background =
                                        '#dc2626';

                                    confirmStatusButton.style.borderColor =
                                        '#dc2626';

                                } else {

                                    statusConfirmTitle.textContent =
                                        'Activate Account';

                                    statusConfirmIcon.textContent =
                                        '✓';

                                    statusConfirmIcon.style.background =
                                        '#ecfdf5';

                                    statusConfirmIcon.style.color =
                                        '#16a34a';

                                    statusConfirmText.innerHTML =
                                        'Are you sure you want to activate <span class="status-confirm-name"></span>?';

                                    confirmStatusButton.textContent =
                                        'Activate';

                                    confirmStatusButton.style.background =
                                        '#16a34a';

                                    confirmStatusButton.style.borderColor =
                                        '#16a34a';
                                }


                                const nameElement =
                                    statusConfirmText.querySelector(
                                        '.status-confirm-name'
                                    );


                                if (
                                    nameElement
                                ) {

                                    nameElement.textContent =
                                        userName;
                                }


                                /*
                                |------------------------------------------------------------------
                                | Reset Icon For Deactivation
                                |------------------------------------------------------------------
                                */

                                if (
                                    currentlyActive
                                ) {

                                    statusConfirmIcon.style.background =
                                        '#fff7ed';

                                    statusConfirmIcon.style.color =
                                        '#ea580c';
                                }


                                openModal(
                                    statusConfirmModal
                                );

                            }
                        );

                    }
                );


            /*
            |--------------------------------------------------------------------------
            | CLOSE STATUS CONFIRM
            |--------------------------------------------------------------------------
            */

            closeStatusConfirm.addEventListener(
                'click',
                () => {

                    closeModal(
                        statusConfirmModal
                    );

                    statusTargetButton =
                        null;

                }
            );


            cancelStatusConfirm.addEventListener(
                'click',
                () => {

                    closeModal(
                        statusConfirmModal
                    );

                    statusTargetButton =
                        null;

                }
            );


            /*
            |--------------------------------------------------------------------------
            | CONFIRM STATUS CHANGE
            |--------------------------------------------------------------------------
            */

            confirmStatusButton.addEventListener(
                'click',
                async () => {

                    if (
                        !statusTargetButton
                    ) {

                        return;
                    }


                    const button =
                        statusTargetButton;


                    const userId =
                        button.dataset.userId;


                    const currentlyActive =
                        button.dataset.active ===
                        '1';


                    const originalText =
                        confirmStatusButton.innerHTML;


                    confirmStatusButton.disabled =
                        true;


                    confirmStatusButton.innerHTML =
                        currentlyActive ?
                        'Deactivating...' :
                        'Activating...';


                    try {

                        /*
                        |--------------------------------------------------------------------------
                        | SEND
                        |--------------------------------------------------------------------------
                        */

                        const formData =
                            new FormData();


                        formData.append(
                            'action',
                            'toggle_status'
                        );


                        formData.append(
                            'user_id',
                            userId
                        );


                        const response =
                            await fetch(
                                'users.php', {
                                    method: 'POST',

                                    body: formData,

                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',

                                        'Accept': 'application/json'
                                    },

                                    credentials: 'same-origin'
                                }
                            );


                        const data =
                            await readJson(
                                response
                            );


                        if (
                            !data.success
                        ) {

                            throw new Error(
                                data.message ||
                                'Unable to change account status.'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | NEW STATUS
                        |--------------------------------------------------------------------------
                        */

                        const newStatus =
                            Number(
                                data.is_active
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | UPDATE BUTTON
                        |--------------------------------------------------------------------------
                        */

                        button.dataset.active =
                            newStatus === 1 ?
                            '1' :
                            '0';


                        if (
                            newStatus === 1
                        ) {

                            button.classList.remove(
                                'user-action-deactivate'
                            );

                            button.classList.add(
                                'user-action-activate'
                            );

                            button.textContent =
                                'Deactivate';

                            /*
                            |--------------------------------------------------------------
                            | Since it was activated, next click should deactivate
                            |--------------------------------------------------------------
                            */

                            button.dataset.active =
                                '1';

                        } else {

                            button.classList.remove(
                                'user-action-activate'
                            );

                            button.classList.add(
                                'user-action-deactivate'
                            );

                            button.textContent =
                                'Activate';

                            button.dataset.active =
                                '0';
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | UPDATE STATUS BADGE
                        |--------------------------------------------------------------------------
                        */

                        const statusBadge =
                            document.querySelector(
                                `[data-status-badge="${userId}"]`
                            );


                        const statusText =
                            document.querySelector(
                                `[data-status-text="${userId}"]`
                            );


                        if (
                            statusBadge
                        ) {

                            statusBadge.classList.toggle(
                                'user-status-active',
                                newStatus === 1
                            );

                            statusBadge.classList.toggle(
                                'user-status-inactive',
                                newStatus !== 1
                            );

                        }


                        if (
                            statusText
                        ) {

                            statusText.textContent =
                                newStatus === 1 ?
                                'Active' :
                                'Inactive';

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Close Confirmation
                        |--------------------------------------------------------------------------
                        */

                        closeModal(
                            statusConfirmModal
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | Clear Target
                        |--------------------------------------------------------------------------
                        */

                        statusTargetButton =
                            null;


                        /*
                        |--------------------------------------------------------------------------
                        | Success
                        |--------------------------------------------------------------------------
                        */

                        showSuccessToast(
                            data.message ||
                            (
                                newStatus === 1 ?
                                'User account activated successfully.' :
                                'User account deactivated successfully.'
                            )
                        );


                    } catch (
                        error
                    ) {

                        console.error(
                            'Status update error:',
                            error
                        );


                        showErrorToast(
                            error.message ||
                            'Unable to change account status.'
                        );


                    } finally {

                        confirmStatusButton.disabled =
                            false;

                        confirmStatusButton.innerHTML =
                            originalText;
                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | BACKDROP CLOSE
            |--------------------------------------------------------------------------
            */

            [
                userModal,
                passwordModal,
                statusConfirmModal
            ].forEach(
                modal => {

                    modal.addEventListener(
                        'mousedown',
                        event => {

                            if (
                                event.target ===
                                modal
                            ) {

                                closeModal(
                                    modal
                                );

                                if (
                                    modal ===
                                    statusConfirmModal
                                ) {

                                    statusTargetButton =
                                        null;
                                }

                            }

                        }
                    );

                }
            );


            /*
            |--------------------------------------------------------------------------
            | ESC
            |--------------------------------------------------------------------------
            */

            document.addEventListener(
                'keydown',
                event => {

                    if (
                        event.key !==
                        'Escape'
                    ) {

                        return;
                    }


                    const openModalElement =
                        document.querySelector(
                            '.users-modal-backdrop.is-open'
                        );


                    if (
                        openModalElement
                    ) {

                        closeModal(
                            openModalElement
                        );


                        if (
                            openModalElement ===
                            statusConfirmModal
                        ) {

                            statusTargetButton =
                                null;
                        }

                    }

                }
            );


        })();
    </script>


</body>

</html>
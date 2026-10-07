<?php

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

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
| Get User ID
|--------------------------------------------------------------------------
*/
$userId = (int)($_GET['id'] ?? 0);

if ($userId <= 0) {
    header('Location: users.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get User
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        id,
        username,
        full_name,
        role,
        is_active
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $userId
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Form Error
|--------------------------------------------------------------------------
*/
$error = '';


/*
|--------------------------------------------------------------------------
| Update User
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validate Required Fields
    |--------------------------------------------------------------------------
    */
    if (
        $fullName === '' ||
        $username === ''
    ) {

        $error = 'Full name and username are required.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Role
    |--------------------------------------------------------------------------
    */ elseif (
        !in_array(
            $role,
            ['admin', 'cashier', 'staff'],
            true
        )
    ) {

        $error = 'Invalid user role.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate New Password
    |--------------------------------------------------------------------------
    */ elseif (
        $password !== '' &&
        strlen($password) < 6
    ) {

        $error = 'New password must be at least 6 characters.';
    }


    /*
    |--------------------------------------------------------------------------
    | Confirm New Password
    |--------------------------------------------------------------------------
    */ elseif (
        $password !== '' &&
        $password !== $confirmPassword
    ) {

        $error = 'Passwords do not match.';
    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Username
        |--------------------------------------------------------------------------
        | Make sure another user isn't already using it.
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
              AND id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $username,
            $userId
        ]);

        if ($stmt->fetch()) {

            $error = 'Username already exists. Please choose another username.';
        } else {

            /*
            |--------------------------------------------------------------------------
            | Update With New Password
            |--------------------------------------------------------------------------
            */
            if ($password !== '') {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        username = ?,
                        role = ?,
                        password = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $fullName,
                    $username,
                    $role,
                    $hashedPassword,
                    $userId
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Update Without Changing Password
            |--------------------------------------------------------------------------
            */ else {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        username = ?,
                        role = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $fullName,
                    $username,
                    $role,
                    $userId
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Update Current Session
            |--------------------------------------------------------------------------
            | If the administrator is editing their own account,
            | update the session values immediately.
            |--------------------------------------------------------------------------
            */
            if (
                $userId === (int)$_SESSION['user_id']
            ) {

                $_SESSION['full_name'] = $fullName;
                $_SESSION['role'] = $role;
            }


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */
            header('Location: users.php?updated=1');
            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Keep Entered Values If Validation Fails
    |--------------------------------------------------------------------------
    */
    $user['full_name'] = $fullName;
    $user['username'] = $username;
    $user['role'] = $role;
}


/*
|--------------------------------------------------------------------------
| Inventory Notifications
|--------------------------------------------------------------------------
*/
$notifications = [];

try {

    $notificationStmt = $pdo->query("
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

    $notifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {

    $notifications = [];
}

$notificationCount = count($notifications);

?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Edit User - Arnold & Jackie's Store
    </title>


    <!-- =========================================================
         BOOTSTRAP
         ========================================================= -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- =========================================================
         UNIVERSAL ADMIN CSS
         ========================================================= -->

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">


    <!-- =========================================================
         PAGE-SPECIFIC CSS
         ========================================================= -->

    <style>
        /* =========================================================
           USER EDIT PAGE
           ========================================================= */

        .user-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .user-page-title {
            font-size: 25px;
            font-weight: 700;
            margin: 0 0 4px;
            color: #172033;
        }

        .user-page-subtitle {
            color: #7b8494;
            font-size: 13px;
            margin: 0;
        }

        .user-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-header-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            background: #ffffff;
            color: #374151;
            border: 1px solid #d9dee7;
            transition: .15s;
        }

        .user-header-button:hover {
            background: #f8fafc;
            color: #111827;
        }

        .user-form-card {
            width: 100%;
            max-width: 720px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 13px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .03);
            overflow: hidden;
        }

        .user-form-header {
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f4;
        }

        .user-form-title {
            font-size: 15px;
            font-weight: 700;
            color: #202938;
            margin: 0;
        }

        .user-form-subtitle {
            font-size: 11px;
            color: #8b94a3;
            margin-top: 3px;
        }

        .user-form-body {
            padding: 22px 20px;
        }

        .form-field {
            margin-bottom: 18px;
        }

        .form-field:last-child {
            margin-bottom: 0;
        }

        .form-label-custom {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 7px;
        }

        .form-label-required::after {
            content: " *";
            color: #dc3545;
        }

        .form-control-custom,
        .form-select-custom {
            width: 100%;
            max-width: 500px;
            height: 40px;
            padding: 8px 11px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #ffffff;
            color: #172033;
            font-size: 13px;
            outline: none;
            transition:
                border-color .15s,
                box-shadow .15s;
        }

        .form-control-custom:focus,
        .form-select-custom:focus {
            border-color: #86b7fe;
            box-shadow:
                0 0 0 3px rgba(13, 110, 253, .10);
        }

        .form-help {
            margin-top: 6px;
            font-size: 11px;
            color: #8b94a3;
        }

        .form-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-top: 5px;
        }

        .form-submit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 17px;
            border: none;
            border-radius: 8px;
            background: #0d6efd;
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: .15s;
        }

        .form-submit-button:hover {
            background: #0b5ed7;
        }

        .form-cancel-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 17px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #ffffff;
            color: #374151;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: .15s;
        }

        .form-cancel-button:hover {
            background: #f8fafc;
            color: #111827;
        }

        .form-error {
            max-width: 720px;
            margin-bottom: 18px;
            padding: 11px 14px;
            border: 1px solid #fecdd3;
            border-radius: 9px;
            background: #fff1f2;
            color: #b91c1c;
            font-size: 13px;
        }

        .password-note {
            margin-top: 6px;
            color: #8b94a3;
            font-size: 11px;
        }

        .account-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
            padding: 5px 9px;
            border-radius: 6px;
            background: #e8f8ef;
            color: #15803d;
            font-size: 10px;
            font-weight: 700;
        }

        .account-status::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .account-status.inactive {
            background: #fff1f2;
            color: #dc3545;
        }

        @media (max-width: 768px) {

            .user-page-header {
                flex-direction: column;
            }

            .user-header-actions {
                width: 100%;
            }

            .user-header-button {
                width: 100%;
            }

            .user-form-body {
                padding: 18px 16px;
            }

        }
    </style>

</head>


<body>


    <!-- =========================================================
     UNIVERSAL SIDEBAR
     ========================================================= -->

    <?php require_once $basePath . 'includes/sidebar.php'; ?>


    <div class="main">


        <!-- =====================================================
         UNIVERSAL TOPBAR
         ===================================================== -->

        <?php require_once $basePath . 'includes/topbar.php'; ?>


        <main class="content">


            <!-- =================================================
             PAGE HEADER
             ================================================= -->

            <div class="user-page-header">

                <div>

                    <h1 class="user-page-title">
                        Edit User
                    </h1>

                    <p class="user-page-subtitle">
                        Update account information.
                    </p>

                </div>


                <div class="user-header-actions">

                    <a
                        href="users.php"
                        class="user-header-button">
                        ← Back to Users
                    </a>

                </div>

            </div>


            <!-- =================================================
             ERROR MESSAGE
             ================================================= -->

            <?php if ($error): ?>

                <div class="form-error">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
             USER FORM CARD
             ================================================= -->

            <div class="user-form-card">


                <div class="user-form-header">

                    <div class="user-form-title">
                        User Information
                    </div>

                    <div class="user-form-subtitle">
                        Update the account details and access level.
                    </div>

                </div>


                <div class="user-form-body">


                    <form method="post">


                        <!-- =====================================
                         FULL NAME
                         ===================================== -->

                        <div class="form-field">

                            <label
                                for="full_name"
                                class="form-label-custom form-label-required">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                value="<?= htmlspecialchars(
                                            $user['full_name']
                                        ) ?>"
                                class="form-control-custom"
                                required
                                autocomplete="name">

                        </div>


                        <!-- =====================================
                         USERNAME
                         ===================================== -->

                        <div class="form-field">

                            <label
                                for="username"
                                class="form-label-custom form-label-required">
                                Username
                            </label>

                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="<?= htmlspecialchars(
                                            $user['username']
                                        ) ?>"
                                class="form-control-custom"
                                required
                                autocomplete="off">

                        </div>


                        <!-- =====================================
                         NEW PASSWORD
                         ===================================== -->

                        <div class="form-field">

                            <label
                                for="password"
                                class="form-label-custom">
                                New Password
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control-custom"
                                minlength="6"
                                autocomplete="new-password">

                            <div class="password-note">
                                Leave blank to keep the current password.
                            </div>

                        </div>


                        <!-- =====================================
                         CONFIRM NEW PASSWORD
                         ===================================== -->

                        <div class="form-field">

                            <label
                                for="confirm_password"
                                class="form-label-custom">
                                Confirm New Password
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control-custom"
                                minlength="6"
                                autocomplete="new-password">

                        </div>


                        <!-- =====================================
                         ROLE
                         ===================================== -->

                        <div class="form-field">

                            <label
                                for="role"
                                class="form-label-custom form-label-required">
                                Role
                            </label>

                            <select
                                id="role"
                                name="role"
                                class="form-select-custom"
                                required>

                                <option
                                    value="admin"
                                    <?= $user['role'] === 'admin'
                                        ? 'selected'
                                        : ''
                                    ?>>
                                    Administrator
                                </option>


                                <option
                                    value="cashier"
                                    <?= $user['role'] === 'cashier'
                                        ? 'selected'
                                        : ''
                                    ?>>
                                    Cashier
                                </option>


                                <option
                                    value="staff"
                                    <?= $user['role'] === 'staff'
                                        ? 'selected'
                                        : ''
                                    ?>>
                                    Staff
                                </option>

                            </select>

                        </div>


                        <!-- =====================================
                         ACCOUNT STATUS
                         ===================================== -->

                        <div class="form-field">

                            <label class="form-label-custom">
                                Account Status
                            </label>

                            <?php if (
                                (int)$user['is_active'] === 1
                            ): ?>

                                <div class="account-status">
                                    Active
                                </div>

                            <?php else: ?>

                                <div class="account-status inactive">
                                    Inactive
                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- =====================================
                         FORM ACTIONS
                         ===================================== -->

                        <div class="form-actions">

                            <button
                                type="submit"
                                class="form-submit-button">
                                Save Changes
                            </button>


                            <a
                                href="users.php"
                                class="form-cancel-button">
                                Cancel
                            </a>

                        </div>


                    </form>


                </div>

            </div>


        </main>

    </div>


    <!-- =========================================================
     BOOTSTRAP JS
     ========================================================= -->

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    <!-- =========================================================
     UNIVERSAL ADMIN JS
     ========================================================= -->

    <script
        src="<?= $basePath ?>assets/js/admin.js"></script>


</body>

</html>
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
| Form Error
|--------------------------------------------------------------------------
*/
$error = '';

/*
|--------------------------------------------------------------------------
| Create User
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validate Required Fields
    |--------------------------------------------------------------------------
    */
    if (
        $fullName === '' ||
        $username === '' ||
        $password === ''
    ) {

        $error = 'Please fill in all required fields.';
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
    | Validate Password
    |--------------------------------------------------------------------------
    */ elseif (strlen($password) < 6) {

        $error = 'Password must be at least 6 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Confirm Password
    |--------------------------------------------------------------------------
    */ elseif ($password !== $confirmPassword) {

        $error = 'Passwords do not match.';
    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Existing Username
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([
            $username
        ]);

        if ($stmt->fetch()) {

            $error = 'Username already exists. Please choose another username.';
        } else {

            /*
            |--------------------------------------------------------------------------
            | Securely Hash Password
            |--------------------------------------------------------------------------
            */
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            /*
            |--------------------------------------------------------------------------
            | Insert User
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                INSERT INTO users
                    (
                        username,
                        password,
                        full_name,
                        role,
                        is_active
                    )
                VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        1
                    )
            ");

            $stmt->execute([
                $username,
                $hashedPassword,
                $fullName,
                $role
            ]);

            /*
            |--------------------------------------------------------------------------
            | Redirect Back To User Management
            |--------------------------------------------------------------------------
            */
            header('Location: users.php?created=1');
            exit;
        }
    }
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
        Add User - Arnold & Jackie's Store
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
           USER ADD PAGE
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
            max-width: 850px;
            margin-left: 0;
            margin-right: auto;

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
            transition: border-color .15s, box-shadow .15s;
        }

        .form-control-custom:focus,
        .form-select-custom:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .10);
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
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            color: #8b94a3;
            font-size: 11px;
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
                        Add User
                    </h1>

                    <p class="user-page-subtitle">
                        Create a new account for the system.
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
             USER FORM
             ================================================= -->

            <div class="user-form-card">


                <div class="user-form-header">

                    <div class="user-form-title">
                        User Information
                    </div>

                    <div class="user-form-subtitle">
                        Enter the account details and access level.
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
                                            $_POST['full_name'] ?? ''
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
                                            $_POST['username'] ?? ''
                                        ) ?>"
                                class="form-control-custom"
                                required
                                autocomplete="off">

                        </div>


                        <!-- =====================================
                         PASSWORD
                         ===================================== -->

                        <div class="form-field">

                            <label
                                for="password"
                                class="form-label-custom form-label-required">
                                Password
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control-custom"
                                required
                                minlength="6"
                                autocomplete="new-password">

                            <div class="password-note">
                                Minimum 6 characters.
                            </div>

                        </div>


                        <!-- =====================================
                         CONFIRM PASSWORD
                         ===================================== -->

                        <div class="form-field">

                            <label
                                for="confirm_password"
                                class="form-label-custom form-label-required">
                                Confirm Password
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control-custom"
                                required
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

                                <option value="">
                                    Select Role
                                </option>


                                <option
                                    value="admin"
                                    <?= (
                                        ($_POST['role'] ?? '') === 'admin'
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>>
                                    Administrator
                                </option>


                                <option
                                    value="cashier"
                                    <?= (
                                        ($_POST['role'] ?? '') === 'cashier'
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>>
                                    Cashier
                                </option>


                                <option
                                    value="staff"
                                    <?= (
                                        ($_POST['role'] ?? '') === 'staff'
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>>
                                    Staff
                                </option>

                            </select>

                        </div>


                        <!-- =====================================
                         FORM ACTIONS
                         ===================================== -->

                        <div class="form-actions">

                            <button
                                type="submit"
                                class="form-submit-button">
                                Create User
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
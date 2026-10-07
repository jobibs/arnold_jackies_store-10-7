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
| Current Session User
|--------------------------------------------------------------------------
*/
$currentUserId = isset($_SESSION['user_id'])
    ? (int)$_SESSION['user_id']
    : 0;

$currentUserRole = strtolower(
    trim($_SESSION['role'] ?? 'staff')
);

$currentUserName = $_SESSION['full_name'] ?? 'System User';


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
| JSON Response Helper
|--------------------------------------------------------------------------
*/
function jsonResponse(
    bool $success,
    string $message,
    array $extra = []
): never {

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/
$error = '';
$success = '';


/*
|--------------------------------------------------------------------------
| Get Current User
|--------------------------------------------------------------------------
*/
$currentUser = null;

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            full_name,
            role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $currentUserId
    ]);

    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {

    $currentUser = null;
}


if (!$currentUser) {

    /*
    |--------------------------------------------------------------------------
    | Session Is Invalid
    |--------------------------------------------------------------------------
    */

    if ($isAjax) {

        jsonResponse(
            false,
            'Your user session could not be verified. Please log in again.'
        );
    }

    $error = 'Your user account could not be loaded.';
}


/*
|--------------------------------------------------------------------------
| Change Password
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'change_password'
) {

    if (!$currentUser) {

        $error =
            'Your user account could not be verified.';
    } else {

        $currentPassword =
            $_POST['current_password'] ?? '';

        $newPassword =
            $_POST['new_password'] ?? '';

        $confirmPassword =
            $_POST['confirm_password'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | Validate Current Password
        |--------------------------------------------------------------------------
        */

        if ($currentPassword === '') {

            $error =
                'Your current password is required.';
        } elseif (
            !password_verify(
                $currentPassword,
                $currentUser['password_hash'] ?? ''
            )
        ) {

            $error =
                'Your current password is incorrect.';
        } elseif ($newPassword === '') {

            $error =
                'Your new password is required.';
        } elseif (strlen($newPassword) < 6) {

            $error =
                'Your new password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {

            $error =
                'The new passwords do not match.';
        } elseif (
            password_verify(
                $newPassword,
                $currentUser['password_hash'] ?? ''
            )
        ) {

            $error =
                'Your new password must be different from your current password.';
        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Hash New Password
                |--------------------------------------------------------------------------
                */

                $newPasswordHash = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


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
                    $newPasswordHash,
                    $currentUserId
                ]);


                /*
                |--------------------------------------------------------------------------
                | AJAX Success
                |--------------------------------------------------------------------------
                */

                if ($isAjax) {

                    jsonResponse(
                        true,
                        'Your password has been changed successfully.'
                    );
                }


                $success =
                    'Your password has been changed successfully.';
            } catch (PDOException $e) {

                $error =
                    'Unable to change your password right now.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | AJAX Error
    |--------------------------------------------------------------------------
    */

    if ($isAjax && $error !== '') {

        jsonResponse(
            false,
            $error
        );
    }
}


/*
|--------------------------------------------------------------------------
| Avatar Initial
|--------------------------------------------------------------------------
*/
$userInitial = strtoupper(
    substr(
        trim($currentUserName),
        0,
        1
    )
);


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

    $notifications =
        $notificationStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {

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
        Security - Arnold & Jackie's Store
    </title>


    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">


    <style>
        /* =========================================================
           SECURITY PAGE
           ========================================================= */

        .security-page {
            width: 100%;
        }


        /* =========================================================
           HEADER
           ========================================================= */

        .security-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .security-title {
            margin: 0 0 5px;
            color: #172033;
            font-size: 25px;
            font-weight: 750;
            line-height: 1.2;
            letter-spacing: -.025em;
        }

        .security-subtitle {
            margin: 0;
            color: #7b8494;
            font-size: 13px;
            line-height: 1.6;
        }

        .security-header-actions {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .security-back-button {
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

            transition:
                background-color .16s ease,
                border-color .16s ease,
                color .16s ease,
                transform .16s ease;
        }

        .security-back-button:hover {
            background: #f8fafc;
            border-color: #cfd8e4;
            color: #111827;
            transform: translateY(-1px);
        }


        /* =========================================================
           ACCOUNT OVERVIEW
           ========================================================= */

        .security-account-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;

            margin-bottom: 18px;
            padding: 18px 20px;

            border: 1px solid #e5eaf0;
            border-radius: 15px;

            background: #ffffff;

            box-shadow:
                0 7px 24px rgba(15, 23, 42, .025);
        }

        .security-account-left {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
        }

        .security-avatar {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 12px;

            background: #eff6ff;
            color: #2563eb;

            font-size: 14px;
            font-weight: 800;
        }

        .security-account-name {
            margin: 0 0 3px;
            color: #202938;
            font-size: 13px;
            font-weight: 750;
        }

        .security-account-username {
            margin: 0;
            color: #8b94a3;
            font-size: 10px;
        }

        .security-role-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 23px;
            padding: 0 9px;

            border-radius: 999px;

            background: #eff6ff;
            color: #1d4ed8;

            font-size: 9px;
            font-weight: 800;

            letter-spacing: .04em;
            text-transform: uppercase;
        }


        /* =========================================================
           CONTENT GRID
           ========================================================= */

        .security-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(280px, .85fr);
            gap: 16px;
            align-items: start;
        }


        /* =========================================================
           CARDS
           ========================================================= */

        .security-card {
            overflow: hidden;

            border: 1px solid #e5eaf0;
            border-radius: 15px;

            background: #ffffff;

            box-shadow:
                0 7px 24px rgba(15, 23, 42, .025);
        }

        .security-card-header {
            padding: 17px 18px;

            border-bottom: 1px solid #edf0f4;
        }

        .security-card-title {
            margin: 0 0 3px;
            color: #202938;
            font-size: 14px;
            font-weight: 750;
        }

        .security-card-subtitle {
            margin: 0;
            color: #8b94a3;
            font-size: 10px;
            line-height: 1.5;
        }

        .security-card-body {
            padding: 19px;
        }


        /* =========================================================
           SECURITY ITEMS
           ========================================================= */

        .security-info-list {
            display: flex;
            flex-direction: column;
        }

        .security-info-item {
            display: flex;
            align-items: center;
            gap: 12px;

            padding: 13px 0;

            border-bottom: 1px solid #f0f2f5;
        }

        .security-info-item:first-child {
            padding-top: 0;
        }

        .security-info-item:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .security-info-icon {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 9px;

            background: #f1f5f9;
            color: #475569;

            font-size: 13px;
        }

        .security-info-label {
            margin: 0 0 2px;
            color: #253044;
            font-size: 10px;
            font-weight: 750;
        }

        .security-info-text {
            margin: 0;
            color: #8b94a3;
            font-size: 9px;
            line-height: 1.5;
        }


        /* =========================================================
           FORM
           ========================================================= */

        .security-form-group {
            margin-bottom: 15px;
        }

        .security-form-label {
            display: block;
            margin-bottom: 6px;

            color: #334155;

            font-size: 10px;
            font-weight: 800;
        }

        .security-form-input-wrap {
            position: relative;
        }

        .security-form-input {
            width: 100%;
            height: 42px;

            padding: 0 70px 0 12px;

            border: 1px solid #dbe2ea;
            border-radius: 9px;

            outline: none;

            background: #ffffff;
            color: #172033;

            font: inherit;
            font-size: 12px;

            box-sizing: border-box;

            transition:
                border-color .16s ease,
                box-shadow .16s ease;
        }

        .security-form-input:hover {
            border-color: #cbd5e1;
        }

        .security-form-input:focus {
            border-color: #2563eb;
            box-shadow:
                0 0 0 3px rgba(37, 99, 235, .10);
        }

        .security-form-input::placeholder {
            color: #a1aab8;
        }

        .security-password-toggle {
            position: absolute;
            top: 50%;
            right: 9px;

            transform: translateY(-50%);

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

        .security-password-toggle:hover {
            background: #f1f5f9;
            color: #334155;
        }

        .security-form-help {
            margin-top: 5px;
            color: #94a3b8;
            font-size: 9px;
            line-height: 1.45;
        }


        /* =========================================================
           ERROR
           ========================================================= */

        .security-error {
            display: none;

            margin-bottom: 16px;
            padding: 10px 12px;

            border: 1px solid #fecaca;
            border-radius: 9px;

            background: #fff1f2;
            color: #b91c1c;

            font-size: 10px;
            font-weight: 650;

            line-height: 1.5;
        }

        .security-error.is-visible {
            display: block;

            animation:
                securityErrorIn .18s ease both;
        }

        @keyframes securityErrorIn {

            from {
                opacity: 0;
                transform: translateY(-4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }

        }


        /* =========================================================
           FORM BUTTONS
           ========================================================= */

        .security-form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;

            margin-top: 18px;
            padding-top: 16px;

            border-top: 1px solid #edf0f4;
        }

        .security-save-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 40px;
            min-width: 145px;
            padding: 0 15px;

            border: 1px solid #2563eb;
            border-radius: 9px;

            background: #2563eb;
            color: #ffffff;

            font: inherit;
            font-size: 11px;
            font-weight: 750;

            cursor: pointer;

            box-shadow:
                0 5px 14px rgba(37, 99, 235, .14);

            transition:
                background-color .15s ease,
                border-color .15s ease,
                transform .15s ease,
                box-shadow .15s ease;
        }

        .security-save-button:hover {
            border-color: #1d4ed8;
            background: #1d4ed8;

            transform: translateY(-1px);

            box-shadow:
                0 7px 18px rgba(37, 99, 235, .18);
        }

        .security-save-button:disabled {
            opacity: .6;
            cursor: wait;
            transform: none;
            box-shadow: none;
        }


        /* =========================================================
           PASSWORD REQUIREMENTS
           ========================================================= */

        .security-requirements {
            margin-top: 14px;
            padding: 12px;

            border: 1px solid #e8edf3;
            border-radius: 10px;

            background: #f8fafc;
        }

        .security-requirements-title {
            margin: 0 0 7px;

            color: #475569;

            font-size: 9px;
            font-weight: 800;

            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .security-requirements-text {
            margin: 0;

            color: #8b94a3;

            font-size: 9px;
            line-height: 1.55;
        }


        /* =========================================================
           STATUS
           ========================================================= */

        .security-status {
            display: flex;
            align-items: flex-start;
            gap: 9px;

            margin-top: 15px;
            padding: 11px 12px;

            border: 1px solid #bbf7d0;
            border-radius: 9px;

            background: #f0fdf4;
            color: #166534;
        }

        .security-status-icon {
            flex: 0 0 auto;
            font-size: 12px;
        }

        .security-status-text {
            margin: 0;
            font-size: 9px;
            line-height: 1.5;
            font-weight: 650;
        }


        /* =========================================================
           RESPONSIVE
           ========================================================= */

        @media (max-width: 900px) {

            .security-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 650px) {

            .security-header {
                flex-direction: column;
            }

            .security-header-actions {
                width: 100%;
            }

            .security-back-button {
                width: 100%;
            }

            .security-account-card {
                align-items: flex-start;
                flex-direction: column;
            }

        }

        @media (max-width: 520px) {

            .security-title {
                font-size: 22px;
            }

            .security-card-body {
                padding: 17px;
            }

            .security-form-actions {
                align-items: stretch;
            }

            .security-save-button {
                width: 100%;
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


            <div class="security-page">


                <!-- =================================================
                     HEADER
                     ================================================= -->

                <div class="security-header">

                    <div>

                        <h1 class="security-title">
                            Security
                        </h1>

                        <p class="security-subtitle">
                            Manage the security of your currently signed-in account.
                        </p>

                    </div>


                    <div class="security-header-actions">

                        <a
                            href="index.php"
                            class="security-back-button">

                            ← Settings

                        </a>

                    </div>

                </div>


                <!-- =================================================
                     ACCOUNT OVERVIEW
                     ================================================= -->

                <?php if ($currentUser): ?>

                    <div class="security-account-card">

                        <div class="security-account-left">

                            <div class="security-avatar">

                                <?= htmlspecialchars(
                                    $userInitial,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </div>


                            <div>

                                <p class="security-account-name">

                                    <?= htmlspecialchars(
                                        $currentUser['full_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </p>


                                <p class="security-account-username">

                                    @<?= htmlspecialchars(
                                            $currentUser['username'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                </p>

                            </div>

                        </div>


                        <span class="security-role-badge">

                            <?= htmlspecialchars(
                                $currentUser['role'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     MAIN GRID
                     ================================================= -->

                <div class="security-grid">


                    <!-- =================================================
                         CHANGE PASSWORD
                         ================================================= -->

                    <div class="security-card">

                        <div class="security-card-header">

                            <p class="security-card-title">
                                Change Password
                            </p>

                            <p class="security-card-subtitle">
                                Update your password without leaving the Settings area.
                            </p>

                        </div>


                        <div class="security-card-body">


                            <div
                                class="security-error <?= $error !== '' ? 'is-visible' : '' ?>"
                                id="securityError">

                                <?= htmlspecialchars(
                                    $error,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </div>


                            <form
                                method="post"
                                action="security.php"
                                id="changePasswordForm">


                                <input
                                    type="hidden"
                                    name="action"
                                    value="change_password">


                                <!-- CURRENT PASSWORD -->

                                <div class="security-form-group">

                                    <label
                                        for="currentPassword"
                                        class="security-form-label">

                                        Current Password

                                    </label>


                                    <div class="security-form-input-wrap">

                                        <input
                                            type="password"
                                            id="currentPassword"
                                            name="current_password"
                                            class="security-form-input"
                                            autocomplete="current-password"
                                            required
                                            placeholder="Enter your current password">


                                        <button
                                            type="button"
                                            class="security-password-toggle"
                                            data-target="currentPassword">

                                            Show

                                        </button>

                                    </div>

                                </div>


                                <!-- NEW PASSWORD -->

                                <div class="security-form-group">

                                    <label
                                        for="newPassword"
                                        class="security-form-label">

                                        New Password

                                    </label>


                                    <div class="security-form-input-wrap">

                                        <input
                                            type="password"
                                            id="newPassword"
                                            name="new_password"
                                            class="security-form-input"
                                            minlength="6"
                                            autocomplete="new-password"
                                            required
                                            placeholder="Enter your new password">


                                        <button
                                            type="button"
                                            class="security-password-toggle"
                                            data-target="newPassword">

                                            Show

                                        </button>

                                    </div>

                                </div>


                                <!-- CONFIRM PASSWORD -->

                                <div class="security-form-group">

                                    <label
                                        for="confirmPassword"
                                        class="security-form-label">

                                        Confirm New Password

                                    </label>


                                    <div class="security-form-input-wrap">

                                        <input
                                            type="password"
                                            id="confirmPassword"
                                            name="confirm_password"
                                            class="security-form-input"
                                            minlength="6"
                                            autocomplete="new-password"
                                            required
                                            placeholder="Repeat your new password">


                                        <button
                                            type="button"
                                            class="security-password-toggle"
                                            data-target="confirmPassword">

                                            Show

                                        </button>

                                    </div>

                                </div>


                                <!-- REQUIREMENTS -->

                                <div class="security-requirements">

                                    <p class="security-requirements-title">
                                        Password requirements
                                    </p>

                                    <p class="security-requirements-text">
                                        Your password must contain at least 6 characters.
                                        Use a password that is unique to this store system.
                                    </p>

                                </div>


                                <!-- ACTION -->

                                <div class="security-form-actions">

                                    <button
                                        type="submit"
                                        class="security-save-button"
                                        id="savePasswordButton">

                                        Change Password

                                    </button>

                                </div>


                            </form>


                            <?php if ($success !== ''): ?>

                                <div class="security-status">

                                    <div class="security-status-icon">
                                        ✓
                                    </div>

                                    <p class="security-status-text">

                                        <?= htmlspecialchars(
                                            $success,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </p>

                                </div>

                            <?php endif; ?>


                        </div>

                    </div>


                    <!-- =================================================
                         SECURITY INFORMATION
                         ================================================= -->

                    <div class="security-card">

                        <div class="security-card-header">

                            <p class="security-card-title">
                                Account Security
                            </p>

                            <p class="security-card-subtitle">
                                Information about your current system access.
                            </p>

                        </div>


                        <div class="security-card-body">


                            <div class="security-info-list">


                                <!-- AUTHENTICATION -->

                                <div class="security-info-item">

                                    <div class="security-info-icon">
                                        🔐
                                    </div>


                                    <div>

                                        <p class="security-info-label">
                                            Password Protection
                                        </p>

                                        <p class="security-info-text">
                                            Your password is stored using a secure password hash.
                                        </p>

                                    </div>

                                </div>


                                <!-- ROLE -->

                                <div class="security-info-item">

                                    <div class="security-info-icon">
                                        🛡
                                    </div>


                                    <div>

                                        <p class="security-info-label">
                                            Account Role
                                        </p>

                                        <p class="security-info-text">

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $currentUserRole
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                            access is currently assigned to your account.

                                        </p>

                                    </div>

                                </div>


                                <!-- SESSION -->

                                <div class="security-info-item">

                                    <div class="security-info-icon">
                                        ●
                                    </div>


                                    <div>

                                        <p class="security-info-label">
                                            Session Status
                                        </p>

                                        <p class="security-info-text">
                                            Your account is currently signed in.
                                        </p>

                                    </div>

                                </div>


                                <!-- ACCESS -->

                                <div class="security-info-item">

                                    <div class="security-info-icon">
                                        ✓
                                    </div>


                                    <div>

                                        <p class="security-info-label">
                                            Access Control
                                        </p>

                                        <p class="security-info-text">
                                            System access is controlled through the store authentication system.
                                        </p>

                                    </div>

                                </div>


                            </div>


                        </div>

                    </div>


                </div>


                <!-- =================================================
                     LOW STOCK STATUS
                     ================================================= -->

                <?php if ($notificationCount > 0): ?>

                    <div
                        style="
                            display:flex;
                            align-items:center;
                            justify-content:space-between;
                            gap:15px;
                            margin-top:18px;
                            padding:13px 15px;
                            border:1px solid #fed7aa;
                            border-radius:12px;
                            background:#fffaf5;
                        ">

                        <div>

                            <div
                                style="
                                    margin-bottom:2px;
                                    color:#9a3412;
                                    font-size:10px;
                                    font-weight:750;
                                ">

                                Store notifications

                            </div>

                            <div
                                style="
                                    color:#c2410c;
                                    font-size:9px;
                                ">

                                <?= number_format($notificationCount) ?>

                                low-stock alert<?= $notificationCount === 1 ? '' : 's' ?>

                                currently require attention.

                            </div>

                        </div>


                        <a
                            href="<?= $basePath ?>products/"
                            style="
                                display:inline-flex;
                                align-items:center;
                                justify-content:center;
                                min-height:31px;
                                padding:0 10px;
                                border:1px solid #fed7aa;
                                border-radius:8px;
                                background:#ffffff;
                                color:#c2410c;
                                text-decoration:none;
                                font-size:9px;
                                font-weight:750;
                                white-space:nowrap;
                            ">

                            View Inventory

                        </a>

                    </div>

                <?php endif; ?>


            </div>


        </main>


    </div>


    <!-- =========================================================
         UNIVERSAL ADMIN JS
         ========================================================= -->

    <script
        src="<?= $basePath ?>assets/js/admin.js">
    </script>


    <script>
        (() => {

            const form =
                document.getElementById(
                    'changePasswordForm'
                );

            const errorBox =
                document.getElementById(
                    'securityError'
                );

            const saveButton =
                document.getElementById(
                    'savePasswordButton'
                );


            if (!form) {
                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Password Show / Hide
            |--------------------------------------------------------------------------
            */

            document
                .querySelectorAll(
                    '.security-password-toggle'
                )
                .forEach(button => {

                    button.addEventListener(
                        'click',
                        () => {

                            const targetId =
                                button.dataset.target;

                            const input =
                                document.getElementById(
                                    targetId
                                );


                            if (!input) {
                                return;
                            }


                            if (
                                input.type === 'password'
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

                });


            /*
            |--------------------------------------------------------------------------
            | Error Helper
            |--------------------------------------------------------------------------
            */

            function showError(message) {

                errorBox.textContent =
                    message;

                errorBox.classList.add(
                    'is-visible'
                );

            }


            function clearError() {

                errorBox.textContent =
                    '';

                errorBox.classList.remove(
                    'is-visible'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Form Submit
            |--------------------------------------------------------------------------
            */

            form.addEventListener(
                'submit',
                async (event) => {

                    event.preventDefault();


                    clearError();


                    if (
                        !form.reportValidity()
                    ) {
                        return;
                    }


                    const currentPassword =
                        document.getElementById(
                            'currentPassword'
                        ).value;


                    const newPassword =
                        document.getElementById(
                            'newPassword'
                        ).value;


                    const confirmPassword =
                        document.getElementById(
                            'confirmPassword'
                        ).value;


                    if (
                        newPassword !==
                        confirmPassword
                    ) {

                        showError(
                            'The new passwords do not match.'
                        );

                        return;
                    }


                    if (
                        newPassword.length < 6
                    ) {

                        showError(
                            'Your new password must be at least 6 characters.'
                        );

                        return;
                    }


                    if (
                        currentPassword ===
                        newPassword
                    ) {

                        showError(
                            'Your new password must be different from your current password.'
                        );

                        return;
                    }


                    const originalText =
                        saveButton.innerHTML;


                    saveButton.disabled =
                        true;

                    saveButton.innerHTML =
                        'Updating...';


                    try {

                        const response =
                            await fetch(
                                form.action, {
                                    method: 'POST',

                                    body: new FormData(
                                        form
                                    ),

                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',

                                        'Accept': 'application/json'
                                    }
                                }
                            );


                        const data =
                            await response.json();


                        if (!data.success) {

                            showError(
                                data.message ||
                                'Unable to change your password.'
                            );

                            return;
                        }


                        form.reset();


                        document
                            .querySelectorAll(
                                '.security-password-toggle'
                            )
                            .forEach(button => {

                                button.textContent =
                                    'Show';

                            });


                        if (
                            typeof window.showToast ===
                            'function'
                        ) {

                            window.showToast(
                                data.message ||
                                'Your password has been changed successfully.',
                                'success'
                            );

                        }


                    } catch (error) {

                        showError(
                            'Something went wrong. Please try again.'
                        );

                    } finally {

                        saveButton.disabled =
                            false;

                        saveButton.innerHTML =
                            originalText;

                    }

                }
            );

        })();
    </script>


</body>

</html>
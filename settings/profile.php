<?php

ob_start();

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();


/*
|--------------------------------------------------------------------------
| UNIVERSAL ADMIN LAYOUT
|--------------------------------------------------------------------------
*/

$basePath = '../';
$activeMenu = 'settings';
$currentSection = 'settings';


/*
|--------------------------------------------------------------------------
| CURRENT SESSION USER
|--------------------------------------------------------------------------
*/

$currentUserId =
    (int)($_SESSION['user_id'] ?? 0);

$currentUserName =
    $_SESSION['full_name'] ?? 'System User';

$currentUserRole =
    $_SESSION['role'] ?? 'staff';


if ($currentUserId <= 0) {

    header('Location: ../login.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| JSON RESPONSE HELPER
|--------------------------------------------------------------------------
*/

function profileJsonResponse(
    bool $success,
    string $message,
    array $extra = []
): never {

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

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
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD CURRENT USER
|--------------------------------------------------------------------------
*/

try {

    $userStmt = $pdo->prepare("
        SELECT
            id,
            username,
            full_name,
            role,
            is_active,
            created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $userStmt->execute([
        $currentUserId
    ]);

    $user = $userStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$user) {

        session_unset();
        session_destroy();

        header(
            'Location: ../login.php'
        );

        exit;
    }
} catch (PDOException $e) {

    error_log(
        'Profile load error: '
            . $e->getMessage()
    );

    die('Unable to load your profile.');
}


/*
|--------------------------------------------------------------------------
| AJAX DETECTION
|--------------------------------------------------------------------------
*/

$isAjax =
    isset(
        $_SERVER['HTTP_X_REQUESTED_WITH']
    )
    &&
    strtolower(
        $_SERVER['HTTP_X_REQUESTED_WITH']
    ) === 'xmlhttprequest';


/*
|--------------------------------------------------------------------------
| PROFILE UPDATE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    ($_POST['action'] ?? '') === 'update_profile'
) {

    $fullName =
        trim(
            $_POST['full_name'] ?? ''
        );

    $username =
        trim(
            $_POST['username'] ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($fullName === '') {

        $message =
            'Full name is required.';

        if ($isAjax) {
            profileJsonResponse(
                false,
                $message
            );
        }
    }


    if (
        mb_strlen($fullName) < 2
    ) {

        $message =
            'Full name must contain at least 2 characters.';

        if ($isAjax) {
            profileJsonResponse(
                false,
                $message
            );
        }
    }


    if (
        mb_strlen($fullName) > 100
    ) {

        $message =
            'Full name cannot exceed 100 characters.';

        if ($isAjax) {
            profileJsonResponse(
                false,
                $message
            );
        }
    }


    if ($username === '') {

        $message =
            'Username is required.';

        if ($isAjax) {
            profileJsonResponse(
                false,
                $message
            );
        }
    }


    if (
        !preg_match(
            '/^[A-Za-z0-9._-]+$/',
            $username
        )
    ) {

        $message =
            'Username may contain only letters, numbers, dots, underscores and hyphens.';

        if ($isAjax) {
            profileJsonResponse(
                false,
                $message
            );
        }
    }


    if (
        mb_strlen($username) < 3
    ) {

        $message =
            'Username must contain at least 3 characters.';

        if ($isAjax) {
            profileJsonResponse(
                false,
                $message
            );
        }
    }


    if (
        mb_strlen($username) > 50
    ) {

        $message =
            'Username cannot exceed 50 characters.';

        if ($isAjax) {
            profileJsonResponse(
                false,
                $message
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK USERNAME
    |--------------------------------------------------------------------------
    */

    try {

        $checkStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
              AND id != ?
            LIMIT 1
        ");

        $checkStmt->execute([
            $username,
            $currentUserId
        ]);


        if ($checkStmt->fetch()) {

            $message =
                'That username is already being used by another account.';

            if ($isAjax) {

                profileJsonResponse(
                    false,
                    $message
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        $updateStmt = $pdo->prepare("
            UPDATE users

            SET
                username = ?,
                full_name = ?

            WHERE id = ?
        ");

        $updateStmt->execute([
            $username,
            $fullName,
            $currentUserId
        ]);


        /*
        |--------------------------------------------------------------------------
        | UPDATE SESSION
        |--------------------------------------------------------------------------
        */

        $_SESSION['full_name'] =
            $fullName;


        /*
        |--------------------------------------------------------------------------
        | REFRESH USER DATA
        |--------------------------------------------------------------------------
        */

        $refreshStmt = $pdo->prepare("
            SELECT
                id,
                username,
                full_name,
                role,
                is_active,
                created_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $refreshStmt->execute([
            $currentUserId
        ]);

        $user =
            $refreshStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if ($isAjax) {

            profileJsonResponse(
                true,
                'Profile updated successfully.',
                [
                    'user' => [
                        'username' =>
                        $user['username'],

                        'full_name' =>
                        $user['full_name'],

                        'role' =>
                        $user['role'],

                        'is_active' =>
                        (int)$user['is_active']
                    ]
                ]
            );
        }


        header(
            'Location: profile.php?saved=1'
        );

        exit;
    } catch (PDOException $e) {

        error_log(
            'Profile update error: '
                . $e->getMessage()
        );


        $message =
            'Unable to update your profile. Please try again.';


        if ($isAjax) {

            profileJsonResponse(
                false,
                $message
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    ($_POST['action'] ?? '') === 'change_password'
) {

    $currentPassword =
        $_POST['current_password'] ?? '';

    $newPassword =
        $_POST['new_password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($currentPassword === '') {

        $message =
            'Current password is required.';

        if ($isAjax) {

            profileJsonResponse(
                false,
                $message
            );
        }
    }


    if ($newPassword === '') {

        $message =
            'New password is required.';

        if ($isAjax) {

            profileJsonResponse(
                false,
                $message
            );
        }
    }


    if (
        strlen($newPassword) < 8
    ) {

        $message =
            'New password must be at least 8 characters long.';

        if ($isAjax) {

            profileJsonResponse(
                false,
                $message
            );
        }
    }


    if (
        $newPassword !== $confirmPassword
    ) {

        $message =
            'New password and confirmation do not match.';

        if ($isAjax) {

            profileJsonResponse(
                false,
                $message
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GET CURRENT PASSWORD
    |--------------------------------------------------------------------------
    */

    try {

        $passwordStmt = $pdo->prepare("
            SELECT password
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $passwordStmt->execute([
            $currentUserId
        ]);

        $passwordHash =
            $passwordStmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | VERIFY CURRENT PASSWORD
        |--------------------------------------------------------------------------
        */

        if (
            !$passwordHash
            ||
            !password_verify(
                $currentPassword,
                $passwordHash
            )
        ) {

            $message =
                'Your current password is incorrect.';

            if ($isAjax) {

                profileJsonResponse(
                    false,
                    $message
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | PREVENT SAME PASSWORD
        |--------------------------------------------------------------------------
        */

        if (
            password_verify(
                $newPassword,
                $passwordHash
            )
        ) {

            $message =
                'Your new password must be different from your current password.';

            if ($isAjax) {

                profileJsonResponse(
                    false,
                    $message
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | HASH NEW PASSWORD
        |--------------------------------------------------------------------------
        */

        $newPasswordHash =
            password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );


        /*
        |--------------------------------------------------------------------------
        | UPDATE PASSWORD
        |--------------------------------------------------------------------------
        */

        $passwordUpdateStmt =
            $pdo->prepare("
                UPDATE users

                SET password = ?

                WHERE id = ?
            ");


        $passwordUpdateStmt->execute([
            $newPasswordHash,
            $currentUserId
        ]);


        if ($isAjax) {

            profileJsonResponse(
                true,
                'Password changed successfully.'
            );
        }


        header(
            'Location: profile.php?password_changed=1'
        );

        exit;
    } catch (PDOException $e) {

        error_log(
            'Password update error: '
                . $e->getMessage()
        );


        $message =
            'Unable to change your password. Please try again.';


        if ($isAjax) {

            profileJsonResponse(
                false,
                $message
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGES
|--------------------------------------------------------------------------
*/

$profileSaved =
    isset(
        $_GET['saved']
    );


$passwordChanged =
    isset(
        $_GET['password_changed']
    );


/*
|--------------------------------------------------------------------------
| PROFILE INITIALS
|--------------------------------------------------------------------------
*/

$profileInitials = '';

$nameParts =
    preg_split(
        '/\s+/',
        trim(
            $user['full_name']
        ),
        -1,
        PREG_SPLIT_NO_EMPTY
    );


if (!empty($nameParts)) {

    $profileInitials =
        strtoupper(
            substr(
                $nameParts[0],
                0,
                1
            )
        );


    if (
        count($nameParts) > 1
    ) {

        $lastPart =
            $nameParts[count($nameParts) - 1];


        $profileInitials .=
            strtoupper(
                substr(
                    $lastPart,
                    0,
                    1
                )
            );
    }
}


if ($profileInitials === '') {

    $profileInitials = 'U';
}


$roleLabel =
    ucfirst(
        strtolower(
            $user['role']
        )
    );


$statusLabel =
    (int)$user['is_active'] === 1
    ? 'Active'
    : 'Inactive';


/*
|--------------------------------------------------------------------------
| CREATED DATE
|--------------------------------------------------------------------------
*/

$createdDate = '';

if (
    !empty($user['created_at'])
) {

    try {

        $createdDate =
            (new DateTime(
                $user['created_at']
            ))->format(
                'F d, Y'
            );
    } catch (Exception $e) {

        $createdDate =
            $user['created_at'];
    }
}

?>

<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">


    <title>
        My Profile - Arnold & Jackie's Store
    </title>


    <!-- =========================================================
         UNIVERSAL ADMIN CSS
    ========================================================== -->

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">


    <style>
        /* =========================================================
           PAGE
        ========================================================== */

        .profile-page {

            width: 100%;
            max-width: none;

        }


        /* =========================================================
           HEADER
        ========================================================== */

        .profile-page-header {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 22px;

        }


        .profile-page-title {

            margin: 0 0 5px;

            color: #172033;

            font-size: 26px;

            font-weight: 750;

            line-height: 1.2;

            letter-spacing: -.025em;

        }


        .profile-page-subtitle {

            margin: 0;

            color: #7b8494;

            font-size: 13px;

            line-height: 1.6;

        }


        /* =========================================================
           BACK BUTTON
        ========================================================== */

        .profile-back-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-height: 38px;

            padding: 0 14px;

            border: 1px solid #dfe5ec;

            border-radius: 10px;

            background: #ffffff;

            color: #475569;

            text-decoration: none;

            font-size: 11px;

            font-weight: 700;

            white-space: nowrap;

            transition:

                border-color .18s ease,

                background-color .18s ease,

                color .18s ease,

                transform .18s ease;

        }


        .profile-back-button:hover {

            transform: translateX(-1px);

            background: #f8fafc;

            border-color: #cfd8e3;

            color: #1d4ed8;

        }


        /* =========================================================
           PROFILE HERO
        ========================================================== */

        .profile-hero {

            display: flex;

            align-items: center;

            gap: 16px;

            margin-bottom: 12px;

            padding: 20px;

            border: 1px solid #e5eaf0;

            border-radius: 16px;

            background:
                linear-gradient(180deg,
                    #ffffff 0%,
                    #fbfcfe 100%);

            box-shadow:
                0 7px 22px rgba(15, 23, 42, .025);

        }


        .profile-hero-avatar {

            width: 58px;

            height: 58px;

            flex: 0 0 58px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            border-radius: 17px;

            background:
                linear-gradient(135deg,
                    #2563eb,
                    #1d4ed8);

            color: #ffffff;

            font-size: 17px;

            font-weight: 800;

            box-shadow:
                0 7px 18px rgba(37, 99, 235, .16);

        }


        .profile-hero-name {

            margin: 0 0 3px;

            color: #202938;

            font-size: 16px;

            font-weight: 750;

        }


        .profile-hero-meta {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 8px;

            color: #8a93a2;

            font-size: 10px;

            font-weight: 600;

        }


        .profile-status {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            min-height: 20px;

            padding: 0 8px;

            border-radius: 999px;

            background: #ecfdf5;

            color: #15803d;

            font-size: 9px;

            font-weight: 800;

        }


        .profile-status-dot {

            width: 5px;

            height: 5px;

            border-radius: 50%;

            background: #22c55e;

        }


        /* =========================================================
           INFORMATION GRID
        ========================================================== */

        .profile-info-grid {

            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 10px;

            margin-bottom: 12px;

        }


        .profile-info-card {

            padding: 14px;

            border: 1px solid #e5eaf0;

            border-radius: 13px;

            background: #ffffff;

        }


        .profile-info-label {

            margin-bottom: 5px;

            color: #94a3b8;

            font-size: 9px;

            font-weight: 800;

            letter-spacing: .06em;

            text-transform: uppercase;

        }


        .profile-info-value {

            color: #253044;

            font-size: 12px;

            font-weight: 750;

            word-break: break-word;

        }


        /* =========================================================
           MAIN GRID
        ========================================================== */

        .profile-content-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 12px;

        }


        /* =========================================================
           SECTION CARD
        ========================================================== */

        .profile-section {

            overflow: hidden;

            border: 1px solid #e5eaf0;

            border-radius: 16px;

            background: #ffffff;

            box-shadow:
                0 7px 22px rgba(15, 23, 42, .025);

        }


        .profile-section-header {

            padding: 18px 20px;

            border-bottom: 1px solid #edf0f4;

        }


        .profile-section-title {

            margin: 0 0 4px;

            color: #202938;

            font-size: 14px;

            font-weight: 750;

        }


        .profile-section-subtitle {

            margin: 0;

            color: #8791a0;

            font-size: 10px;

            line-height: 1.5;

        }


        .profile-section-body {

            padding: 20px;

        }


        /* =========================================================
           FORM
        ========================================================== */

        .profile-field {

            margin-bottom: 15px;

        }


        .profile-field:last-child {

            margin-bottom: 0;

        }


        .profile-label {

            display: block;

            margin-bottom: 7px;

            color: #334155;

            font-size: 11px;

            font-weight: 750;

        }


        .profile-input {

            width: 100%;

            min-height: 42px;

            padding: 0 12px;

            border: 1px solid #dfe5ec;

            border-radius: 10px;

            background: #ffffff;

            color: #1f2937;

            font-family: inherit;

            font-size: 12px;

            outline: none;

            transition:

                border-color .18s ease,

                box-shadow .18s ease;

        }


        .profile-input:focus {

            border-color: #93b4f4;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, .08);

        }


        .profile-help {

            margin-top: 5px;

            color: #94a3b8;

            font-size: 9px;

            line-height: 1.5;

        }


        /* =========================================================
           PASSWORD WRAPPER
        ========================================================== */

        .password-wrapper {

            position: relative;

        }


        .password-wrapper .profile-input {

            padding-right: 44px;

        }


        .password-toggle {

            position: absolute;

            top: 50%;

            right: 8px;

            width: 31px;

            height: 31px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            transform: translateY(-50%);

            border: 0;

            border-radius: 8px;

            background: transparent;

            color: #94a3b8;

            cursor: pointer;

            transition:

                background-color .15s ease,

                color .15s ease;

        }


        .password-toggle:hover {

            background: #f1f5f9;

            color: #475569;

        }


        /* =========================================================
           BUTTON
        ========================================================== */

        .profile-form-footer {

            display: flex;

            justify-content: flex-end;

            padding-top: 18px;

            margin-top: 18px;

            border-top: 1px solid #edf0f4;

        }


        .profile-save-button {

            min-height: 40px;

            padding: 0 16px;

            border: 1px solid #2563eb;

            border-radius: 10px;

            background: #2563eb;

            color: #ffffff;

            font-family: inherit;

            font-size: 11px;

            font-weight: 750;

            cursor: pointer;

            box-shadow:
                0 5px 15px rgba(37, 99, 235, .14);

            transition:

                transform .18s ease,

                background-color .18s ease,

                border-color .18s ease;

        }


        .profile-save-button:hover {

            transform: translateY(-1px);

            background: #1d4ed8;

            border-color: #1d4ed8;

        }


        .profile-save-button:disabled {

            cursor: wait;

            opacity: .7;

        }


        /* =========================================================
           SECURITY NOTE
        ========================================================== */

        .profile-note {

            margin-top: 12px;

            padding: 13px 15px;

            border: 1px solid #dbeafe;

            border-radius: 12px;

            background: #f8fbff;

            color: #64748b;

            font-size: 10px;

            line-height: 1.6;

        }


        .profile-note strong {

            color: #1e3a8a;

        }


        /* =========================================================
           RESPONSIVE
        ========================================================== */

        @media (max-width: 850px) {

            .profile-page-header {

                flex-direction: column;

            }


            .profile-back-button {

                width: 100%;

            }


            .profile-content-grid {

                grid-template-columns: 1fr;

            }


            .profile-info-grid {

                grid-template-columns:
                    repeat(3, minmax(0, 1fr));

            }

        }


        @media (max-width: 600px) {

            .profile-info-grid {

                grid-template-columns: 1fr;

            }


            .profile-hero {

                align-items: flex-start;

            }


            .profile-section-body {

                padding: 16px;

            }

        }


        @media (max-width: 480px) {

            .profile-page-title {

                font-size: 23px;

            }


            .profile-form-footer {

                justify-content: stretch;

            }


            .profile-save-button {

                width: 100%;

            }

        }
    </style>

</head>


<body>


    <!-- =========================================================
         SIDEBAR
    ========================================================== -->

    <?php require_once $basePath . 'includes/sidebar.php'; ?>


    <div class="main">


        <!-- =====================================================
             TOPBAR
        ====================================================== -->

        <?php require_once $basePath . 'includes/topbar.php'; ?>


        <main class="content">


            <div class="profile-page">


                <!-- =================================================
                     PAGE HEADER
                ================================================== -->

                <div class="profile-page-header">

                    <div>

                        <h1 class="profile-page-title">
                            My Profile
                        </h1>


                        <p class="profile-page-subtitle">
                            Manage your personal account information and password.
                        </p>

                    </div>


                    <a
                        href="index.php"
                        class="profile-back-button">

                        ← Back to Settings

                    </a>

                </div>


                <!-- =================================================
                     PROFILE HERO
                ================================================== -->

                <section class="profile-hero">


                    <div class="profile-hero-avatar"
                        id="profileHeroAvatar">

                        <?= htmlspecialchars(
                            $profileInitials,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>


                    <div>

                        <h2
                            class="profile-hero-name"
                            id="profileHeroName">

                            <?= htmlspecialchars(
                                $user['full_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </h2>


                        <div class="profile-hero-meta">


                            <span>
                                @<?= htmlspecialchars(
                                        $user['username'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                            </span>


                            <span>
                                •
                            </span>


                            <span>
                                <?= htmlspecialchars(
                                    $roleLabel,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>


                            <span class="profile-status">

                                <span class="profile-status-dot"></span>

                                <?= htmlspecialchars(
                                    $statusLabel,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>


                        </div>

                    </div>


                </section>


                <!-- =================================================
                     ACCOUNT SUMMARY
                ================================================== -->

                <div class="profile-info-grid">


                    <div class="profile-info-card">

                        <div class="profile-info-label">
                            Username
                        </div>


                        <div
                            class="profile-info-value"
                            id="profileSummaryUsername">

                            <?= htmlspecialchars(
                                $user['username'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    </div>


                    <div class="profile-info-card">

                        <div class="profile-info-label">
                            Role
                        </div>


                        <div class="profile-info-value">

                            <?= htmlspecialchars(
                                $roleLabel,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    </div>


                    <div class="profile-info-card">

                        <div class="profile-info-label">
                            Account Created
                        </div>


                        <div class="profile-info-value">

                            <?= htmlspecialchars(
                                $createdDate,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    </div>


                </div>


                <!-- =================================================
                     CONTENT
                ================================================== -->

                <div class="profile-content-grid">


                    <!-- =================================================
                         EDIT PROFILE
                    ================================================== -->

                    <section class="profile-section">


                        <div class="profile-section-header">

                            <h2 class="profile-section-title">
                                Profile Information
                            </h2>


                            <p class="profile-section-subtitle">
                                Update the name and username associated with your account.
                            </p>

                        </div>


                        <div class="profile-section-body">


                            <form
                                method="post"
                                id="profileForm">


                                <input
                                    type="hidden"
                                    name="action"
                                    value="update_profile">


                                <!-- FULL NAME -->

                                <div class="profile-field">

                                    <label
                                        for="profileFullName"
                                        class="profile-label">

                                        Full Name

                                    </label>


                                    <input
                                        type="text"
                                        id="profileFullName"
                                        name="full_name"
                                        class="profile-input"
                                        maxlength="100"
                                        value="<?= htmlspecialchars(
                                                    $user['full_name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                        required>


                                    <div class="profile-help">
                                        This name is displayed throughout the system.
                                    </div>

                                </div>


                                <!-- USERNAME -->

                                <div class="profile-field">

                                    <label
                                        for="profileUsername"
                                        class="profile-label">

                                        Username

                                    </label>


                                    <input
                                        type="text"
                                        id="profileUsername"
                                        name="username"
                                        class="profile-input"
                                        maxlength="50"
                                        value="<?= htmlspecialchars(
                                                    $user['username'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                        autocomplete="username"
                                        required>


                                    <div class="profile-help">
                                        Use letters, numbers, dots, underscores or hyphens.
                                    </div>

                                </div>


                                <div class="profile-form-footer">

                                    <button
                                        type="submit"
                                        class="profile-save-button"
                                        id="saveProfileButton">

                                        Save Profile

                                    </button>

                                </div>


                            </form>


                        </div>

                    </section>


                    <!-- =================================================
                         CHANGE PASSWORD
                    ================================================== -->

                    <section class="profile-section">


                        <div class="profile-section-header">

                            <h2 class="profile-section-title">
                                Change Password
                            </h2>


                            <p class="profile-section-subtitle">
                                Update your password using your current password for verification.
                            </p>

                        </div>


                        <div class="profile-section-body">


                            <form
                                method="post"
                                id="passwordForm">


                                <input
                                    type="hidden"
                                    name="action"
                                    value="change_password">


                                <!-- CURRENT PASSWORD -->

                                <div class="profile-field">

                                    <label
                                        for="currentPassword"
                                        class="profile-label">

                                        Current Password

                                    </label>


                                    <div class="password-wrapper">

                                        <input
                                            type="password"
                                            id="currentPassword"
                                            name="current_password"
                                            class="profile-input"
                                            autocomplete="current-password"
                                            required>


                                        <button
                                            type="button"
                                            class="password-toggle"
                                            data-target="currentPassword"
                                            aria-label="Show current password">

                                            👁

                                        </button>

                                    </div>

                                </div>


                                <!-- NEW PASSWORD -->

                                <div class="profile-field">

                                    <label
                                        for="newPassword"
                                        class="profile-label">

                                        New Password

                                    </label>


                                    <div class="password-wrapper">

                                        <input
                                            type="password"
                                            id="newPassword"
                                            name="new_password"
                                            class="profile-input"
                                            minlength="8"
                                            autocomplete="new-password"
                                            required>


                                        <button
                                            type="button"
                                            class="password-toggle"
                                            data-target="newPassword"
                                            aria-label="Show new password">

                                            👁

                                        </button>

                                    </div>


                                    <div class="profile-help">
                                        Minimum of 8 characters.
                                    </div>

                                </div>


                                <!-- CONFIRM PASSWORD -->

                                <div class="profile-field">

                                    <label
                                        for="confirmPassword"
                                        class="profile-label">

                                        Confirm New Password

                                    </label>


                                    <div class="password-wrapper">

                                        <input
                                            type="password"
                                            id="confirmPassword"
                                            name="confirm_password"
                                            class="profile-input"
                                            minlength="8"
                                            autocomplete="new-password"
                                            required>


                                        <button
                                            type="button"
                                            class="password-toggle"
                                            data-target="confirmPassword"
                                            aria-label="Show confirm password">

                                            👁

                                        </button>

                                    </div>

                                </div>


                                <div class="profile-form-footer">

                                    <button
                                        type="submit"
                                        class="profile-save-button"
                                        id="changePasswordButton">

                                        Change Password

                                    </button>

                                </div>


                            </form>


                        </div>

                    </section>


                </div>


                <!-- =================================================
                     NOTE
                ================================================== -->

                <div class="profile-note">

                    <strong>
                        Account note:
                    </strong>

                    Your role and account status are controlled by the system administrator.
                    Changing your profile information does not change your assigned role or permissions.

                </div>


            </div>


        </main>


    </div>


    <!-- =========================================================
         UNIVERSAL ADMIN JS
    ========================================================== -->

    <script
        src="<?= $basePath ?>assets/js/admin.js">
    </script>


    <!-- =========================================================
         PROFILE JAVASCRIPT
    ========================================================== -->

    <script>
        (function() {


            /*
            |--------------------------------------------------------------------------
            | PROFILE FORM
            |--------------------------------------------------------------------------
            */

            const profileForm =
                document.getElementById(
                    'profileForm'
                );


            const saveProfileButton =
                document.getElementById(
                    'saveProfileButton'
                );


            /*
            |--------------------------------------------------------------------------
            | PASSWORD FORM
            |--------------------------------------------------------------------------
            */

            const passwordForm =
                document.getElementById(
                    'passwordForm'
                );


            const changePasswordButton =
                document.getElementById(
                    'changePasswordButton'
                );


            /*
            |--------------------------------------------------------------------------
            | PROFILE ELEMENTS
            |--------------------------------------------------------------------------
            */

            const profileFullName =
                document.getElementById(
                    'profileFullName'
                );


            const profileUsername =
                document.getElementById(
                    'profileUsername'
                );


            const profileHeroName =
                document.getElementById(
                    'profileHeroName'
                );


            const profileHeroAvatar =
                document.getElementById(
                    'profileHeroAvatar'
                );


            const profileSummaryUsername =
                document.getElementById(
                    'profileSummaryUsername'
                );


            /*
            |--------------------------------------------------------------------------
            | INITIALS
            |--------------------------------------------------------------------------
            */

            function getInitials(
                name
            ) {

                const parts =
                    name.trim().split(
                        /\s+/
                    );


                if (
                    !parts.length ||
                    !parts[0]
                ) {

                    return 'U';

                }


                let initials =
                    parts[0]
                    .charAt(0)
                    .toUpperCase();


                if (
                    parts.length > 1
                ) {

                    initials +=
                        parts[
                            parts.length - 1
                        ]
                        .charAt(0)
                        .toUpperCase();

                }


                return initials;

            }


            /*
            |--------------------------------------------------------------------------
            | SHOW TOAST
            |--------------------------------------------------------------------------
            */

            function showMessage(
                type,
                title,
                message
            ) {

                if (
                    typeof window.showToast ===
                    'function'
                ) {

                    window.showToast(
                        type,
                        title,
                        message
                    );

                    return;

                }


                alert(
                    message
                );

            }


            /*
            |--------------------------------------------------------------------------
            | PROFILE FORM
            |--------------------------------------------------------------------------
            */

            if (
                profileForm &&
                saveProfileButton
            ) {

                profileForm.addEventListener(
                    'submit',
                    async function(event) {

                        event.preventDefault();


                        saveProfileButton.disabled =
                            true;


                        saveProfileButton.textContent =
                            'Saving...';


                        try {

                            const formData =
                                new FormData(
                                    profileForm
                                );


                            const response =
                                await fetch(
                                    'profile.php', {
                                        method: 'POST',

                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest'
                                        },

                                        body: formData
                                    }
                                );


                            const data =
                                await response.json();


                            if (
                                !data.success
                            ) {

                                showMessage(
                                    'error',
                                    'Unable to save',
                                    data.message ||
                                    'Unable to update your profile.'
                                );

                                return;

                            }


                            const updatedUser =
                                data.user || {};


                            /*
                            |--------------------------------------------------------------------------
                            | UPDATE PAGE
                            |--------------------------------------------------------------------------
                            */

                            if (
                                updatedUser.full_name
                            ) {

                                profileHeroName.textContent =
                                    updatedUser.full_name;

                            }


                            if (
                                updatedUser.username
                            ) {

                                profileSummaryUsername.textContent =
                                    updatedUser.username;

                                document.querySelectorAll(
                                    '.topbar-profile-name'
                                ).forEach(
                                    function(element) {

                                        /*
                                        | Topbar name should stay the
                                        | same here because it displays
                                        | full_name, not username.
                                        */

                                    }
                                );

                            }


                            if (
                                updatedUser.full_name &&
                                profileHeroAvatar
                            ) {

                                profileHeroAvatar.textContent =
                                    getInitials(
                                        updatedUser.full_name
                                    );

                            }


                            /*
                            |--------------------------------------------------------------------------
                            | UPDATE TOPBAR
                            |--------------------------------------------------------------------------
                            */

                            if (
                                updatedUser.full_name
                            ) {

                                document.querySelectorAll(
                                    '.topbar-profile-name'
                                ).forEach(
                                    function(element) {

                                        element.textContent =
                                            updatedUser.full_name;

                                    }
                                );


                                document.querySelectorAll(
                                    '.topbar-profile-avatar'
                                ).forEach(
                                    function(element) {

                                        element.textContent =
                                            getInitials(
                                                updatedUser.full_name
                                            );

                                    }
                                );


                                document.querySelectorAll(
                                    '.profile-dropdown-name'
                                ).forEach(
                                    function(element) {

                                        element.textContent =
                                            updatedUser.full_name;

                                    }
                                );


                                document.querySelectorAll(
                                    '.profile-dropdown-avatar'
                                ).forEach(
                                    function(element) {

                                        element.textContent =
                                            getInitials(
                                                updatedUser.full_name
                                            );

                                    }
                                );

                            }


                            showMessage(
                                'success',
                                'Profile updated',
                                data.message
                            );


                        } catch (
                            error
                        ) {

                            showMessage(
                                'error',
                                'Connection error',
                                'Unable to communicate with the server.'
                            );


                        } finally {

                            saveProfileButton.disabled =
                                false;

                            saveProfileButton.textContent =
                                'Save Profile';

                        }

                    }
                );

            }


            /*
            |--------------------------------------------------------------------------
            | PASSWORD FORM
            |--------------------------------------------------------------------------
            */

            if (
                passwordForm &&
                changePasswordButton
            ) {

                passwordForm.addEventListener(
                    'submit',
                    async function(event) {

                        event.preventDefault();


                        changePasswordButton.disabled =
                            true;


                        changePasswordButton.textContent =
                            'Changing...';


                        try {

                            const formData =
                                new FormData(
                                    passwordForm
                                );


                            const response =
                                await fetch(
                                    'profile.php', {
                                        method: 'POST',

                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest'
                                        },

                                        body: formData
                                    }
                                );


                            const data =
                                await response.json();


                            if (
                                !data.success
                            ) {

                                showMessage(
                                    'error',
                                    'Unable to change password',
                                    data.message ||
                                    'Unable to change your password.'
                                );

                                return;

                            }


                            passwordForm.reset();


                            showMessage(
                                'success',
                                'Password changed',
                                data.message
                            );


                        } catch (
                            error
                        ) {

                            showMessage(
                                'error',
                                'Connection error',
                                'Unable to communicate with the server.'
                            );


                        } finally {

                            changePasswordButton.disabled =
                                false;

                            changePasswordButton.textContent =
                                'Change Password';

                        }

                    }
                );

            }


            /*
            |--------------------------------------------------------------------------
            | PASSWORD VISIBILITY
            |--------------------------------------------------------------------------
            */

            document.querySelectorAll(
                '.password-toggle'
            ).forEach(
                function(button) {

                    button.addEventListener(
                        'click',
                        function() {

                            const targetId =
                                button.getAttribute(
                                    'data-target'
                                );


                            const target =
                                document.getElementById(
                                    targetId
                                );


                            if (!target) {
                                return;
                            }


                            if (
                                target.type ===
                                'password'
                            ) {

                                target.type =
                                    'text';

                                button.textContent =
                                    '🙈';

                            } else {

                                target.type =
                                    'password';

                                button.textContent =
                                    '👁';

                            }

                        }
                    );

                }
            );


            /*
            |--------------------------------------------------------------------------
            | SUCCESS MESSAGE FROM NORMAL POST
            |--------------------------------------------------------------------------
            */

            <?php if ($profileSaved): ?>

                showMessage(
                    'success',
                    'Profile updated',
                    'Your profile information has been updated successfully.'
                );

            <?php endif; ?>


            <?php if ($passwordChanged): ?>

                showMessage(
                    'success',
                    'Password changed',
                    'Your password has been changed successfully.'
                );

            <?php endif; ?>


        })();
    </script>


</body>

</html>
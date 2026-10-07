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
| Current Logged-In User
|--------------------------------------------------------------------------
*/

$currentUserName = $_SESSION['full_name'] ?? 'System User';
$currentUserRole = $_SESSION['role'] ?? 'staff';


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


/*
|--------------------------------------------------------------------------
| User Avatar Initial
|--------------------------------------------------------------------------
*/

$userInitial = strtoupper(
    substr(
        trim($currentUserName),
        0,
        1
    )
);

?>

<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>
        Settings - Arnold & Jackie's Store
    </title>


    <!-- =========================================================
         UNIVERSAL ADMIN CSS
    ========================================================== -->

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">


    <!-- =========================================================
         SETTINGS PAGE CSS
    ========================================================== -->

    <style>
        /* =========================================================
           SETTINGS PAGE
        ========================================================== */

        .settings-page {
            width: 100%;
            max-width: none;
            margin: 0;
        }


        /* =========================================================
           HEADER
        ========================================================== */

        .settings-header {
            width: 100%;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
        }


        .settings-heading {
            min-width: 0;
        }


        .settings-title {
            margin: 0 0 5px;
            color: #172033;
            font-size: 26px;
            font-weight: 750;
            line-height: 1.2;
            letter-spacing: -.025em;
        }


        .settings-subtitle {
            margin: 0;
            max-width: 650px;
            color: #7b8494;
            font-size: 13px;
            line-height: 1.6;
        }


        /* =========================================================
           CURRENT USER
        ========================================================== */

        .settings-user-card {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 220px;
            padding: 12px 14px;

            border: 1px solid #e5eaf0;
            border-radius: 14px;

            background: #ffffff;

            box-shadow:
                0 8px 25px rgba(15, 23, 42, .035);
        }


        .settings-avatar {
            width: 40px;
            height: 40px;
            flex: 0 0 40px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 11px;

            background: #eff6ff;
            color: #2563eb;

            font-size: 14px;
            font-weight: 800;
        }


        .settings-user-name {
            margin: 0 0 2px;
            color: #202938;
            font-size: 12px;
            font-weight: 750;
            line-height: 1.35;
        }


        .settings-user-role {
            margin: 0;
            color: #8a93a2;
            font-size: 10px;
            font-weight: 600;
            text-transform: capitalize;
        }


        /* =========================================================
           SECTION
        ========================================================== */

        .settings-section {
            margin-top: 28px;
        }


        .settings-section:first-of-type {
            margin-top: 0;
        }


        .settings-section-label {
            margin: 0 0 11px;

            color: #8a93a2;

            font-size: 10px;
            font-weight: 800;

            letter-spacing: .09em;
            text-transform: uppercase;
        }


        /* =========================================================
           STACKED SETTINGS LAYOUT
        ========================================================== */

        .settings-grid {
            width: 100%;

            display: flex;
            flex-direction: column;

            gap: 10px;
        }


        /* =========================================================
           SETTINGS CARD
        ========================================================== */

        .settings-card {
            position: relative;

            width: 100%;
            min-height: 118px;

            display: flex;
            align-items: center;

            gap: 15px;

            padding: 20px;

            border: 1px solid #e5eaf0;
            border-radius: 16px;

            background: #ffffff;

            color: inherit;
            text-decoration: none;

            box-shadow:
                0 7px 22px rgba(15, 23, 42, .025);

            transition:
                transform .18s ease,
                border-color .18s ease,
                box-shadow .18s ease,
                background-color .18s ease;
        }


        .settings-card:hover {
            transform: translateY(-2px);

            border-color: #d7e0eb;

            background: #ffffff;

            color: inherit;

            box-shadow:
                0 15px 35px rgba(15, 23, 42, .065);
        }


        /* =========================================================
           ICON
        ========================================================== */

        .settings-icon {
            width: 46px;
            height: 46px;
            flex: 0 0 46px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 13px;

            font-size: 18px;
            font-weight: 800;

            transition:
                transform .18s ease;
        }


        .settings-card:hover .settings-icon {
            transform: scale(1.035);
        }


        .settings-icon-blue {
            background: #eff6ff;
            color: #2563eb;
        }


        .settings-icon-green {
            background: #ecfdf5;
            color: #16a34a;
        }


        .settings-icon-slate {
            background: #f1f5f9;
            color: #475569;
        }


        .settings-icon-purple {
            background: #f5f3ff;
            color: #7c3aed;
        }


        /* =========================================================
           CARD CONTENT
        ========================================================== */

        .settings-card-content {
            flex: 1;
            min-width: 0;
        }


        .settings-card-title-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;

            gap: 7px;

            margin-bottom: 4px;
        }


        .settings-card-title {
            margin: 0;

            color: #202938;

            font-size: 14px;
            font-weight: 750;

            line-height: 1.4;
        }


        .settings-card-description {
            margin: 0;

            color: #7d8795;

            font-size: 11px;
            line-height: 1.6;
        }


        .settings-card-footer {
            display: flex;
            align-items: center;
            gap: 6px;

            margin-top: 10px;

            color: #94a3b8;

            font-size: 10px;
            font-weight: 650;
        }


        /* =========================================================
           STATUS DOT
        ========================================================== */

        .settings-status-dot {
            width: 5px;
            height: 5px;
            flex: 0 0 5px;

            border-radius: 50%;

            background: #22c55e;
        }


        /* =========================================================
           ARROW
        ========================================================== */

        .settings-arrow {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border: 1px solid #e4e9ef;
            border-radius: 9px;

            color: #94a3b8;

            font-size: 15px;

            transition:
                transform .18s ease,
                color .18s ease,
                border-color .18s ease;
        }


        .settings-card:hover .settings-arrow {
            transform: translateX(2px);

            color: #2563eb;
            border-color: #cddcf4;
        }


        /* =========================================================
           BADGES
        ========================================================== */

        .settings-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 18px;

            padding: 0 7px;

            border-radius: 999px;

            background: #f1f5f9;
            color: #64748b;

            font-size: 8px;
            font-weight: 800;

            letter-spacing: .045em;

            text-transform: uppercase;
        }


        .settings-badge-core {
            background: #ecfdf5;
            color: #15803d;
        }


        .settings-badge-admin {
            background: #eff6ff;
            color: #1d4ed8;
        }


        /* =========================================================
           SYSTEM STATUS
        ========================================================== */

        .settings-status-panel {
            display: grid;

            grid-template-columns:
                minmax(0, 1fr) auto;

            align-items: center;

            gap: 20px;

            margin-top: 18px;
            padding: 17px 19px;

            border: 1px solid #e5eaf0;
            border-radius: 15px;

            background:
                linear-gradient(180deg,
                    #ffffff 0%,
                    #fbfcfe 100%);
        }


        .settings-status-title {
            margin: 0 0 3px;

            color: #253044;

            font-size: 12px;
            font-weight: 750;
        }


        .settings-status-text {
            margin: 0;

            color: #7f8998;

            font-size: 10px;
            line-height: 1.6;
        }


        /* =========================================================
           LOW STOCK ALERT
        ========================================================== */

        .settings-stock-alert {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            gap: 8px;

            min-height: 34px;

            padding: 0 11px;

            border: 1px solid #fed7aa;
            border-radius: 10px;

            background: #fffaf5;
            color: #c2410c;

            text-decoration: none;

            font-size: 10px;
            font-weight: 750;

            white-space: nowrap;

            transition:
                background-color .15s ease,
                border-color .15s ease,
                color .15s ease;
        }


        .settings-stock-alert:hover {
            background: #fff7ed;

            border-color: #fdba74;

            color: #9a3412;
        }


        .settings-stock-alert-ok {
            border-color: #bbf7d0;

            background: #f0fdf4;

            color: #15803d;
        }


        .settings-stock-alert-ok:hover {
            background: #ecfdf5;

            border-color: #86efac;

            color: #166534;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================== */

        @media (max-width: 900px) {

            .settings-header {
                flex-direction: column;
            }


            .settings-user-card {
                width: 100%;
                min-width: 0;
            }

        }


        @media (max-width: 520px) {

            .settings-title {
                font-size: 23px;
            }


            .settings-card {
                padding: 17px;
            }


            .settings-icon {
                width: 42px;
                height: 42px;
                flex-basis: 42px;

                border-radius: 11px;

                font-size: 17px;
            }


            .settings-arrow {
                display: none;
            }


            .settings-status-panel {
                grid-template-columns: 1fr;

                padding: 15px;
            }

        }
    </style>

</head>


<body>


    <!-- =========================================================
         UNIVERSAL SIDEBAR
    ========================================================== -->

    <?php require_once $basePath . 'includes/sidebar.php'; ?>


    <div class="main">


        <!-- =====================================================
             UNIVERSAL TOPBAR
        ====================================================== -->

        <?php require_once $basePath . 'includes/topbar.php'; ?>


        <main class="content">


            <div class="settings-page">


                <!-- =================================================
                     HEADER
                ================================================== -->

                <div class="settings-header">

                    <div class="settings-heading">

                        <h1 class="settings-title">
                            Settings
                        </h1>


                        <p class="settings-subtitle">
                            Manage your store, user access and system preferences.
                        </p>

                    </div>


                    <!-- CURRENT USER -->

                    <div class="settings-user-card">

                        <div class="settings-avatar">

                            <?= htmlspecialchars(
                                $userInitial,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>


                        <div>

                            <p class="settings-user-name">

                                <?= htmlspecialchars(
                                    $currentUserName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>


                            <p class="settings-user-role">

                                <?= htmlspecialchars(
                                    $currentUserRole,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                account

                            </p>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     ADMINISTRATION
                ================================================== -->

                <section class="settings-section">

                    <div class="settings-section-label">
                        Administration
                    </div>


                    <div class="settings-grid">


                        <!-- USER ACCOUNTS -->

                        <a
                            href="users.php"
                            class="settings-card">

                            <div class="settings-icon settings-icon-blue">
                                👤
                            </div>


                            <div class="settings-card-content">

                                <div class="settings-card-title-row">

                                    <h2 class="settings-card-title">
                                        User Accounts
                                    </h2>


                                    <span class="settings-badge settings-badge-core">
                                        Core
                                    </span>

                                </div>


                                <p class="settings-card-description">
                                    Create and manage staff accounts, roles, passwords and account status.
                                </p>


                                <div class="settings-card-footer">

                                    <span class="settings-status-dot"></span>

                                    Manage system access

                                </div>

                            </div>


                            <span class="settings-arrow">
                                →
                            </span>

                        </a>


                    </div>

                </section>


                <!-- =================================================
                     SYSTEM
                ================================================== -->

                <section class="settings-section">

                    <div class="settings-section-label">
                        System
                    </div>


                    <div class="settings-grid">


                        <!-- SYSTEM PREFERENCES -->

                        <a
                            href="system.php"
                            class="settings-card">

                            <div class="settings-icon settings-icon-slate">
                                ⚙
                            </div>


                            <div class="settings-card-content">

                                <div class="settings-card-title-row">

                                    <h2 class="settings-card-title">
                                        System Preferences
                                    </h2>


                                    <span class="settings-badge settings-badge-core">
                                        Core
                                    </span>

                                </div>


                                <p class="settings-card-description">
                                    Configure currency, date formats, timezone and general POS system behavior.
                                </p>


                                <div class="settings-card-footer">

                                    <span class="settings-status-dot"></span>

                                    General system behavior

                                </div>

                            </div>


                            <span class="settings-arrow">
                                →
                            </span>

                        </a>


                        <!-- =========================================================
     BACKUP & DATA
========================================================== -->

                        <a
                            href="backup.php"
                            class="settings-card">

                            <div class="settings-icon settings-icon-purple">
                                💾
                            </div>


                            <div class="settings-card-content">

                                <div class="settings-card-title-row">

                                    <h2 class="settings-card-title">
                                        Backup & Data
                                    </h2>


                                    <span class="settings-badge settings-badge-admin">
                                        Admin
                                    </span>

                                </div>


                                <p class="settings-card-description">
                                    Create, download and manage backups of your store database and important system data.
                                </p>


                                <div class="settings-card-footer">

                                    <span class="settings-status-dot"></span>

                                    Database backup and data protection

                                </div>

                            </div>


                            <span class="settings-arrow">
                                →
                            </span>

                        </a>

                </section>


                <!-- =================================================
                     SYSTEM STATUS
                ================================================== -->

                <div class="settings-status-panel">


                    <div>

                        <p class="settings-status-title">
                            System status
                        </p>


                        <p class="settings-status-text">
                            Your account is currently signed in.
                            Low-stock alerts are connected to the store notification system.
                        </p>

                    </div>


                    <?php if ($notificationCount > 0): ?>

                        <a
                            href="<?= $basePath ?>products/"
                            class="settings-stock-alert">

                            <span>
                                ●
                            </span>


                            <?= number_format($notificationCount) ?>


                            low-stock alert<?=

                                            $notificationCount === 1
                                                ? ''
                                                : 's'

                                            ?>

                        </a>


                    <?php else: ?>


                        <div
                            class="settings-stock-alert settings-stock-alert-ok">

                            <span>
                                ●
                            </span>

                            No low-stock alerts

                        </div>


                    <?php endif; ?>


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


</body>

</html>
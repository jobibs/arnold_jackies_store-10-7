<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();


/*
|--------------------------------------------------------------------------
| ADMIN ONLY
|--------------------------------------------------------------------------
*/

if (($_SESSION['role'] ?? '') !== 'admin') {

    header('Location: ../dashboard.php');

    exit;
}


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
| CURRENT USER
|--------------------------------------------------------------------------
*/

$currentUserName = $_SESSION['full_name'] ?? 'System User';
$currentUserRole = $_SESSION['role'] ?? 'staff';


/*
|--------------------------------------------------------------------------
| DEFAULT SYSTEM SETTINGS
|--------------------------------------------------------------------------
*/

$defaults = [

    'currency' => 'PHP',

    'currency_symbol' => '₱',

    'date_format' => 'F d, Y',

    'timezone' => 'Asia/Manila',

    'decimal_places' => '2',

    'default_payment_method' => 'cash',

    'low_stock_notifications' => '1'

];


/*
|--------------------------------------------------------------------------
| CREATE SYSTEM SETTINGS TABLE
|--------------------------------------------------------------------------
*/

try {

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (

            id INT UNSIGNED NOT NULL AUTO_INCREMENT,

            currency VARCHAR(20) NOT NULL DEFAULT 'PHP',

            currency_symbol VARCHAR(10) NOT NULL DEFAULT '₱',

            date_format VARCHAR(50) NOT NULL DEFAULT 'F d, Y',

            timezone VARCHAR(100) NOT NULL DEFAULT 'Asia/Manila',

            decimal_places TINYINT UNSIGNED NOT NULL DEFAULT 2,

            default_payment_method VARCHAR(20) NOT NULL DEFAULT 'cash',

            low_stock_notifications TINYINT(1) NOT NULL DEFAULT 1,

            updated_at TIMESTAMP
                NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id)

        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ");


    /*
    |--------------------------------------------------------------------------
    | CHECK EXISTING SETTINGS
    |--------------------------------------------------------------------------
    */

    $settingsStmt = $pdo->query("
        SELECT
            currency,
            currency_symbol,
            date_format,
            timezone,
            decimal_places,
            default_payment_method,
            low_stock_notifications
        FROM system_settings
        WHERE id = 1
        LIMIT 1
    ");

    $savedSettings = $settingsStmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | CREATE DEFAULT SETTINGS
    |--------------------------------------------------------------------------
    */

    if (!$savedSettings) {

        $insertStmt = $pdo->prepare("
            INSERT INTO system_settings (
                id,
                currency,
                currency_symbol,
                date_format,
                timezone,
                decimal_places,
                default_payment_method,
                low_stock_notifications
            )
            VALUES (
                1,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $insertStmt->execute([
            $defaults['currency'],
            $defaults['currency_symbol'],
            $defaults['date_format'],
            $defaults['timezone'],
            $defaults['decimal_places'],
            $defaults['default_payment_method'],
            $defaults['low_stock_notifications']
        ]);

        $savedSettings = $defaults;
    }
} catch (PDOException $e) {

    die('Unable to load system preferences: '
        . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        ));
}


/*
|--------------------------------------------------------------------------
| MERGE SETTINGS
|--------------------------------------------------------------------------
*/

$settings = array_merge(
    $defaults,
    $savedSettings ?? []
);


/*
|--------------------------------------------------------------------------
| SAVE SETTINGS
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $currency = trim(
        $_POST['currency'] ?? 'PHP'
    );

    $currencySymbol = trim(
        $_POST['currency_symbol'] ?? '₱'
    );

    $dateFormat = trim(
        $_POST['date_format'] ?? 'F d, Y'
    );

    $timezone = trim(
        $_POST['timezone'] ?? 'Asia/Manila'
    );

    $decimalPlaces = (int)(
        $_POST['decimal_places'] ?? 2
    );

    $defaultPaymentMethod =
        strtolower(
            trim(
                $_POST['default_payment_method']
                    ?? 'cash'
            )
        );

    $lowStockNotifications =
        isset(
            $_POST['low_stock_notifications']
        )
        ? 1
        : 0;


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedDateFormats = [

        'F d, Y',

        'M d, Y',

        'd F Y',

        'Y-m-d',

        'd/m/Y',

        'm/d/Y'

    ];


    $allowedPaymentMethods = [

        'cash',

        'gcash',

        'maya'

    ];


    if ($currency === '') {

        $errorMessage =
            'Currency code is required.';
    } elseif ($currencySymbol === '') {

        $errorMessage =
            'Currency symbol is required.';
    } elseif (!in_array(
        $dateFormat,
        $allowedDateFormats,
        true
    )) {

        $errorMessage =
            'Please select a valid date format.';
    } elseif (!in_array(
        $defaultPaymentMethod,
        $allowedPaymentMethods,
        true
    )) {

        $errorMessage =
            'Please select a valid payment method.';
    } elseif (
        !in_array(
            $decimalPlaces,
            [0, 1, 2, 3],
            true
        )
    ) {

        $errorMessage =
            'Decimal places must be between 0 and 3.';
    } else {


        /*
        |--------------------------------------------------------------------------
        | VALIDATE TIMEZONE
        |--------------------------------------------------------------------------
        */

        try {

            new DateTimeZone($timezone);
        } catch (Exception $e) {

            $errorMessage =
                'Please select a valid timezone.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE
    |--------------------------------------------------------------------------
    */

    if ($errorMessage === '') {

        try {

            $updateStmt = $pdo->prepare("
                UPDATE system_settings

                SET
                    currency = ?,
                    currency_symbol = ?,
                    date_format = ?,
                    timezone = ?,
                    decimal_places = ?,
                    default_payment_method = ?,
                    low_stock_notifications = ?

                WHERE id = 1
            ");


            $updateStmt->execute([

                $currency,

                $currencySymbol,

                $dateFormat,

                $timezone,

                $decimalPlaces,

                $defaultPaymentMethod,

                $lowStockNotifications

            ]);


            $successMessage =
                'System preferences saved successfully.';


            /*
            |--------------------------------------------------------------------------
            | REFRESH SETTINGS
            |--------------------------------------------------------------------------
            */

            $refreshStmt = $pdo->query("
                SELECT
                    currency,
                    currency_symbol,
                    date_format,
                    timezone,
                    decimal_places,
                    default_payment_method,
                    low_stock_notifications
                FROM system_settings
                WHERE id = 1
                LIMIT 1
            ");


            $settings = array_merge(
                $defaults,
                $refreshStmt->fetch(PDO::FETCH_ASSOC)
            );
        } catch (PDOException $e) {

            $errorMessage =
                'Unable to save system preferences.';

            error_log(
                'System preferences error: '
                    . $e->getMessage()
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT DATE PREVIEW
|--------------------------------------------------------------------------
*/

try {

    $previewTimezone =
        new DateTimeZone(
            $settings['timezone']
        );

    $previewDate = new DateTime(
        'now',
        $previewTimezone
    );

    $formattedPreviewDate =
        $previewDate->format(
            $settings['date_format']
        );
} catch (Exception $e) {

    $formattedPreviewDate =
        date('F d, Y');
}


/*
|--------------------------------------------------------------------------
| USER AVATAR
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
        System Preferences - Arnold & Jackie's Store
    </title>


    <!-- =========================================================
         UNIVERSAL CSS
    ========================================================== -->

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">


    <style>
        /* =========================================================
           PAGE
        ========================================================== */

        .system-settings-page {

            width: 100%;
            max-width: none;
            margin: 0;

        }


        /* =========================================================
           HEADER
        ========================================================== */

        .system-settings-header {

            display: flex;
            align-items: flex-start;
            justify-content: space-between;

            gap: 20px;

            margin-bottom: 22px;

        }


        .system-settings-title {

            margin: 0 0 5px;

            color: #172033;

            font-size: 26px;
            font-weight: 750;

            line-height: 1.2;

            letter-spacing: -.025em;

        }


        .system-settings-subtitle {

            margin: 0;

            color: #7b8494;

            font-size: 13px;

            line-height: 1.6;

        }


        /* =========================================================
           BACK BUTTON
        ========================================================== */

        .system-back-button {

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

            transition:
                border-color .18s ease,
                background-color .18s ease,
                color .18s ease,
                transform .18s ease;

        }


        .system-back-button:hover {

            transform: translateX(-1px);

            background: #f8fafc;

            border-color: #cfd8e3;

            color: #1d4ed8;

        }


        /* =========================================================
           NOTICE
        ========================================================== */

        .system-notice {

            margin-bottom: 14px;

            padding: 13px 15px;

            border-radius: 12px;

            font-size: 11px;
            line-height: 1.5;

        }


        .system-notice-success {

            border: 1px solid #bbf7d0;

            background: #f0fdf4;

            color: #166534;

        }


        .system-notice-error {

            border: 1px solid #fecaca;

            background: #fef2f2;

            color: #b91c1c;

        }


        /* =========================================================
           SECTION CARD
        ========================================================== */

        .system-section {

            margin-bottom: 12px;

            overflow: hidden;

            border: 1px solid #e5eaf0;

            border-radius: 16px;

            background: #ffffff;

            box-shadow:
                0 7px 22px rgba(15, 23, 42, .025);

        }


        .system-section-header {

            padding: 18px 20px;

            border-bottom: 1px solid #edf0f4;

        }


        .system-section-title {

            margin: 0 0 4px;

            color: #202938;

            font-size: 14px;
            font-weight: 750;

        }


        .system-section-subtitle {

            margin: 0;

            color: #8791a0;

            font-size: 11px;

            line-height: 1.5;

        }


        .system-section-body {

            padding: 20px;

        }


        /* =========================================================
           FORM GRID
        ========================================================== */

        .system-form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 18px;

        }


        .system-field {

            min-width: 0;

        }


        .system-field-full {

            grid-column: 1 / -1;

        }


        .system-label {

            display: block;

            margin-bottom: 7px;

            color: #334155;

            font-size: 11px;
            font-weight: 750;

        }


        .system-help {

            margin-top: 5px;

            color: #94a3b8;

            font-size: 10px;

            line-height: 1.5;

        }


        .system-input,
        .system-select {

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
                box-shadow .18s ease,
                background-color .18s ease;

        }


        .system-input:focus,
        .system-select:focus {

            border-color: #93b4f4;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, .08);

        }


        /* =========================================================
           SELECT
        ========================================================== */

        .system-select {

            cursor: pointer;

        }


        /* =========================================================
           PAYMENT METHOD CARDS
        ========================================================== */

        .payment-method-options {

            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 10px;

        }


        .payment-method-option {

            position: relative;

        }


        .payment-method-option input {

            position: absolute;

            opacity: 0;

            pointer-events: none;

        }


        .payment-method-label {

            display: flex;

            align-items: center;

            gap: 10px;

            min-height: 48px;

            padding: 0 12px;

            border: 1px solid #e2e8f0;

            border-radius: 10px;

            background: #ffffff;

            color: #475569;

            cursor: pointer;

            font-size: 11px;

            font-weight: 700;

            transition:
                border-color .18s ease,
                background-color .18s ease,
                color .18s ease,
                box-shadow .18s ease;

        }


        .payment-method-label:hover {

            border-color: #cbd5e1;

            background: #f8fafc;

        }


        .payment-method-option input:checked+.payment-method-label {

            border-color: #93b4f4;

            background: #eff6ff;

            color: #1d4ed8;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, .06);

        }


        .payment-dot {

            width: 8px;
            height: 8px;

            border-radius: 50%;

            background: #94a3b8;

        }


        .payment-method-option input:checked+.payment-method-label .payment-dot {

            background: #2563eb;

        }


        /* =========================================================
           TOGGLE
        ========================================================== */

        .system-toggle-row {

            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 16px;

            padding: 14px;

            border: 1px solid #e5eaf0;

            border-radius: 12px;

            background: #fbfcfe;

        }


        .system-toggle-content {

            min-width: 0;

        }


        .system-toggle-title {

            margin: 0 0 3px;

            color: #334155;

            font-size: 11px;

            font-weight: 750;

        }


        .system-toggle-description {

            margin: 0;

            color: #8a93a2;

            font-size: 10px;

            line-height: 1.5;

        }


        .system-switch {

            position: relative;

            width: 44px;
            height: 24px;

            flex: 0 0 44px;

        }


        .system-switch input {

            opacity: 0;

            width: 0;
            height: 0;

        }


        .system-slider {

            position: absolute;

            inset: 0;

            border-radius: 999px;

            background: #cbd5e1;

            cursor: pointer;

            transition:
                background .18s ease;

        }


        .system-slider::before {

            content: "";

            position: absolute;

            width: 18px;
            height: 18px;

            left: 3px;
            top: 3px;

            border-radius: 50%;

            background: #ffffff;

            box-shadow:
                0 1px 3px rgba(15, 23, 42, .18);

            transition:
                transform .18s ease;

        }


        .system-switch input:checked+.system-slider {

            background: #2563eb;

        }


        .system-switch input:checked+.system-slider::before {

            transform: translateX(20px);

        }


        /* =========================================================
           PREVIEW
        ========================================================== */

        .system-preview {

            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 10px;

            margin-top: 18px;

        }


        .system-preview-card {

            padding: 13px;

            border: 1px solid #e5eaf0;

            border-radius: 12px;

            background: #fbfcfe;

        }


        .system-preview-label {

            margin-bottom: 4px;

            color: #94a3b8;

            font-size: 9px;
            font-weight: 750;

            text-transform: uppercase;

            letter-spacing: .06em;

        }


        .system-preview-value {

            color: #253044;

            font-size: 12px;
            font-weight: 750;

        }


        /* =========================================================
           FOOTER
        ========================================================== */

        .system-form-footer {

            display: flex;

            justify-content: flex-end;

            gap: 9px;

            padding: 16px 20px;

            border-top: 1px solid #edf0f4;

            background: #fbfcfe;

        }


        .system-button {

            min-height: 40px;

            padding: 0 16px;

            border-radius: 10px;

            font-family: inherit;

            font-size: 11px;
            font-weight: 750;

            cursor: pointer;

            transition:
                transform .18s ease,
                background-color .18s ease,
                border-color .18s ease,
                color .18s ease;

        }


        .system-button:hover {

            transform: translateY(-1px);

        }


        .system-button-secondary {

            border: 1px solid #dfe5ec;

            background: #ffffff;

            color: #475569;

        }


        .system-button-secondary:hover {

            background: #f8fafc;

        }


        .system-button-primary {

            border: 1px solid #2563eb;

            background: #2563eb;

            color: #ffffff;

            box-shadow:
                0 5px 15px rgba(37, 99, 235, .14);

        }


        .system-button-primary:hover {

            background: #1d4ed8;

            border-color: #1d4ed8;

        }


        /* =========================================================
           RESPONSIVE
        ========================================================== */

        @media (max-width: 800px) {

            .system-settings-header {

                flex-direction: column;

            }


            .system-back-button {

                width: 100%;

            }


            .system-form-grid {

                grid-template-columns: 1fr;

            }


            .system-field-full {

                grid-column: auto;

            }


            .payment-method-options {

                grid-template-columns: 1fr;

            }


            .system-preview {

                grid-template-columns: 1fr;

            }

        }


        @media (max-width: 520px) {

            .system-settings-title {

                font-size: 23px;

            }


            .system-section-body {

                padding: 16px;

            }


            .system-form-footer {

                flex-direction: column;

            }


            .system-button {

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


            <div class="system-settings-page">


                <!-- =================================================
                     HEADER
                ================================================== -->

                <div class="system-settings-header">

                    <div>

                        <h1 class="system-settings-title">
                            System Preferences
                        </h1>


                        <p class="system-settings-subtitle">
                            Configure how your store system displays information and handles common POS behavior.
                        </p>

                    </div>


                    <a
                        href="index.php"
                        class="system-back-button">

                        ← Back to Settings

                    </a>

                </div>


                <!-- =================================================
                     SUCCESS
                ================================================== -->

                <?php if ($successMessage !== ''): ?>

                    <div
                        class="system-notice system-notice-success">

                        <?= htmlspecialchars(
                            $successMessage,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     ERROR
                ================================================== -->

                <?php if ($errorMessage !== ''): ?>

                    <div
                        class="system-notice system-notice-error">

                        <?= htmlspecialchars(
                            $errorMessage,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     MAIN FORM
                ================================================== -->

                <form
                    method="post"
                    id="systemPreferencesForm">


                    <!-- =================================================
                         REGIONAL SETTINGS
                    ================================================== -->

                    <section class="system-section">


                        <div class="system-section-header">

                            <h2 class="system-section-title">
                                Regional & Display
                            </h2>


                            <p class="system-section-subtitle">
                                Control currency, dates, timezone and number formatting used throughout the system.
                            </p>

                        </div>


                        <div class="system-section-body">


                            <div class="system-form-grid">


                                <!-- CURRENCY -->

                                <div class="system-field">

                                    <label
                                        for="currency"
                                        class="system-label">

                                        Currency

                                    </label>


                                    <select
                                        id="currency"
                                        name="currency"
                                        class="system-select">

                                        <option
                                            value="PHP"
                                            <?= $settings['currency'] === 'PHP'
                                                ? 'selected'
                                                : '' ?>>

                                            PHP — Philippine Peso

                                        </option>


                                        <option
                                            value="USD"
                                            <?= $settings['currency'] === 'USD'
                                                ? 'selected'
                                                : '' ?>>

                                            USD — US Dollar

                                        </option>


                                        <option
                                            value="EUR"
                                            <?= $settings['currency'] === 'EUR'
                                                ? 'selected'
                                                : '' ?>>

                                            EUR — Euro

                                        </option>

                                    </select>


                                    <div class="system-help">
                                        Currency used for prices, sales and financial displays.
                                    </div>

                                </div>


                                <!-- CURRENCY SYMBOL -->

                                <div class="system-field">

                                    <label
                                        for="currency_symbol"
                                        class="system-label">

                                        Currency Symbol

                                    </label>


                                    <input
                                        type="text"
                                        id="currency_symbol"
                                        name="currency_symbol"
                                        class="system-input"
                                        maxlength="10"
                                        value="<?= htmlspecialchars(
                                                    $settings['currency_symbol'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>">


                                    <div class="system-help">
                                        Example: ₱, $, €, etc.
                                    </div>

                                </div>


                                <!-- DATE FORMAT -->

                                <div class="system-field">

                                    <label
                                        for="date_format"
                                        class="system-label">

                                        Date Format

                                    </label>


                                    <select
                                        id="date_format"
                                        name="date_format"
                                        class="system-select">


                                        <option
                                            value="F d, Y"
                                            <?= $settings['date_format'] === 'F d, Y'
                                                ? 'selected'
                                                : '' ?>>

                                            October 06, 2026

                                        </option>


                                        <option
                                            value="M d, Y"
                                            <?= $settings['date_format'] === 'M d, Y'
                                                ? 'selected'
                                                : '' ?>>

                                            Oct 06, 2026

                                        </option>


                                        <option
                                            value="d F Y"
                                            <?= $settings['date_format'] === 'd F Y'
                                                ? 'selected'
                                                : '' ?>>

                                            06 October 2026

                                        </option>


                                        <option
                                            value="Y-m-d"
                                            <?= $settings['date_format'] === 'Y-m-d'
                                                ? 'selected'
                                                : '' ?>>

                                            2026-10-06

                                        </option>


                                        <option
                                            value="d/m/Y"
                                            <?= $settings['date_format'] === 'd/m/Y'
                                                ? 'selected'
                                                : '' ?>>

                                            06/10/2026

                                        </option>


                                        <option
                                            value="m/d/Y"
                                            <?= $settings['date_format'] === 'm/d/Y'
                                                ? 'selected'
                                                : '' ?>>

                                            10/06/2026

                                        </option>


                                    </select>

                                </div>


                                <!-- TIMEZONE -->

                                <div class="system-field">

                                    <label
                                        for="timezone"
                                        class="system-label">

                                        Timezone

                                    </label>


                                    <select
                                        id="timezone"
                                        name="timezone"
                                        class="system-select">


                                        <option
                                            value="Asia/Manila"
                                            <?= $settings['timezone'] === 'Asia/Manila'
                                                ? 'selected'
                                                : '' ?>>

                                            Asia/Manila — Philippine Time

                                        </option>


                                        <option
                                            value="Asia/Tokyo"
                                            <?= $settings['timezone'] === 'Asia/Tokyo'
                                                ? 'selected'
                                                : '' ?>>

                                            Asia/Tokyo — Japan Time

                                        </option>


                                        <option
                                            value="Asia/Singapore"
                                            <?= $settings['timezone'] === 'Asia/Singapore'
                                                ? 'selected'
                                                : '' ?>>

                                            Asia/Singapore — Singapore Time

                                        </option>


                                        <option
                                            value="UTC"
                                            <?= $settings['timezone'] === 'UTC'
                                                ? 'selected'
                                                : '' ?>>

                                            UTC

                                        </option>


                                    </select>

                                </div>


                                <!-- DECIMAL PLACES -->

                                <div class="system-field">

                                    <label
                                        for="decimal_places"
                                        class="system-label">

                                        Decimal Places

                                    </label>


                                    <select
                                        id="decimal_places"
                                        name="decimal_places"
                                        class="system-select">


                                        <option
                                            value="0"
                                            <?= (int)$settings['decimal_places'] === 0
                                                ? 'selected'
                                                : '' ?>>

                                            0 — ₱100

                                        </option>


                                        <option
                                            value="1"
                                            <?= (int)$settings['decimal_places'] === 1
                                                ? 'selected'
                                                : '' ?>>

                                            1 — ₱100.0

                                        </option>


                                        <option
                                            value="2"
                                            <?= (int)$settings['decimal_places'] === 2
                                                ? 'selected'
                                                : '' ?>>

                                            2 — ₱100.00

                                        </option>


                                        <option
                                            value="3"
                                            <?= (int)$settings['decimal_places'] === 3
                                                ? 'selected'
                                                : '' ?>>

                                            3 — ₱100.000

                                        </option>


                                    </select>

                                </div>


                                <!-- DATE PREVIEW -->

                                <div class="system-field">

                                    <label class="system-label">

                                        Current Date Preview

                                    </label>


                                    <input
                                        type="text"
                                        class="system-input"
                                        value="<?= htmlspecialchars(
                                                    $formattedPreviewDate,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                        readonly>

                                    <div class="system-help">
                                        Based on the selected timezone and date format.
                                    </div>

                                </div>


                            </div>


                        </div>

                    </section>


                    <!-- =================================================
                         POS SETTINGS
                    ================================================== -->

                    <section class="system-section">


                        <div class="system-section-header">

                            <h2 class="system-section-title">
                                POS Preferences
                            </h2>


                            <p class="system-section-subtitle">
                                Set the default payment method used when starting a new POS transaction.
                            </p>

                        </div>


                        <div class="system-section-body">


                            <label class="system-label">
                                Default Payment Method
                            </label>


                            <div class="payment-method-options">


                                <!-- CASH -->

                                <div class="payment-method-option">

                                    <input
                                        type="radio"
                                        id="paymentCash"
                                        name="default_payment_method"
                                        value="cash"
                                        <?= $settings['default_payment_method'] === 'cash'
                                            ? 'checked'
                                            : '' ?>>


                                    <label
                                        for="paymentCash"
                                        class="payment-method-label">

                                        <span class="payment-dot"></span>

                                        Cash

                                    </label>

                                </div>


                                <!-- GCASH -->

                                <div class="payment-method-option">

                                    <input
                                        type="radio"
                                        id="paymentGcash"
                                        name="default_payment_method"
                                        value="gcash"
                                        <?= $settings['default_payment_method'] === 'gcash'
                                            ? 'checked'
                                            : '' ?>>


                                    <label
                                        for="paymentGcash"
                                        class="payment-method-label">

                                        <span class="payment-dot"></span>

                                        GCash

                                    </label>

                                </div>


                                <!-- MAYA -->

                                <div class="payment-method-option">

                                    <input
                                        type="radio"
                                        id="paymentMaya"
                                        name="default_payment_method"
                                        value="maya"
                                        <?= $settings['default_payment_method'] === 'maya'
                                            ? 'checked'
                                            : '' ?>>


                                    <label
                                        for="paymentMaya"
                                        class="payment-method-label">

                                        <span class="payment-dot"></span>

                                        Maya

                                    </label>

                                </div>


                            </div>


                        </div>

                    </section>


                    <!-- =================================================
                         NOTIFICATIONS
                    ================================================== -->

                    <section class="system-section">


                        <div class="system-section-header">

                            <h2 class="system-section-title">
                                Notifications
                            </h2>


                            <p class="system-section-subtitle">
                                Control whether low-stock information is included in system notifications.
                            </p>

                        </div>


                        <div class="system-section-body">


                            <div class="system-toggle-row">


                                <div class="system-toggle-content">

                                    <p class="system-toggle-title">
                                        Low Stock Notifications
                                    </p>


                                    <p class="system-toggle-description">
                                        Show notifications when active products reach or fall below their reorder level.
                                    </p>

                                </div>


                                <label class="system-switch">

                                    <input
                                        type="checkbox"
                                        name="low_stock_notifications"
                                        <?= (int)$settings['low_stock_notifications'] === 1
                                            ? 'checked'
                                            : '' ?>>


                                    <span class="system-slider"></span>

                                </label>


                            </div>


                        </div>

                    </section>


                    <!-- =================================================
                         PREVIEW
                    ================================================== -->

                    <section class="system-section">


                        <div class="system-section-header">

                            <h2 class="system-section-title">
                                Quick Preview
                            </h2>


                            <p class="system-section-subtitle">
                                See how the selected preferences will look in the store system.
                            </p>

                        </div>


                        <div class="system-section-body">


                            <div class="system-preview">


                                <div class="system-preview-card">

                                    <div class="system-preview-label">
                                        Currency
                                    </div>


                                    <div
                                        class="system-preview-value"
                                        id="previewCurrency">

                                        <?= htmlspecialchars(
                                            $settings['currency_symbol'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                        1,250.00

                                    </div>

                                </div>


                                <div class="system-preview-card">

                                    <div class="system-preview-label">
                                        Date
                                    </div>


                                    <div
                                        class="system-preview-value"
                                        id="previewDate">

                                        <?= htmlspecialchars(
                                            $formattedPreviewDate,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </div>

                                </div>


                                <div class="system-preview-card">

                                    <div class="system-preview-label">
                                        Default Payment
                                    </div>


                                    <div
                                        class="system-preview-value"
                                        id="previewPayment">

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $settings['default_payment_method']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </div>

                                </div>


                            </div>


                        </div>

                    </section>


                    <!-- =================================================
                         FORM FOOTER
                    ================================================== -->

                    <div class="system-section">


                        <div class="system-form-footer">


                            <a
                                href="index.php"
                                class="system-button system-button-secondary"
                                style="
                                    display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    text-decoration:none;
                                ">

                                Cancel

                            </a>


                            <button
                                type="submit"
                                class="system-button system-button-primary">

                                Save Preferences

                            </button>


                        </div>


                    </div>


                </form>


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
         SYSTEM PREFERENCES JS
    ========================================================== -->

    <script>
        (function() {

            const currencySymbol =
                document.getElementById(
                    'currency_symbol'
                );

            const dateFormat =
                document.getElementById(
                    'date_format'
                );

            const timezone =
                document.getElementById(
                    'timezone'
                );

            const paymentMethods =
                document.querySelectorAll(
                    'input[name="default_payment_method"]'
                );

            const previewCurrency =
                document.getElementById(
                    'previewCurrency'
                );

            const previewDate =
                document.getElementById(
                    'previewDate'
                );

            const previewPayment =
                document.getElementById(
                    'previewPayment'
                );


            /*
            |--------------------------------------------------------------------------
            | CURRENCY PREVIEW
            |--------------------------------------------------------------------------
            */

            function updateCurrencyPreview() {

                if (
                    !currencySymbol ||
                    !previewCurrency
                ) {
                    return;
                }


                const symbol =
                    currencySymbol.value.trim() ||
                    '₱';


                previewCurrency.textContent =
                    symbol + '1,250.00';

            }


            /*
            |--------------------------------------------------------------------------
            | DATE PREVIEW
            |--------------------------------------------------------------------------
            */

            function updateDatePreview() {

                if (
                    !dateFormat ||
                    !timezone ||
                    !previewDate
                ) {
                    return;
                }


                /*
                | PHP remains the final source of date formatting
                | after saving.
                |
                | The browser preview here gives the user
                | immediate visual feedback.
                */

                const now =
                    new Date();


                const format =
                    dateFormat.value;


                let formatted;


                if (format === 'Y-m-d') {

                    formatted =
                        now.getFullYear() +
                        '-' +
                        String(
                            now.getMonth() + 1
                        ).padStart(2, '0') +
                        '-' +
                        String(
                            now.getDate()
                        ).padStart(2, '0');

                } else if (
                    format === 'd/m/Y'
                ) {

                    formatted =
                        String(
                            now.getDate()
                        ).padStart(2, '0') +
                        '/' +
                        String(
                            now.getMonth() + 1
                        ).padStart(2, '0') +
                        '/' +
                        now.getFullYear();

                } else if (
                    format === 'm/d/Y'
                ) {

                    formatted =
                        String(
                            now.getMonth() + 1
                        ).padStart(2, '0') +
                        '/' +
                        String(
                            now.getDate()
                        ).padStart(2, '0') +
                        '/' +
                        now.getFullYear();

                } else if (
                    format === 'M d, Y'
                ) {

                    formatted =
                        now.toLocaleDateString(
                            'en-US', {
                                month: 'short',
                                day: '2-digit',
                                year: 'numeric'
                            }
                        );

                } else if (
                    format === 'd F Y'
                ) {

                    formatted =
                        now.toLocaleDateString(
                            'en-US', {
                                day: '2-digit',
                                month: 'long',
                                year: 'numeric'
                            }
                        );

                } else {

                    formatted =
                        now.toLocaleDateString(
                            'en-US', {
                                month: 'long',
                                day: '2-digit',
                                year: 'numeric'
                            }
                        );

                }


                previewDate.textContent =
                    formatted;

            }


            /*
            |--------------------------------------------------------------------------
            | PAYMENT PREVIEW
            |--------------------------------------------------------------------------
            */

            function updatePaymentPreview() {

                if (!previewPayment) {
                    return;
                }


                let selected =
                    'cash';


                paymentMethods.forEach(
                    function(radio) {

                        if (radio.checked) {

                            selected =
                                radio.value;

                        }

                    }
                );


                previewPayment.textContent =
                    selected.charAt(0).toUpperCase() +
                    selected.slice(1);

            }


            /*
            |--------------------------------------------------------------------------
            | EVENTS
            |--------------------------------------------------------------------------
            */

            if (currencySymbol) {

                currencySymbol.addEventListener(
                    'input',
                    updateCurrencyPreview
                );

            }


            if (dateFormat) {

                dateFormat.addEventListener(
                    'change',
                    updateDatePreview
                );

            }


            paymentMethods.forEach(
                function(radio) {

                    radio.addEventListener(
                        'change',
                        updatePaymentPreview
                    );

                }
            );


            /*
            |--------------------------------------------------------------------------
            | INITIALIZE
            |--------------------------------------------------------------------------
            */

            updateCurrencyPreview();

            updateDatePreview();

            updatePaymentPreview();

        })();
    </script>


</body>

</html>
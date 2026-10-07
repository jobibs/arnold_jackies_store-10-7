<?php

require_once '../config/database.php';
require_once '../config/auth.php';
require_login();

/*
|--------------------------------------------------------------------------
| UNIVERSAL ADMIN SHELL
|--------------------------------------------------------------------------
*/

$basePath = '../';
$activeMenu = 'categories';
$currentSection = 'categories';


/*
|--------------------------------------------------------------------------
| CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = $pdo->query("
    SELECT
        c.id,
        c.category_name,
        COUNT(p.id) AS product_count

    FROM categories c

    LEFT JOIN products p
        ON p.category_id = c.id
        AND p.is_active = 1

    GROUP BY
        c.id,
        c.category_name

    ORDER BY
        c.id ASC
")->fetchAll();


/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
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

    $notifications = $notificationStmt->fetchAll(
        PDO::FETCH_ASSOC
    );
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
        Categories - Arnold & Jackie's Store
    </title>


    <!--
    |--------------------------------------------------------------------------
    | Bootstrap
    |--------------------------------------------------------------------------
    -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!--
    |--------------------------------------------------------------------------
    | UNIVERSAL ADMIN CSS
    |--------------------------------------------------------------------------
    -->

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">


    <!--
    |--------------------------------------------------------------------------
    | CATEGORIES-SPECIFIC CSS ONLY
    |--------------------------------------------------------------------------
    -->

    <style>
        /*
        |--------------------------------------------------------------------------
        | CATEGORY BUTTONS
        |--------------------------------------------------------------------------
        */

        .dashboard-button {
            display: inline-block;
            padding: 8px 13px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: .15s;
            white-space: nowrap;
        }


        .button-primary {
            background: #0d6efd;
            color: #ffffff;
            border: 1px solid #0d6efd;
        }


        .button-primary:hover {
            background: #0b5ed7;
            border-color: #0b5ed7;
            color: #ffffff;
        }


        .button-secondary {
            background: #ffffff;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }


        .button-secondary:hover {
            background: #f8fafc;
            color: #111827;
            border-color: #9ca3af;
        }


        .button-danger {
            background: #ffffff;
            color: #dc3545;
            border: 1px solid #dc3545;
        }


        .button-danger:hover {
            background: #fff5f5;
            color: #b02a37;
            border-color: #b02a37;
        }


        .button-disabled {
            background: #f8fafc;
            color: #9ca3af;
            border: 1px solid #d1d5db;
            cursor: not-allowed;
        }


        /*
        |--------------------------------------------------------------------------
        | TEMPORARY SUCCESS ALERT
        |--------------------------------------------------------------------------
        */

        .category-alert {
            border-radius: 10px;
            font-size: 13px;

            opacity: 1;
            max-height: 100px;

            overflow: hidden;

            margin-top: 0;
            margin-bottom: 24px;

            padding-top: 12px;
            padding-bottom: 12px;

            transition:
                opacity 0.5s ease,
                max-height 0.5s ease,
                margin 0.5s ease,
                padding 0.5s ease;
        }

        .category-alert.category-alert-hide {
            opacity: 0;

            max-height: 0;

            margin-top: 0 !important;
            margin-bottom: 0 !important;

            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }

        /*
        |--------------------------------------------------------------------------
        | CATEGORY TABLE
        |--------------------------------------------------------------------------
        */

        .category-id {
            color: #8b94a3;
            font-size: 13px;
        }


        .category-name {
            font-size: 13px;
            font-weight: 600;
            color: #202938;
        }


        .product-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            padding: 5px 9px;
            border-radius: 7px;
            background: #eef4ff;
            color: #0d6efd;
            font-size: 11px;
            font-weight: 600;
        }


        .category-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY STATE
        |--------------------------------------------------------------------------
        */

        .category-empty {
            text-align: center;
            padding: 45px 20px !important;
        }


        .category-empty-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 12px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }


        .category-empty-title {
            font-size: 13px;
            font-weight: 600;
            color: #273142;
            margin-bottom: 4px;
        }


        .category-empty-text {
            font-size: 12px;
            color: #8b94a3;
        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 767px) {

            .category-actions {
                flex-wrap: wrap;
            }

        }

        /*
|--------------------------------------------------------------------------
| ADD CATEGORY MODAL
|--------------------------------------------------------------------------
*/

        .category-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .45);

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;

            z-index: 5000;

            opacity: 0;
            visibility: hidden;

            transition:
                opacity .25s ease,
                visibility .25s ease;
        }


        .category-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }


        .category-modal {
            width: 100%;
            max-width: 500px;

            background: #ffffff;
            border-radius: 12px;

            box-shadow:
                0 20px 50px rgba(15, 23, 42, .20);

            transform: translateY(15px) scale(.98);

            transition:
                transform .25s ease,
                opacity .25s ease;

            opacity: 0;
        }


        .category-modal-overlay.show .category-modal {
            transform: translateY(0) scale(1);
            opacity: 1;
        }


        .category-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;

            padding: 20px 22px;

            border-bottom: 1px solid #edf0f4;
        }


        .category-modal-title {
            font-size: 16px;
            font-weight: 700;
            color: #172033;
        }


        .category-modal-subtitle {
            margin-top: 4px;
            font-size: 12px;
            color: #8b94a3;
        }


        .category-modal-close {
            width: 32px;
            height: 32px;

            display: flex;
            align-items: center;
            justify-content: center;

            border: 0;
            border-radius: 7px;

            background: transparent;

            color: #6b7280;

            font-size: 23px;
            line-height: 1;

            cursor: pointer;

            transition: .15s;
        }


        .category-modal-close:hover {
            background: #f1f5f9;
            color: #111827;
        }


        .category-modal-body {
            padding: 22px;
        }


        .category-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;

            padding-top: 20px;
            margin-top: 22px;

            border-top: 1px solid #edf0f4;
        }


        .category-modal-alert {
            font-size: 13px;
            border-radius: 8px;
            margin-bottom: 18px;
        }


        .category-modal-loading {
            opacity: .65;
            pointer-events: none;
        }


        @media (max-width: 576px) {

            .category-modal-overlay {
                align-items: flex-end;
                padding: 12px;
            }


            .category-modal {
                max-width: 100%;
                border-radius: 12px;
            }


            .category-modal-actions {
                flex-direction: column-reverse;
            }


            .category-modal-actions .category-button {
                width: 100%;
            }

        }

        /* =========================================================
   ADD CATEGORY MODAL
========================================================= */

        .category-modal-overlay {
            position: fixed;
            inset: 0;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;

            background: rgba(15, 23, 42, 0.48);

            z-index: 9999;

            opacity: 0;
            visibility: hidden;

            transition:
                opacity .25s ease,
                visibility .25s ease;
        }


        .category-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }


        /* =========================================================
   MODAL BOX
========================================================= */

        .category-modal {
            width: 100%;
            max-width: 500px;

            background: #ffffff;

            border-radius: 12px;

            box-shadow:
                0 20px 50px rgba(15, 23, 42, .22);

            overflow: hidden;

            opacity: 0;

            transform:
                translateY(18px) scale(.97);

            transition:
                opacity .25s ease,
                transform .25s ease;
        }


        .category-modal-overlay.show .category-modal {
            opacity: 1;

            transform:
                translateY(0) scale(1);
        }


        /* =========================================================
   MODAL HEADER
========================================================= */

        .category-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;

            padding: 20px 22px;

            border-bottom: 1px solid #edf0f4;

            background: #ffffff;
        }


        .category-modal-title {
            margin: 0;

            color: #172033;

            font-size: 16px;
            font-weight: 700;
            line-height: 1.4;
        }


        .category-modal-subtitle {
            margin-top: 4px;

            color: #8b94a3;

            font-size: 12px;
            line-height: 1.5;
        }


        /* =========================================================
   CLOSE BUTTON
========================================================= */

        .category-modal-close {
            width: 32px !important;
            height: 32px !important;

            display: flex !important;
            align-items: center !important;
            justify-content: center !important;

            padding: 0 !important;
            margin: 0 !important;

            border: 0 !important;
            border-radius: 8px !important;

            background: #f1f5f9 !important;

            color: #374151 !important;

            font-family: Arial, sans-serif !important;
            font-size: 21px !important;
            font-weight: 600 !important;
            line-height: 1 !important;

            cursor: pointer;

            transition:
                background .15s ease,
                color .15s ease,
                transform .15s ease;
        }


        .category-modal-close:hover {
            background: #e5e7eb !important;

            color: #111827 !important;

            transform: scale(1.03);
        }


        .category-modal-close:active {
            transform: scale(.96);
        }


        /* =========================================================
   MODAL BODY
========================================================= */

        .category-modal-body {
            padding: 22px;
        }


        /* =========================================================
   FORM LABEL
========================================================= */

        .category-modal-body .category-label {
            display: block;

            margin: 0 0 7px 0;

            color: #344054;

            font-family: Arial, sans-serif;

            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
        }


        .category-modal-body .category-label .text-danger {
            color: #dc3545;
        }


        /* =========================================================
   INPUT
========================================================= */

        .category-modal-body .category-input {
            display: block;

            width: 100% !important;
            height: 43px !important;

            padding: 0 12px !important;
            margin: 0 !important;

            border: 1px solid #d9dee7 !important;
            border-radius: 8px !important;

            outline: none !important;

            background: #ffffff !important;

            color: #172033 !important;

            font-family: Arial, sans-serif !important;
            font-size: 13px !important;
            line-height: 43px !important;

            box-shadow: none !important;

            transition:
                border-color .15s ease,
                box-shadow .15s ease;
        }


        .category-modal-body .category-input::placeholder {
            color: #9ca3af;
        }


        .category-modal-body .category-input:hover {
            border-color: #b8c0cc !important;
        }


        .category-modal-body .category-input:focus {
            border-color: #86b7fe !important;

            box-shadow:
                0 0 0 3px rgba(13, 110, 253, .10) !important;
        }


        /* =========================================================
   HELP TEXT
========================================================= */

        .category-modal-body .category-help {
            margin-top: 7px;

            color: #8b94a3;

            font-family: Arial, sans-serif;

            font-size: 11px;
            line-height: 1.5;
        }


        /* =========================================================
   ERROR MESSAGE
========================================================= */

        #addCategoryError {
            margin-bottom: 16px;

            padding: 10px 12px;

            border-radius: 8px;

            font-family: Arial, sans-serif;

            font-size: 12px;
            line-height: 1.4;
        }


        /* =========================================================
   MODAL ACTIONS
========================================================= */

        .category-modal-actions {
            display: flex;

            align-items: center;

            justify-content: flex-end;

            gap: 8px;

            margin-top: 22px;

            padding-top: 20px;

            border-top: 1px solid #edf0f4;
        }


        /* =========================================================
   MODAL BUTTONS
========================================================= */

        .category-modal-actions .category-button {
            display: inline-flex !important;

            align-items: center !important;
            justify-content: center !important;

            min-width: 110px;
            min-height: 40px !important;

            padding: 0 16px !important;

            margin: 0 !important;

            border-radius: 8px !important;

            font-family: Arial, sans-serif !important;

            font-size: 13px !important;
            font-weight: 600 !important;

            line-height: 1 !important;

            text-decoration: none !important;

            cursor: pointer;

            transition:
                background .15s ease,
                border-color .15s ease,
                color .15s ease,
                transform .1s ease;
        }


        /* =========================================================
   CANCEL
========================================================= */

        .category-modal-actions .category-button-secondary {
            background: #ffffff !important;

            border: 1px solid #d1d5db !important;

            color: #374151 !important;
        }


        .category-modal-actions .category-button-secondary:hover {
            background: #f8fafc !important;

            border-color: #9ca3af !important;

            color: #111827 !important;
        }


        /* =========================================================
   SAVE
========================================================= */

        .category-modal-actions .category-button-primary {
            background: #0d6efd !important;

            border: 1px solid #0d6efd !important;

            color: #ffffff !important;
        }


        .category-modal-actions .category-button-primary:hover {
            background: #0b5ed7 !important;

            border-color: #0b5ed7 !important;

            color: #ffffff !important;
        }


        .category-modal-actions .category-button:active {
            transform: scale(.98);
        }


        .category-modal-actions .category-button:disabled {
            opacity: .65;

            cursor: not-allowed;

            transform: none;
        }


        /* =========================================================
   MOBILE
========================================================= */

        @media (max-width: 576px) {

            .category-modal-overlay {
                align-items: flex-end;

                padding: 12px;
            }


            .category-modal {
                max-width: 100%;

                border-radius: 12px;
            }


            .category-modal-header {
                padding: 18px;
            }


            .category-modal-body {
                padding: 18px;
            }


            .category-modal-actions {
                flex-direction: column-reverse;

                align-items: stretch;
            }


            .category-modal-actions .category-button {
                width: 100% !important;
            }

        }

        /* =========================================================
   CATEGORY SUCCESS ALERT
========================================================= */

        .category-alert {
            display: flex;
            align-items: center;
            gap: 10px;

            width: 100%;

            margin-bottom: 20px;
            padding: 12px 15px;

            border: 1px solid #badbcc;
            border-radius: 9px;

            background: #d1e7dd;
            color: #0f5132;

            font-size: 13px;
            line-height: 1.4;

            opacity: 1;
            max-height: 100px;

            overflow: hidden;

            transition:
                opacity .4s ease,
                max-height .4s ease,
                margin .4s ease,
                padding .4s ease;
        }


        .category-alert-hide {
            opacity: 0;

            max-height: 0;

            margin-top: 0 !important;
            margin-bottom: 0 !important;

            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }


        .category-alert-icon {
            width: 22px;
            height: 22px;

            flex-shrink: 0;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #198754;
            color: #ffffff;

            font-size: 13px;
            font-weight: 700;
        }

        /* =========================================================
           CATEGORY DELETE BUTTON
        ========================================================= */

        .category-action-delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            min-height: 36px;
            padding: 0 12px;
            border: 1px solid #dc3545;
            border-radius: 8px;
            background: #ffffff;
            color: #dc3545;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition:
                background-color .15s ease,
                border-color .15s ease,
                color .15s ease,
                transform .15s ease;
        }

        .category-action-delete:hover {
            background: #fff5f5;
            border-color: #b02a37;
            color: #b02a37;
            transform: translateY(-1px);
        }

        .category-action-delete:active {
            transform: scale(.98);
        }

        .category-action-delete:focus-visible {
            outline: 2px solid #fecaca;
            outline-offset: 2px;
        }


        /* =========================================================
           DELETE CATEGORY MODAL
        ========================================================= */

        .category-delete-overlay {
            position: fixed;
            inset: 0;
            z-index: 10000;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;

            background: rgba(15, 23, 42, .48);
            backdrop-filter: blur(3px);

            opacity: 0;
            visibility: hidden;
            pointer-events: none;

            transition:
                opacity .18s ease,
                visibility .18s ease;
        }

        .category-delete-overlay.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .category-delete-modal {
            width: 100%;
            max-width: 410px;

            overflow: hidden;

            border: 1px solid #e5e7eb;
            border-radius: 16px;

            background: #ffffff;

            box-shadow:
                0 24px 70px rgba(15, 23, 42, .20);

            transform:
                translateY(10px) scale(.97);

            transition:
                transform .18s ease;
        }

        .category-delete-overlay.show .category-delete-modal {
            transform:
                translateY(0) scale(1);
        }

        .category-delete-body {
            padding: 26px 24px 22px;
            text-align: center;
        }

        .category-delete-icon {
            width: 52px;
            height: 52px;

            margin: 0 auto 14px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #fef2f2;
            color: #dc2626;

            font-size: 22px;
            font-weight: 800;
        }

        .category-delete-title {
            margin: 0 0 7px;

            color: #172033;

            font-size: 16px;
            font-weight: 750;
            line-height: 1.4;
        }

        .category-delete-message {
            margin: 0;

            color: #6b7280;

            font-size: 12px;
            line-height: 1.6;
        }

        .category-delete-name {
            margin-top: 10px;

            color: #374151;

            font-size: 13px;
            font-weight: 750;

            word-break: break-word;
        }

        .category-delete-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;

            gap: 8px;

            padding: 14px 20px;

            border-top: 1px solid #e5e7eb;

            background: #fafbfc;
        }

        .category-delete-cancel,
        .category-delete-confirm {
            min-height: 38px;

            padding: 0 14px;

            border-radius: 9px;

            font-family: inherit;
            font-size: 11px;
            font-weight: 700;

            cursor: pointer;

            transition:
                background-color .15s ease,
                border-color .15s ease,
                color .15s ease,
                transform .15s ease,
                opacity .15s ease;
        }

        .category-delete-cancel {
            border: 1px solid #dfe5ec;
            background: #ffffff;
            color: #475569;
        }

        .category-delete-cancel:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #1f2937;
            transform: translateY(-1px);
        }

        .category-delete-confirm {
            border: 1px solid #dc2626;
            background: #dc2626;
            color: #ffffff;

            box-shadow:
                0 5px 12px rgba(220, 38, 38, .12);
        }

        .category-delete-confirm:hover {
            background: #b91c1c;
            border-color: #b91c1c;
            transform: translateY(-1px);
        }

        .category-delete-confirm:disabled {
            opacity: .65;
            cursor: wait;
            transform: none;
        }

        body.category-delete-open {
            overflow: hidden;
        }

        @media (max-width: 576px) {

            .category-delete-overlay {
                align-items: flex-end;
                padding: 12px;
            }

            .category-delete-modal {
                max-width: 100%;
                border-radius: 14px;
            }

            .category-delete-footer {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .category-delete-cancel,
            .category-delete-confirm {
                width: 100%;
            }
        }
    </style>

</head>


<body>


    <?php

    /*
    |--------------------------------------------------------------------------
    | UNIVERSAL SIDEBAR
    |--------------------------------------------------------------------------
    */

    require_once $basePath . 'includes/sidebar.php';

    ?>


    <div class="main">


        <?php

        /*
        |--------------------------------------------------------------------------
        | UNIVERSAL TOPBAR
        |--------------------------------------------------------------------------
        */

        require_once $basePath . 'includes/topbar.php';

        ?>


        <!--
        |--------------------------------------------------------------------------
        | CATEGORY CONTENT
        |--------------------------------------------------------------------------
        -->

        <main class="content">


            <!--
            |--------------------------------------------------------------------------
            | PAGE HEADER
            |--------------------------------------------------------------------------
            -->

            <div
                class="d-flex justify-content-between align-items-start mb-4">


                <div>

                    <div class="page-title">
                        Categories
                    </div>


                    <div class="page-subtitle">
                        Manage your product categories.
                    </div>

                </div>


                <div class="d-flex gap-2">


                    <a
                        href="../products/"
                        class="dashboard-button button-secondary">

                        Products

                    </a>


                    <button
                        type="button"
                        id="openAddCategoryModal"
                        class="dashboard-button button-primary">
                        + Add Category
                    </button>


                </div>


            </div>


            <!-- =========================================================
     SUCCESS / ACTION MESSAGES
========================================================= -->

            <?php if (isset($_GET['added'])): ?>

                <div
                    id="categoryAlert"
                    class="category-alert"
                    role="alert">

                    <span class="category-alert-icon">✓</span>

                    <span>
                        Category added successfully.
                    </span>

                </div>

            <?php endif; ?>


            <?php if (isset($_GET['updated'])): ?>

                <div
                    id="categoryAlert"
                    class="category-alert"
                    role="alert">

                    <span class="category-alert-icon">✓</span>

                    <span>
                        Category updated successfully.
                    </span>

                </div>

            <?php endif; ?>


            <?php if (isset($_GET['deleted'])): ?>

                <div
                    id="categoryAlert"
                    class="category-alert"
                    role="alert">

                    <span class="category-alert-icon">✓</span>

                    <span>
                        Category deleted successfully.
                    </span>

                </div>

            <?php endif; ?>


            <!--
            |--------------------------------------------------------------------------
            | CATEGORIES TABLE
            |--------------------------------------------------------------------------
            -->

            <div class="section-card">


                <div class="section-header">


                    <div>

                        <div class="section-title">
                            Product Categories
                        </div>


                        <div class="section-subtitle">
                            View and manage your store's categories.
                        </div>

                    </div>


                    <div class="section-subtitle">

                        <?= count($categories) ?>

                        categor<?= count($categories) === 1 ? 'y' : 'ies' ?>

                    </div>


                </div>


                <div class="table-responsive">


                    <table
                        class="table dashboard-table">


                        <thead>

                            <tr>


                                <th style="width: 100px;">
                                    ID
                                </th>


                                <th>
                                    Category
                                </th>


                                <th>
                                    Products
                                </th>


                                <th style="width: 180px;">
                                    Action
                                </th>


                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach (
                                $categories
                                as $category
                            ): ?>


                                <tr>


                                    <!-- ID -->

                                    <td class="category-id">

                                        <?= (int)$category['id'] ?>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <div class="category-name">

                                            <?= htmlspecialchars(
                                                $category['category_name']
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- PRODUCT COUNT -->

                                    <td>

                                        <span class="product-count">

                                            <?= (int)$category['product_count'] ?>

                                        </span>

                                    </td>


                                    <!-- ACTION -->

                                    <td>

                                        <div class="category-actions">


                                            <!-- EDIT -->

                                            <a
                                                href="edit.php?id=<?= (int)$category['id'] ?>"
                                                class="dashboard-button button-secondary">

                                                Edit

                                            </a>


                                            <?php if (
                                                (int)$category['product_count'] === 0
                                            ): ?>


                                                <!-- DELETE -->

                                                <button
                                                    type="button"
                                                    class="category-action-delete delete-category-button"
                                                    data-id="<?= (int)$category['id'] ?>"
                                                    data-name="<?= htmlspecialchars(
                                                                    $category['category_name'],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>">
                                                    Delete
                                                </button>


                                            <?php else: ?>


                                                <!-- DELETE DISABLED -->

                                                <button
                                                    type="button"
                                                    class="dashboard-button button-disabled"
                                                    disabled
                                                    title="This category is being used by products">

                                                    Delete

                                                </button>


                                            <?php endif; ?>


                                        </div>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            <?php if (!$categories): ?>


                                <tr>


                                    <td
                                        colspan="4"
                                        class="category-empty">


                                        <div class="category-empty-icon">
                                            ☷
                                        </div>


                                        <div class="category-empty-title">
                                            No categories found
                                        </div>


                                        <div class="category-empty-text">
                                            Add your first product category to get started.
                                        </div>


                                    </td>


                                </tr>


                            <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </div>

        </main>


    </div>


    <!--
    |--------------------------------------------------------------------------
    | BOOTSTRAP
    |--------------------------------------------------------------------------
    -->

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>


    <!--
    |--------------------------------------------------------------------------
    | UNIVERSAL ADMIN JS
    |--------------------------------------------------------------------------
    -->

    <script
        src="<?= $basePath ?>assets/js/admin.js">
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const alert = document.getElementById('categoryAlert');

            if (!alert) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | SHOW FOR 5 SECONDS
            |--------------------------------------------------------------------------
            */

            setTimeout(function() {

                alert.classList.add('category-alert-hide');

                /*
                |--------------------------------------------------------------------------
                | REMOVE AFTER FADE
                |--------------------------------------------------------------------------
                */

                setTimeout(function() {

                    if (alert && alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }

                }, 500);

            }, 5000);

        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const alerts = document.querySelectorAll('.category-alert');

            alerts.forEach(function(alert) {

                setTimeout(function() {

                    alert.classList.add('category-alert-hide');

                    setTimeout(function() {

                        if (alert && alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }

                    }, 550);

                }, 5000);

            });

        });
    </script>

    <!-- =========================================================
     ADD CATEGORY MODAL
========================================================== -->

    <div
        class="category-modal-overlay"
        id="addCategoryModal"
        aria-hidden="true">

        <div
            class="category-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="addCategoryModalTitle">


            <div class="category-modal-header">

                <div>

                    <div
                        class="category-modal-title"
                        id="addCategoryModalTitle">

                        Add Category

                    </div>

                    <div class="category-modal-subtitle">

                        Create a new product category for your inventory.

                    </div>

                </div>


                <button
                    type="button"
                    class="category-modal-close"
                    id="closeAddCategoryModal"
                    aria-label="Close">

                    ×

                </button>

            </div>


            <div class="category-modal-body">


                <div
                    id="addCategoryError"
                    class="alert alert-danger category-modal-alert"
                    style="display: none;">
                </div>


                <form
                    id="addCategoryForm"
                    method="post"
                    action="add.php">


                    <label
                        for="modalCategoryName"
                        class="category-label">

                        Category Name

                        <span class="text-danger">*</span>

                    </label>


                    <input
                        type="text"
                        id="modalCategoryName"
                        name="category_name"
                        class="category-input"
                        placeholder="e.g. Beverages"
                        autocomplete="off"
                        required>


                    <div class="category-help">

                        Use a clear name that will be easy to identify when adding products.

                    </div>


                    <div class="category-modal-actions">


                        <button
                            type="button"
                            class="category-button category-button-secondary"
                            id="cancelAddCategory">

                            Cancel

                        </button>


                        <button
                            type="submit"
                            class="category-button category-button-primary"
                            id="saveCategoryButton">

                            Save Category

                        </button>


                    </div>


                </form>


            </div>

        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const modal =
                document.getElementById('addCategoryModal');

            const openButton =
                document.getElementById('openAddCategoryModal');

            const closeButton =
                document.getElementById('closeAddCategoryModal');

            const cancelButton =
                document.getElementById('cancelAddCategory');

            const form =
                document.getElementById('addCategoryForm');

            const input =
                document.getElementById('modalCategoryName');

            const errorBox =
                document.getElementById('addCategoryError');

            const saveButton =
                document.getElementById('saveCategoryButton');


            /*
            |--------------------------------------------------------------------------
            | OPEN
            |--------------------------------------------------------------------------
            */

            function openModal() {

                modal.classList.add('show');

                document.body.style.overflow = 'hidden';

                setTimeout(function() {

                    input.focus();

                }, 250);

            }


            /*
            |--------------------------------------------------------------------------
            | CLOSE
            |--------------------------------------------------------------------------
            */

            function closeModal() {

                modal.classList.remove('show');

                document.body.style.overflow = '';

                form.reset();

                errorBox.style.display = 'none';

                errorBox.textContent = '';

                saveButton.disabled = false;

                saveButton.textContent = 'Save Category';

            }


            /*
            |--------------------------------------------------------------------------
            | OPEN BUTTON
            |--------------------------------------------------------------------------
            */

            openButton.addEventListener(
                'click',
                openModal
            );


            /*
            |--------------------------------------------------------------------------
            | CLOSE BUTTON
            |--------------------------------------------------------------------------
            */

            closeButton.addEventListener(
                'click',
                closeModal
            );


            /*
            |--------------------------------------------------------------------------
            | CANCEL BUTTON
            |--------------------------------------------------------------------------
            */

            cancelButton.addEventListener(
                'click',
                closeModal
            );


            /*
            |--------------------------------------------------------------------------
            | CLICK OUTSIDE
            |--------------------------------------------------------------------------
            */

            modal.addEventListener(
                'click',
                function(event) {

                    if (event.target === modal) {

                        closeModal();

                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | ESC KEY
            |--------------------------------------------------------------------------
            */

            document.addEventListener(
                'keydown',
                function(event) {

                    if (
                        event.key === 'Escape' &&
                        modal.classList.contains('show')
                    ) {

                        closeModal();

                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | SAVE CATEGORY
            |--------------------------------------------------------------------------
            */

            form.addEventListener(
                'submit',
                async function(event) {

                    event.preventDefault();


                    const categoryName =
                        input.value.trim();


                    if (categoryName === '') {

                        errorBox.textContent =
                            'Category name is required.';

                        errorBox.style.display =
                            'block';

                        input.focus();

                        return;

                    }


                    saveButton.disabled = true;

                    saveButton.textContent =
                        'Saving...';


                    try {

                        const formData =
                            new FormData(form);


                        const response =
                            await fetch(
                                'add.php', {
                                    method: 'POST',

                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },

                                    body: formData
                                }
                            );


                        const result =
                            await response.json();


                        if (result.success) {

                            closeModal();

                            window.location.href =
                                'index.php?added=1';

                            return;

                        }


                        errorBox.textContent =
                            result.message ||
                            'Unable to save category.';

                        errorBox.style.display =
                            'block';

                        saveButton.disabled =
                            false;

                        saveButton.textContent =
                            'Save Category';

                        input.focus();


                    } catch (error) {

                        errorBox.textContent =
                            'Unable to save category. Please try again.';

                        errorBox.style.display =
                            'block';

                        saveButton.disabled =
                            false;

                        saveButton.textContent =
                            'Save Category';

                    }

                }
            );

        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const alert =
                document.getElementById('categoryAlert');

            if (!alert) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | SHOW FOR 5 SECONDS
            |--------------------------------------------------------------------------
            */

            setTimeout(function() {

                alert.classList.add('category-alert-hide');

                /*
                |--------------------------------------------------------------
                | REMOVE AFTER FADE
                |--------------------------------------------------------------
                */

                setTimeout(function() {

                    if (alert && alert.parentNode) {

                        alert.parentNode.removeChild(alert);

                    }

                }, 450);

            }, 5000);

        });
    </script>

    <!-- =========================================================
     DELETE CATEGORY MODAL
========================================================= -->

    <div
        class="category-delete-overlay"
        id="categoryDeleteOverlay"
        aria-hidden="true">

        <div
            class="category-delete-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="categoryDeleteTitle">

            <div class="category-delete-body">

                <div class="category-delete-icon">
                    !
                </div>

                <h2
                    class="category-delete-title"
                    id="categoryDeleteTitle">

                    Delete Category?

                </h2>

                <p class="category-delete-message">

                    Are you sure you want to delete this category?
                    This action cannot be undone.

                </p>

                <div
                    class="category-delete-name"
                    id="categoryDeleteName">
                </div>

            </div>


            <div class="category-delete-footer">

                <button
                    type="button"
                    class="category-delete-cancel"
                    id="cancelCategoryDelete">

                    Cancel

                </button>


                <button
                    type="button"
                    class="category-delete-confirm"
                    id="confirmCategoryDelete">

                    Delete Category

                </button>

            </div>

        </div>

    </div>

    <!-- =========================================================
         DELETE CATEGORY JAVASCRIPT
    ========================================================== -->

    <script>
        (function() {

            const overlay =
                document.getElementById(
                    'categoryDeleteOverlay'
                );

            const nameBox =
                document.getElementById(
                    'categoryDeleteName'
                );

            const cancelButton =
                document.getElementById(
                    'cancelCategoryDelete'
                );

            const confirmButton =
                document.getElementById(
                    'confirmCategoryDelete'
                );

            const deleteButtons =
                document.querySelectorAll(
                    '.delete-category-button'
                );


            if (
                !overlay ||
                !nameBox ||
                !cancelButton ||
                !confirmButton
            ) {
                return;
            }


            let selectedId = null;


            function openDeleteModal(
                id,
                name
            ) {

                selectedId = id;

                nameBox.textContent =
                    name || 'this category';

                confirmButton.disabled = false;

                confirmButton.textContent =
                    'Delete Category';

                overlay.classList.add(
                    'show'
                );

                overlay.setAttribute(
                    'aria-hidden',
                    'false'
                );

                document.body.classList.add(
                    'category-delete-open'
                );

            }


            function closeDeleteModal() {

                if (
                    confirmButton.disabled
                ) {
                    return;
                }

                overlay.classList.remove(
                    'show'
                );

                overlay.setAttribute(
                    'aria-hidden',
                    'true'
                );

                document.body.classList.remove(
                    'category-delete-open'
                );

                selectedId = null;

                confirmButton.disabled = false;

                confirmButton.textContent =
                    'Delete Category';

            }


            deleteButtons.forEach(
                function(button) {

                    button.addEventListener(
                        'click',
                        function() {

                            openDeleteModal(
                                button.dataset.id,
                                button.dataset.name
                            );

                        }
                    );

                }
            );


            cancelButton.addEventListener(
                'click',
                closeDeleteModal
            );


            overlay.addEventListener(
                'click',
                function(event) {

                    if (
                        event.target === overlay
                    ) {

                        closeDeleteModal();

                    }

                }
            );


            document.addEventListener(
                'keydown',
                function(event) {

                    if (
                        event.key === 'Escape' &&
                        overlay.classList.contains(
                            'show'
                        )
                    ) {

                        closeDeleteModal();

                    }

                }
            );


            confirmButton.addEventListener(
                'click',
                function() {

                    if (!selectedId) {
                        return;
                    }

                    if (
                        confirmButton.disabled
                    ) {
                        return;
                    }


                    confirmButton.disabled = true;

                    confirmButton.textContent =
                        'Deleting...';


                    /*
                    |--------------------------------------------------------------------------
                    | SUBMIT TO THE EXISTING DELETE.PHP
                    |--------------------------------------------------------------------------
                    */

                    const form =
                        document.createElement(
                            'form'
                        );

                    form.method = 'POST';

                    form.action = 'delete.php';

                    form.style.display =
                        'none';


                    const idInput =
                        document.createElement(
                            'input'
                        );

                    idInput.type = 'hidden';

                    idInput.name = 'id';

                    idInput.value =
                        selectedId;


                    form.appendChild(
                        idInput
                    );

                    document.body.appendChild(
                        form
                    );


                    setTimeout(
                        function() {

                            form.submit();

                        },
                        300
                    );

                }
            );

        })();
    </script>

</body>

</html>
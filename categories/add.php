<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

$basePath = '../';
$activeMenu = 'categories';

$from = $_GET['from'] ?? $_POST['from'] ?? '';

$error = '';
$categoryName = '';

$isAjax =
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';


/*
|--------------------------------------------------------------------------
| Add Category
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $categoryName = trim($_POST['category_name'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($categoryName === '') {

        $error = 'Category name is required.';
    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO categories (category_name)
                VALUES (?)
            ");

            $stmt->execute([$categoryName]);


            /*
            |--------------------------------------------------------------------------
            | AJAX RESPONSE
            |--------------------------------------------------------------------------
            */

            if ($isAjax) {

                header('Content-Type: application/json');

                echo json_encode([
                    'success' => true,
                    'message' => 'Category added successfully.'
                ]);

                exit;
            }


            /*
            |--------------------------------------------------------------------------
            | If Add Category was opened from Add Product
            |--------------------------------------------------------------------------
            */

            if ($from === 'product') {

                header('Location: ../products/add.php');
                exit;
            }


            /*
            |--------------------------------------------------------------------------
            | Normal Direct Access
            |--------------------------------------------------------------------------
            */

            header('Location: index.php?added=1');
            exit;
        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {

                $error = 'This category already exists.';
            } else {

                $error = 'Unable to save category.';
            }


            /*
            |--------------------------------------------------------------------------
            | AJAX ERROR RESPONSE
            |--------------------------------------------------------------------------
            */

            if ($isAjax) {

                header('Content-Type: application/json');

                http_response_code(422);

                echo json_encode([
                    'success' => false,
                    'message' => $error
                ]);

                exit;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | AJAX VALIDATION ERROR
    |--------------------------------------------------------------------------
    */

    if ($isAjax) {

        header('Content-Type: application/json');

        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => $error
        ]);

        exit;
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
        Add Category | Arnold & Jackie's Store
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">


    <style>
        .category-card {
            width: 100%;
            max-width: 500px;
            margin-left: 0;
            margin-right: auto;
        }


        .category-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #edf0f4;
        }


        .category-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #172033;
            margin: 0;
        }


        .category-card-subtitle {
            margin-top: 4px;
            font-size: 12px;
            color: #8b94a3;
        }


        .category-card-body {
            padding: 24px;
        }


        .category-label {
            display: block;
            margin-bottom: 7px;
            color: #344054;
            font-size: 13px;
            font-weight: 600;
        }


        .category-input {
            width: 100%;
            height: 43px;
            padding: 0 12px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #172033;
            background: #ffffff;
            outline: none;
        }


        .category-input:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .10);
        }


        .category-help {
            margin-top: 7px;
            font-size: 11px;
            color: #8b94a3;
        }


        .category-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            padding-top: 20px;
            margin-top: 22px;
            border-top: 1px solid #edf0f4;
        }


        .category-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 17px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: .15s;
            font-family: Arial, sans-serif;
        }


        .category-button-primary {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #ffffff;
        }


        .category-button-primary:hover {
            background: #0b5ed7;
            border-color: #0b5ed7;
            color: #ffffff;
        }


        .category-button-secondary {
            background: #ffffff;
            border-color: #d1d5db;
            color: #374151;
        }


        .category-button-secondary:hover {
            background: #f8fafc;
            color: #374151;
        }


        .category-alert {
            border-radius: 10px;
            font-size: 13px;
        }


        @media (max-width: 576px) {

            .category-card {
                max-width: 100%;
            }

            .category-card-body {
                padding: 18px;
            }

            .category-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .category-button {
                width: 100%;
            }

        }
    </style>

</head>


<body>


    <?php require_once $basePath . 'includes/sidebar.php'; ?>


    <div class="main">


        <?php require_once $basePath . 'includes/topbar.php'; ?>


        <main class="content">


            <div class="d-flex justify-content-between align-items-start mb-4">

                <div>

                    <div class="page-title">
                        Add Category
                    </div>

                    <div class="page-subtitle">
                        Create a new product category for your inventory.
                    </div>

                </div>


                <a
                    href="<?= $from === 'product'
                                ? '../products/add.php'
                                : 'index.php' ?>"
                    class="category-button category-button-secondary">

                    ← Back

                </a>

            </div>


            <div class="category-card">


                <div class="category-card-header">

                    <div class="category-card-title">
                        Category Information
                    </div>

                    <div class="category-card-subtitle">
                        Enter the name of the new product category.
                    </div>

                </div>


                <div class="category-card-body">


                    <?php if ($error): ?>

                        <div class="alert alert-danger category-alert">

                            <?= htmlspecialchars($error) ?>

                        </div>

                    <?php endif; ?>


                    <form method="post">


                        <input
                            type="hidden"
                            name="from"
                            value="<?= htmlspecialchars($from) ?>">


                        <div>

                            <label
                                for="category_name"
                                class="category-label">

                                Category Name

                                <span class="text-danger">*</span>

                            </label>


                            <input
                                type="text"
                                id="category_name"
                                name="category_name"
                                class="category-input"
                                value="<?= htmlspecialchars($categoryName) ?>"
                                placeholder="e.g. Beverages"
                                autocomplete="off"
                                required
                                autofocus>


                            <div class="category-help">

                                Use a clear name that will be easy to identify when adding products.

                            </div>

                        </div>


                        <div class="category-actions">


                            <button
                                type="submit"
                                class="category-button category-button-primary">

                                Save Category

                            </button>


                            <a
                                href="<?= $from === 'product'
                                            ? '../products/add.php'
                                            : 'index.php' ?>"
                                class="category-button category-button-secondary">

                                Cancel

                            </a>


                        </div>


                    </form>


                </div>


            </div>


        </main>


    </div>


    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>


    <script
        src="<?= $basePath ?>assets/js/admin.js">
    </script>


</body>

</html>
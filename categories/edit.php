<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();


/*
|--------------------------------------------------------------------------
| PAGE SETTINGS
|--------------------------------------------------------------------------
*/

$currentSection = 'categories';
$basePath = '../';
$activeMenu = 'categories';


/*
|--------------------------------------------------------------------------
| AJAX REQUEST CHECK
|--------------------------------------------------------------------------
*/

$isAjax =
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower(
        $_SERVER['HTTP_X_REQUESTED_WITH']
    ) === 'xmlhttprequest';


/*
|--------------------------------------------------------------------------
| GET CATEGORY ID
|--------------------------------------------------------------------------
*/

$id = (int) (
    $_GET['id']
    ?? $_POST['id']
    ?? 0
);


if ($id <= 0) {

    if ($isAjax) {

        header('Content-Type: application/json');

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid category.'
        ]);

        exit;
    }


    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD CATEGORY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        category_name
    FROM categories
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$category =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$category) {

    if ($isAjax) {

        header('Content-Type: application/json');

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Category not found.'
        ]);

        exit;
    }


    header('Location: index.php');
    exit;
}


$categoryName =
    $category['category_name'];

$error = '';


/*
|--------------------------------------------------------------------------
| UPDATE CATEGORY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $categoryName =
        trim(
            $_POST['category_name'] ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($categoryName === '') {

        $error =
            'Category name is required.';
    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE categories
                SET category_name = ?
                WHERE id = ?
            ");


            $stmt->execute([
                $categoryName,
                $id
            ]);


            /*
            |--------------------------------------------------------------------------
            | AJAX SUCCESS
            |--------------------------------------------------------------------------
            */

            if ($isAjax) {

                header(
                    'Content-Type: application/json'
                );

                echo json_encode([
                    'success' => true,
                    'message' =>
                    'Category updated successfully.'
                ]);

                exit;
            }


            /*
            |--------------------------------------------------------------------------
            | NORMAL PAGE SUCCESS
            |--------------------------------------------------------------------------
            */

            header(
                'Location: index.php?updated=1'
            );

            exit;
        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {

                $error =
                    'This category already exists.';
            } else {

                $error =
                    'Unable to update category.';
            }


            /*
            |--------------------------------------------------------------------------
            | AJAX ERROR
            |--------------------------------------------------------------------------
            */

            if ($isAjax) {

                header(
                    'Content-Type: application/json'
                );

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

        header(
            'Content-Type: application/json'
        );

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
| INVENTORY NOTIFICATIONS
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
        $notificationStmt->fetchAll(
            PDO::FETCH_ASSOC
        );
} catch (PDOException $e) {

    $notifications = [];
}


$notificationCount =
    count($notifications);

?>


<!doctype html>

<html lang="en">

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">


    <title>
        Edit Category - Arnold & Jackie's Store
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">


    <style>
        /*
        |--------------------------------------------------------------------------
        | EDIT CATEGORY
        |--------------------------------------------------------------------------
        */

        .edit-category-card {
            width: 100%;
            max-width: 650px;
        }


        .edit-category-input {
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


        .edit-category-input:focus {

            border-color: #86b7fe;

            box-shadow:
                0 0 0 3px rgba(13, 110, 253, .10);

        }


        .edit-category-alert {

            border-radius: 9px;
            font-size: 13px;

        }


        @media (max-width: 767px) {

            .edit-category-card {
                max-width: 100%;
            }

        }
    </style>

</head>


<body>


    <!--
    |--------------------------------------------------------------------------
    | SHARED SIDEBAR
    |--------------------------------------------------------------------------
    -->

    <?php

    require_once
        $basePath . 'includes/sidebar.php';

    ?>


    <main class="main">


        <!--
        |--------------------------------------------------------------------------
        | SHARED TOPBAR
        |--------------------------------------------------------------------------
        -->

        <?php

        require_once
            $basePath . 'includes/topbar.php';

        ?>


        <!--
        |--------------------------------------------------------------------------
        | PAGE CONTENT
        |--------------------------------------------------------------------------
        -->

        <div class="content">


            <!--
            |--------------------------------------------------------------------------
            | PAGE HEADER
            |--------------------------------------------------------------------------
            -->

            <div
                class="d-flex justify-content-between align-items-start mb-4">


                <div>

                    <div class="page-title">

                        Edit Category

                    </div>


                    <div class="page-subtitle">

                        Update the product category
                        information.

                    </div>

                </div>


                <div class="d-none d-md-block">

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary">

                        Back to Categories

                    </a>

                </div>


            </div>


            <!--
            |--------------------------------------------------------------------------
            | CATEGORY FORM
            |--------------------------------------------------------------------------
            -->

            <div class="edit-category-card">


                <div class="section-card">


                    <!-- SECTION HEADER -->

                    <div class="section-header">

                        <div>

                            <div class="section-title">

                                Category Information

                            </div>


                            <div class="section-subtitle">

                                Update the category name
                                below.

                            </div>

                        </div>

                    </div>


                    <!-- SECTION BODY -->

                    <div class="section-body">


                        <?php if ($error !== ''): ?>

                            <div
                                class="alert alert-danger edit-category-alert"
                                role="alert">

                                <?= htmlspecialchars(
                                    $error
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <form
                            method="POST"
                            action="">


                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$id ?>">


                            <div class="mb-4">


                                <label
                                    for="category_name"
                                    class="form-label fw-semibold">

                                    Category Name

                                </label>


                                <input
                                    type="text"
                                    class="edit-category-input"
                                    id="category_name"
                                    name="category_name"
                                    value="<?= htmlspecialchars(
                                                $categoryName
                                            ) ?>"
                                    placeholder="e.g. Beverages"
                                    autocomplete="off"
                                    autofocus
                                    required>


                                <div
                                    class="form-text">

                                    Enter the name that
                                    will be used when
                                    assigning products
                                    to this category.

                                </div>


                            </div>


                            <!-- BUTTONS -->

                            <div
                                class="d-flex gap-2">


                                <button
                                    type="submit"
                                    class="btn btn-primary">

                                    Save Changes

                                </button>


                                <a
                                    href="index.php"
                                    class="btn btn-outline-secondary">

                                    Cancel

                                </a>


                            </div>


                        </form>


                    </div>


                </div>


            </div>


        </div>


    </main>


    <!--
    |--------------------------------------------------------------------------
    | SHARED JAVASCRIPT
    |--------------------------------------------------------------------------
    -->

    <script
        src="<?= $basePath ?>assets/js/admin.js"></script>


</body>

</html>
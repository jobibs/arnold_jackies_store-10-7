<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();


/*
|--------------------------------------------------------------------------
| RESPONSE TYPE
|--------------------------------------------------------------------------
*/

$isAjax =
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower(
        $_SERVER['HTTP_X_REQUESTED_WITH']
    ) === 'xmlhttprequest';


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function sendJson(
    bool $success,
    string $message,
    int $status = 200
): never {

    http_response_code($status);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        [
            'success' => $success,
            'message' => $message
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| GET CATEGORY ID
|--------------------------------------------------------------------------
|
| Support both POST and GET so older links still work.
|
*/

$id = (int)(
    $_POST['id']
    ?? $_GET['id']
    ?? 0
);


if ($id <= 0) {

    if ($isAjax) {

        sendJson(
            false,
            'Invalid category ID.',
            422
        );
    }


    header(
        'Location: index.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD CATEGORY
|--------------------------------------------------------------------------
*/

try {

    $categoryStmt = $pdo->prepare("
        SELECT
            id,
            category_name
        FROM categories
        WHERE id = ?
        LIMIT 1
    ");

    $categoryStmt->execute([
        $id
    ]);

    $category =
        $categoryStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$category) {

        if ($isAjax) {

            sendJson(
                false,
                'Category not found.',
                404
            );
        }


        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK PRODUCT USAGE
    |--------------------------------------------------------------------------
    |
    | A category must not be deleted while active products
    | are still assigned to it.
    |
    */

    $productStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM products
        WHERE category_id = ?
          AND is_active = 1
    ");

    $productStmt->execute([
        $id
    ]);

    $productCount =
        (int)$productStmt->fetchColumn();


    if ($productCount > 0) {

        if ($isAjax) {

            sendJson(
                false,
                'This category cannot be deleted because it is being used by '
                    . $productCount
                    . ' active product'
                    . (
                        $productCount === 1
                        ? ''
                        : 's'
                    )
                    . '.',
                409
            );
        }


        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE CATEGORY
    |--------------------------------------------------------------------------
    */

    $deleteStmt = $pdo->prepare("
        DELETE FROM categories
        WHERE id = ?
    ");

    $deleteStmt->execute([
        $id
    ]);


    if (
        $deleteStmt->rowCount() === 0
    ) {

        if ($isAjax) {

            sendJson(
                false,
                'The category could not be deleted.',
                404
            );
        }


        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    $successMessage =
        'Category "'
        . $category['category_name']
        . '" deleted successfully.';


    if ($isAjax) {

        sendJson(
            true,
            $successMessage
        );
    }


    /*
    |--------------------------------------------------------------------------
    | NORMAL REQUEST
    |--------------------------------------------------------------------------
    */

    header(
        'Location: index.php?deleted=1'
    );

    exit;
} catch (PDOException $e) {

    error_log(
        'Category delete error: '
            . $e->getMessage()
    );


    if ($isAjax) {

        sendJson(
            false,
            'Unable to delete the category. Please try again.',
            500
        );
    }


    header(
        'Location: index.php'
    );

    exit;
}

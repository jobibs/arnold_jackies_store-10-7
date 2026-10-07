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
$activeMenu = 'inventory';
$currentSection = 'inventory';

/*
|--------------------------------------------------------------------------
| JSON Helper
|--------------------------------------------------------------------------
*/
function products_json_response(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/*
|--------------------------------------------------------------------------
| AJAX Actions: Add Stock / Delete (Deactivate) Product
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    in_array($_POST['action'] ?? '', ['add_stock', 'delete_product'], true)
) {

    $action = $_POST['action'];
    $productId = (int)($_POST['product_id'] ?? 0);

    if ($productId <= 0) {

        products_json_response([
            'success' => false,
            'message' => 'Invalid product.'
        ]);
    }

    try {

        /*
        | ADD STOCK
        */
        if ($action === 'add_stock') {

            $quantity = (float)($_POST['quantity'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');

            if ($quantity <= 0) {

                products_json_response([
                    'success' => false,
                    'message' => 'Quantity must be greater than zero.'
                ]);
            }

            $pdo->beginTransaction();

            $find = $pdo->prepare("
                SELECT stock
                FROM products
                WHERE id = ?
                  AND is_active = 1
                FOR UPDATE
            ");

            $find->execute([$productId]);

            $currentStock = $find->fetchColumn();

            if ($currentStock === false) {

                $pdo->rollBack();

                products_json_response([
                    'success' => false,
                    'message' => 'Product not found or already deleted.'
                ]);
            }

            $update = $pdo->prepare("
                UPDATE products
                SET stock = stock + ?
                WHERE id = ?
            ");

            $update->execute([
                $quantity,
                $productId
            ]);

            $move = $pdo->prepare("
                INSERT INTO stock_movements
                    (
                        product_id,
                        movement_type,
                        quantity,
                        remarks
                    )
                VALUES
                    (
                        ?,
                        'stock_in',
                        ?,
                        ?
                    )
            ");

            $move->execute([
                $productId,
                $quantity,
                $remarks !== '' ? $remarks : 'Stock added'
            ]);

            $pdo->commit();

            products_json_response([
                'success' => true,
                'message' => 'Stock added successfully.'
            ]);
        }

        /*
        | DELETE (DEACTIVATE) PRODUCT
        */
        $delete = $pdo->prepare("
            UPDATE products
            SET is_active = 0
            WHERE id = ?
              AND is_active = 1
        ");

        $delete->execute([$productId]);

        if ($delete->rowCount() === 0) {

            products_json_response([
                'success' => false,
                'message' => 'Product not found or already deleted.'
            ]);
        }

        products_json_response([
            'success' => true,
            'message' => 'Product deleted successfully.'
        ]);
    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();
        }

        products_json_response([
            'success' => false,
            'message' => $action === 'add_stock'
                ? 'Unable to add stock. Please try again.'
                : 'Unable to delete product. Please try again.'
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| Success Notice (after redirect)
|--------------------------------------------------------------------------
*/
$notice = '';

if (isset($_GET['added'])) {

    $notice = 'Product added successfully.';
} elseif (isset($_GET['stocked'])) {

    $notice = 'Stock added successfully.';
} elseif (isset($_GET['deleted'])) {

    $notice = 'Product deleted successfully.';
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$filterCategory = (int)($_GET['category'] ?? 0);
$filterStatus = $_GET['status'] ?? '';

if (!in_array($filterStatus, ['in_stock', 'low_stock', 'out_of_stock'], true)) {

    $filterStatus = '';
}

$where = ['p.is_active = 1'];
$params = [];

if ($search !== '') {

    $where[] = '(p.product_name LIKE ? OR p.product_code LIKE ?)';
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
}

if ($filterCategory > 0) {

    $where[] = 'p.category_id = ?';
    $params[] = $filterCategory;
}

if ($filterStatus === 'out_of_stock') {

    $where[] = 'p.stock <= 0';
} elseif ($filterStatus === 'low_stock') {

    $where[] = 'p.stock > 0 AND p.stock <= p.reorder_level';
} elseif ($filterStatus === 'in_stock') {

    $where[] = 'p.stock > 0 AND p.stock > p.reorder_level';
}

/*
|--------------------------------------------------------------------------
| Get Products
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        p.*,
        c.category_name
    FROM products p
    LEFT JOIN categories c
        ON c.id = p.category_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY p.product_name
");

$stmt->execute($params);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Summary Cards + Filter Options
|--------------------------------------------------------------------------
*/
$stats = [
    'total_items' => 0,
    'total_value' => 0,
    'low_stock' => 0
];

$categoryCount = 0;
$filterCategories = [];

try {

    $stats = $pdo->query("
        SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(stock * cost_price), 0) AS total_value,
            COALESCE(SUM(CASE WHEN stock <= reorder_level THEN 1 ELSE 0 END), 0) AS low_stock
        FROM products
        WHERE is_active = 1
    ")->fetch(PDO::FETCH_ASSOC);

    $filterCategories = $pdo->query("
        SELECT id, category_name
        FROM categories
        ORDER BY category_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $categoryCount = count($filterCategories);
} catch (PDOException $e) {

    // Keep defaults
}

function inv_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>
        Products - Arnold & Jackie's Store
    </title>


    <!-- BOOTSTRAP -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- UNIVERSAL ADMIN CSS -->
    <link
        href="<?= $basePath ?>assets/css/admin.css"
        rel="stylesheet">


    <!-- PAGE-SPECIFIC CSS -->
    <style>
        /* =========================================================
           PRODUCTS PAGE
           ========================================================= */

        .products-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .products-title {
            font-size: 25px;
            font-weight: 700;
            margin: 0 0 4px;
            color: #172033;
        }

        .products-subtitle {
            color: #7b8494;
            font-size: 13px;
            margin: 0;
        }

        .products-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .products-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: .15s;
        }

        .products-button-secondary {
            background: #ffffff;
            border: 1px solid #d9dee7;
            color: #374151;
        }

        .products-button-secondary:hover {
            background: #f8fafc;
            color: #111827;
        }

        .products-button-dark {
            background: #111827;
            border: 1px solid #111827;
            color: #ffffff;
        }

        .products-button-dark:hover {
            background: #1f2937;
            border-color: #1f2937;
            color: #ffffff;
        }

        .products-button-primary {
            background: #0d6efd;
            border: 1px solid #0d6efd;
            color: #ffffff;
        }

        .products-button-primary:hover {
            background: #0b5ed7;
            border-color: #0b5ed7;
            color: #ffffff;
        }


        /* =========================================================
           SEARCH
           ========================================================= */

        .products-search-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 13px;
            padding: 15px;
            margin-bottom: 18px;
        }

        .products-search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .products-search-input {
            width: 100%;
            max-width: 430px;
            height: 39px;
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

        .products-search-input:focus {
            border-color: #86b7fe;
            box-shadow:
                0 0 0 3px rgba(13, 110, 253, .10);
        }

        .products-search-button {
            height: 39px;
            padding: 0 15px;
            border: none;
            border-radius: 8px;
            background: #6b7280;
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: .15s;
        }

        .products-search-button:hover {
            background: #4b5563;
        }

        .products-clear-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 39px;
            padding: 0 14px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #ffffff;
            color: #4b5563;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: .15s;
        }

        .products-clear-button:hover {
            background: #f8fafc;
            color: #111827;
        }


        /* =========================================================
           PRODUCTS TABLE CARD
           ========================================================= */

        .products-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 13px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .03);
        }

        .products-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 17px 18px;
            border-bottom: 1px solid #edf0f4;
        }

        .products-card-title {
            font-size: 15px;
            font-weight: 700;
            color: #202938;
        }

        .products-card-subtitle {
            font-size: 11px;
            color: #8b94a3;
            margin-top: 3px;
        }

        .products-count {
            background: #eff6ff;
            color: #2563eb;
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 700;
        }

        .products-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            min-width: 1050px;
        }

        .products-table th {
            background: #f8fafc;
            color: #6b7280;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            padding: 13px 15px;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
            text-align: left;
        }

        .products-table td {
            padding: 13px 15px;
            border-bottom: 1px solid #f0f2f5;
            font-size: 12px;
            color: #273142;
            vertical-align: middle;
        }

        .products-table tbody tr:last-child td {
            border-bottom: none;
        }

        .products-table tbody tr:hover {
            background: #fafcff;
        }

        .product-code {
            color: #6b7280;
            font-family: monospace;
            font-size: 11px;
        }

        .product-name {
            font-weight: 600;
            color: #202938;
        }

        .category-name {
            color: #6b7280;
        }

        .price-value {
            font-weight: 600;
            color: #273142;
            white-space: nowrap;
        }

        .profit-positive {
            color: #198754;
            font-weight: 600;
            white-space: nowrap;
        }

        .profit-negative {
            color: #dc3545;
            font-weight: 600;
            white-space: nowrap;
        }

        .stock-normal {
            color: #374151;
        }

        .stock-low {
            color: #dc3545;
            font-weight: 700;
        }


        /* =========================================================
           PRODUCT ACTIONS
           ========================================================= */

        .product-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 31px;
            padding: 0 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            margin-right: 5px;
            margin-bottom: 3px;
            transition: .15s;
            border: 1px solid transparent;
        }

        .product-action-edit {
            color: #0d6efd;
            background: #eff6ff;
            border-color: #dbeafe;
        }

        .product-action-edit:hover {
            background: #dbeafe;
            color: #0b5ed7;
        }

        .product-action-stock {
            color: #198754;
            background: #ecfdf3;
            border-color: #bbf7d0;
        }

        .product-action-stock:hover {
            background: #dcfce7;
            color: #146c43;
        }

        .product-action-delete {
            color: #dc3545;
            background: #fff1f2;
            border-color: #fecdd3;
        }

        .product-action-delete:hover {
            background: #ffe4e6;
            color: #b02a37;
        }


        /* =========================================================
           EMPTY STATE
           ========================================================= */

        .products-empty {
            text-align: center;
            padding: 45px 20px !important;
            color: #8b94a3 !important;
        }

        .products-empty-icon {
            width: 46px;
            height: 46px;
            margin: 0 auto 10px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }


        /* =========================================================
           ADD PRODUCT MODAL — MODERN FORM UI
           ========================================================= */

        .product-modal-body {
            color: #172033;
        }

        .product-modal-body form {
            margin: 0;
        }

        .product-modal-body .row {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 14px 16px;
            align-items: start;
            margin: 0;
        }

        .product-modal-body .row>[class*="col-"] {
            width: auto;
            max-width: none;
            padding: 0;
            margin: 0;
        }

        .product-modal-body .col-md-4 {
            grid-column: span 4;
        }

        .product-modal-body .col-md-6 {
            grid-column: span 6;
        }

        .product-modal-body .col-md-8 {
            grid-column: span 8;
        }

        .product-field {
            min-width: 0;
        }

        .product-label {
            display: flex;
            align-items: center;
            gap: 5px;
            min-height: 18px;
            margin: 0 0 7px;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
            letter-spacing: -.005em;
        }

        .product-label-required::after {
            content: "*";
            color: #ef4444;
            font-weight: 800;
        }

        .product-label-optional {
            color: #94a3b8;
            font-size: 11px;
            font-weight: 500;
        }

        .product-label-optional::after {
            content: none;
        }

        .product-input,
        .product-select {
            width: 100%;
            height: 44px;
            min-width: 0;
            padding: 0 13px;
            border: 1px solid #dbe2ea;
            border-radius: 10px;
            outline: none;
            background: #fff;
            color: #172033;
            font: inherit;
            font-size: 13px;
            font-weight: 500;
            /* FIX: was 44px. A large line-height made Chrome's number spinner
               grow on hover, which resized the field and shifted the modal. */
            line-height: normal;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .02);
            transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
            box-sizing: border-box;
        }

        .product-select {
            appearance: auto;
            padding-right: 10px;
            cursor: pointer;
        }

        /* FIX: Hide the hover spinner arrows on number inputs */
        .product-input[type="number"] {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .product-input[type="number"]::-webkit-outer-spin-button,
        .product-input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .product-input::placeholder {
            color: #a1aab8;
            opacity: 1;
        }

        .product-input:hover,
        .product-select:hover {
            border-color: #cbd5e1;
        }

        .product-input:focus,
        .product-select:focus {
            border-color: #2563eb;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
        }

        .product-input:read-only {
            cursor: default;
        }

        .product-input-group {
            display: flex;
            width: 100%;
            height: 44px;
        }

        .product-input-prefix {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 42px;
            height: 44px;
            border: 1px solid #dbe2ea;
            border-right: 0;
            border-radius: 10px 0 0 10px;
            background: #f8fafc;
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
            box-sizing: border-box;
        }

        .product-input-group .product-input {
            flex: 1;
            border-radius: 0 10px 10px 0;
        }

        .product-input-group:focus-within .product-input-prefix {
            border-color: #2563eb;
            background: #eff6ff;
            color: #2563eb;
        }

        .product-profit-input {
            background: #f8fafc;
            color: #15803d;
            font-weight: 700;
        }

        .product-category-group {
            display: flex;
            align-items: stretch;
            gap: 8px;
            width: 100%;
        }

        .product-category-group .product-select {
            flex: 1;
        }

        .product-add-category {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            height: 44px;
            padding: 0 13px;
            border: 1px solid #dbe2ea;
            border-radius: 10px;
            background: #fff;
            color: #475569;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            transition: .16s ease;
        }

        .product-add-category:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #111827;
            transform: translateY(-1px);
        }

        .product-help {
            display: none;
        }

        .product-form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 9px;
            margin-top: 24px;
            padding-top: 17px;
            border-top: 1px solid #edf1f5;
        }

        .product-save-button,
        .product-cancel-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 16px;
            border-radius: 10px;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            text-decoration: none;
            transition: transform .16s ease, box-shadow .16s ease, background .16s ease, border-color .16s ease;
        }

        .product-cancel-button {
            border: 1px solid #dbe2ea;
            background: #fff;
            color: #475569;
        }

        .product-cancel-button:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #111827;
        }

        .product-save-button {
            min-width: 116px;
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #fff;
            box-shadow: 0 5px 14px rgba(37, 99, 235, .16);
        }

        .product-save-button:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            box-shadow: 0 7px 18px rgba(37, 99, 235, .20);
            transform: translateY(-1px);
        }

        .product-save-button:disabled {
            opacity: .65;
            cursor: wait;
            transform: none;
            box-shadow: none;
        }

        .product-modal-error {
            display: none;
            margin: 0 0 18px;
            padding: 11px 13px;
            border: 1px solid #fecaca;
            border-radius: 10px;
            background: #fff7f7;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.45;
        }

        .product-modal-error.is-visible {
            display: block;
            animation: productModalErrorIn .18s ease both;
        }

        @keyframes productModalErrorIn {
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
           ADD PRODUCT MODAL
           ========================================================= */

        .product-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1080;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, .58);
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .24s ease, visibility 0s linear .24s;
        }

        .product-modal-backdrop.is-open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transition: opacity .24s ease, visibility 0s linear 0s;
        }

        .product-modal {
            width: min(900px, 100%);
            max-height: min(860px, calc(100vh - 48px));
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #fff;
            border: 1px solid rgba(226, 232, 240, .9);
            border-radius: 18px;
            box-shadow: 0 28px 80px rgba(15, 23, 42, .22);
            transform: translateY(14px) scale(.975);
            opacity: .96;
            transition: transform .28s cubic-bezier(.22, 1, .36, 1), opacity .18s ease;
        }

        .product-modal-backdrop.is-open .product-modal {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .product-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 24px 18px;
            border-bottom: 1px solid #edf0f4;
        }

        .product-modal-eyebrow {
            margin-bottom: 5px;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .product-modal-title {
            margin: 0;
            color: #111827;
            font-size: 20px;
            font-weight: 750;
            letter-spacing: -.03em;
        }

        .product-modal-subtitle {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 12px;
        }

        .product-modal-close {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            color: #475569;
            font-size: 21px;
            line-height: 1;
            cursor: pointer;
            transition: .16s ease;
        }

        .product-modal-close:hover {
            background: #f1f5f9;
            color: #111827;
            transform: translateY(-1px);
        }

        .product-modal-body {
            overflow-y: auto;
            /* FIX: never allow a horizontal scrollbar inside the modal */
            overflow-x: hidden;
            padding: 20px 22px 22px;
        }

        /* FIX: negative margins/bottom now match the body padding (22px).
           They were 24px, which overflowed the body by 2px and made
           scrollbars appear (and the modal shift) when hovering. */
        .product-modal-body .product-form-actions {
            position: sticky;
            bottom: -22px;
            margin: 24px -22px -22px;
            padding: 16px 22px;
            background: rgba(255, 255, 255, .96);
            backdrop-filter: blur(10px);
            border-top: 1px solid #edf0f4;
        }

        .product-modal-error {
            display: none;
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #fecaca;
            border-radius: 11px;
            background: #fff1f2;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 600;
        }

        .product-modal-error.is-visible {
            display: block;
        }

        body.product-modal-open {
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .product-modal-backdrop {
                align-items: flex-end;
                padding: 0;
            }

            .product-modal {
                width: 100%;
                max-height: 94vh;
                border-radius: 20px 20px 0 0;
                transform: translateY(24px);
            }

            .product-modal-backdrop.is-open .product-modal {
                transform: translateY(0);
            }

            .product-modal-header,
            .product-modal-body {
                padding-left: 18px;
                padding-right: 18px;
            }

            .product-modal-body .row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .product-modal-body .col-md-4,
            .product-modal-body .col-md-6,
            .product-modal-body .col-md-8 {
                grid-column: 1 / -1;
            }

            .product-modal-body .product-form-actions {
                margin-left: -18px;
                margin-right: -18px;
                padding-left: 18px;
                padding-right: 18px;
            }
        }

        /* =========================================================
           MOBILE
           ========================================================= */

        @media (max-width: 768px) {

            .products-header {
                flex-direction: column;
            }

            .products-actions {
                width: 100%;
            }

            .products-action-button {
                flex: 1;
            }

            .products-search-form {
                align-items: stretch;
            }

            .products-search-input {
                max-width: none;
            }

        }

        /* =========================================================
           ADD STOCK + DELETE MODALS
           ========================================================= */

        button.product-action {
            font-family: inherit;
            cursor: pointer;
        }

        .product-modal-sm {
            width: min(480px, 100%);
        }

        .product-modal-body .product-field+.product-field {
            margin-top: 14px;
        }

        .product-modal-info {
            margin: 0 0 16px;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f8fafc;
        }

        .product-modal-info-name {
            color: #111827;
            font-size: 13px;
            font-weight: 700;
        }

        .product-modal-info-meta {
            margin-top: 3px;
            color: #64748b;
            font-size: 12px;
        }

        .product-modal-text {
            margin: 0;
            color: #475569;
            font-size: 13px;
            line-height: 1.6;
        }

        .product-modal-text strong {
            color: #111827;
        }

        .product-danger-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            min-width: 116px;
            padding: 0 16px;
            border: 1px solid #dc2626;
            border-radius: 10px;
            background: #dc2626;
            color: #fff;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 5px 14px rgba(220, 38, 38, .16);
            transition: background .16s ease, box-shadow .16s ease;
        }

        .product-danger-button:hover {
            background: #b91c1c;
            border-color: #b91c1c;
        }

        .product-danger-button:disabled {
            opacity: .65;
            cursor: wait;
            box-shadow: none;
        }

        @media (max-width: 768px) {
            .product-modal-sm {
                width: 100%;
            }
        }

        /* Spinner spacing for admin.js setButtonLoading() */
        .product-save-button.is-loading,
        .product-danger-button.is-loading {
            gap: 8px;
        }

        /* =========================================================
           INVENTORY LAYOUT (summary cards, filters, item list)
           ========================================================= */

        .inv-ico {
            width: 14px;
            height: 14px;
            fill: currentColor;
            flex: 0 0 auto;
        }

        .inv-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .inv-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px 12px 14px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            text-align: center;
        }

        .inv-stat-icon {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            border-radius: 9px;
            font-size: 16px;
            font-weight: 800;
        }

        .inv-stat-icon .inv-ico {
            width: 17px;
            height: 17px;
        }

        .inv-ic-blue {
            background: #e7f1ff;
            color: #2563eb;
        }

        .inv-ic-green {
            background: #e7f8ee;
            color: #16a34a;
        }

        .inv-ic-orange {
            background: #fff3df;
            color: #f59e0b;
        }

        .inv-ic-cyan {
            background: #e3f8fa;
            color: #0891b2;
        }

        .inv-stat-label {
            color: #6b7280;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .inv-stat-value {
            margin-top: 3px;
            color: #111827;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -.3px;
        }

        .inv-card {
            margin-bottom: 14px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .inv-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 13px 16px;
            border: 0;
            background: #fff;
            color: #111827;
            text-align: left;
        }

        .inv-card-toggle {
            cursor: pointer;
        }

        .inv-card-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 800;
        }

        .inv-chevron {
            transition: transform .2s ease;
        }

        .inv-card.is-collapsed .inv-chevron {
            transform: rotate(-90deg);
        }

        .inv-card.is-collapsed .inv-filters-body {
            display: none;
        }

        .inv-filters-body {
            padding: 12px 14px 16px;
            border-top: 1px solid #edf0f4;
        }

        .inv-filters-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .inv-input {
            height: 36px;
            padding: 0 11px;
            border: 1px solid #dbe2ea;
            border-radius: 7px;
            background: #fff;
            color: #172033;
            font-size: 12px;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .inv-input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
        }

        .inv-filters-form .inv-input {
            flex: 1 1 180px;
            min-width: 0;
        }

        .inv-filters-form .inv-filter-search {
            flex: 1.2 1 220px;
        }

        .inv-filter-actions {
            display: flex;
            gap: 6px;
        }

        .inv-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 30px;
            padding: 0 12px;
            border: 0;
            border-radius: 6px;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .inv-btn-primary {
            background: #0d6efd;
        }

        .inv-btn-primary:hover {
            background: #0b5ed7;
            color: #fff;
        }

        .inv-btn-dark {
            background: #6b7280;
        }

        .inv-btn-dark:hover {
            background: #4b5563;
            color: #fff;
        }

        .inv-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 10px 16px;
            border-top: 1px solid #edf0f4;
            color: #374151;
            font-size: 12px;
        }

        .inv-length,
        .inv-tsearch {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin: 0;
        }

        .inv-input-sm {
            height: 32px;
        }

        .inv-tsearch .inv-input {
            width: 220px;
        }

        .inv-table-wrap {
            width: 100%;
            overflow-x: auto;
            border-top: 1px solid #e5e7eb;
        }

        .inv-table {
            width: 100%;
            min-width: 980px;
            margin: 0;
            border-collapse: collapse;
        }

        .inv-table th {
            padding: 12px 14px;
            border-right: 1px solid #edf0f4;
            border-bottom: 1px solid #e5e7eb;
            background: #fff;
            color: #6b7280;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .3px;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
            user-select: none;
        }

        .inv-table th[data-type] {
            cursor: pointer;
        }

        .inv-table th[data-type]::after {
            content: "\2195";
            margin-left: 8px;
            color: #cbd5e1;
            font-size: 10px;
        }

        .inv-table th.sorted-asc::after {
            content: "\25B2";
            color: #475569;
        }

        .inv-table th.sorted-desc::after {
            content: "\25BC";
            color: #475569;
        }

        .inv-table td {
            padding: 14px;
            border-right: 1px solid #f0f2f5;
            border-bottom: 1px solid #f0f2f5;
            color: #273142;
            font-size: 12px;
            vertical-align: middle;
        }

        .inv-table th:last-child,
        .inv-table td:last-child {
            border-right: 0;
        }

        .inv-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .inv-row-low {
            background: #fdf1cf;
        }

        .inv-row-low:hover {
            background: #fbe9b8;
        }

        .inv-code {
            color: #d63384;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11px;
        }

        .inv-name {
            color: #111827;
            font-weight: 700;
        }

        .inv-stock {
            font-weight: 800;
            color: #111827;
        }

        .inv-stock-warn {
            margin-left: 3px;
            color: #f59e0b;
        }

        .inv-badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 20px;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .inv-badge-ok {
            background: #22a559;
        }

        .inv-badge-low {
            background: #d97706;
        }

        .inv-badge-out {
            background: #dc2626;
        }

        .inv-actions {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .inv-act {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 24px;
            padding: 0;
            border: 0;
            border-radius: 5px;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
        }

        .inv-act:hover {
            color: #fff;
            filter: brightness(.92);
        }

        .inv-act-edit {
            background: #0d6efd;
        }

        .inv-act-stock {
            background: #22a559;
        }

        .inv-act-delete {
            background: #dc3545;
        }

        .inv-empty {
            padding: 40px 16px !important;
            color: #8b94a3 !important;
            text-align: center;
        }

        .inv-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 16px;
            border-top: 1px solid #edf0f4;
            color: #6b7280;
            font-size: 12px;
        }

        .inv-pager {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .inv-page-btn {
            min-width: 32px;
            height: 30px;
            padding: 0 10px;
            border: 1px solid #dbe2ea;
            border-radius: 6px;
            background: #fff;
            color: #374151;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .inv-page-btn:hover:not(:disabled) {
            background: #f8fafc;
        }

        .inv-page-btn.is-active {
            border-color: #0d6efd;
            background: #0d6efd;
            color: #fff;
        }

        .inv-page-btn:disabled {
            cursor: default;
            opacity: .5;
        }

        .inv-page-gap {
            padding: 0 4px;
            color: #94a3b8;
        }

        @media (max-width: 991px) {
            .inv-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .inv-stats {
                grid-template-columns: 1fr;
            }

            .inv-tsearch,
            .inv-tsearch .inv-input {
                width: 100%;
            }
        }

        /* =========================================================
   COMPACT MODERN OVERRIDE
   Add AFTER the modern redesign CSS
   ========================================================= */

        /* Keep the page compact */
        .content {
            padding: 22px;
        }

        /* Header */
        .products-header {
            margin-bottom: 22px;
        }

        .products-title {
            font-size: 25px;
            letter-spacing: -.6px;
        }

        .products-subtitle {
            margin-top: 4px;
            font-size: 13px;
        }

        /* Header action buttons: restore compact size */
        .products-action-button {
            min-height: 38px;
            padding: 0 14px;
            border-radius: 8px;
            font-size: 12px;
        }

        /* Summary cards: do not increase their dimensions */
        .inv-stats {
            gap: 14px;
            margin-bottom: 16px;
        }

        .inv-stat {
            min-height: 0;
            padding: 16px 12px 14px;
            border-radius: 12px;
            align-items: center;
            text-align: center;
        }

        .inv-stat::after {
            width: 90px;
            height: 90px;
            right: -35px;
            bottom: -42px;
        }

        .inv-stat-icon {
            width: 34px;
            height: 34px;
            margin-bottom: 8px;
            border-radius: 9px;
            font-size: 16px;
        }

        .inv-stat-value {
            margin-top: 3px;
            font-size: 20px;
            letter-spacing: -.3px;
        }

        /* Keep original compact cards */
        .inv-card,
        .products-card,
        .products-search-card {
            border-radius: 12px;
        }

        .inv-card-head,
        .products-card-header {
            min-height: auto;
            padding: 13px 16px;
        }

        .inv-card-title,
        .products-card-title {
            font-size: 12px;
        }

        /* Compact filters */
        .inv-filters-body {
            padding: 12px 14px 16px;
        }

        .inv-filters-form {
            gap: 10px;
        }

        .inv-input {
            height: 36px;
            padding: 0 11px;
            border-radius: 7px;
            font-size: 12px;
        }

        .inv-btn {
            height: 30px;
            padding: 0 12px;
            border-radius: 6px;
            font-size: 11px;
        }

        /* Compact search / table toolbar */
        .inv-toolbar {
            padding: 10px 16px;
            font-size: 12px;
        }

        .inv-input-sm {
            height: 32px;
        }

        .inv-tsearch .inv-input {
            width: 220px;
        }

        /* Keep table row height compact */
        .inv-table th {
            padding: 12px 14px;
            font-size: 10px;
        }

        .inv-table td {
            padding: 14px;
            font-size: 12px;
        }

        /* Keep status labels small */
        .inv-badge {
            padding: 3px 9px;
            font-size: 10px;
        }

        /* Keep action buttons compact */
        .inv-actions {
            gap: 4px;
        }

        .inv-act {
            width: 28px;
            height: 24px;
            border-radius: 5px;
        }

        /* Footer / pagination */
        .inv-footer {
            padding: 12px 16px;
            font-size: 12px;
        }

        .inv-page-btn {
            min-width: 32px;
            height: 30px;
            padding: 0 10px;
            border-radius: 6px;
            font-size: 12px;
        }

        /* Product modal: restore your original size */
        .product-modal {
            border-radius: 18px;
        }

        .product-modal-header {
            padding: 22px 24px 18px;
        }

        .product-modal-title {
            font-size: 20px;
        }

        .product-modal-body {
            padding: 20px 22px 22px;
        }

        .product-modal-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
        }

        /* Product form controls: preserve original dimensions */
        .product-input,
        .product-select {
            height: 44px;
            border-radius: 10px;
            font-size: 13px;
        }

        .product-input-group,
        .product-input-prefix {
            height: 44px;
        }

        .product-input-prefix {
            flex-basis: 42px;
            border-radius: 10px 0 0 10px;
        }

        .product-input-group .product-input {
            border-radius: 0 10px 10px 0;
        }

        /* Modal buttons */
        .product-save-button,
        .product-cancel-button,
        .product-danger-button {
            min-height: 40px;
            border-radius: 10px;
            font-size: 12px;
        }

        .product-save-button,
        .product-danger-button {
            min-width: 116px;
        }

        /* Avoid oversized mobile layout */
        @media (max-width: 768px) {
            .content {
                padding: 18px 14px;
            }

            .products-title {
                font-size: 25px;
            }

            .inv-stat {
                min-height: 0;
            }

            .product-modal-header,
            .product-modal-body {
                padding-left: 18px;
                padding-right: 18px;
            }
        }

        /* =========================================================
   MODERN TOAST NOTIFICATIONS
   ========================================================= */

        .app-toast-area {
            position: fixed;
            z-index: 9999;
            top: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
            width: min(380px, calc(100vw - 32px));
            pointer-events: none;
        }

        .app-toast {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr) 28px;
            align-items: center;
            gap: 11px;
            min-height: 66px;
            padding: 12px 12px 12px 14px;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, .95);
            border-radius: 14px;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 16px 42px rgba(15, 23, 42, .16);
            color: #1e293b;
            pointer-events: auto;
            transform: translateX(115%);
            opacity: 0;
            transition:
                transform .34s cubic-bezier(.22, 1, .36, 1),
                opacity .22s ease;
        }

        .app-toast.is-visible {
            transform: translateX(0);
            opacity: 1;
        }

        .app-toast.is-hiding {
            transform: translateX(115%);
            opacity: 0;
        }

        .app-toast-icon {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            font-size: 18px;
            font-weight: 900;
        }

        .app-toast-content {
            min-width: 0;
        }

        .app-toast-title {
            color: #172033;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.25;
        }

        .app-toast-message {
            margin-top: 3px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.4;
        }

        .app-toast-close {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #94a3b8;
            font-size: 19px;
            line-height: 1;
            cursor: pointer;
            transition: background .16s ease, color .16s ease;
        }

        .app-toast-close:hover {
            background: #f1f5f9;
            color: #334155;
        }

        .app-toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            transform-origin: left;
            animation: appToastProgress linear forwards;
        }

        @keyframes appToastProgress {
            from {
                transform: scaleX(1);
            }

            to {
                transform: scaleX(0);
            }
        }

        .app-toast-success .app-toast-icon {
            background: #dcfce7;
            color: #15803d;
        }

        .app-toast-success .app-toast-progress {
            background: #22c55e;
        }

        .app-toast-error .app-toast-icon {
            background: #fee2e2;
            color: #dc2626;
        }

        .app-toast-error .app-toast-progress {
            background: #ef4444;
        }

        .app-toast-warning .app-toast-icon {
            background: #fef3c7;
            color: #b45309;
        }

        .app-toast-warning .app-toast-progress {
            background: #f59e0b;
        }

        .app-toast-info .app-toast-icon {
            background: #dbeafe;
            color: #2563eb;
        }

        .app-toast-info .app-toast-progress {
            background: #3b82f6;
        }

        @media (max-width: 576px) {
            .app-toast-area {
                top: 12px;
                right: 12px;
                width: calc(100vw - 24px);
            }

            .app-toast {
                min-height: 62px;
                border-radius: 12px;
            }
        }
    </style>

</head>


<body>


    <!-- UNIVERSAL SIDEBAR -->
    <?php require_once $basePath . 'includes/sidebar.php'; ?>


    <div class="main">


        <!-- UNIVERSAL TOPBAR -->
        <?php require_once $basePath . 'includes/topbar.php'; ?>


        <main class="content">



            <!-- PAGE HEADER -->
            <div class="products-header">

                <div>

                    <h1 class="products-title">
                        Products
                    </h1>

                    <p class="products-subtitle">
                        Manage products, prices and inventory.
                    </p>

                </div>


                <div class="products-actions">

                    <a
                        href="<?= $basePath ?>categories/"
                        class="products-action-button products-button-secondary">
                        Manage Categories
                    </a>


                    <button
                        type="button"
                        class="products-action-button products-button-primary"
                        id="openAddProductModal">
                        + Add Product
                    </button>

                </div>

            </div>


            <!-- ICON SPRITE -->
            <svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
                <symbol id="i-box" viewBox="0 0 16 16">
                    <path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5 8 5.961 14.154 3.5 8.186 1.113zM15 4.239l-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923l6.5 2.6zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1 1 0 0 1-.629.928l-7.185 2.874a.5.5 0 0 1-.372 0L.63 13.09a1 1 0 0 1-.63-.928V3.5a.5.5 0 0 1 .314-.464L7.443.184z" />
                </symbol>
                <symbol id="i-tag" viewBox="0 0 16 16">
                    <path d="M2 1a1 1 0 0 0-1 1v4.586a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l4.586-4.586a1 1 0 0 0 0-1.414l-7-7A1 1 0 0 0 6.586 1H2zm4 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z" />
                </symbol>
                <symbol id="i-warn" viewBox="0 0 16 16">
                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z" />
                </symbol>
                <symbol id="i-filter" viewBox="0 0 16 16">
                    <path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z" />
                </symbol>
                <symbol id="i-list" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z" />
                </symbol>
                <symbol id="i-chevron" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z" />
                </symbol>
                <symbol id="i-edit" viewBox="0 0 16 16">
                    <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z" />
                    <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z" />
                </symbol>
                <symbol id="i-down" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M8 1a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L7.5 13.293V1.5A.5.5 0 0 1 8 1z" />
                </symbol>
                <symbol id="i-trash" viewBox="0 0 16 16">
                    <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z" />
                    <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z" />
                </symbol>
            </svg>


            <!-- SUMMARY CARDS -->
            <div class="inv-stats">

                <div class="inv-stat">
                    <div class="inv-stat-icon inv-ic-blue">
                        <svg class="inv-ico">
                            <use href="#i-box" />
                        </svg>
                    </div>
                    <div class="inv-stat-label">Total Items</div>
                    <div class="inv-stat-value"><?= number_format((int)$stats['total_items']) ?></div>
                </div>

                <div class="inv-stat">
                    <div class="inv-stat-icon inv-ic-green">₱</div>
                    <div class="inv-stat-label">Total Value</div>
                    <div class="inv-stat-value">₱<?= number_format((float)$stats['total_value'], 2) ?></div>
                </div>

                <div class="inv-stat">
                    <div class="inv-stat-icon inv-ic-orange">
                        <svg class="inv-ico">
                            <use href="#i-warn" />
                        </svg>
                    </div>
                    <div class="inv-stat-label">Low Stock</div>
                    <div class="inv-stat-value"><?= number_format((int)$stats['low_stock']) ?></div>
                </div>

                <div class="inv-stat">
                    <div class="inv-stat-icon inv-ic-cyan">
                        <svg class="inv-ico">
                            <use href="#i-tag" />
                        </svg>
                    </div>
                    <div class="inv-stat-label">Categories</div>
                    <div class="inv-stat-value"><?= number_format((int)$categoryCount) ?></div>
                </div>

            </div>


            <!-- FILTERS -->
            <div class="inv-card" id="filtersCard">

                <button
                    type="button"
                    class="inv-card-head inv-card-toggle"
                    id="filtersToggle"
                    aria-expanded="true">

                    <span class="inv-card-title">
                        <svg class="inv-ico">
                            <use href="#i-filter" />
                        </svg>
                        Filters
                    </span>

                    <svg class="inv-ico inv-chevron">
                        <use href="#i-chevron" />
                    </svg>

                </button>

                <div class="inv-filters-body">

                    <form method="get" class="inv-filters-form">

                        <input
                            type="text"
                            name="search"
                            value="<?= inv_e($search) ?>"
                            class="inv-input inv-filter-search"
                            placeholder="Search items...">

                        <select name="category" class="inv-input">

                            <option value="0">All Categories</option>

                            <?php foreach ($filterCategories as $c): ?>

                                <option
                                    value="<?= (int)$c['id'] ?>"
                                    <?= $filterCategory === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= inv_e($c['category_name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <select name="status" class="inv-input">

                            <option value="">All Status</option>
                            <option value="in_stock" <?= $filterStatus === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                            <option value="low_stock" <?= $filterStatus === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out_of_stock" <?= $filterStatus === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>

                        </select>

                        <div class="inv-filter-actions">

                            <button type="submit" class="inv-btn inv-btn-primary">
                                Apply
                            </button>

                            <a href="index.php" class="inv-btn inv-btn-dark">
                                Reset
                            </a>

                        </div>

                    </form>

                </div>

            </div>


            <!-- ITEM LIST -->
            <div class="inv-card">

                <div class="inv-card-head">

                    <span class="inv-card-title">
                        <svg class="inv-ico">
                            <use href="#i-list" />
                        </svg>
                        Item List
                    </span>

                </div>

                <div class="inv-toolbar">

                    <label class="inv-length">
                        Show
                        <select id="invLength" class="inv-input inv-input-sm">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        entries
                    </label>

                    <label class="inv-tsearch">
                        Search:
                        <input type="search" id="invSearch" class="inv-input inv-input-sm" autocomplete="off">
                    </label>

                </div>

                <div class="inv-table-wrap">

                    <table class="inv-table" id="inventoryTable">

                        <thead>
                            <tr>
                                <th data-type="text">Item Code</th>
                                <th data-type="text">Name</th>
                                <th data-type="text">Category</th>
                                <th data-type="text">Unit</th>
                                <th data-type="num">Cost</th>
                                <th data-type="num">Selling</th>
                                <th data-type="num">Stock</th>
                                <th data-type="num">Reorder</th>
                                <th data-type="text">Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($products as $p): ?>

                                <?php
                                $stockQty = (float)$p['stock'];
                                $reorderQty = (float)$p['reorder_level'];
                                $low = $stockQty <= $reorderQty;

                                if ($stockQty <= 0) {
                                    $statusClass = 'inv-badge-out';
                                    $statusLabel = 'Out of Stock';
                                } elseif ($low) {
                                    $statusClass = 'inv-badge-low';
                                    $statusLabel = 'Low Stock';
                                } else {
                                    $statusClass = 'inv-badge-ok';
                                    $statusLabel = 'In Stock';
                                }

                                $categoryLabel = $p['category_name'] ?? 'Uncategorized';
                                $stockText = rtrim(rtrim(number_format($stockQty, 3, '.', ''), '0'), '.');
                                $reorderText = rtrim(rtrim(number_format($reorderQty, 3, '.', ''), '0'), '.');
                                $searchText = mb_strtolower(
                                    ($p['product_code'] ?? '') . ' ' .
                                        $p['product_name'] . ' ' .
                                        $categoryLabel . ' ' .
                                        $p['unit'] . ' ' .
                                        $statusLabel
                                );
                                ?>

                                <tr
                                    data-row
                                    class="<?= $low ? 'inv-row-low' : '' ?>"
                                    data-search="<?= inv_e($searchText) ?>">

                                    <td data-sort="<?= inv_e(mb_strtolower($p['product_code'] ?? '')) ?>">
                                        <span class="inv-code"><?= inv_e($p['product_code'] ?? '') ?></span>
                                    </td>

                                    <td data-sort="<?= inv_e(mb_strtolower($p['product_name'])) ?>">
                                        <span class="inv-name"><?= inv_e($p['product_name']) ?></span>
                                    </td>

                                    <td data-sort="<?= inv_e(mb_strtolower($categoryLabel)) ?>">
                                        <?= inv_e($categoryLabel) ?>
                                    </td>

                                    <td data-sort="<?= inv_e(mb_strtolower($p['unit'])) ?>">
                                        <?= inv_e(strtoupper($p['unit'])) ?>
                                    </td>

                                    <td data-sort="<?= (float)$p['cost_price'] ?>">
                                        ₱<?= number_format((float)$p['cost_price'], 2) ?>
                                    </td>

                                    <td data-sort="<?= (float)$p['selling_price'] ?>">
                                        ₱<?= number_format((float)$p['selling_price'], 2) ?>
                                    </td>

                                    <td data-sort="<?= $stockQty ?>">
                                        <span class="inv-stock">
                                            <?= inv_e($stockText) ?>
                                        </span>

                                        <?php if ($low): ?>
                                            <svg class="inv-ico inv-stock-warn" title="Low stock">
                                                <use href="#i-warn" />
                                            </svg>
                                        <?php endif; ?>
                                    </td>

                                    <td data-sort="<?= $reorderQty ?>">
                                        <?= inv_e($reorderText) ?>
                                    </td>

                                    <td data-sort="<?= inv_e($statusLabel) ?>">
                                        <span class="inv-badge <?= $statusClass ?>">
                                            <?= inv_e($statusLabel) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="inv-actions">

                                            <a
                                                href="edit.php?id=<?= (int)$p['id'] ?>"
                                                class="inv-act inv-act-edit"
                                                title="Edit"
                                                aria-label="Edit <?= inv_e($p['product_name']) ?>">
                                                <svg class="inv-ico">
                                                    <use href="#i-edit" />
                                                </svg>
                                            </a>

                                            <button
                                                type="button"
                                                class="inv-act inv-act-stock"
                                                title="Add stock"
                                                aria-label="Add stock to <?= inv_e($p['product_name']) ?>"
                                                data-action="add-stock"
                                                data-id="<?= (int)$p['id'] ?>"
                                                data-name="<?= inv_e($p['product_name']) ?>"
                                                data-unit="<?= inv_e($p['unit']) ?>"
                                                data-stock="<?= inv_e((string)$p['stock']) ?>">
                                                <svg class="inv-ico">
                                                    <use href="#i-down" />
                                                </svg>
                                            </button>

                                            <button
                                                type="button"
                                                class="inv-act inv-act-delete"
                                                title="Delete"
                                                aria-label="Delete <?= inv_e($p['product_name']) ?>"
                                                data-action="delete"
                                                data-id="<?= (int)$p['id'] ?>"
                                                data-name="<?= inv_e($p['product_name']) ?>">
                                                <svg class="inv-ico">
                                                    <use href="#i-trash" />
                                                </svg>
                                            </button>

                                        </div>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="inv-footer">
                    <div class="inv-info" id="invInfo"></div>
                    <div class="inv-pager" id="invPager"></div>
                </div>

            </div>



            <!-- ADD PRODUCT MODAL -->
            <div
                class="product-modal-backdrop"
                id="addProductModal"
                aria-hidden="true">

                <section
                    class="product-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="addProductModalTitle">

                    <div class="product-modal-header">

                        <div>
                            <div class="product-modal-eyebrow">
                                Inventory
                            </div>

                            <h2
                                class="product-modal-title"
                                id="addProductModalTitle">
                                Add Product
                            </h2>

                            <p class="product-modal-subtitle">
                                Add a new product, set its pricing and record its initial stock.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="product-modal-close"
                            id="closeAddProductModal"
                            aria-label="Close add product modal">
                            ×
                        </button>

                    </div>

                    <div class="product-modal-body">

                        <div
                            class="product-modal-error"
                            id="addProductModalError"
                            role="alert"></div>

                        <form
                            method="post"
                            action="add.php"
                            id="addProductForm"
                            data-no-double-submit="true">

                            <div class="row g-3">

                                <div class="col-md-4">
                                    <div class="product-field">
                                        <label for="modal_product_code" class="product-label">
                                            Product Code
                                            <span class="product-label-optional">(optional)</span>
                                        </label>

                                        <input
                                            type="text"
                                            id="modal_product_code"
                                            name="product_code"
                                            class="product-input"
                                            placeholder="e.g. COKE15"
                                            autocomplete="off">
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <div class="product-field">
                                        <label
                                            for="modal_product_name"
                                            class="product-label product-label-required">
                                            Product Name
                                        </label>

                                        <input
                                            type="text"
                                            id="modal_product_name"
                                            name="product_name"
                                            class="product-input"
                                            required
                                            placeholder="e.g. Coca-Cola 1.5L">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="product-field">
                                        <label for="modal_category_id" class="product-label">
                                            Category
                                        </label>

                                        <div class="product-category-group">
                                            <select
                                                name="category_id"
                                                id="modal_category_id"
                                                class="product-select">

                                                <option value="">
                                                    Select Category
                                                </option>

                                                <?php foreach (
                                                    $pdo->query("
                                                    SELECT id, category_name
                                                    FROM categories
                                                    ORDER BY category_name
                                                ")->fetchAll(PDO::FETCH_ASSOC) as $c
                                                ): ?>

                                                    <option value="<?= (int)$c['id'] ?>">
                                                        <?= htmlspecialchars($c['category_name']) ?>
                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                            <a
                                                href="<?= $basePath ?>categories/"
                                                class="product-add-category"
                                                target="_blank"
                                                rel="noopener">
                                                + Add
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="product-field">
                                        <label
                                            for="modal_unit"
                                            class="product-label product-label-required">
                                            Unit
                                        </label>

                                        <select
                                            id="modal_unit"
                                            name="unit"
                                            class="product-select"
                                            required>

                                            <option value="pc" selected>Piece (pc)</option>
                                            <option value="pack">Pack (pack)</option>
                                            <option value="box">Box (box)</option>
                                            <option value="sack">Sack (sack)</option>
                                            <option value="bottle">Bottle (bottle)</option>
                                            <option value="can">Can (can)</option>
                                            <option value="pouch">Pouch (pouch)</option>
                                            <option value="bundle">Bundle (bundle)</option>
                                            <option value="set">Set (set)</option>
                                            <option value="dozen">Dozen (dozen)</option>
                                            <option value="kg">Kilogram (kg)</option>
                                            <option value="g">Gram (g)</option>
                                            <option value="L">Liter (L)</option>
                                            <option value="mL">Milliliter (mL)</option>

                                        </select>

                                        <div class="product-help">
                                            Select how this product is counted in your inventory.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="product-field">
                                        <label
                                            for="modal_cost"
                                            class="product-label product-label-required">
                                            Cost Price
                                        </label>

                                        <div class="product-input-group">
                                            <span class="product-input-prefix">₱</span>

                                            <input
                                                name="cost_price"
                                                id="modal_cost"
                                                type="number"
                                                inputmode="decimal"
                                                step="0.01"
                                                min="0"
                                                value="0"
                                                class="product-input"
                                                required>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="product-field">
                                        <label
                                            for="modal_selling"
                                            class="product-label product-label-required">
                                            Selling Price
                                        </label>

                                        <div class="product-input-group">
                                            <span class="product-input-prefix">₱</span>

                                            <input
                                                name="selling_price"
                                                id="modal_selling"
                                                type="number"
                                                inputmode="decimal"
                                                step="0.01"
                                                min="0"
                                                value="0"
                                                class="product-input"
                                                required>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="product-field">
                                        <label
                                            for="modal_profit"
                                            class="product-label">
                                            Profit / Unit
                                        </label>

                                        <div class="product-input-group">
                                            <span class="product-input-prefix">₱</span>

                                            <input
                                                id="modal_profit"
                                                type="text"
                                                class="product-input product-profit-input"
                                                value="0.00"
                                                readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="product-field">
                                        <label
                                            for="modal_stock"
                                            class="product-label product-label-required">
                                            Initial Stock
                                        </label>

                                        <input
                                            name="stock"
                                            id="modal_stock"
                                            type="number"
                                            inputmode="decimal"
                                            step="0.001"
                                            min="0"
                                            value="0"
                                            class="product-input"
                                            required>

                                        <div class="product-help">
                                            Decimal quantities are allowed.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="product-field">
                                        <label
                                            for="modal_reorder_level"
                                            class="product-label">
                                            Reorder Level
                                        </label>

                                        <input
                                            name="reorder_level"
                                            id="modal_reorder_level"
                                            type="number"
                                            inputmode="decimal"
                                            step="0.001"
                                            min="0"
                                            value="0"
                                            class="product-input">

                                        <div class="product-help">
                                            Low-stock notification starts at this level.
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="product-form-actions">

                                <button
                                    type="button"
                                    class="product-cancel-button"
                                    id="cancelAddProductModal">
                                    Cancel
                                </button>

                                <button
                                    type="submit"
                                    class="product-save-button"
                                    id="saveAddProductButton">
                                    Save Product
                                </button>

                            </div>

                        </form>

                    </div>

                </section>
            </div>



            <!-- ADD STOCK MODAL -->
            <div
                class="product-modal-backdrop"
                id="addStockModal"
                aria-hidden="true">

                <section
                    class="product-modal product-modal-sm"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="addStockModalTitle">

                    <div class="product-modal-header">

                        <div>
                            <div class="product-modal-eyebrow">
                                Inventory
                            </div>

                            <h2
                                class="product-modal-title"
                                id="addStockModalTitle">
                                Add Stock
                            </h2>

                            <p class="product-modal-subtitle">
                                Record new stock coming in for this product.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="product-modal-close"
                            id="closeAddStockModal"
                            aria-label="Close add stock modal">
                            ×
                        </button>

                    </div>

                    <div class="product-modal-body">

                        <div
                            class="product-modal-error"
                            id="addStockModalError"
                            role="alert"></div>

                        <form
                            method="post"
                            id="addStockForm"
                            data-no-double-submit="true"
                            novalidate>

                            <input type="hidden" name="action" value="add_stock">
                            <input type="hidden" name="product_id" id="stock_product_id" value="">

                            <div class="product-modal-info">
                                <div class="product-modal-info-name" id="stockProductName"></div>
                                <div class="product-modal-info-meta" id="stockProductCurrent"></div>
                            </div>

                            <div class="product-field">
                                <label
                                    for="stock_quantity"
                                    class="product-label product-label-required">
                                    Quantity to Add
                                </label>

                                <input
                                    type="number"
                                    inputmode="decimal"
                                    step="0.001"
                                    min="0"
                                    name="quantity"
                                    id="stock_quantity"
                                    class="product-input"
                                    placeholder="0"
                                    required>
                            </div>

                            <div class="product-field">
                                <label
                                    for="stock_new_total"
                                    class="product-label">
                                    New Stock Total
                                </label>

                                <input
                                    type="text"
                                    id="stock_new_total"
                                    class="product-input product-profit-input"
                                    value="0"
                                    readonly>
                            </div>

                            <div class="product-field">
                                <label
                                    for="stock_remarks"
                                    class="product-label">
                                    Remarks
                                    <span class="product-label-optional">(optional)</span>
                                </label>

                                <input
                                    type="text"
                                    name="remarks"
                                    id="stock_remarks"
                                    class="product-input"
                                    placeholder="e.g. Delivery from supplier"
                                    autocomplete="off">
                            </div>

                            <div class="product-form-actions">

                                <button
                                    type="button"
                                    class="product-cancel-button"
                                    id="cancelAddStockModal">
                                    Cancel
                                </button>

                                <button
                                    type="submit"
                                    class="product-save-button"
                                    id="saveAddStockButton">
                                    Add Stock
                                </button>

                            </div>

                        </form>

                    </div>

                </section>
            </div>


            <!-- DELETE PRODUCT MODAL -->
            <div
                class="product-modal-backdrop"
                id="deleteProductModal"
                aria-hidden="true">

                <section
                    class="product-modal product-modal-sm"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="deleteProductModalTitle">

                    <div class="product-modal-header">

                        <div>
                            <div class="product-modal-eyebrow">
                                Inventory
                            </div>

                            <h2
                                class="product-modal-title"
                                id="deleteProductModalTitle">
                                Delete Product
                            </h2>

                            <p class="product-modal-subtitle">
                                Please confirm before continuing.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="product-modal-close"
                            id="closeDeleteProductModal"
                            aria-label="Close delete product modal">
                            ×
                        </button>

                    </div>

                    <div class="product-modal-body">

                        <div
                            class="product-modal-error"
                            id="deleteProductModalError"
                            role="alert"></div>

                        <p class="product-modal-text">
                            Deactivate <strong id="deleteProductName"></strong>?
                            It will be removed from the product list but will remain
                            in historical sales.
                        </p>

                        <input type="hidden" id="delete_product_id" value="">

                        <div class="product-form-actions">

                            <button
                                type="button"
                                class="product-cancel-button"
                                id="cancelDeleteProductModal">
                                Cancel
                            </button>

                            <button
                                type="button"
                                class="product-danger-button"
                                id="confirmDeleteProductButton">
                                Delete
                            </button>

                        </div>

                    </div>

                </section>
            </div>

        </main>

    </div>


    <!-- BOOTSTRAP JS -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    <!-- UNIVERSAL ADMIN JS -->
    <script
        src="<?= $basePath ?>assets/js/admin.js"></script>


    <script>
        (() => {
            const modal = document.getElementById('addProductModal');
            const openButton = document.getElementById('openAddProductModal');
            const closeButton = document.getElementById('closeAddProductModal');
            const cancelButton = document.getElementById('cancelAddProductModal');
            const form = document.getElementById('addProductForm');
            const errorBox = document.getElementById('addProductModalError');
            const saveButton = document.getElementById('saveAddProductButton');
            const cost = document.getElementById('modal_cost');
            const selling = document.getElementById('modal_selling');
            const profit = document.getElementById('modal_profit');
            const nameInput = document.getElementById('modal_product_name');

            if (!modal || !openButton || !form) return;

            let lastFocusedElement = null;

            function updateProfit() {
                const costValue = parseFloat(cost.value) || 0;
                const sellingValue = parseFloat(selling.value) || 0;
                profit.value = (sellingValue - costValue).toFixed(2);
            }

            function showError(box, message) {
                box.textContent = message;
                box.classList.add('is-visible');

                if (typeof window.showAppToast === 'function') {
                    window.showAppToast(
                        'error',
                        'Action failed',
                        message
                    );
                }
            }

            function clearError() {
                errorBox.textContent = '';
                errorBox.classList.remove('is-visible');
            }

            function openModal() {
                lastFocusedElement = document.activeElement;
                clearError();
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('product-modal-open');

                requestAnimationFrame(() => nameInput.focus());
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('product-modal-open');

                if (lastFocusedElement && document.contains(lastFocusedElement)) {
                    lastFocusedElement.focus();
                }
            }

            function resetForm() {
                form.reset();
                document.getElementById('modal_unit').value = 'pc';
                document.getElementById('modal_cost').value = '0';
                document.getElementById('modal_selling').value = '0';
                document.getElementById('modal_stock').value = '0';
                document.getElementById('modal_reorder_level').value = '0';
                updateProfit();
                clearError();
            }

            openButton.addEventListener('click', openModal);
            closeButton.addEventListener('click', closeModal);
            cancelButton.addEventListener('click', closeModal);

            modal.addEventListener('mousedown', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });

            cost.addEventListener('input', updateProfit);
            selling.addEventListener('input', updateProfit);
            updateProfit();

            // FIX: Prevent the mouse wheel from changing number input values
            form.querySelectorAll('input[type="number"]').forEach((input) => {
                input.addEventListener('wheel', () => input.blur(), {
                    passive: true
                });
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                clearError();

                if (!form.reportValidity()) return;

                safeSetButtonLoading(saveButton, true);

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();

                    if (!data.success) {
                        showError(data.message || 'Unable to save product.');
                        return;
                    }

                    closeModal();
                    resetForm();

                    window.location.href = 'index.php?added=1';

                } catch (error) {
                    showError('Something went wrong while saving the product. Please try again.');
                } finally {
                    safeSetButtonLoading(saveButton, false);
                }
            });
        })();
    </script>


    <!-- INVENTORY TABLE SCRIPT (search, sort, paging, filters toggle) -->
    <script>
        (() => {
            const table = document.getElementById('inventoryTable');

            if (!table) return;

            const tbody = table.tBodies[0];
            const rows = Array.from(tbody.querySelectorAll('tr[data-row]'));
            const headers = Array.from(table.tHead.rows[0].cells);
            const lengthSelect = document.getElementById('invLength');
            const searchInput = document.getElementById('invSearch');
            const info = document.getElementById('invInfo');
            const pager = document.getElementById('invPager');
            const columnCount = headers.length;

            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML =
                '<td colspan="' + columnCount + '" class="inv-empty">' +
                '<strong>No products found</strong>' +
                '<div>Try changing your search or filters.</div></td>';

            const state = {
                page: 1,
                perPage: parseInt(lengthSelect.value, 10) || 25,
                query: '',
                col: 1,
                dir: 'asc'
            };

            function cellValue(row, col, type) {
                const cell = row.cells[col];
                const raw = cell.dataset.sort !== undefined ?
                    cell.dataset.sort :
                    cell.textContent.trim().toLowerCase();

                return type === 'num' ? (parseFloat(raw) || 0) : raw;
            }

            function getVisibleRows() {
                const q = state.query.trim().toLowerCase();
                const list = q ?
                    rows.filter((r) => r.dataset.search.includes(q)) :
                    rows.slice();

                const type = headers[state.col].dataset.type;
                const factor = state.dir === 'asc' ? 1 : -1;

                list.sort((a, b) => {
                    const va = cellValue(a, state.col, type);
                    const vb = cellValue(b, state.col, type);

                    if (type === 'num') {
                        return (va - vb) * factor;
                    }

                    return String(va).localeCompare(String(vb), undefined, {
                        numeric: true
                    }) * factor;
                });

                return list;
            }

            function pageButton(label, page, options = {}) {
                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'inv-page-btn' + (options.active ? ' is-active' : '');
                button.textContent = label;
                button.disabled = !!options.disabled;

                button.addEventListener('click', () => {
                    state.page = page;
                    render();
                });

                return button;
            }

            function renderPager(pages) {
                pager.textContent = '';

                pager.appendChild(pageButton('Previous', state.page - 1, {
                    disabled: state.page <= 1
                }));

                const shown = [];

                for (let i = 1; i <= pages; i++) {
                    if (i === 1 || i === pages || Math.abs(i - state.page) <= 1) {
                        shown.push(i);
                    }
                }

                let previous = 0;

                shown.forEach((i) => {
                    if (i - previous > 1) {
                        const gap = document.createElement('span');
                        gap.className = 'inv-page-gap';
                        gap.textContent = '…';
                        pager.appendChild(gap);
                    }

                    pager.appendChild(pageButton(String(i), i, {
                        active: i === state.page
                    }));

                    previous = i;
                });

                pager.appendChild(pageButton('Next', state.page + 1, {
                    disabled: state.page >= pages
                }));
            }

            function render() {
                const list = getVisibleRows();
                const total = list.length;
                const pages = Math.max(1, Math.ceil(total / state.perPage));

                if (state.page > pages) state.page = pages;

                const start = (state.page - 1) * state.perPage;
                const slice = list.slice(start, start + state.perPage);

                tbody.textContent = '';

                if (slice.length) {
                    slice.forEach((row) => tbody.appendChild(row));
                } else {
                    tbody.appendChild(emptyRow);
                }

                headers.forEach((th, i) => {
                    th.classList.toggle('sorted-asc', i === state.col && state.dir === 'asc');
                    th.classList.toggle('sorted-desc', i === state.col && state.dir === 'desc');
                });

                info.textContent = total === 0 ?
                    'Showing 0 to 0 of 0 entries' :
                    'Showing ' + (start + 1) + ' to ' + (start + slice.length) +
                    ' of ' + total + ' entries' +
                    (total < rows.length ? ' (filtered from ' + rows.length + ' total entries)' : '');

                renderPager(pages);
            }

            headers.forEach((th, i) => {
                if (!th.dataset.type) return;

                th.addEventListener('click', () => {
                    if (state.col === i) {
                        state.dir = state.dir === 'asc' ? 'desc' : 'asc';
                    } else {
                        state.col = i;
                        state.dir = 'asc';
                    }

                    state.page = 1;
                    render();
                });
            });

            lengthSelect.addEventListener('change', () => {
                state.perPage = parseInt(lengthSelect.value, 10) || 25;
                state.page = 1;
                render();
            });

            searchInput.addEventListener('input', () => {
                state.query = searchInput.value;
                state.page = 1;
                render();
            });

            /* Collapsible filters */
            const filtersCard = document.getElementById('filtersCard');
            const filtersToggle = document.getElementById('filtersToggle');

            if (filtersCard && filtersToggle) {
                filtersToggle.addEventListener('click', () => {
                    const collapsed = filtersCard.classList.toggle('is-collapsed');
                    filtersToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                });
            }

            render();
        })();
    </script>


    <!-- SAFE BUTTON LOADING WRAPPER
         Uses setButtonLoading() from admin.js when available and falls back
         to a simple version, so the Save buttons never break if admin.js is
         cached or changes. -->
    <script>
        function safeSetButtonLoading(button, loading) {
            if (!button) return;

            if (typeof window.setButtonLoading === 'function') {
                window.setButtonLoading(button, loading);
                return;
            }

            if (loading) {
                if (!button.dataset.originalContent) {
                    button.dataset.originalContent = button.innerHTML;
                }
                button.disabled = true;
                button.classList.add('is-loading');
                button.innerHTML = 'Please wait...';
            } else {
                button.disabled = false;
                button.classList.remove('is-loading');
                if (button.dataset.originalContent) {
                    button.innerHTML = button.dataset.originalContent;
                }
            }
        }
    </script>

    <?php if ($notice !== ''): ?>
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                showAppToast(
                    'success',
                    'Success',
                    <?= json_encode($notice) ?>
                );

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(
                        null,
                        '',
                        window.location.pathname
                    );
                }
            });
        </script>
    <?php endif; ?>


    <!-- ADD STOCK + DELETE MODAL SCRIPT -->
    <script>
        (() => {
            const table = document.getElementById('inventoryTable');
            const stockModal = document.getElementById('addStockModal');
            const deleteModal = document.getElementById('deleteProductModal');

            if (!table || !stockModal || !deleteModal) return;

            const stockForm = document.getElementById('addStockForm');
            const stockError = document.getElementById('addStockModalError');
            const stockSave = document.getElementById('saveAddStockButton');
            const stockId = document.getElementById('stock_product_id');
            const stockName = document.getElementById('stockProductName');
            const stockCurrentLabel = document.getElementById('stockProductCurrent');
            const stockQuantity = document.getElementById('stock_quantity');
            const stockNewTotal = document.getElementById('stock_new_total');
            const stockRemarks = document.getElementById('stock_remarks');

            const deleteError = document.getElementById('deleteProductModalError');
            const deleteId = document.getElementById('delete_product_id');
            const deleteName = document.getElementById('deleteProductName');
            const deleteConfirm = document.getElementById('confirmDeleteProductButton');

            let lastFocused = null;
            let currentStock = 0;
            let currentUnit = '';

            function formatNumber(value) {
                return String(parseFloat(Number(value).toFixed(3)));
            }

            function showError(message) {
                errorBox.textContent = message;
                errorBox.classList.add('is-visible');

                if (typeof window.showAppToast === 'function') {
                    window.showAppToast(
                        'error',
                        'Unable to save product',
                        message
                    );
                }
            }

            function clearError(box) {
                box.textContent = '';
                box.classList.remove('is-visible');
            }

            function openModal(modal, focusEl) {
                lastFocused = document.activeElement;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('product-modal-open');

                if (focusEl) {
                    requestAnimationFrame(() => focusEl.focus());
                }
            }

            function closeModal(modal) {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('product-modal-open');

                if (lastFocused && document.contains(lastFocused)) {
                    lastFocused.focus();
                }
            }

            async function send(formData) {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                return response.json();
            }

            function updateNewTotal() {
                const qty = parseFloat(stockQuantity.value) || 0;
                stockNewTotal.value = formatNumber(currentStock + qty) +
                    (currentUnit ? ' ' + currentUnit : '');
            }

            /* Open modals from the table buttons */
            table.addEventListener('click', (event) => {
                const button = event.target.closest('[data-action]');

                if (!button) return;

                if (button.dataset.action === 'add-stock') {
                    currentStock = parseFloat(button.dataset.stock) || 0;
                    currentUnit = button.dataset.unit || '';

                    stockForm.reset();
                    clearError(stockError);

                    stockId.value = button.dataset.id;
                    stockName.textContent = button.dataset.name;
                    stockCurrentLabel.textContent =
                        'Current stock: ' + formatNumber(currentStock) +
                        (currentUnit ? ' ' + currentUnit : '');

                    updateNewTotal();
                    openModal(stockModal, stockQuantity);
                }

                if (button.dataset.action === 'delete') {
                    clearError(deleteError);

                    deleteId.value = button.dataset.id;
                    deleteName.textContent = button.dataset.name;

                    openModal(deleteModal, deleteConfirm);
                }
            });

            /* Close handlers */
            document.getElementById('closeAddStockModal')
                .addEventListener('click', () => closeModal(stockModal));
            document.getElementById('cancelAddStockModal')
                .addEventListener('click', () => closeModal(stockModal));
            document.getElementById('closeDeleteProductModal')
                .addEventListener('click', () => closeModal(deleteModal));
            document.getElementById('cancelDeleteProductModal')
                .addEventListener('click', () => closeModal(deleteModal));

            [stockModal, deleteModal].forEach((modal) => {
                modal.addEventListener('mousedown', (event) => {
                    if (event.target === modal) {
                        closeModal(modal);
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;

                if (stockModal.classList.contains('is-open')) closeModal(stockModal);
                if (deleteModal.classList.contains('is-open')) closeModal(deleteModal);
            });

            /* Live new-total preview + wheel protection */
            stockQuantity.addEventListener('input', updateNewTotal);
            stockQuantity.addEventListener('wheel', () => stockQuantity.blur(), {
                passive: true
            });

            /* Submit: add stock */
            stockForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                clearError(stockError);

                const qty = parseFloat(stockQuantity.value);

                if (!qty || qty <= 0) {
                    showError(stockError, 'Please enter a quantity greater than zero.');
                    stockQuantity.focus();
                    return;
                }

                safeSetButtonLoading(stockSave, true);

                try {
                    const data = await send(new FormData(stockForm));

                    if (!data.success) {
                        showError(stockError, data.message || 'Unable to add stock.');
                        return;
                    }

                    closeModal(stockModal);
                    window.location.href = 'index.php?stocked=1';

                } catch (error) {
                    showError(stockError, 'Something went wrong while adding stock. Please try again.');
                } finally {
                    safeSetButtonLoading(stockSave, false);
                }
            });

            /* Confirm: delete product */
            deleteConfirm.addEventListener('click', async () => {
                clearError(deleteError);

                safeSetButtonLoading(deleteConfirm, true);

                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_product');
                    formData.append('product_id', deleteId.value);

                    const data = await send(formData);

                    if (!data.success) {
                        showError(deleteError, data.message || 'Unable to delete product.');
                        return;
                    }

                    closeModal(deleteModal);
                    window.location.href = 'index.php?deleted=1';

                } catch (error) {
                    showError(deleteError, 'Something went wrong while deleting the product. Please try again.');
                } finally {
                    safeSetButtonLoading(deleteConfirm, false);
                }
            });
        })();
    </script>
    <script>
        (() => {
            const icons = {
                success: '✓',
                error: '!',
                warning: '!',
                info: 'i'
            };

            function getToastArea() {
                let area = document.getElementById('appToastArea');

                if (!area) {
                    area = document.createElement('div');
                    area.id = 'appToastArea';
                    area.className = 'app-toast-area';
                    area.setAttribute('aria-live', 'polite');
                    area.setAttribute('aria-atomic', 'true');
                    document.body.appendChild(area);
                }

                return area;
            }

            window.showAppToast = function(type, title, message, duration = 4200) {
                const allowedTypes = ['success', 'error', 'warning', 'info'];
                const toastType = allowedTypes.includes(type) ? type : 'info';
                const area = getToastArea();

                const toast = document.createElement('div');
                toast.className = 'app-toast app-toast-' + toastType;
                toast.setAttribute('role', toastType === 'error' ? 'alert' : 'status');

                const icon = document.createElement('div');
                icon.className = 'app-toast-icon';
                icon.textContent = icons[toastType];

                const content = document.createElement('div');
                content.className = 'app-toast-content';

                const titleElement = document.createElement('div');
                titleElement.className = 'app-toast-title';
                titleElement.textContent = title || 'Notice';

                const messageElement = document.createElement('div');
                messageElement.className = 'app-toast-message';
                messageElement.textContent = message || '';

                const closeButton = document.createElement('button');
                closeButton.type = 'button';
                closeButton.className = 'app-toast-close';
                closeButton.setAttribute('aria-label', 'Close notification');
                closeButton.textContent = '×';

                const progress = document.createElement('div');
                progress.className = 'app-toast-progress';
                progress.style.animationDuration = duration + 'ms';

                content.appendChild(titleElement);
                content.appendChild(messageElement);

                toast.appendChild(icon);
                toast.appendChild(content);
                toast.appendChild(closeButton);
                toast.appendChild(progress);

                area.appendChild(toast);

                requestAnimationFrame(() => {
                    toast.classList.add('is-visible');
                });

                let removed = false;

                function removeToast() {
                    if (removed) return;

                    removed = true;
                    toast.classList.remove('is-visible');
                    toast.classList.add('is-hiding');

                    window.setTimeout(() => {
                        toast.remove();

                        if (!area.children.length) {
                            area.remove();
                        }
                    }, 360);
                }

                closeButton.addEventListener('click', removeToast);

                window.setTimeout(removeToast, duration);

                return toast;
            };

            /*
             * Makes the page compatible with your existing code:
             * showToast('success', 'Success', 'Product added successfully.')
             */
            window.showToast = function(type, title, message) {
                return window.showAppToast(type, title, message);
            };
        })();
    </script>
</body>

</html>
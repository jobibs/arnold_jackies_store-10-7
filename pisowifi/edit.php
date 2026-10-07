<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

$basePath = '../';
$activeMenu = 'pisowifi';

$notifications = [];


/*
|--------------------------------------------------------------------------
| LOW STOCK NOTIFICATIONS
|--------------------------------------------------------------------------
*/

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


$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD BOX
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        box_name,
        location,
        description,
        status
    FROM pisowifi
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$box = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$box) {

    header('Location: index.php');
    exit;
}


$error = '';

$boxName = $box['box_name'];
$location = $box['location'];
$description = $box['description'] ?? '';
$status = $box['status'];


/*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $boxName = trim($_POST['box_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';


    if ($boxName === '') {

        $error = 'PisoWiFi box name is required.';
    } elseif ($location === '') {

        $error = 'Location is required.';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = 'Invalid status selected.';
    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM pisowifi
                WHERE box_name = ?
                  AND id != ?
            ");

            $stmt->execute([
                $boxName,
                $id
            ]);

            if ((int)$stmt->fetchColumn() > 0) {

                $error = 'Another PisoWiFi box already uses this name.';
            } else {

                $stmt = $pdo->prepare("
                    UPDATE pisowifi
                    SET
                        box_name = ?,
                        location = ?,
                        description = ?,
                        status = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $boxName,
                    $location,
                    $description !== '' ? $description : null,
                    $status,
                    $id
                ]);

                header('Location: index.php?updated=1');
                exit;
            }
        } catch (PDOException $e) {

            $error = 'Unable to update the PisoWiFi box. Please try again.';
        }
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

    <title>Edit PisoWiFi - Arnold & Jackie's Store</title>

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">

    <style>
        .form-page {
            width: 100%;
        }

        .page-form-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
        }

        .page-title {
            margin: 0;
            font-size: 25px;
            font-weight: 700;
            color: #172033;
        }

        .page-subtitle {
            margin-top: 5px;
            color: #6b7280;
            font-size: 13px;
        }

        .back-link {
            color: #374151;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .back-link:hover {
            color: #0d6efd;
        }

        .form-center {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .form-card {
            width: 100%;
            max-width: 700px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .form-card-header {
            padding: 18px 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .form-card-title {
            font-size: 15px;
            font-weight: 700;
            color: #172033;
        }

        .form-card-subtitle {
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
        }

        .form-card-body {
            padding: 22px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        .form-label {
            display: block;
            margin-bottom: 7px;
            font-size: 12px;
            font-weight: 700;
            color: #374151;
        }

        .form-control {
            width: 100%;
            height: 42px;
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            color: #172033;
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }

        textarea.form-control {
            height: 100px;
            resize: vertical;
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .10);
        }

        .error-box {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 8px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 13px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 9px;
            margin-top: 22px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 9px 15px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-secondary {
            background: #ffffff;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #f9fafb;
        }

        .btn-primary {
            background: #0d6efd;
            border: 1px solid #0d6efd;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0b5ed7;
        }

        @media (max-width: 650px) {

            .page-form-header {
                flex-direction: column;
            }

            .form-card-body {
                padding: 18px;
            }

        }
    </style>

</head>

<body>

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="main">

        <?php require_once $basePath . 'includes/topbar.php'; ?>

        <main class="content">

            <div class="form-page">

                <div class="page-form-header">

                    <div>

                        <h1 class="page-title">
                            Edit PisoWiFi
                        </h1>

                        <div class="page-subtitle">
                            Update the information for this PisoWiFi box.
                        </div>

                    </div>

                    <a
                        href="index.php"
                        class="back-link">
                        ← Back to PisoWiFi
                    </a>

                </div>


                <div class="form-center">

                    <div class="form-card">

                        <div class="form-card-header">

                            <div class="form-card-title">
                                PisoWiFi Information
                            </div>

                            <div class="form-card-subtitle">
                                Update the machine details below.
                            </div>

                        </div>


                        <div class="form-card-body">

                            <?php if ($error): ?>

                                <div class="error-box">
                                    <?= htmlspecialchars($error) ?>
                                </div>

                            <?php endif; ?>


                            <form
                                method="POST"
                                autocomplete="off">

                                <div class="form-group">

                                    <label class="form-label">
                                        Box Name
                                    </label>

                                    <input
                                        type="text"
                                        name="box_name"
                                        class="form-control"
                                        value="<?= htmlspecialchars($boxName) ?>"
                                        maxlength="100"
                                        required>

                                </div>


                                <div class="form-group">

                                    <label class="form-label">
                                        Location
                                    </label>

                                    <input
                                        type="text"
                                        name="location"
                                        class="form-control"
                                        value="<?= htmlspecialchars($location) ?>"
                                        maxlength="255"
                                        required>

                                </div>


                                <div class="form-group">

                                    <label class="form-label">
                                        Description
                                    </label>

                                    <textarea
                                        name="description"
                                        class="form-control"
                                        maxlength="500"><?= htmlspecialchars($description) ?></textarea>

                                </div>


                                <div class="form-group">

                                    <label class="form-label">
                                        Status
                                    </label>

                                    <select
                                        name="status"
                                        class="form-control">

                                        <option
                                            value="active"
                                            <?= $status === 'active' ? 'selected' : '' ?>>
                                            Active
                                        </option>

                                        <option
                                            value="inactive"
                                            <?= $status === 'inactive' ? 'selected' : '' ?>>
                                            Inactive
                                        </option>

                                    </select>

                                </div>


                                <div class="form-actions">

                                    <a
                                        href="index.php"
                                        class="btn btn-secondary">
                                        Cancel
                                    </a>

                                    <button
                                        type="submit"
                                        class="btn btn-primary">
                                        Save Changes
                                    </button>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

            </div>

        </main>

    </div>

    <script src="<?= $basePath ?>assets/js/admin.js"></script>

</body>

</html>
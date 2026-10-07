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


$error = '';

$boxName = '';
$location = '';
$description = '';
$status = 'active';


/*
|--------------------------------------------------------------------------
| SAVE
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
            ");

            $stmt->execute([$boxName]);

            if ((int)$stmt->fetchColumn() > 0) {

                $error = 'A PisoWiFi box with this name already exists.';
            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO pisowifi
                    (
                        box_name,
                        location,
                        description,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $stmt->execute([
                    $boxName,
                    $location,
                    $description !== '' ? $description : null,
                    $status
                ]);

                header('Location: index.php?added=1');
                exit;
            }
        } catch (PDOException $e) {

            $error = 'Unable to save the PisoWiFi box. Please try again.';
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

    <title>Add PisoWiFi - Arnold & Jackie's Store</title>

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
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
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

        .form-help {
            margin-top: 5px;
            font-size: 11px;
            color: #8b94a3;
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

        /* =========================================
   PISOWIFI MODAL EFFECTS
========================================= */

        .piso-modal-overlay.closing,
        .piso-delete-overlay.closing {
            opacity: 0;
            transition: opacity .22s ease;
        }

        .piso-modal-overlay.closing .piso-modal,
        .piso-delete-overlay.closing .piso-delete-modal {
            opacity: 0;
            transform: translateY(8px) scale(.98);
            transition:
                opacity .22s ease,
                transform .22s ease;
        }


        /* =========================================
   SUCCESS / ERROR MESSAGE EFFECTS
========================================= */

        #toastContainer .toast-notification {
            animation:
                pisoToastIn .32s cubic-bezier(.2, .8, .2, 1) both;
        }

        #toastContainer .toast-notification.toast-removing {
            animation:
                pisoToastOut .28s ease both;
        }

        @keyframes pisoToastIn {

            from {
                opacity: 0;
                transform:
                    translateY(-10px) translateX(12px) scale(.97);
            }

            to {
                opacity: 1;
                transform:
                    translateY(0) translateX(0) scale(1);
            }

        }

        @keyframes pisoToastOut {

            from {
                opacity: 1;
                transform:
                    translateY(0) translateX(0) scale(1);
            }

            to {
                opacity: 0;
                transform:
                    translateY(-6px) translateX(12px) scale(.97);
            }

        }


        /* Error message inside Add modal */

        .piso-form-error.show {
            animation:
                pisoErrorIn .25s ease both;
        }

        @keyframes pisoErrorIn {

            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
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
                            Add PisoWiFi
                        </h1>

                        <div class="page-subtitle">
                            Add a new PisoWiFi box to your records.
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
                                Enter the basic details of the machine.
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
                                        placeholder="e.g. PisoWiFi 1"
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
                                        placeholder="e.g. Brgy. San Jose"
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
                                        placeholder="Optional notes about this PisoWiFi box..."
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
                                        Save PisoWiFi
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
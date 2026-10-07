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
| DATABASE NAME
|--------------------------------------------------------------------------
|
| Get the current database name directly from MySQL.
|
*/

try {

    $databaseName = $pdo->query(
        "SELECT DATABASE()"
    )->fetchColumn();

    if (!$databaseName) {

        throw new RuntimeException(
            'Unable to determine the database name.'
        );
    }
} catch (Throwable $e) {

    die('Unable to determine the database name.');
}


/*
|--------------------------------------------------------------------------
| BACKUP DIRECTORY
|--------------------------------------------------------------------------
*/

$backupDirectory =
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';


if (!is_dir($backupDirectory)) {

    if (!mkdir(
        $backupDirectory,
        0775,
        true
    )) {

        die('Unable to create the backup directory.');
    }
}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function sqlQuoteValue(
    PDO $pdo,
    $value
): string {

    if ($value === null) {

        return 'NULL';
    }


    /*
    |--------------------------------------------------------------------------
    | BINARY DATA
    |--------------------------------------------------------------------------
    */

    if (is_string($value)) {

        /*
        | Detect binary-looking values.
        | Use hex representation when necessary.
        */

        $isBinary = false;

        $length = strlen($value);

        for (
            $i = 0;
            $i < min($length, 100);
            $i++
        ) {

            $ord = ord($value[$i]);

            if (
                $ord < 9 ||
                (
                    $ord > 13 &&
                    $ord < 32
                )
            ) {

                $isBinary = true;

                break;
            }
        }


        if ($isBinary) {

            return 'X\'' .
                bin2hex($value) .
                '\'';
        }
    }


    return $pdo->quote(
        (string)$value
    );
}


/*
|--------------------------------------------------------------------------
| CREATE SQL BACKUP
|--------------------------------------------------------------------------
*/

function createDatabaseBackup(
    PDO $pdo,
    string $databaseName,
    string $backupDirectory
): array {

    $timestamp =
        date('Y-m-d_H-i-s');

    $safeDatabaseName =
        preg_replace(
            '/[^A-Za-z0-9_-]/',
            '_',
            $databaseName
        );

    $filename =
        $safeDatabaseName
        . '_backup_'
        . $timestamp
        . '.sql';


    $filepath =
        $backupDirectory
        . DIRECTORY_SEPARATOR
        . $filename;


    $handle = fopen(
        $filepath,
        'wb'
    );


    if ($handle === false) {

        throw new RuntimeException(
            'Unable to create the backup file.'
        );
    }


    try {


        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        fwrite(
            $handle,
            "-- =========================================================\n"
        );

        fwrite(
            $handle,
            "-- Arnold & Jackie's Store Database Backup\n"
        );

        fwrite(
            $handle,
            "-- Database: "
                . $databaseName
                . "\n"
        );

        fwrite(
            $handle,
            "-- Created: "
                . date('Y-m-d H:i:s')
                . "\n"
        );

        fwrite(
            $handle,
            "-- =========================================================\n\n"
        );


        fwrite(
            $handle,
            "SET FOREIGN_KEY_CHECKS = 0;\n"
        );

        fwrite(
            $handle,
            "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n"
        );

        fwrite(
            $handle,
            "SET AUTOCOMMIT = 0;\n\n"
        );


        /*
        |--------------------------------------------------------------------------
        | GET TABLES
        |--------------------------------------------------------------------------
        */

        $tablesStmt = $pdo->query(
            "SHOW TABLES"
        );

        $tables =
            $tablesStmt->fetchAll(
                PDO::FETCH_COLUMN
            );


        /*
        |--------------------------------------------------------------------------
        | EXPORT EACH TABLE
        |--------------------------------------------------------------------------
        */

        foreach ($tables as $table) {


            /*
            |--------------------------------------------------------------------------
            | TABLE NAME
            |--------------------------------------------------------------------------
            */

            $quotedTable =
                '`'
                . str_replace(
                    '`',
                    '``',
                    $table
                )
                . '`';


            /*
            |--------------------------------------------------------------------------
            | DROP TABLE
            |--------------------------------------------------------------------------
            */

            fwrite(
                $handle,
                "\n-- =========================================================\n"
            );

            fwrite(
                $handle,
                "-- Table: "
                    . $table
                    . "\n"
            );

            fwrite(
                $handle,
                "-- =========================================================\n\n"
            );


            fwrite(
                $handle,
                "DROP TABLE IF EXISTS "
                    . $quotedTable
                    . ";\n\n"
            );


            /*
            |--------------------------------------------------------------------------
            | CREATE TABLE
            |--------------------------------------------------------------------------
            */

            $createStmt =
                $pdo->query(
                    "SHOW CREATE TABLE "
                        . $quotedTable
                );

            $createRow =
                $createStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if ($createRow) {

                $createSql =
                    $createRow['Create Table']
                    ?? $createRow['Create View']
                    ?? null;


                if ($createSql) {

                    fwrite(
                        $handle,
                        $createSql
                            . ";\n\n"
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | GET COLUMNS
            |--------------------------------------------------------------------------
            */

            $columnStmt =
                $pdo->query(
                    "SHOW COLUMNS FROM "
                        . $quotedTable
                );

            $columns =
                $columnStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            $columnNames = [];


            foreach ($columns as $column) {

                $columnNames[] =
                    '`'
                    . str_replace(
                        '`',
                        '``',
                        $column['Field']
                    )
                    . '`';
            }


            /*
            |--------------------------------------------------------------------------
            | GET DATA
            |--------------------------------------------------------------------------
            */

            $dataStmt =
                $pdo->query(
                    "SELECT * FROM "
                        . $quotedTable
                );


            $rowCount = 0;

            while (
                $row =
                $dataStmt->fetch(
                    PDO::FETCH_ASSOC
                )
            ) {

                $values = [];


                foreach (
                    $columnNames as $index => $columnName
                ) {

                    $originalColumn =
                        $columns[$index]['Field'];


                    $values[] =
                        sqlQuoteValue(
                            $pdo,
                            $row[$originalColumn]
                        );
                }


                $insertSql =
                    "INSERT INTO "
                    . $quotedTable
                    . " ("
                    . implode(
                        ', ',
                        $columnNames
                    )
                    . ") VALUES ("
                    . implode(
                        ', ',
                        $values
                    )
                    . ");\n";


                fwrite(
                    $handle,
                    $insertSql
                );


                $rowCount++;
            }


            if ($rowCount > 0) {

                fwrite(
                    $handle,
                    "\n"
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        fwrite(
            $handle,
            "\nSET FOREIGN_KEY_CHECKS = 1;\n"
        );

        fwrite(
            $handle,
            "COMMIT;\n"
        );

        fwrite(
            $handle,
            "SET AUTOCOMMIT = 1;\n"
        );


        fclose($handle);
    } catch (Throwable $e) {

        fclose($handle);


        if (file_exists($filepath)) {

            unlink($filepath);
        }


        throw $e;
    }


    return [

        'filename' =>
        $filename,

        'filepath' =>
        $filepath,

        'filesize' =>
        filesize($filepath)

    ];
}


/*
|--------------------------------------------------------------------------
| DOWNLOAD BACKUP
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['download'])
) {

    $requestedFile =
        basename(
            $_GET['download']
        );


    /*
    |--------------------------------------------------------------------------
    | Only allow SQL files
    |--------------------------------------------------------------------------
    */

    if (
        !preg_match(
            '/\.sql$/i',
            $requestedFile
        )
    ) {

        http_response_code(400);

        exit('Invalid backup file.');
    }


    $requestedPath =
        $backupDirectory
        . DIRECTORY_SEPARATOR
        . $requestedFile;


    if (
        !is_file($requestedPath)
    ) {

        http_response_code(404);

        exit('Backup file not found.');
    }


    header(
        'Content-Type: application/sql'
    );

    header(
        'Content-Disposition: attachment; filename="'
            . $requestedFile
            . '"'
    );

    header(
        'Content-Length: '
            . filesize($requestedPath)
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate'
    );

    readfile(
        $requestedPath
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CREATE BACKUP
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['create_backup'])
) {

    try {

        $backup =
            createDatabaseBackup(
                $pdo,
                $databaseName,
                $backupDirectory
            );


        $successMessage =
            'Database backup created successfully.';


        /*
        |--------------------------------------------------------------------------
        | AUTO DOWNLOAD
        |--------------------------------------------------------------------------
        |
        | The page itself will remain visible while the browser
        | downloads the generated file.
        |
        */

        $downloadFile =
            $backup['filename'];
    } catch (Throwable $e) {

        $errorMessage =
            'Unable to create the database backup.';

        error_log(
            'Database backup error: '
                . $e->getMessage()
        );

        $downloadFile = '';
    }
}


/*
|--------------------------------------------------------------------------
| RECENT BACKUPS
|--------------------------------------------------------------------------
*/

$backupFiles = [];

$files =
    glob(
        $backupDirectory
            . DIRECTORY_SEPARATOR
            . '*.sql'
    );


if ($files !== false) {

    foreach ($files as $file) {

        if (!is_file($file)) {
            continue;
        }


        $backupFiles[] = [

            'filename' =>
            basename($file),

            'size' =>
            filesize($file),

            'created' =>
            filemtime($file)

        ];
    }
}


/*
|--------------------------------------------------------------------------
| SORT NEWEST FIRST
|--------------------------------------------------------------------------
*/

usort(
    $backupFiles,
    function (
        $a,
        $b
    ) {

        return
            $b['created']
            <=>
            $a['created'];
    }
);


/*
|--------------------------------------------------------------------------
| LIMIT DISPLAY
|--------------------------------------------------------------------------
*/

$backupFiles =
    array_slice(
        $backupFiles,
        0,
        10
    );


/*
|--------------------------------------------------------------------------
| TOTAL BACKUPS
|--------------------------------------------------------------------------
*/

$totalBackups =
    count(
        glob(
            $backupDirectory
                . DIRECTORY_SEPARATOR
                . '*.sql'
        ) ?: []
    );


/*
|--------------------------------------------------------------------------
| USER AVATAR
|--------------------------------------------------------------------------
*/

$userInitial =
    strtoupper(
        substr(
            trim(
                $currentUserName
            ),
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
        Backup & Data - Arnold & Jackie's Store
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

        .backup-page {

            width: 100%;
            max-width: none;

        }


        /* =========================================================
           HEADER
        ========================================================== */

        .backup-header {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 22px;

        }


        .backup-title {

            margin: 0 0 5px;

            color: #172033;

            font-size: 26px;

            font-weight: 750;

            line-height: 1.2;

            letter-spacing: -.025em;

        }


        .backup-subtitle {

            margin: 0;

            color: #7b8494;

            font-size: 13px;

            line-height: 1.6;

        }


        /* =========================================================
           BACK BUTTON
        ========================================================== */

        .backup-back-button {

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


        .backup-back-button:hover {

            transform: translateX(-1px);

            background: #f8fafc;

            border-color: #cfd8e3;

            color: #1d4ed8;

        }


        /* =========================================================
           NOTICE
        ========================================================== */

        .backup-notice {

            margin-bottom: 14px;

            padding: 13px 15px;

            border-radius: 12px;

            font-size: 11px;

            line-height: 1.5;

        }


        .backup-notice-success {

            border: 1px solid #bbf7d0;

            background: #f0fdf4;

            color: #166534;

        }


        .backup-notice-error {

            border: 1px solid #fecaca;

            background: #fef2f2;

            color: #b91c1c;

        }


        /* =========================================================
           CARD
        ========================================================== */

        .backup-card {

            margin-bottom: 12px;

            overflow: hidden;

            border: 1px solid #e5eaf0;

            border-radius: 16px;

            background: #ffffff;

            box-shadow:
                0 7px 22px rgba(15, 23, 42, .025);

        }


        .backup-card-header {

            display: flex;

            align-items: center;

            gap: 13px;

            padding: 18px 20px;

            border-bottom: 1px solid #edf0f4;

        }


        .backup-icon {

            width: 42px;

            height: 42px;

            flex: 0 0 42px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            border-radius: 12px;

            background: #f5f3ff;

            color: #7c3aed;

            font-size: 18px;

        }


        .backup-card-title {

            margin: 0 0 3px;

            color: #202938;

            font-size: 14px;

            font-weight: 750;

        }


        .backup-card-subtitle {

            margin: 0;

            color: #8791a0;

            font-size: 10px;

            line-height: 1.5;

        }


        .backup-card-body {

            padding: 20px;

        }


        /* =========================================================
           INFO GRID
        ========================================================== */

        .backup-info-grid {

            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 10px;

            margin-bottom: 18px;

        }


        .backup-info-box {

            padding: 13px;

            border: 1px solid #e5eaf0;

            border-radius: 12px;

            background: #fbfcfe;

        }


        .backup-info-label {

            margin-bottom: 4px;

            color: #94a3b8;

            font-size: 9px;

            font-weight: 750;

            letter-spacing: .06em;

            text-transform: uppercase;

        }


        .backup-info-value {

            color: #253044;

            font-size: 12px;

            font-weight: 750;

            word-break: break-word;

        }


        /* =========================================================
           ACTION
        ========================================================== */

        .backup-action {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 16px;

            padding: 15px;

            border: 1px solid #dbeafe;

            border-radius: 12px;

            background: #f8fbff;

        }


        .backup-action-title {

            margin: 0 0 3px;

            color: #1e3a8a;

            font-size: 11px;

            font-weight: 750;

        }


        .backup-action-text {

            margin: 0;

            color: #64748b;

            font-size: 10px;

            line-height: 1.5;

        }


        .backup-button {

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

            white-space: nowrap;

            box-shadow:
                0 5px 15px rgba(37, 99, 235, .14);

            transition:

                transform .18s ease,

                background-color .18s ease,

                border-color .18s ease;

        }


        .backup-button:hover {

            transform: translateY(-1px);

            background: #1d4ed8;

            border-color: #1d4ed8;

        }


        .backup-button:disabled {

            cursor: wait;

            opacity: .7;

        }


        /* =========================================================
           TABLE
        ========================================================== */

        .backup-table-wrapper {

            overflow-x: auto;

        }


        .backup-table {

            width: 100%;

            border-collapse: collapse;

        }


        .backup-table th {

            padding: 11px 12px;

            border-bottom: 1px solid #edf0f4;

            color: #8a93a2;

            font-size: 9px;

            font-weight: 800;

            text-align: left;

            text-transform: uppercase;

            letter-spacing: .055em;

            white-space: nowrap;

        }


        .backup-table td {

            padding: 12px;

            border-bottom: 1px solid #f0f2f5;

            color: #475569;

            font-size: 10px;

            vertical-align: middle;

        }


        .backup-table tr:last-child td {

            border-bottom: 0;

        }


        .backup-filename {

            color: #253044;

            font-weight: 700;

            word-break: break-all;

        }


        .backup-download {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-height: 30px;

            padding: 0 10px;

            border: 1px solid #dbeafe;

            border-radius: 8px;

            background: #eff6ff;

            color: #1d4ed8;

            text-decoration: none;

            font-size: 9px;

            font-weight: 750;

            transition:

                background-color .15s ease,

                border-color .15s ease;

        }


        .backup-download:hover {

            background: #dbeafe;

            border-color: #bfdbfe;

        }


        /* =========================================================
           EMPTY
        ========================================================== */

        .backup-empty {

            padding: 28px 15px;

            text-align: center;

            color: #94a3b8;

            font-size: 11px;

        }


        .backup-empty-icon {

            margin-bottom: 8px;

            font-size: 25px;

            opacity: .65;

        }


        /* =========================================================
           SAFETY NOTE
        ========================================================== */

        .backup-safety {

            padding: 14px 16px;

            border: 1px solid #fde68a;

            border-radius: 12px;

            background: #fffbeb;

            color: #92400e;

            font-size: 10px;

            line-height: 1.6;

        }


        /* =========================================================
           RESPONSIVE
        ========================================================== */

        @media (max-width: 800px) {

            .backup-header {

                flex-direction: column;

            }


            .backup-back-button {

                width: 100%;

            }


            .backup-info-grid {

                grid-template-columns: 1fr;

            }


            .backup-action {

                flex-direction: column;

                align-items: stretch;

            }


            .backup-button {

                width: 100%;

            }

        }


        @media (max-width: 520px) {

            .backup-title {

                font-size: 23px;

            }


            .backup-card-body {

                padding: 16px;

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


            <div class="backup-page">


                <!-- =================================================
                     HEADER
                ================================================== -->

                <div class="backup-header">

                    <div>

                        <h1 class="backup-title">
                            Backup & Data
                        </h1>


                        <p class="backup-subtitle">
                            Protect your store database by creating downloadable backups of your system data.
                        </p>

                    </div>


                    <a
                        href="index.php"
                        class="backup-back-button">

                        ← Back to Settings

                    </a>

                </div>


                <!-- =================================================
                     SUCCESS
                ================================================== -->

                <?php if ($successMessage !== ''): ?>

                    <div
                        class="backup-notice backup-notice-success">

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
                        class="backup-notice backup-notice-error">

                        <?= htmlspecialchars(
                            $errorMessage,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     DATABASE BACKUP
                ================================================== -->

                <section class="backup-card">


                    <div class="backup-card-header">

                        <div class="backup-icon">
                            💾
                        </div>


                        <div>

                            <h2 class="backup-card-title">
                                Database Backup
                            </h2>


                            <p class="backup-card-subtitle">
                                Create a complete SQL copy of your store database.
                            </p>

                        </div>

                    </div>


                    <div class="backup-card-body">


                        <!-- INFO -->

                        <div class="backup-info-grid">


                            <div class="backup-info-box">

                                <div class="backup-info-label">
                                    Database
                                </div>


                                <div class="backup-info-value">

                                    <?= htmlspecialchars(
                                        $databaseName,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                            </div>


                            <div class="backup-info-box">

                                <div class="backup-info-label">
                                    Available Backups
                                </div>


                                <div class="backup-info-value">

                                    <?= number_format(
                                        $totalBackups
                                    ) ?>

                                </div>

                            </div>


                            <div class="backup-info-box">

                                <div class="backup-info-label">
                                    Backup Format
                                </div>


                                <div class="backup-info-value">
                                    SQL
                                </div>

                            </div>


                        </div>


                        <!-- ACTION -->

                        <div class="backup-action">


                            <div>

                                <p class="backup-action-title">
                                    Create a fresh database backup
                                </p>


                                <p class="backup-action-text">
                                    This creates a new SQL file containing your store tables and their current data.
                                </p>

                            </div>


                            <form
                                method="post"
                                id="backupForm">

                                <input
                                    type="hidden"
                                    name="create_backup"
                                    value="1">


                                <button
                                    type="submit"
                                    class="backup-button"
                                    id="createBackupButton">

                                    Create Backup

                                </button>

                            </form>


                        </div>


                    </div>

                </section>


                <!-- =================================================
                     RECENT BACKUPS
                ================================================== -->

                <section class="backup-card">


                    <div class="backup-card-header">

                        <div class="backup-icon">
                            🗂️
                        </div>


                        <div>

                            <h2 class="backup-card-title">
                                Recent Backups
                            </h2>


                            <p class="backup-card-subtitle">
                                Your latest database backup files.
                            </p>

                        </div>

                    </div>


                    <div class="backup-card-body">


                        <?php if ($backupFiles): ?>


                            <div class="backup-table-wrapper">


                                <table class="backup-table">


                                    <thead>

                                        <tr>

                                            <th>
                                                Backup File
                                            </th>


                                            <th>
                                                Date
                                            </th>


                                            <th>
                                                Size
                                            </th>


                                            <th>
                                                Action
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>


                                        <?php foreach (
                                            $backupFiles
                                            as $backup
                                        ): ?>


                                            <tr>


                                                <td>

                                                    <div class="backup-filename">

                                                        <?= htmlspecialchars(
                                                            $backup['filename'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>

                                                    </div>

                                                </td>


                                                <td>

                                                    <?= date(
                                                        'F d, Y h:i A',
                                                        $backup['created']
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?php

                                                    $size =
                                                        $backup['size'];

                                                    if (
                                                        $size >= 1048576
                                                    ) {

                                                        echo number_format(
                                                            $size / 1048576,
                                                            2
                                                        )
                                                            . ' MB';
                                                    } elseif (
                                                        $size >= 1024
                                                    ) {

                                                        echo number_format(
                                                            $size / 1024,
                                                            2
                                                        )
                                                            . ' KB';
                                                    } else {

                                                        echo number_format(
                                                            $size
                                                        )
                                                            . ' B';
                                                    }

                                                    ?>

                                                </td>


                                                <td>

                                                    <a
                                                        href="?download=<?= urlencode(
                                                                            $backup['filename']
                                                                        ) ?>"
                                                        class="backup-download">

                                                        ↓ Download

                                                    </a>

                                                </td>


                                            </tr>


                                        <?php endforeach; ?>


                                    </tbody>


                                </table>


                            </div>


                        <?php else: ?>


                            <div class="backup-empty">


                                <div class="backup-empty-icon">
                                    💾
                                </div>


                                No backups have been created yet.

                            </div>


                        <?php endif; ?>


                    </div>

                </section>


                <!-- =================================================
                     SAFETY NOTE
                ================================================== -->

                <div class="backup-safety">

                    <strong>
                        Backup reminder:
                    </strong>

                    Database backups contain important store information.
                    Keep downloaded backup files in a safe location and avoid sharing them publicly.

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
         BACKUP JS
    ========================================================== -->

    <script>
        (function() {

            const form =
                document.getElementById(
                    'backupForm'
                );


            const button =
                document.getElementById(
                    'createBackupButton'
                );


            if (!form || !button) {
                return;
            }


            form.addEventListener(
                'submit',
                function() {

                    button.disabled = true;

                    button.textContent =
                        'Creating Backup...';

                }
            );

        })();
    </script>


    <?php if (
        !empty($downloadFile)
    ): ?>

        <script>
            window.addEventListener(
                'load',
                function() {

                    const downloadUrl =
                        'backup.php?download=' +
                        encodeURIComponent(
                            <?= json_encode(
                                $downloadFile
                            ) ?>
                        );


                    window.location.href =
                        downloadUrl;

                }
            );
        </script>

    <?php endif; ?>


</body>

</html>
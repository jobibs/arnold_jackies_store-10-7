<?php

require_once '../config/database.php';
require_once '../config/auth.php';

require_login();

$basePath = '../';
$activeMenu = 'pisowifi';
$currentSection = 'pisowifi';

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| PAGE STATE
|--------------------------------------------------------------------------
*/
$selectedMonth = trim($_GET['month'] ?? date('Y-m'));

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$selectedMonthDate = $selectedMonth . '-01';
$selectedMonthLabel = date('F Y', strtotime($selectedMonthDate));
$selectedMonthNumber = (int) date('n', strtotime($selectedMonthDate));
$selectedYear = (int) date('Y', strtotime($selectedMonthDate));

/*
|--------------------------------------------------------------------------
| LOW STOCK NOTIFICATIONS
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
| PISOWIFI DATA
|--------------------------------------------------------------------------
*/
$setupError = '';
$boxes = [];

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pisowifi (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            box_name VARCHAR(100) NOT NULL,
            location VARCHAR(255) NOT NULL,
            description VARCHAR(500) DEFAULT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pisowifi_box_name (box_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pisowifi_earnings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            pisowifi_id INT UNSIGNED NOT NULL,
            earning_month TINYINT UNSIGNED NOT NULL,
            earning_year SMALLINT UNSIGNED NOT NULL,
            gross_earnings DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            expenses DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            notes VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pisowifi_earning_month (pisowifi_id, earning_month, earning_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.box_name,
            p.location,
            p.description,
            p.status,
            e.id AS earning_id,
            COALESCE(e.gross_earnings, 0) AS gross_earnings,
            COALESCE(e.expenses, 0) AS expenses,
            e.notes
        FROM pisowifi p
        LEFT JOIN pisowifi_earnings e
            ON e.pisowifi_id = p.id
           AND e.earning_month = ?
           AND e.earning_year = ?
        ORDER BY p.box_name ASC
    ");
    $stmt->execute([$selectedMonthNumber, $selectedYear]);
    $boxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $setupError = 'Unable to load PisoWiFi data.';
    $boxes = [];
}

/*
|--------------------------------------------------------------------------
| KPI CALCULATIONS
|--------------------------------------------------------------------------
*/
$totalBoxes = count($boxes);
$activeBoxes = 0;
$inactiveBoxes = 0;
$pendingBoxes = 0;
$monthlyGross = 0.00;
$monthlyExpenses = 0.00;
$monthlyNet = 0.00;

foreach ($boxes as &$box) {
    $box['gross_earnings'] = (float) $box['gross_earnings'];
    $box['expenses'] = (float) $box['expenses'];
    $box['net_earnings'] = $box['gross_earnings'] - $box['expenses'];

    if ($box['status'] === 'active') {
        $activeBoxes++;
    } else {
        $inactiveBoxes++;
    }

    if ($box['earning_id']) {
        $monthlyGross += $box['gross_earnings'];
        $monthlyExpenses += $box['expenses'];
        $monthlyNet += $box['net_earnings'];
    } else {
        $pendingBoxes++;
    }
}
unset($box);

/*
|--------------------------------------------------------------------------
| ALL-TIME GROSS
|--------------------------------------------------------------------------
*/
$allTimeGross = 0.00;

try {
    $allTimeStmt = $pdo->query("
        SELECT COALESCE(SUM(gross_earnings), 0)
        FROM pisowifi_earnings
    ");
    $allTimeGross = (float) $allTimeStmt->fetchColumn();
} catch (PDOException $e) {
    $allTimeGross = 0.00;
}

/*
|--------------------------------------------------------------------------
| MONTHLY HISTORY TABLE
|--------------------------------------------------------------------------
*/
$monthlyRecords = [];

try {
    $historyStmt = $pdo->prepare("
        SELECT
            e.id,
            e.pisowifi_id,
            p.box_name,
            e.gross_earnings,
            e.expenses,
            (e.gross_earnings - e.expenses) AS net_earnings,
            e.notes,
            e.created_at,
            e.updated_at
        FROM pisowifi_earnings e
        INNER JOIN pisowifi p
            ON p.id = e.pisowifi_id
        WHERE e.earning_month = ?
          AND e.earning_year = ?
        ORDER BY p.box_name ASC
    ");
    $historyStmt->execute([$selectedMonthNumber, $selectedYear]);
    $monthlyRecords = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $monthlyRecords = [];
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>PisoWiFi - Arnold & Jackie's Store</title>

    <link
        rel="stylesheet"
        href="<?= $basePath ?>assets/css/admin.css">

    <style>
        /* =========================================================
           PISOWIFI PAGE
        ========================================================= */
        .pisowifi-page {
            width: calc(100% + 100px);
            max-width: 1550px;
            margin: 0 auto;
        }

        .piso-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 9px;
            margin-bottom: 12px;
        }

        .piso-title {
            margin: 0;
            color: #172033;
            font-size: 27px;
            font-weight: 750;
            line-height: 1.15;
            letter-spacing: -.03em;
        }

        .piso-subtitle {
            margin: 6px 0 0;
            color: #71809a;
            font-size: 13px;
            line-height: 1.5;
        }

        .piso-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .piso-add-button {
            min-height: 40px;
            padding: 0 15px;
            border: 0;
            border-radius: 9px;
            background: #2563eb;
            color: #fff;
            font: inherit;
            font-size: 12px;
            font-weight: 750;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(37, 99, 235, .18);
            transition: .16s ease;
        }

        .piso-add-button:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .piso-month-form {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 40px;
            padding: 4px 5px 4px 10px;
            border: 1px solid #e4e9f0;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 5px 18px rgba(15, 23, 42, .035);
        }

        .piso-month-label {
            color: #7b8494;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .07em;
        }

        .piso-month-input {
            width: 142px;
            height: 32px;
            padding: 0 8px;
            border: 1px solid #e0e6ee;
            border-radius: 7px;
            background: #fff;
            color: #1f2937;
            font: inherit;
            font-size: 12px;
            font-weight: 650;
            outline: none;
        }

        .piso-view-button {
            min-height: 32px;
            padding: 0 12px;
            border: 1px solid #d9e0e8;
            border-radius: 7px;
            background: #fff;
            color: #374151;
            font: inherit;
            font-size: 11px;
            font-weight: 750;
            cursor: pointer;
        }

        .piso-view-button:hover {
            background: #f8fafc;
        }

        /* =========================================================
           KPI
        ========================================================= */
        .piso-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .piso-kpi {
            min-height: 86px;
            padding: 12px 13px;
            border: 1px solid #e4e9f0;
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 5px 20px rgba(15, 23, 42, .035);
        }

        .piso-kpi.kpi-blue {
            box-shadow: inset 3px 0 0 #2563eb, 0 5px 20px rgba(15, 23, 42, .035);
        }

        .piso-kpi.kpi-green {
            box-shadow: inset 3px 0 0 #16a34a, 0 5px 20px rgba(15, 23, 42, .035);
        }

        .piso-kpi.kpi-orange {
            box-shadow: inset 3px 0 0 #d97706, 0 5px 20px rgba(15, 23, 42, .035);
        }

        .piso-kpi.kpi-purple {
            box-shadow: inset 3px 0 0 #7c3aed, 0 5px 20px rgba(15, 23, 42, .035);
        }

        .piso-kpi-label {
            margin-bottom: 8px;
            color: #7b8494;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .piso-kpi-value {
            color: #152038;
            font-size: 24px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -.03em;
        }

        .piso-kpi.kpi-blue .piso-kpi-value {
            color: #2563eb;
        }

        .piso-kpi.kpi-green .piso-kpi-value {
            color: #16a34a;
        }

        .piso-kpi.kpi-orange .piso-kpi-value {
            color: #d97706;
        }

        .piso-kpi.kpi-purple .piso-kpi-value {
            color: #6d28d9;
        }

        .piso-kpi-note {
            margin-top: 8px;
            color: #8791a2;
            font-size: 10px;
            line-height: 1.35;
        }

        /* =========================================================
           MAIN TWO-COLUMN AREA
        ========================================================= */
        .piso-layout {
            display: grid;
            grid-template-columns: minmax(0, 2.05fr) minmax(300px, .88fr);
            gap: 14px;
            align-items: start;
        }

        .piso-card {
            overflow: hidden;
            border: 1px solid #e3e8ef;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 5px 22px rgba(15, 23, 42, .035);
        }

        .piso-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 11px 13px;
            border-bottom: 1px solid #edf0f4;
        }

        .piso-card-title {
            margin: 0;
            color: #1b2538;
            font-size: 13px;
            font-weight: 800;
        }

        .piso-card-subtitle {
            margin: 4px 0 0;
            color: #8791a2;
            font-size: 10px;
            line-height: 1.4;
        }

        .piso-count-chip {
            padding: 5px 9px;
            border: 1px solid #dbeafe;
            border-radius: 999px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .piso-boxes-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            padding: 11px;
        }

        .piso-box-card {
            min-width: 0;
            padding: 12px;
            border: 1px solid #e4e9f0;
            border-radius: 12px;
            background: linear-gradient(#fff, #fcfdff);
            transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease;
        }

        .piso-box-card:hover {
            border-color: #cfd8e5;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .05);
            transform: translateY(-1px);
        }

        .piso-box-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 9px;
        }

        .piso-box-name-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .piso-box-name {
            margin: 0;
            color: #1a2437;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.2;
        }

        .piso-rename-button {
            min-height: 23px;
            padding: 0 7px;
            border: 1px solid #dbe4ef;
            border-radius: 6px;
            background: #fff;
            color: #55708d;
            font: inherit;
            font-size: 9px;
            font-weight: 750;
            cursor: pointer;
        }

        .piso-rename-button:hover {
            border-color: #bfdbfe;
            background: #eff6ff;
            color: #2563eb;
        }

        .piso-box-location {
            margin-top: 6px;
            color: #97a0ad;
            font-size: 9px;
            line-height: 1.35;
        }

        .piso-status-wrap {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-shrink: 0;
        }

        .piso-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 800;
            white-space: nowrap;
        }

        .piso-status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .piso-status-active {
            color: #15803d;
            background: #ecfdf3;
        }

        .piso-status-inactive {
            color: #64748b;
            background: #f1f5f9;
        }

        .piso-status-select {
            width: 96px;
            height: 28px;
            padding: 0 24px 0 8px;
            border: 1px solid #dbe3ec;
            border-radius: 7px;
            background: #fff;
            color: #334155;
            font: inherit;
            font-size: 9px;
            font-weight: 700;
            outline: none;
            cursor: pointer;
        }

        .piso-earnings-label {
            margin-top: 11px;
            color: #738094;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .piso-earnings-amount {
            margin-top: 5px;
            color: #172033;
            font-size: 21px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -.025em;
        }

        .piso-earnings-breakdown {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
            color: #8a94a4;
            font-size: 9px;
        }

        .piso-earnings-breakdown strong {
            color: #374151;
        }

        .piso-record-state {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            margin-top: 9px;
            padding: 0 7px;
            border-radius: 6px;
            background: #f8fafc;
            color: #8791a2;
            font-size: 9px;
            font-weight: 650;
        }

        .piso-record-state.recorded {
            color: #166534;
            background: #f0fdf4;
        }

        .piso-box-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
            margin-top: 10px;
        }

        .piso-mini-button {
            min-height: 32px;
            padding: 0 8px;
            border-radius: 7px;
            font: inherit;
            font-size: 9px;
            font-weight: 800;
            cursor: pointer;
            transition: .15s ease;
        }

        .piso-mini-primary {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #2563eb;
        }

        .piso-mini-primary:hover {
            background: #dbeafe;
            border-color: #93c5fd;
        }

        .piso-mini-secondary {
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
        }

        .piso-mini-secondary:hover {
            background: #f8fafc;
            color: #1e293b;
        }

        .piso-mini-danger {
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #dc2626;
        }

        .piso-mini-danger:hover {
            background: #ffe4e6;
            border-color: #fda4af;
        }

        .piso-box-actions .full {
            grid-column: 1 / -1;
        }

        /* =========================================================
           RIGHT SIDE
        ========================================================= */
        .piso-side-stack {
            display: grid;
            gap: 15px;
        }

        .piso-status-list {
            padding: 10px 12px 12px;
        }

        .piso-status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 38px;
            margin-bottom: 6px;
            padding: 0 11px;
            border: 1px solid #e5eaf0;
            border-radius: 9px;
            background: #fcfdff;
        }

        .piso-status-row:last-child {
            margin-bottom: 0;
        }

        .piso-status-row-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #405067;
            font-size: 10px;
            font-weight: 650;
        }

        .piso-status-row-count {
            color: #1f2937;
            font-size: 11px;
            font-weight: 800;
        }

        .piso-collected-card {
            padding: 17px 16px 18px;
            background: linear-gradient(180deg, #f8fbff 0%, #f7fbff 100%);
        }

        .piso-collected-label {
            color: #667892;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .07em;
        }

        .piso-collected-value {
            margin-top: 9px;
            color: #2563eb;
            font-size: 25px;
            font-weight: 850;
            line-height: 1;
            letter-spacing: -.03em;
        }

        /* =========================================================
           MONTHLY RECORD TABLE
        ========================================================= */
        .piso-history-card {
            margin-top: 12px;
        }

        .piso-table-wrap {
            overflow-x: auto;
        }

        .piso-table {
            width: 100%;
            border-collapse: collapse;
        }

        .piso-table th {
            padding: 10px 14px;
            border-bottom: 1px solid #edf0f4;
            background: #fafbfc;
            color: #8791a2;
            font-size: 9px;
            font-weight: 800;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .07em;
            white-space: nowrap;
        }

        .piso-table td {
            padding: 11px 14px;
            border-bottom: 1px solid #f0f2f5;
            color: #4b5563;
            font-size: 10px;
            vertical-align: middle;
        }

        .piso-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .piso-table tbody tr:hover td {
            background: #fbfdff;
        }

        .piso-table-amount {
            color: #1f2937 !important;
            font-weight: 800;
            white-space: nowrap;
        }

        .piso-muted {
            color: #8d98a7;
        }

        .piso-table-actions {
            display: flex;
            justify-content: flex-end;
            gap: 6px;
            white-space: nowrap;
        }

        /* =========================================================
           MODALS / OVERLAY
        ========================================================= */
        .piso-overlay {
            position: fixed;
            inset: 0;
            z-index: 3500;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, .48);
            backdrop-filter: blur(3px);
        }

        .piso-overlay.show {
            display: flex;
        }

        .piso-overlay.closing {
            display: flex;
            opacity: 0;
            transition: opacity .2s ease;
        }

        .piso-modal {
            width: min(100%, 520px);
            max-height: min(88vh, 720px);
            overflow: hidden;
            border: 1px solid #e4e8ee;
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(15, 23, 42, .18);
            animation: pisoModalIn .18s ease-out;
        }

        .piso-overlay.closing .piso-modal {
            opacity: 0;
            transform: translateY(9px) scale(.985);
            transition: opacity .2s ease, transform .2s ease;
        }

        @keyframes pisoModalIn {
            from {
                opacity: 0;
                transform: translateY(8px) scale(.985);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .piso-modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            padding: 17px 18px;
            border-bottom: 1px solid #edf0f4;
        }

        .piso-modal-eyebrow {
            margin-bottom: 4px;
            color: #71809a;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .piso-modal-title {
            margin: 0;
            color: #172033;
            font-size: 16px;
            font-weight: 800;
        }

        .piso-modal-subtitle {
            margin: 4px 0 0;
            color: #8a94a4;
            font-size: 10px;
            line-height: 1.4;
        }

        .piso-modal-close {
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            border: 1px solid #e3e8ef;
            border-radius: 8px;
            background: #f8fafc;
            color: #64748b;
            font-size: 19px;
            cursor: pointer;
        }

        .piso-modal-close:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .piso-modal-body {
            padding: 18px;
            max-height: 58vh;
            overflow: auto;
        }

        .piso-form-group {
            margin-bottom: 14px;
        }

        .piso-form-label {
            display: block;
            margin-bottom: 6px;
            color: #364152;
            font-size: 10px;
            font-weight: 800;
        }

        .piso-form-control {
            width: 100%;
            min-height: 40px;
            padding: 8px 10px;
            border: 1px solid #d7dee7;
            border-radius: 8px;
            background: #fff;
            color: #172033;
            font: inherit;
            font-size: 12px;
            outline: none;
        }

        textarea.piso-form-control {
            min-height: 84px;
            resize: vertical;
        }

        .piso-form-control:focus {
            border-color: #93b4ff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
        }

        .piso-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .piso-error-box {
            display: none;
            margin-bottom: 14px;
            padding: 10px 12px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 11px;
            line-height: 1.45;
        }

        .piso-error-box.show {
            display: block;
            animation: pisoErrorIn .22s ease both;
        }

        @keyframes pisoErrorIn {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .piso-modal-foot {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            padding: 13px 18px;
            border-top: 1px solid #edf0f4;
            background: #fafbfc;
        }

        .piso-btn {
            min-height: 37px;
            padding: 0 13px;
            border-radius: 8px;
            font: inherit;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
        }

        .piso-btn-secondary {
            border: 1px solid #d7dee7;
            background: #fff;
            color: #465466;
        }

        .piso-btn-primary {
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #fff;
        }

        .piso-btn-danger {
            border: 1px solid #dc2626;
            background: #dc2626;
            color: #fff;
        }

        .piso-btn:disabled {
            opacity: .65;
            cursor: not-allowed;
        }

        /* =========================================================
           HISTORY MODAL
        ========================================================= */
        .piso-history-list {
            display: grid;
            gap: 8px;
        }

        .piso-history-item {
            padding: 11px 12px;
            border: 1px solid #e6ebf1;
            border-radius: 10px;
            background: #fcfdff;
        }

        .piso-history-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .piso-history-month {
            color: #334155;
            font-size: 11px;
            font-weight: 800;
        }

        .piso-history-amount {
            color: #2563eb;
            font-size: 12px;
            font-weight: 850;
            white-space: nowrap;
        }

        .piso-history-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 5px;
            color: #8994a4;
            font-size: 9px;
        }

        .piso-history-empty {
            padding: 26px 10px;
            text-align: center;
            color: #8994a4;
            font-size: 11px;
        }

        /* =========================================================
           TOAST
        ========================================================= */
        #toastContainer .toast-notification {
            animation: pisoToastIn .3s cubic-bezier(.2, .8, .2, 1) both;
        }

        @keyframes pisoToastIn {
            from {
                opacity: 0;
                transform: translateY(-8px) translateX(10px) scale(.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) translateX(0) scale(1);
            }
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */
        @media (max-width: 1150px) {
            .piso-layout {
                grid-template-columns: minmax(0, 1fr);
            }

            .piso-side-stack {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 900px) {
            .piso-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .piso-page-header {
                flex-direction: column;
            }

            .piso-header-actions {
                width: 100%;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 700px) {
            .piso-boxes-grid {
                grid-template-columns: 1fr;
            }

            .piso-side-stack {
                grid-template-columns: 1fr;
            }

            .piso-form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 540px) {
            .piso-kpi-grid {
                grid-template-columns: 1fr;
            }

            .piso-header-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .piso-add-button {
                width: 100%;
            }

            .piso-month-form {
                width: 100%;
            }

            .piso-month-input {
                flex: 1;
                width: auto;
            }

            .piso-title {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="main">

        <?php require_once $basePath . 'includes/topbar.php'; ?>

        <main class="content">

            <div class="pisowifi-page">

                <!-- HEADER -->
                <div class="piso-page-header">

                    <div>
                        <h1 class="piso-title">PisoWiFi</h1>
                        <p class="piso-subtitle">
                            Manage your PisoWiFi machines and record the money collected from each machine every month.
                        </p>
                    </div>

                    <div class="piso-header-actions">
                        <button type="button" class="piso-add-button" id="openAddModal">
                            + Add PisoWiFi
                        </button>

                        <form method="get" class="piso-month-form">
                            <span class="piso-month-label">Month</span>
                            <input
                                type="month"
                                name="month"
                                value="<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>"
                                class="piso-month-input"
                                aria-label="Select month">
                            <button type="submit" class="piso-view-button">View</button>
                        </form>
                    </div>

                </div>

                <?php if ($setupError !== ''): ?>
                    <div class="piso-error-box show"><?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <!-- KPI -->
                <div class="piso-kpi-grid">

                    <div class="piso-kpi kpi-blue">
                        <div class="piso-kpi-label">Monthly Gross</div>
                        <div class="piso-kpi-value">₱<?= number_format($monthlyGross, 2) ?></div>
                        <div class="piso-kpi-note">Total recorded collections for <?= htmlspecialchars($selectedMonthLabel, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>

                    <div class="piso-kpi kpi-green">
                        <div class="piso-kpi-label">Active Boxes</div>
                        <div class="piso-kpi-value"><?= number_format($activeBoxes) ?>/<?= number_format($totalBoxes) ?></div>
                        <div class="piso-kpi-note">PisoWiFi boxes currently marked active</div>
                    </div>

                    <div class="piso-kpi kpi-orange">
                        <div class="piso-kpi-label">Pending Records</div>
                        <div class="piso-kpi-value"><?= number_format($pendingBoxes) ?></div>
                        <div class="piso-kpi-note">Boxes without a <?= htmlspecialchars($selectedMonthLabel, ENT_QUOTES, 'UTF-8') ?> entry</div>
                    </div>

                    <div class="piso-kpi kpi-purple">
                        <div class="piso-kpi-label">All-Time Gross</div>
                        <div class="piso-kpi-value">₱<?= number_format($allTimeGross, 2) ?></div>
                        <div class="piso-kpi-note">All recorded PisoWiFi collections</div>
                    </div>

                </div>

                <!-- MAIN -->
                <div class="piso-layout">

                    <section class="piso-card">

                        <div class="piso-card-header">
                            <div>
                                <h2 class="piso-card-title">PisoWiFi Boxes</h2>
                                <p class="piso-card-subtitle">
                                    <?= htmlspecialchars($selectedMonthLabel, ENT_QUOTES, 'UTF-8') ?> earnings and machine condition.
                                </p>
                            </div>
                            <span class="piso-count-chip">
                                <?= number_format($totalBoxes) ?> box<?= $totalBoxes === 1 ? '' : 'es' ?>
                            </span>
                        </div>

                        <?php if ($boxes): ?>

                            <div class="piso-boxes-grid">

                                <?php foreach ($boxes as $box): ?>

                                    <?php
                                    $statusLabel = $box['status'] === 'active' ? 'Active' : 'Inactive';
                                    $statusClass = $box['status'] === 'active'
                                        ? 'piso-status-active'
                                        : 'piso-status-inactive';
                                    $hasRecord = !empty($box['earning_id']);
                                    ?>

                                    <article class="piso-box-card" data-row-id="<?= (int)$box['id'] ?>">

                                        <div class="piso-box-top">

                                            <div>
                                                <div class="piso-box-name-row">
                                                    <h3 class="piso-box-name" data-box-name="<?= (int)$box['id'] ?>">
                                                        <?= htmlspecialchars($box['box_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </h3>

                                                    <button
                                                        type="button"
                                                        class="piso-rename-button rename-box"
                                                        data-id="<?= (int)$box['id'] ?>"
                                                        data-name="<?= htmlspecialchars($box['box_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                        Rename
                                                    </button>

                                                    <a
                                                        href="edit.php?id=<?= (int)$box['id'] ?>"
                                                        class="piso-rename-button"
                                                        style="text-decoration:none;">
                                                        Edit
                                                    </a>
                                                </div>

                                                <div class="piso-box-location">
                                                    <?= htmlspecialchars($box['location'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            </div>

                                            <div class="piso-status-wrap">

                                                <span
                                                    class="piso-status-badge <?= $statusClass ?>"
                                                    data-status-badge="<?= (int)$box['id'] ?>">
                                                    <span class="piso-status-dot"></span>
                                                    <?= $statusLabel ?>
                                                </span>

                                                <select
                                                    class="piso-status-select status-select"
                                                    data-id="<?= (int)$box['id'] ?>"
                                                    data-previous="<?= htmlspecialchars($box['status'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <option value="active" <?= $box['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= $box['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                </select>

                                            </div>

                                        </div>

                                        <div class="piso-earnings-label">
                                            <?= htmlspecialchars($selectedMonthLabel, ENT_QUOTES, 'UTF-8') ?> Earnings
                                        </div>

                                        <div class="piso-earnings-amount">
                                            ₱<?= number_format($box['gross_earnings'], 2) ?>
                                        </div>

                                        <div class="piso-earnings-breakdown">
                                            <span>Expenses <strong>₱<?= number_format($box['expenses'], 2) ?></strong></span>
                                            <span>Net <strong>₱<?= number_format($box['net_earnings'], 2) ?></strong></span>
                                        </div>

                                        <div class="piso-record-state <?= $hasRecord ? 'recorded' : '' ?>" data-record-state="<?= (int)$box['id'] ?>">
                                            <?= $hasRecord ? 'Earnings recorded' : 'No earnings recorded yet' ?>
                                        </div>

                                        <div class="piso-box-actions">

                                            <button
                                                type="button"
                                                class="piso-mini-button piso-mini-primary open-earning-modal"
                                                data-id="<?= (int)$box['id'] ?>"
                                                data-name="<?= htmlspecialchars($box['box_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-month="<?= (int)$selectedMonthNumber ?>"
                                                data-year="<?= (int)$selectedYear ?>"
                                                data-gross="<?= number_format($box['gross_earnings'], 2, '.', '') ?>"
                                                data-expenses="<?= number_format($box['expenses'], 2, '.', '') ?>"
                                                data-notes="<?= htmlspecialchars($box['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= $hasRecord ? 'Edit Earnings' : 'Record Earnings' ?>
                                            </button>

                                            <button
                                                type="button"
                                                class="piso-mini-button piso-mini-secondary open-history-modal"
                                                data-id="<?= (int)$box['id'] ?>"
                                                data-name="<?= htmlspecialchars($box['box_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                History
                                            </button>

                                            <button
                                                type="button"
                                                class="piso-mini-button piso-mini-danger delete-box"
                                                data-id="<?= (int)$box['id'] ?>"
                                                data-name="<?= htmlspecialchars($box['box_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                style="grid-column:1 / -1;">
                                                Delete
                                            </button>

                                        </div>

                                    </article>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="piso-history-empty">
                                No PisoWiFi boxes are available yet.
                                <div style="margin-top:13px;">
                                    <button type="button" class="piso-add-button" id="openAddEmpty">+ Add PisoWiFi</button>
                                </div>
                            </div>

                        <?php endif; ?>

                    </section>

                    <aside class="piso-side-stack">

                        <section class="piso-card">

                            <div class="piso-card-header">
                                <div>
                                    <h2 class="piso-card-title">Box Status</h2>
                                    <p class="piso-card-subtitle">Current machine condition.</p>
                                </div>
                            </div>

                            <div class="piso-status-list">

                                <div class="piso-status-row">
                                    <div class="piso-status-row-label">
                                        <span class="piso-status-dot" style="color:#16a34a;"></span>
                                        Active
                                    </div>
                                    <div class="piso-status-row-count"><?= number_format($activeBoxes) ?></div>
                                </div>

                                <div class="piso-status-row">
                                    <div class="piso-status-row-label">
                                        <span class="piso-status-dot" style="color:#64748b;"></span>
                                        Inactive
                                    </div>
                                    <div class="piso-status-row-count"><?= number_format($inactiveBoxes) ?></div>
                                </div>

                                <div class="piso-status-row">
                                    <div class="piso-status-row-label">
                                        <span class="piso-status-dot" style="color:#d97706;"></span>
                                        Pending Earnings
                                    </div>
                                    <div class="piso-status-row-count"><?= number_format($pendingBoxes) ?></div>
                                </div>

                            </div>

                        </section>

                        <section class="piso-card">

                            <div class="piso-collected-card">
                                <div class="piso-collected-label">Collected in <?= htmlspecialchars($selectedMonthLabel, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="piso-collected-value">₱<?= number_format($monthlyGross, 2) ?></div>
                                <div class="piso-kpi-note">
                                    Record the amount when you collect the money from each PisoWiFi machine.
                                </div>
                            </div>

                        </section>

                    </aside>

                </div>

                <!-- MONTHLY RECORDS -->
                <section class="piso-card piso-history-card">

                    <div class="piso-card-header">
                        <div>
                            <h2 class="piso-card-title"><?= htmlspecialchars($selectedMonthLabel, ENT_QUOTES, 'UTF-8') ?> Earnings Records</h2>
                            <p class="piso-card-subtitle">Recorded monthly collections for the selected period.</p>
                        </div>
                    </div>

                    <div class="piso-table-wrap">

                        <?php if ($monthlyRecords): ?>

                            <table class="piso-table">

                                <thead>
                                    <tr>
                                        <th>PisoWiFi</th>
                                        <th>Gross</th>
                                        <th>Expenses</th>
                                        <th>Net</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($monthlyRecords as $row): ?>

                                        <tr data-history-row="<?= (int)$row['id'] ?>">

                                            <td>
                                                <strong style="color:#263246;">
                                                    <?= htmlspecialchars($row['box_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </strong>
                                            </td>

                                            <td class="piso-table-amount">
                                                ₱<?= number_format((float)$row['gross_earnings'], 2) ?>
                                            </td>

                                            <td>
                                                ₱<?= number_format((float)$row['expenses'], 2) ?>
                                            </td>

                                            <td>
                                                <strong style="color:#15803d;">
                                                    ₱<?= number_format((float)$row['net_earnings'], 2) ?>
                                                </strong>
                                            </td>

                                            <td class="piso-muted">
                                                <?= $row['notes'] !== null && $row['notes'] !== ''
                                                    ? htmlspecialchars($row['notes'], ENT_QUOTES, 'UTF-8')
                                                    : '—' ?>
                                            </td>

                                            <td>
                                                <div class="piso-table-actions">
                                                    <button
                                                        type="button"
                                                        class="piso-mini-button piso-mini-secondary open-earning-modal"
                                                        data-id="<?= (int)$row['pisowifi_id'] ?>"
                                                        data-name="<?= htmlspecialchars($row['box_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-month="<?= (int)$selectedMonthNumber ?>"
                                                        data-year="<?= (int)$selectedYear ?>"
                                                        data-gross="<?= number_format((float)$row['gross_earnings'], 2, '.', '') ?>"
                                                        data-expenses="<?= number_format((float)$row['expenses'], 2, '.', '') ?>"
                                                        data-notes="<?= htmlspecialchars($row['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                        Edit
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="piso-mini-button piso-mini-danger delete-earning"
                                                        data-id="<?= (int)$row['id'] ?>"
                                                        data-name="<?= htmlspecialchars($row['box_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-month="<?= htmlspecialchars($selectedMonthLabel, ENT_QUOTES, 'UTF-8') ?>">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        <?php else: ?>

                            <div class="piso-history-empty">
                                No earnings were recorded for <?= htmlspecialchars($selectedMonthLabel, ENT_QUOTES, 'UTF-8') ?> yet.
                            </div>

                        <?php endif; ?>

                    </div>

                </section>

            </div>

        </main>

    </div>

    <!-- =========================================================
     ADD PISOWIFI MODAL
========================================================= -->
    <div class="piso-overlay" id="addModal" aria-hidden="true">
        <section class="piso-modal" role="dialog" aria-modal="true" aria-labelledby="addModalTitle">

            <div class="piso-modal-head">
                <div>
                    <div class="piso-modal-eyebrow">PisoWiFi</div>
                    <h2 class="piso-modal-title" id="addModalTitle">Add PisoWiFi</h2>
                    <p class="piso-modal-subtitle">Add a new machine to your PisoWiFi records.</p>
                </div>
                <button type="button" class="piso-modal-close close-modal" data-target="addModal">×</button>
            </div>

            <form id="addForm">

                <div class="piso-modal-body">

                    <div class="piso-error-box" id="addError"></div>

                    <div class="piso-form-group">
                        <label class="piso-form-label" for="addBoxName">Box Name</label>
                        <input class="piso-form-control" type="text" id="addBoxName" name="box_name" maxlength="100" placeholder="e.g. PisoWiFi 1" required>
                    </div>

                    <div class="piso-form-group">
                        <label class="piso-form-label" for="addLocation">Location</label>
                        <input class="piso-form-control" type="text" id="addLocation" name="location" maxlength="255" placeholder="e.g. Store Front" required>
                    </div>

                    <div class="piso-form-group">
                        <label class="piso-form-label" for="addDescription">Description</label>
                        <textarea class="piso-form-control" id="addDescription" name="description" maxlength="500" placeholder="Optional description"></textarea>
                    </div>

                    <div class="piso-form-group">
                        <label class="piso-form-label" for="addStatus">Status</label>
                        <select class="piso-form-control" id="addStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                </div>

                <div class="piso-modal-foot">
                    <button type="button" class="piso-btn piso-btn-secondary close-modal" data-target="addModal">Cancel</button>
                    <button type="submit" class="piso-btn piso-btn-primary" id="saveAddButton">Add PisoWiFi</button>
                </div>

            </form>
        </section>
    </div>

    <!-- =========================================================
     RENAME MODAL
========================================================= -->
    <div class="piso-overlay" id="renameModal" aria-hidden="true">
        <section class="piso-modal" role="dialog" aria-modal="true" aria-labelledby="renameModalTitle">

            <div class="piso-modal-head">
                <div>
                    <div class="piso-modal-eyebrow">PisoWiFi</div>
                    <h2 class="piso-modal-title" id="renameModalTitle">Rename PisoWiFi</h2>
                    <p class="piso-modal-subtitle">Change the machine name without affecting its earning history.</p>
                </div>
                <button type="button" class="piso-modal-close close-modal" data-target="renameModal">×</button>
            </div>

            <form id="renameForm">

                <div class="piso-modal-body">

                    <div class="piso-error-box" id="renameError"></div>

                    <input type="hidden" id="renameId" name="id">

                    <div class="piso-form-group">
                        <label class="piso-form-label" for="renameName">PisoWiFi Name</label>
                        <input class="piso-form-control" type="text" id="renameName" name="box_name" maxlength="100" required>
                    </div>

                </div>

                <div class="piso-modal-foot">
                    <button type="button" class="piso-btn piso-btn-secondary close-modal" data-target="renameModal">Cancel</button>
                    <button type="submit" class="piso-btn piso-btn-primary" id="saveRenameButton">Save Name</button>
                </div>

            </form>
        </section>
    </div>

    <!-- =========================================================
     EARNINGS MODAL
========================================================= -->
    <div class="piso-overlay" id="earningModal" aria-hidden="true">
        <section class="piso-modal" role="dialog" aria-modal="true" aria-labelledby="earningModalTitle">

            <div class="piso-modal-head">
                <div>
                    <div class="piso-modal-eyebrow">Monthly Collection</div>
                    <h2 class="piso-modal-title" id="earningModalTitle">Record Earnings</h2>
                    <p class="piso-modal-subtitle" id="earningModalSubtitle">Record the money collected from this machine.</p>
                </div>
                <button type="button" class="piso-modal-close close-modal" data-target="earningModal">×</button>
            </div>

            <form id="earningForm">

                <div class="piso-modal-body">

                    <div class="piso-error-box" id="earningError"></div>

                    <input type="hidden" id="earningPisoId" name="pisowifi_id">
                    <input type="hidden" id="earningMonth" name="earning_month">
                    <input type="hidden" id="earningYear" name="earning_year">

                    <div class="piso-form-group">
                        <label class="piso-form-label">PisoWiFi</label>
                        <input class="piso-form-control" type="text" id="earningBoxName" readonly>
                    </div>

                    <div class="piso-form-row">
                        <div class="piso-form-group">
                            <label class="piso-form-label">Month</label>
                            <input class="piso-form-control" type="text" id="earningMonthLabel" readonly>
                        </div>

                        <div class="piso-form-group">
                            <label class="piso-form-label" for="earningGross">Gross Earnings</label>
                            <input class="piso-form-control" type="number" step="0.01" min="0" id="earningGross" name="gross_earnings" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="piso-form-row">
                        <div class="piso-form-group">
                            <label class="piso-form-label" for="earningExpenses">Expenses</label>
                            <input class="piso-form-control" type="number" step="0.01" min="0" id="earningExpenses" name="expenses" placeholder="0.00" required>
                        </div>

                        <div class="piso-form-group">
                            <label class="piso-form-label" for="earningNotes">Notes</label>
                            <input class="piso-form-control" type="text" id="earningNotes" name="notes" maxlength="500" placeholder="Optional">
                        </div>
                    </div>

                </div>

                <div class="piso-modal-foot">
                    <button type="button" class="piso-btn piso-btn-secondary close-modal" data-target="earningModal">Cancel</button>
                    <button type="submit" class="piso-btn piso-btn-primary" id="saveEarningButton">Save Earnings</button>
                </div>

            </form>
        </section>
    </div>

    <!-- =========================================================
     HISTORY MODAL
========================================================= -->
    <div class="piso-overlay" id="historyModal" aria-hidden="true">
        <section class="piso-modal" role="dialog" aria-modal="true" aria-labelledby="historyModalTitle">

            <div class="piso-modal-head">
                <div>
                    <div class="piso-modal-eyebrow">PisoWiFi</div>
                    <h2 class="piso-modal-title" id="historyModalTitle">Earnings History</h2>
                    <p class="piso-modal-subtitle" id="historyModalSubtitle">All recorded monthly earnings for this machine.</p>
                </div>
                <button type="button" class="piso-modal-close close-modal" data-target="historyModal">×</button>
            </div>

            <div class="piso-modal-body">
                <div class="piso-history-list" id="historyList"></div>
            </div>

            <div class="piso-modal-foot">
                <button type="button" class="piso-btn piso-btn-secondary close-modal" data-target="historyModal">Close</button>
            </div>

        </section>
    </div>

    <!-- =========================================================
     DELETE CONFIRMATION MODAL
========================================================= -->
    <div class="piso-overlay" id="confirmModal" aria-hidden="true">
        <section class="piso-modal" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">

            <div class="piso-modal-head">
                <div>
                    <div class="piso-modal-eyebrow" style="color:#dc2626;">Confirmation</div>
                    <h2 class="piso-modal-title" id="confirmModalTitle">Delete PisoWiFi?</h2>
                    <p class="piso-modal-subtitle" id="confirmMessage">
                        This action permanently removes the selected record.
                    </p>
                </div>
                <button type="button" class="piso-modal-close close-modal" data-target="confirmModal">×</button>
            </div>

            <div class="piso-modal-body">
                <div style="padding:12px 13px;border-radius:9px;background:#fff7f7;border:1px solid #fee2e2;color:#991b1b;font-size:11px;line-height:1.5;">
                    <strong id="confirmName"></strong>
                </div>
            </div>

            <div class="piso-modal-foot">
                <button type="button" class="piso-btn piso-btn-secondary close-modal" data-target="confirmModal">Cancel</button>
                <button type="button" class="piso-btn piso-btn-danger" id="confirmDeleteButton">Delete</button>
            </div>

        </section>
    </div>

    <script>
        (function() {
            const monthLabel = <?= json_encode($selectedMonthLabel) ?>;

            function showModal(id) {
                const modal = document.getElementById(id);
                if (!modal) return;
                modal.classList.remove('closing');
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function hideModal(id, animated = true) {
                const modal = document.getElementById(id);
                if (!modal) return;

                if (animated && modal.classList.contains('show')) {
                    modal.classList.add('closing');
                    setTimeout(function() {
                        modal.classList.remove('show', 'closing');
                        modal.setAttribute('aria-hidden', 'true');
                        if (!document.querySelector('.piso-overlay.show')) {
                            document.body.style.overflow = '';
                        }
                    }, 200);
                } else {
                    modal.classList.remove('show', 'closing');
                    modal.setAttribute('aria-hidden', 'true');
                    if (!document.querySelector('.piso-overlay.show')) {
                        document.body.style.overflow = '';
                    }
                }
            }

            function closeAllModals() {
                document.querySelectorAll('.piso-overlay.show').forEach(function(modal) {
                    modal.classList.remove('show', 'closing');
                    modal.setAttribute('aria-hidden', 'true');
                });
                document.body.style.overflow = '';
            }

            document.querySelectorAll('.close-modal').forEach(function(button) {
                button.addEventListener('click', function() {
                    hideModal(button.dataset.target);
                });
            });

            document.querySelectorAll('.piso-overlay').forEach(function(overlay) {
                overlay.addEventListener('click', function(event) {
                    if (event.target === overlay) {
                        hideModal(overlay.id);
                    }
                });
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const open = document.querySelector('.piso-overlay.show');
                    if (open) hideModal(open.id);
                }
            });

            /* =========================================================
               ADD
            ========================================================= */
            function resetAddForm() {
                const form = document.getElementById('addForm');
                form.reset();
                document.getElementById('addStatus').value = 'active';
                hideError('addError');
            }

            function hideError(id) {
                const box = document.getElementById(id);
                if (!box) return;
                box.textContent = '';
                box.classList.remove('show');
            }

            function showError(id, message) {
                const box = document.getElementById(id);
                if (!box) return;
                box.textContent = message;
                box.classList.remove('show');
                void box.offsetWidth;
                box.classList.add('show');
            }

            function notify(type, title, message) {
                if (typeof window.showToast === 'function') {
                    window.showToast(type, title, message, 3600);
                }
            }

            document.getElementById('openAddModal')?.addEventListener('click', function() {
                resetAddForm();
                showModal('addModal');
                setTimeout(() => document.getElementById('addBoxName')?.focus(), 80);
            });

            document.getElementById('openAddEmpty')?.addEventListener('click', function() {
                resetAddForm();
                showModal('addModal');
                setTimeout(() => document.getElementById('addBoxName')?.focus(), 80);
            });

            document.getElementById('addForm')?.addEventListener('submit', async function(event) {
                event.preventDefault();

                const button = document.getElementById('saveAddButton');
                hideError('addError');
                button.disabled = true;
                button.textContent = 'Saving...';

                try {
                    const response = await fetch('save.php', {
                        method: 'POST',
                        body: new FormData(this),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Unable to save the PisoWiFi box.');
                    }

                    hideModal('addModal', true);
                    notify('success', 'PisoWiFi Added', result.message || 'PisoWiFi box added successfully.');
                    setTimeout(() => window.location.reload(), 650);

                } catch (error) {
                    showError('addError', error.message || 'Something went wrong while saving.');
                    button.disabled = false;
                    button.textContent = 'Add PisoWiFi';
                }
            });

            /* =========================================================
               RENAME
            ========================================================= */
            document.querySelectorAll('.rename-box').forEach(function(button) {
                button.addEventListener('click', function() {
                    document.getElementById('renameId').value = button.dataset.id;
                    document.getElementById('renameName').value = button.dataset.name || '';
                    hideError('renameError');
                    showModal('renameModal');
                    setTimeout(() => document.getElementById('renameName')?.focus(), 80);
                });
            });

            document.getElementById('renameForm')?.addEventListener('submit', async function(event) {
                event.preventDefault();

                const button = document.getElementById('saveRenameButton');
                hideError('renameError');
                button.disabled = true;
                button.textContent = 'Saving...';

                try {
                    const response = await fetch('rename.php', {
                        method: 'POST',
                        body: new FormData(this),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Unable to rename the PisoWiFi box.');
                    }

                    hideModal('renameModal', true);
                    notify('success', 'PisoWiFi Renamed', result.message || 'PisoWiFi box renamed successfully.');
                    setTimeout(() => window.location.reload(), 650);

                } catch (error) {
                    showError('renameError', error.message || 'Something went wrong while renaming.');
                } finally {
                    button.disabled = false;
                    button.textContent = 'Save Name';
                }
            });

            /* =========================================================
               STATUS
            ========================================================= */
            document.querySelectorAll('.status-select').forEach(function(select) {
                select.addEventListener('change', async function() {
                    const previous = select.dataset.previous || select.value;
                    const id = select.dataset.id;

                    select.disabled = true;

                    const formData = new FormData();
                    formData.append('id', id);
                    formData.append('status', select.value);

                    try {
                        const response = await fetch('status.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const result = await response.json();

                        if (!response.ok || !result.success) {
                            throw new Error(result.message || 'Unable to update status.');
                        }

                        select.dataset.previous = result.status;

                        const badge = document.querySelector('[data-status-badge="' + id + '"]');
                        if (badge) {
                            const isActive = result.status === 'active';
                            badge.classList.toggle('piso-status-active', isActive);
                            badge.classList.toggle('piso-status-inactive', !isActive);
                            badge.innerHTML = '<span class="piso-status-dot"></span>' + (isActive ? 'Active' : 'Inactive');
                        }

                        notify('success', 'Status Updated', result.message || 'PisoWiFi status updated.');
                        setTimeout(() => window.location.reload(), 600);

                    } catch (error) {
                        select.value = previous;
                        notify('error', 'Status Update Failed', error.message || 'Unable to update status.');
                    } finally {
                        select.disabled = false;
                    }
                });
            });

            /* =========================================================
               EARNINGS
            ========================================================= */
            function openEarning(button) {
                document.getElementById('earningPisoId').value = button.dataset.id;
                document.getElementById('earningMonth').value = button.dataset.month;
                document.getElementById('earningYear').value = button.dataset.year;
                document.getElementById('earningBoxName').value = button.dataset.name || '';
                document.getElementById('earningMonthLabel').value = monthLabel;
                document.getElementById('earningGross').value = button.dataset.gross || '0.00';
                document.getElementById('earningExpenses').value = button.dataset.expenses || '0.00';
                document.getElementById('earningNotes').value = button.dataset.notes || '';
                document.getElementById('earningModalTitle').textContent =
                    (parseFloat(button.dataset.gross || '0') > 0 || button.closest('.piso-box-card')?.querySelector('.piso-record-state.recorded')) ?
                    'Edit Earnings' :
                    'Record Earnings';
                document.getElementById('earningModalSubtitle').textContent =
                    'Record the money collected from ' + (button.dataset.name || 'this machine') + '.';
                hideError('earningError');
                showModal('earningModal');
                setTimeout(() => document.getElementById('earningGross')?.focus(), 80);
            }

            document.querySelectorAll('.open-earning-modal').forEach(function(button) {
                button.addEventListener('click', function() {
                    openEarning(button);
                });
            });

            document.getElementById('earningForm')?.addEventListener('submit', async function(event) {
                event.preventDefault();

                const button = document.getElementById('saveEarningButton');
                hideError('earningError');
                button.disabled = true;
                button.textContent = 'Saving...';

                try {
                    const response = await fetch('save_earning.php', {
                        method: 'POST',
                        body: new FormData(this),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Unable to save earnings.');
                    }

                    hideModal('earningModal', true);
                    notify('success', 'Earnings Saved', result.message || 'Monthly earnings saved successfully.');
                    setTimeout(() => window.location.reload(), 650);

                } catch (error) {
                    showError('earningError', error.message || 'Something went wrong while saving.');
                    button.disabled = false;
                    button.textContent = 'Save Earnings';
                }
            });

            /* =========================================================
               HISTORY
            ========================================================= */
            document.querySelectorAll('.open-history-modal').forEach(function(button) {
                button.addEventListener('click', async function() {

                    const list = document.getElementById('historyList');
                    const subtitle = document.getElementById('historyModalSubtitle');

                    subtitle.textContent = 'Loading monthly earnings for ' + (button.dataset.name || 'this machine') + '...';
                    list.innerHTML = '<div class="piso-history-empty">Loading history...</div>';
                    showModal('historyModal');

                    try {
                        const response = await fetch('history.php?id=' + encodeURIComponent(button.dataset.id), {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const result = await response.json();

                        if (!response.ok || !result.success) {
                            throw new Error(result.message || 'Unable to load earnings history.');
                        }

                        subtitle.textContent = 'All recorded monthly earnings for ' + result.box_name + '.';

                        if (!Array.isArray(result.records) || result.records.length === 0) {
                            list.innerHTML = '<div class="piso-history-empty">No earnings history recorded yet.</div>';
                            return;
                        }

                        list.innerHTML = result.records.map(function(row) {
                            const month = new Date(Number(row.earning_year), Number(row.earning_month) - 1, 1)
                                .toLocaleDateString('en-US', {
                                    month: 'long',
                                    year: 'numeric'
                                });

                            const gross = Number(row.gross_earnings || 0).toLocaleString('en-PH', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                            const expenses = Number(row.expenses || 0).toLocaleString('en-PH', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                            const net = (Number(row.gross_earnings || 0) - Number(row.expenses || 0))
                                .toLocaleString('en-PH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });

                            return `
                        <div class="piso-history-item">
                            <div class="piso-history-top">
                                <div class="piso-history-month">${escapeHtml(month)}</div>
                                <div class="piso-history-amount">₱${gross}</div>
                            </div>
                            <div class="piso-history-meta">
                                <span>Expenses ₱${expenses}</span>
                                <span>Net ₱${net}</span>
                            </div>
                        </div>
                    `;
                        }).join('');

                    } catch (error) {
                        list.innerHTML = '<div class="piso-history-empty">' + escapeHtml(error.message || 'Unable to load history.') + '</div>';
                    }
                });
            });

            /* =========================================================
               DELETE
            ========================================================= */
            let pendingDelete = null;

            function openConfirm(type, id, name, month) {
                pendingDelete = {
                    type,
                    id
                };
                document.getElementById('confirmName').textContent = name || 'Selected record';
                document.getElementById('confirmModalTitle').textContent =
                    type === 'box' ? 'Delete PisoWiFi?' : 'Delete Earnings Record?';

                document.getElementById('confirmMessage').textContent =
                    type === 'box' ?
                    'This permanently removes the machine and all of its earnings history.' :
                    'This permanently removes the selected monthly earnings record.';

                if (month) {
                    document.getElementById('confirmMessage').textContent += ' Period: ' + month + '.';
                }

                showModal('confirmModal');
            }

            document.querySelectorAll('.delete-box').forEach(function(button) {
                button.addEventListener('click', function() {
                    openConfirm('box', button.dataset.id, button.dataset.name, '');
                });
            });

            document.querySelectorAll('.delete-earning').forEach(function(button) {
                button.addEventListener('click', function() {
                    openConfirm('earning', button.dataset.id, button.dataset.name, button.dataset.month);
                });
            });

            document.getElementById('confirmDeleteButton')?.addEventListener('click', async function() {
                if (!pendingDelete) return;

                const button = this;
                button.disabled = true;
                button.textContent = 'Deleting...';

                const formData = new FormData();
                formData.append('id', pendingDelete.id);

                const endpoint = pendingDelete.type === 'box' ? 'delete.php' : 'delete_earning.php';

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Unable to delete the selected record.');
                    }

                    hideModal('confirmModal', true);
                    notify('success',
                        pendingDelete.type === 'box' ? 'PisoWiFi Deleted' : 'Earnings Deleted',
                        result.message || 'Deleted successfully.'
                    );

                    setTimeout(() => window.location.reload(), 650);

                } catch (error) {
                    notify('error', 'Delete Failed', error.message || 'Unable to delete the selected record.');
                    button.disabled = false;
                    button.textContent = 'Delete';
                }
            });

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }
        })();
    </script>

    <script src="<?= $basePath ?>assets/js/admin.js"></script>

</body>

</html>
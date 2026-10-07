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
$activeMenu = 'computer_shop';
$currentSection = 'computer_shop';

/*
|--------------------------------------------------------------------------
| Philippine Time
|--------------------------------------------------------------------------
*/
date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| Page State
|--------------------------------------------------------------------------
*/
$selectedMonth = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$selectedMonthDate = $selectedMonth . '-01';
$selectedMonthLabel = date('F Y', strtotime($selectedMonthDate));

/*
|--------------------------------------------------------------------------
| Database Setup
|--------------------------------------------------------------------------
| The module creates its own tables and seeds PC 01 - PC 06 automatically.
| No manual phpMyAdmin changes are required.
|--------------------------------------------------------------------------
*/

$setupError = '';

try {

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pisonet_units (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            unit_name VARCHAR(50) NOT NULL,
            status ENUM('working','maintenance','offline') NOT NULL DEFAULT 'working',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pisonet_unit_name (unit_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pisonet_harvests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            unit_id INT UNSIGNED NOT NULL,
            harvest_month DATE NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            remarks VARCHAR(500) NULL,
            user_id INT UNSIGNED NULL,
            harvested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pisonet_unit_month (unit_id, harvest_month),
            KEY idx_pisonet_harvest_month (harvest_month),
            KEY idx_pisonet_harvest_user (user_id),
            CONSTRAINT fk_pisonet_harvest_unit
                FOREIGN KEY (unit_id) REFERENCES pisonet_units(id)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $seedStmt = $pdo->prepare("INSERT IGNORE INTO pisonet_units (unit_name, status) VALUES (?, 'working')");

    for ($i = 1; $i <= 6; $i++) {
        $seedStmt->execute([sprintf('PC %02d', $i)]);
    }
} catch (PDOException $e) {

    $setupError = 'The Pisonet tables could not be prepared. Please check your database connection.';
}

/*
|--------------------------------------------------------------------------
| Load Units / Harvests
|--------------------------------------------------------------------------
*/
$units = [];
$harvestRows = [];
$recentHarvests = [];
$totalMonthHarvest = 0.00;
$harvestedUnitCount = 0;
$workingUnitCount = 0;
$maintenanceUnitCount = 0;
$offlineUnitCount = 0;
$allTimeHarvest = 0.00;
$totalUnitCount = 0;
$nextUnitName = 'PC 01';

if ($setupError === '') {

    try {

        $unitStmt = $pdo->prepare("
            SELECT
                u.id,
                u.unit_name,
                u.status,
                COALESCE(h.amount, 0) AS month_harvest,
                h.id AS harvest_id,
                h.remarks AS harvest_remarks,
                h.harvested_at
            FROM pisonet_units u
            LEFT JOIN pisonet_harvests h
                ON h.unit_id = u.id
               AND h.harvest_month = ?
            ORDER BY u.id ASC
        ");

        $unitStmt->execute([$selectedMonthDate]);
        $units = $unitStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($units as &$unit) {
            $unit['month_harvest'] = (float)$unit['month_harvest'];
            $totalMonthHarvest += $unit['month_harvest'];

            if ($unit['harvest_id']) {
                $harvestedUnitCount++;
            }

            switch ($unit['status']) {
                case 'working':
                    $workingUnitCount++;
                    break;
                case 'maintenance':
                    $maintenanceUnitCount++;
                    break;
                case 'offline':
                    $offlineUnitCount++;
                    break;
            }
        }
        unset($unit);

        $totalUnitCount = count($units);
        $nextUnitNumber = $totalUnitCount + 1;
        $nextUnitName = 'PC ' . str_pad((string)$nextUnitNumber, 2, '0', STR_PAD_LEFT);

        $historyStmt = $pdo->prepare("
            SELECT
                h.id,
                h.unit_id,
                h.harvest_month,
                h.amount,
                h.remarks,
                h.harvested_at,
                u.unit_name,
                COALESCE(NULLIF(us.full_name, ''), 'System User') AS recorded_by
            FROM pisonet_harvests h
            INNER JOIN pisonet_units u
                ON u.id = h.unit_id
            LEFT JOIN users us
                ON us.id = h.user_id
            WHERE h.harvest_month = ?
            ORDER BY h.harvested_at DESC, u.id ASC
        ");

        $historyStmt->execute([$selectedMonthDate]);
        $harvestRows = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        $recentStmt = $pdo->query("
            SELECT
                h.id,
                h.unit_id,
                h.harvest_month,
                h.amount,
                h.harvested_at,
                u.unit_name,
                COALESCE(NULLIF(us.full_name, ''), 'System User') AS recorded_by
            FROM pisonet_harvests h
            INNER JOIN pisonet_units u
                ON u.id = h.unit_id
            LEFT JOIN users us
                ON us.id = h.user_id
            ORDER BY h.harvest_month DESC, h.harvested_at DESC
            LIMIT 12
        ");

        $recentHarvests = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        $allTimeStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM pisonet_harvests");
        $allTimeHarvest = (float)$allTimeStmt->fetchColumn();
    } catch (PDOException $e) {

        $setupError = 'The Pisonet data could not be loaded. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| Shared Header Data
|--------------------------------------------------------------------------
*/
$currentUserName = $_SESSION['full_name'] ?? 'System User';
$currentUserRole = $_SESSION['role'] ?? 'staff';

?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Computer Shop - Arnold &amp; Jackie's Store</title>

    <link rel="stylesheet" href="<?= $basePath ?>assets/css/admin.css">

    <style>
        /* =========================================================
           PISONET PAGE
        ========================================================= */

        .pisonet-page {
            width: 100%;
            max-width: 1550px;
            margin: 0 auto;
        }

        .pisonet-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
        }

        .pisonet-title {
            margin: 0 0 5px;
            color: #172033;
            font-size: 26px;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -.03em;
        }

        .pisonet-subtitle {
            margin: 0;
            color: #7b8494;
            font-size: 13px;
            line-height: 1.6;
        }

        .pisonet-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 0 0 auto;
        }

        .pisonet-month-form {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px;
            border: 1px solid #e3e8ef;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 4px 16px rgba(15, 23, 42, .035);
        }

        .pisonet-month-form label {
            margin-left: 8px;
            color: #7b8494;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .pisonet-month-input {
            min-height: 36px;
            padding: 0 9px;
            border: 1px solid #e0e6ee;
            border-radius: 8px;
            background: #fff;
            color: #172033;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            outline: none;
        }

        .pisonet-month-input:focus {
            border-color: #93b4ff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .09);
        }

        .pisonet-filter-button,
        .pisonet-primary-button,
        .pisonet-secondary-button,
        .pisonet-danger-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: .16s ease;
        }

        .pisonet-filter-button,
        .pisonet-secondary-button {
            border: 1px solid #dce3eb;
            background: #fff;
            color: #475569;
        }

        .pisonet-filter-button:hover,
        .pisonet-secondary-button:hover {
            background: #f8fafc;
            color: #172033;
        }

        .pisonet-primary-button {
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #fff;
        }

        .pisonet-primary-button:hover {
            background: #1d4ed8;
            color: #fff;
            transform: translateY(-1px);
        }

        .pisonet-danger-button {
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #b91c1c;
        }

        .pisonet-danger-button:hover {
            background: #fee2e2;
            color: #991b1b;
        }

        .pisonet-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .pisonet-kpi {
            min-width: 0;
            padding: 17px 18px;
            border: 1px solid #e6eaf0;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(15, 23, 42, .035);
        }

        .pisonet-kpi-label {
            margin-bottom: 7px;
            color: #7b8494;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .pisonet-kpi-value {
            color: #172033;
            font-size: 25px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -.025em;
        }

        .pisonet-kpi-note {
            margin-top: 6px;
            color: #8a93a2;
            font-size: 11px;
            line-height: 1.45;
        }

        .pisonet-kpi.kpi-blue .pisonet-kpi-value {
            color: #2563eb;
        }

        .pisonet-kpi.kpi-green .pisonet-kpi-value {
            color: #16a34a;
        }

        .pisonet-kpi.kpi-orange .pisonet-kpi-value {
            color: #d97706;
        }

        .pisonet-layout {
            display: grid;
            grid-template-columns: minmax(0, 2.125fr) minmax(320px, 1fr);
            gap: 18px;
            align-items: start;
        }

        .pisonet-card {
            border: 1px solid #e6eaf0;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(15, 23, 42, .035);
            overflow: hidden;
        }

        .pisonet-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f4;
        }

        .pisonet-card-title {
            margin: 0;
            color: #172033;
            font-size: 14px;
            font-weight: 800;
        }

        .pisonet-card-subtitle {
            margin: 4px 0 0;
            color: #8a93a2;
            font-size: 11px;
            line-height: 1.45;
        }

        .pisonet-units-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            padding: 16px;
        }

        .pisonet-unit-card {
            min-width: 0;
            min-height: 184px;
            padding: 16px;
            border: 1px solid #e9edf2;
            border-radius: 13px;
            background: #ffffff;
            transition: .16s ease;
        }

        .pisonet-unit-card:hover {
            border-color: #dce5ef;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .055);
            transform: translateY(-1px);
        }

        .pisonet-unit-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .pisonet-unit-name {
            margin: 0;
            color: #172033;
            font-size: 15px;
            font-weight: 800;
        }

        .pisonet-unit-label {
            margin: 3px 0 0;
            color: #8a93a2;
            font-size: 10px;
        }

        .pisonet-status-wrap {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .pisonet-status-select {
            min-height: 30px;
            padding: 0 7px;
            border: 1px solid #dfe5ec;
            border-radius: 7px;
            background: #fff;
            color: #475569;
            font: inherit;
            font-size: 10px;
            font-weight: 700;
            outline: none;
        }

        .pisonet-status-select:focus {
            border-color: #93b4ff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
        }

        .pisonet-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .pisonet-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 26px;
            padding: 0 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
        }

        .pisonet-status-working {
            color: #15803d;
            background: #f0fdf4;
        }

        .pisonet-status-maintenance {
            color: #b45309;
            background: #fffbeb;
        }

        .pisonet-status-offline {
            color: #64748b;
            background: #f1f5f9;
        }

        .pisonet-unit-amount-label {
            margin-top: 22px;
            color: #8a93a2;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .pisonet-unit-amount {
            margin-top: 4px;
            color: #172033;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.03em;
        }

        .pisonet-unit-state {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            margin-top: 8px;
            padding: 0 8px;
            border-radius: 7px;
            background: #f8fafc;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
        }

        .pisonet-unit-state.recorded {
            color: #166534;
            background: #ecfdf5;
        }

        .pisonet-unit-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
        }

        .pisonet-unit-actions button {
            flex: 1;
        }

        .pisonet-mini-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 9px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 800;
            cursor: pointer;
            transition: .15s ease;
        }

        .pisonet-mini-primary {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #2563eb;
        }

        .pisonet-mini-primary:hover {
            background: #dbeafe;
        }

        .pisonet-mini-secondary {
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
        }

        .pisonet-mini-secondary:hover {
            background: #f8fafc;
            color: #172033;
        }

        .pisonet-table-wrap {
            overflow-x: auto;
        }

        .pisonet-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 560px;
        }

        .pisonet-table th {
            padding: 12px 18px;
            border-bottom: 1px solid #edf0f4;
            background: #fbfcfe;
            color: #8a93a2;
            font-size: 10px;
            font-weight: 800;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .pisonet-table td {
            padding: 13px 18px;
            border-bottom: 1px solid #f0f2f5;
            color: #334155;
            font-size: 12px;
            vertical-align: middle;
        }

        .pisonet-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .pisonet-table tbody tr:hover {
            background: #fbfdff;
        }

        .pisonet-table-amount {
            color: #172033;
            font-weight: 800;
            white-space: nowrap;
        }

        .pisonet-muted {
            color: #8a93a2;
            font-size: 11px;
        }

        .pisonet-empty {
            padding: 36px 20px;
            text-align: center;
            color: #8a93a2;
            font-size: 12px;
        }

        .pisonet-sidebar-section+.pisonet-sidebar-section {
            margin-top: 18px;
        }

        .pisonet-status-summary {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            padding: 18px;
        }

        .pisonet-status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 12px;
            border: 1px solid #edf0f4;
            border-radius: 10px;
            background: #fbfcfe;
        }

        .pisonet-status-row-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
        }

        .pisonet-status-row-count {
            color: #172033;
            font-size: 13px;
            font-weight: 800;
        }

        .pisonet-monthly-total {
            padding: 20px;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 70%);
        }

        .pisonet-monthly-total-label {
            color: #6b7280;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .pisonet-monthly-total-value {
            margin-top: 6px;
            color: #1d4ed8;
            font-size: 30px;
            font-weight: 850;
            letter-spacing: -.04em;
        }

        .pisonet-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, .42);
            backdrop-filter: blur(4px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .18s ease, visibility .18s ease;
        }

        .pisonet-modal-backdrop.is-open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .pisonet-modal {
            width: 100%;
            max-width: 470px;
            border: 1px solid #e3e8ef;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .20);
            overflow: hidden;
            transform: translateY(10px) scale(.985);
            transition: transform .18s ease;
        }

        .pisonet-modal-backdrop.is-open .pisonet-modal {
            transform: translateY(0) scale(1);
        }

        .pisonet-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f4;
        }

        .pisonet-modal-title {
            margin: 0;
            color: #172033;
            font-size: 17px;
            font-weight: 800;
        }

        .pisonet-modal-subtitle {
            margin: 4px 0 0;
            color: #8a93a2;
            font-size: 11px;
            line-height: 1.5;
        }

        .pisonet-modal-close {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: #f8fafc;
            color: #475569;
            font-size: 19px;
            line-height: 1;
            cursor: pointer;
            transition: .15s ease;
        }

        .pisonet-modal-close:hover {
            background: #f1f5f9;
            color: #172033;
        }

        .pisonet-modal-body {
            padding: 20px;
        }

        .pisonet-form-group {
            margin-bottom: 16px;
        }

        .pisonet-form-label {
            display: block;
            margin-bottom: 7px;
            color: #475569;
            font-size: 11px;
            font-weight: 800;
        }

        .pisonet-form-input,
        .pisonet-form-select,
        .pisonet-form-textarea {
            width: 100%;
            min-height: 40px;
            padding: 9px 11px;
            border: 1px solid #dfe5ec;
            border-radius: 9px;
            background: #fff;
            color: #172033;
            font: inherit;
            font-size: 12px;
            outline: none;
            box-sizing: border-box;
        }

        .pisonet-form-textarea {
            min-height: 78px;
            resize: vertical;
        }

        .pisonet-form-input:focus,
        .pisonet-form-select:focus,
        .pisonet-form-textarea:focus {
            border-color: #93b4ff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .09);
        }

        .pisonet-form-help {
            margin-top: 6px;
            color: #8a93a2;
            font-size: 10px;
            line-height: 1.45;
        }

        .pisonet-modal-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 9px;
            padding: 14px 20px;
            border-top: 1px solid #edf0f4;
            background: #fbfcfe;
        }

        .pisonet-error {
            display: none;
            margin-bottom: 14px;
            padding: 11px 12px;
            border: 1px solid #fecaca;
            border-radius: 9px;
            background: #fff1f2;
            color: #b91c1c;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.45;
        }

        .pisonet-error.is-visible {
            display: block;
        }

        body.pisonet-modal-open {
            overflow: hidden;
        }

        .pisonet-alert {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 12px;
            font-weight: 700;
        }

        /* =========================================================
           UNIT RENAME
        ========================================================= */

        .pisonet-unit-name-row {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
        }

        .pisonet-rename-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 54px;
            height: 27px;
            padding: 0 9px;
            flex: 0 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #ffffff;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: .16s ease;
        }

        .pisonet-rename-button:hover {
            border-color: #bfdbfe;
            background: #eff6ff;
            color: #2563eb;
            transform: translateY(-1px);
        }

        .pisonet-delete-warning {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px;
            border: 1px solid #fecaca;
            border-radius: 12px;
            background: #fff7f7;
            color: #7f1d1d;
            font-size: 12px;
            line-height: 1.55;
        }

        .pisonet-delete-warning-icon {
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            border-radius: 10px;
            background: #fee2e2;
            color: #b91c1c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        .pisonet-delete-warning strong {
            color: #991b1b;
        }

        /* =========================================================
           FEEDBACK POPUP
        ========================================================= */

        .pisonet-feedback-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1300;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, .35);
            backdrop-filter: blur(4px);
            opacity: 0;
            visibility: hidden;
            transition: opacity .18s ease, visibility .18s ease;
        }

        .pisonet-feedback-backdrop.is-open {
            opacity: 1;
            visibility: visible;
        }

        .pisonet-feedback {
            width: min(420px, 100%);
            border: 1px solid #e8ecf2;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .18);
            padding: 26px;
            text-align: center;
            transform: translateY(10px) scale(.985);
            transition: transform .18s ease;
        }

        .pisonet-feedback-backdrop.is-open .pisonet-feedback {
            transform: translateY(0) scale(1);
        }

        .pisonet-feedback-icon {
            width: 52px;
            height: 52px;
            margin: 0 auto 14px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 800;
        }

        .pisonet-feedback-icon.success {
            background: #ecfdf3;
            color: #15803d;
        }

        .pisonet-feedback-icon.error {
            background: #fff1f2;
            color: #b91c1c;
        }

        .pisonet-feedback-icon.info {
            background: #eff6ff;
            color: #2563eb;
        }

        .pisonet-feedback-title {
            margin: 0 0 6px;
            color: #172033;
            font-size: 18px;
            font-weight: 750;
        }

        .pisonet-feedback-message {
            margin: 0 auto 20px;
            max-width: 340px;
            color: #697386;
            font-size: 13px;
            line-height: 1.6;
        }

        .pisonet-feedback-button {
            min-width: 110px;
            min-height: 40px;
            padding: 0 16px;
            border: 0;
            border-radius: 10px;
            background: #2563eb;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        /* =========================================================
           UNIT ACTIONS
        ========================================================= */

        .pisonet-unit-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pisonet-mini-danger {
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #b91c1c;
        }

        .pisonet-mini-danger:hover {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #991b1b;
        }

        .pisonet-mini-danger:disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none;
        }

        /* =========================================================
           RENAME MODAL
        ========================================================= */

        .pisonet-rename-note {
            margin-top: 6px;
            color: #8a93a2;
            font-size: 11px;
            line-height: 1.5;
        }

        .pisonet-total-highlight {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 9px;
            border-radius: 999px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 10px;
            font-weight: 800;
        }

        @media (max-width: 1100px) {
            .pisonet-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .pisonet-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .pisonet-header {
                flex-direction: column;
            }

            .pisonet-header-actions,
            .pisonet-month-form {
                width: 100%;
            }

            .pisonet-header-actions {
                flex-wrap: wrap;
            }

            .pisonet-header-actions>button {
                flex: 1 1 150px;
            }

            .pisonet-month-form {
                justify-content: space-between;
            }

            .pisonet-month-input {
                flex: 1;
            }

            .pisonet-units-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            .pisonet-kpi-grid {
                grid-template-columns: 1fr;
            }

            .pisonet-card-header {
                padding: 16px;
            }

            .pisonet-units-grid,
            .pisonet-status-summary,
            .pisonet-modal-body {
                padding: 14px;
            }

            .pisonet-modal-header,
            .pisonet-modal-actions {
                padding-left: 14px;
                padding-right: 14px;
            }

            .pisonet-modal-actions {
                flex-direction: column-reverse;
            }

            .pisonet-modal-actions button {
                width: 100%;
            }
        }

        .pisonet-history-modal {
            width: min(860px, calc(100vw - 32px));
            max-height: min(780px, calc(100vh - 40px));
        }

        .pisonet-history-body {
            padding: 0;
            overflow: auto;
            max-height: 58vh;
        }

        .pisonet-history-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        .pisonet-history-table th {
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 11px 14px;
            background: #f8fafc;
            border-bottom: 1px solid #e8edf3;
            color: #7b8494;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            text-align: left;
            white-space: nowrap;
        }

        .pisonet-history-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #eef1f5;
            color: #334155;
            font-size: 12px;
            vertical-align: middle;
        }

        .pisonet-history-table tr:last-child td {
            border-bottom: 0;
        }

        .pisonet-history-table .history-amount {
            color: #172033;
            font-weight: 800;
            white-space: nowrap;
        }

        .pisonet-history-actions {
            display: flex;
            justify-content: flex-end;
            gap: 6px;
            white-space: nowrap;
        }

        .pisonet-history-empty {
            padding: 44px 20px;
            text-align: center;
            color: #8a93a2;
            font-size: 12px;
        }

        .pisonet-history-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            border-top: 1px solid #e8edf3;
            background: #fafbfc;
        }

        .pisonet-history-count {
            color: #8a93a2;
            font-size: 11px;
        }
    </style>
</head>

<body>

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="main">

        <?php require_once $basePath . 'includes/topbar.php'; ?>

        <main class="content">

            <div class="pisonet-page">

                <div class="pisonet-header">

                    <div>
                        <h1 class="pisonet-title">Computer Shop</h1>
                        <p class="pisonet-subtitle">
                            Manage your Pisonet machines and record the money collected from each machine every month.
                        </p>
                    </div>

                    <div class="pisonet-header-actions">
                        <button type="button" class="pisonet-primary-button" id="openAddPisonetModal">+ Add Pisonet</button>

                        <form method="get" class="pisonet-month-form">
                            <label for="pisonetMonth">Month</label>
                            <input
                                type="month"
                                id="pisonetMonth"
                                name="month"
                                value="<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>"
                                class="pisonet-month-input">
                            <button type="submit" class="pisonet-filter-button">View</button>
                        </form>
                    </div>

                </div>

                <?php if ($setupError !== ''): ?>
                    <div class="pisonet-alert">
                        <?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div class="pisonet-kpi-grid">

                    <div class="pisonet-kpi kpi-blue">
                        <div class="pisonet-kpi-label">Monthly Harvest</div>
                        <div class="pisonet-kpi-value">₱<?= number_format($totalMonthHarvest, 2) ?></div>
                        <div class="pisonet-kpi-note">Total collected for <?= htmlspecialchars($selectedMonthLabel) ?></div>
                    </div>

                    <div class="pisonet-kpi kpi-green">
                        <div class="pisonet-kpi-label">Harvested Units</div>
                        <div class="pisonet-kpi-value"><?= number_format($harvestedUnitCount) ?>/<?= number_format($totalUnitCount) ?></div>
                        <div class="pisonet-kpi-note">Pisonets with a recorded harvest</div>
                    </div>

                    <div class="pisonet-kpi kpi-orange">
                        <div class="pisonet-kpi-label">Pending Harvests</div>
                        <div class="pisonet-kpi-value"><?= number_format(max(0, $totalUnitCount - $harvestedUnitCount)) ?></div>
                        <div class="pisonet-kpi-note">Units without a <?= htmlspecialchars($selectedMonthLabel) ?> entry</div>
                    </div>

                    <div class="pisonet-kpi">
                        <div class="pisonet-kpi-label">All-Time Harvest</div>
                        <div class="pisonet-kpi-value">₱<?= number_format($allTimeHarvest, 2) ?></div>
                        <div class="pisonet-kpi-note">All recorded Pisonet collections</div>
                    </div>

                </div>

                <div class="pisonet-layout">

                    <section class="pisonet-card">

                        <div class="pisonet-card-header">
                            <div>
                                <h2 class="pisonet-card-title">Pisonet Units</h2>
                                <p class="pisonet-card-subtitle">
                                    <?= htmlspecialchars($selectedMonthLabel) ?> harvest status and machine condition.
                                </p>
                            </div>
                            <span class="pisonet-total-highlight"><?= number_format($totalUnitCount) ?> unit<?= $totalUnitCount === 1 ? '' : 's' ?></span>
                        </div>

                        <?php if ($units): ?>

                            <div class="pisonet-units-grid">

                                <?php foreach ($units as $unit): ?>

                                    <?php
                                    $statusClass = 'pisonet-status-working';
                                    $statusLabel = 'Working';

                                    if ($unit['status'] === 'maintenance') {
                                        $statusClass = 'pisonet-status-maintenance';
                                        $statusLabel = 'Maintenance';
                                    } elseif ($unit['status'] === 'offline') {
                                        $statusClass = 'pisonet-status-offline';
                                        $statusLabel = 'Offline';
                                    }

                                    $hasHarvest = !empty($unit['harvest_id']);
                                    ?>

                                    <article class="pisonet-unit-card">

                                        <div class="pisonet-unit-top">

                                            <div>
                                                <div class="pisonet-unit-name-row">
                                                    <h3 class="pisonet-unit-name">
                                                        <?= htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </h3>

                                                    <button
                                                        type="button"
                                                        class="pisonet-rename-button open-rename-modal"
                                                        data-unit-id="<?= (int)$unit['id'] ?>"
                                                        data-unit-name="<?= htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        aria-label="Rename <?= htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Rename Pisonet">
                                                        Rename
                                                    </button>
                                                </div>
                                                <p class="pisonet-unit-label">Pisonet machine</p>
                                            </div>

                                            <div class="pisonet-status-wrap">
                                                <span class="pisonet-status-badge <?= $statusClass ?>">
                                                    <span class="pisonet-status-dot"></span>
                                                    <?= htmlspecialchars($statusLabel) ?>
                                                </span>

                                                <select
                                                    class="pisonet-status-select pisonet-status-control"
                                                    data-id="<?= (int)$unit['id'] ?>"
                                                    data-previous-value="<?= htmlspecialchars($unit['status'], ENT_QUOTES, 'UTF-8') ?>"
                                                    aria-label="Change status for <?= htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <option value="working" <?= $unit['status'] === 'working' ? 'selected' : '' ?>>Working</option>
                                                    <option value="maintenance" <?= $unit['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                                    <option value="offline" <?= $unit['status'] === 'offline' ? 'selected' : '' ?>>Offline</option>
                                                </select>
                                            </div>

                                        </div>

                                        <div class="pisonet-unit-amount-label">
                                            <?= htmlspecialchars($selectedMonthLabel) ?> Harvest
                                        </div>

                                        <div class="pisonet-unit-amount">
                                            ₱<?= number_format($unit['month_harvest'], 2) ?>
                                        </div>

                                        <div class="pisonet-unit-state <?= $hasHarvest ? 'recorded' : '' ?>">
                                            <?= $hasHarvest ? 'Harvest recorded' : 'No harvest recorded yet' ?>
                                        </div>

                                        <div class="pisonet-unit-actions">

                                            <button
                                                type="button"
                                                class="pisonet-mini-button pisonet-mini-primary open-harvest-modal"
                                                data-unit-id="<?= (int)$unit['id'] ?>"
                                                data-unit-name="<?= htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-amount="<?= number_format($unit['month_harvest'], 2, '.', '') ?>"
                                                data-remarks="<?= htmlspecialchars($unit['harvest_remarks'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= $hasHarvest ? 'Edit Harvest' : 'Record Harvest' ?>
                                            </button>

                                            <button
                                                type="button"
                                                class="pisonet-mini-button pisonet-mini-secondary open-history-modal"
                                                data-unit-id="<?= (int)$unit['id'] ?>"
                                                data-unit-name="<?= htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                History
                                            </button>

                                            <button
                                                type="button"
                                                class="pisonet-mini-button pisonet-mini-danger delete-pisonet-button"
                                                data-unit-id="<?= (int)$unit['id'] ?>"
                                                data-unit-name="<?= htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-has-history="<?= $hasHarvest ? '1' : '0' ?>">
                                                Delete
                                            </button>

                                        </div>

                                    </article>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="pisonet-empty">
                                No Pisonet units are available yet.
                                <div style="margin-top:14px;">
                                    <button type="button" class="pisonet-primary-button" id="openAddPisonetEmpty">+ Add Pisonet</button>
                                </div>
                            </div>

                        <?php endif; ?>

                    </section>

                    <aside>

                        <section class="pisonet-card pisonet-sidebar-section">

                            <div class="pisonet-card-header">
                                <div>
                                    <h2 class="pisonet-card-title">Unit Status</h2>
                                    <p class="pisonet-card-subtitle">Current machine condition.</p>
                                </div>
                            </div>

                            <div class="pisonet-status-summary">

                                <div class="pisonet-status-row">
                                    <div class="pisonet-status-row-label">
                                        <span class="pisonet-status-dot" style="color:#16a34a;"></span>
                                        Working
                                    </div>
                                    <div class="pisonet-status-row-count"><?= number_format($workingUnitCount) ?></div>
                                </div>

                                <div class="pisonet-status-row">
                                    <div class="pisonet-status-row-label">
                                        <span class="pisonet-status-dot" style="color:#d97706;"></span>
                                        Maintenance
                                    </div>
                                    <div class="pisonet-status-row-count"><?= number_format($maintenanceUnitCount) ?></div>
                                </div>

                                <div class="pisonet-status-row">
                                    <div class="pisonet-status-row-label">
                                        <span class="pisonet-status-dot" style="color:#64748b;"></span>
                                        Offline
                                    </div>
                                    <div class="pisonet-status-row-count"><?= number_format($offlineUnitCount) ?></div>
                                </div>

                            </div>

                        </section>

                        <section class="pisonet-card pisonet-sidebar-section">

                            <div class="pisonet-monthly-total">
                                <div class="pisonet-monthly-total-label">Collected in <?= htmlspecialchars($selectedMonthLabel) ?></div>
                                <div class="pisonet-monthly-total-value">₱<?= number_format($totalMonthHarvest, 2) ?></div>
                                <div class="pisonet-kpi-note">
                                    Record the amount only when you physically harvest the money from the machine.
                                </div>
                            </div>

                        </section>

                    </aside>

                </div>

                <section class="pisonet-card" style="margin-top:18px;">

                    <div class="pisonet-card-header">
                        <div>
                            <h2 class="pisonet-card-title"><?= htmlspecialchars($selectedMonthLabel) ?> Harvest Records</h2>
                            <p class="pisonet-card-subtitle">Recorded collections for the selected month.</p>
                        </div>
                    </div>

                    <div class="pisonet-table-wrap">

                        <?php if ($harvestRows): ?>

                            <table class="pisonet-table">
                                <thead>
                                    <tr>
                                        <th>Pisonet</th>
                                        <th>Amount Collected</th>
                                        <th>Remarks</th>
                                        <th>Recorded By</th>
                                        <th>Recorded</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($harvestRows as $row): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            </td>

                                            <td class="pisonet-table-amount">
                                                ₱<?= number_format((float)$row['amount'], 2) ?>
                                            </td>

                                            <td>
                                                <span class="pisonet-muted">
                                                    <?= $row['remarks'] !== null && $row['remarks'] !== ''
                                                        ? htmlspecialchars($row['remarks'], ENT_QUOTES, 'UTF-8')
                                                        : '—' ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['recorded_by'], ENT_QUOTES, 'UTF-8') ?>
                                            </td>

                                            <td>
                                                <span class="pisonet-muted">
                                                    <?= htmlspecialchars(date('M d, Y h:i A', strtotime($row['harvested_at'])), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>

                                            <td style="text-align:right; white-space:nowrap;">
                                                <button
                                                    type="button"
                                                    class="pisonet-mini-button pisonet-mini-secondary open-harvest-modal"
                                                    data-unit-id="<?= (int)$row['unit_id'] ?>"
                                                    data-harvest-row-id="<?= (int)$row['id'] ?>"
                                                    data-unit-name="<?= htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-amount="<?= number_format((float)$row['amount'], 2, '.', '') ?>"
                                                    data-remarks="<?= htmlspecialchars($row['remarks'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    class="pisonet-mini-button pisonet-mini-danger delete-harvest-button"
                                                    data-id="<?= (int)$row['id'] ?>"
                                                    data-unit-name="<?= htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-month="<?= htmlspecialchars(date('F Y', strtotime($row['harvest_month'])), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-amount="<?= number_format((float)$row['amount'], 2, '.', '') ?>">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                </tbody>
                            </table>

                        <?php else: ?>

                            <div class="pisonet-empty">
                                No harvest has been recorded for <?= htmlspecialchars($selectedMonthLabel) ?> yet.
                            </div>

                        <?php endif; ?>

                    </div>

                </section>

                <section class="pisonet-card" style="margin-top:18px;">

                    <div class="pisonet-card-header">
                        <div>
                            <h2 class="pisonet-card-title">Recent Harvest History</h2>
                            <p class="pisonet-card-subtitle">The 12 most recently recorded collections.</p>
                        </div>
                    </div>

                    <div class="pisonet-table-wrap">

                        <?php if ($recentHarvests): ?>

                            <table class="pisonet-table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Pisonet</th>
                                        <th>Amount</th>
                                        <th>Recorded By</th>
                                        <th>Recorded</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($recentHarvests as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(date('F Y', strtotime($row['harvest_month'])), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><strong><?= htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                            <td class="pisonet-table-amount">₱<?= number_format((float)$row['amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($row['recorded_by'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><span class="pisonet-muted"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($row['harvested_at'])), ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <button
                                                    type="button"
                                                    class="pisonet-mini-button pisonet-mini-danger delete-harvest-button"
                                                    data-id="<?= (int)$row['id'] ?>"
                                                    data-unit-name="<?= htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-month="<?= htmlspecialchars(date('F Y', strtotime($row['harvest_month'])), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-amount="<?= number_format((float)$row['amount'], 2, '.', '') ?>">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                </tbody>
                            </table>

                        <?php else: ?>

                            <div class="pisonet-empty">
                                No Pisonet harvest records have been created yet.
                            </div>

                        <?php endif; ?>

                    </div>

                </section>

            </div>

        </main>

    </div>

    <!-- =========================================================
     ADD PISONET MODAL
========================================================= -->

    <div class="pisonet-modal-backdrop" id="addPisonetModal" aria-hidden="true">

        <section class="pisonet-modal" role="dialog" aria-modal="true" aria-labelledby="addPisonetModalTitle">

            <div class="pisonet-modal-header">
                <div>
                    <h2 class="pisonet-modal-title" id="addPisonetModalTitle">Add Pisonet</h2>
                    <p class="pisonet-modal-subtitle">Add another machine to your Computer Shop.</p>
                </div>

                <button type="button" class="pisonet-modal-close" id="closeAddPisonetModal" aria-label="Close">×</button>
            </div>

            <form id="addPisonetForm" novalidate>

                <div class="pisonet-modal-body">

                    <div class="pisonet-error" id="addPisonetFormError"></div>

                    <div class="pisonet-form-group">
                        <label class="pisonet-form-label" for="newPisonetName">Pisonet Name</label>
                        <input
                            type="text"
                            class="pisonet-form-input"
                            id="newPisonetName"
                            name="unit_name"
                            maxlength="50"
                            autocomplete="off"
                            value="<?= htmlspecialchars($nextUnitName, ENT_QUOTES, 'UTF-8') ?>"
                            required>
                        <div class="pisonet-form-help">You can rename this machine later.</div>
                    </div>

                    <div class="pisonet-form-group" style="margin-bottom:0;">
                        <label class="pisonet-form-label" for="newPisonetStatus">Initial Status</label>
                        <select class="pisonet-form-select" id="newPisonetStatus" name="status">
                            <option value="working" selected>Working</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="offline">Offline</option>
                        </select>
                    </div>

                </div>

                <div class="pisonet-modal-actions">
                    <button type="button" class="pisonet-secondary-button" id="cancelAddPisonetModal">Cancel</button>
                    <button type="submit" class="pisonet-primary-button" id="saveAddPisonetButton">Add Pisonet</button>
                </div>

            </form>

        </section>

    </div>

    <!-- =========================================================
     RENAME PISONET MODAL
========================================================= -->

    <div class="pisonet-modal-backdrop" id="renameModal" aria-hidden="true">

        <section class="pisonet-modal" role="dialog" aria-modal="true" aria-labelledby="renameModalTitle">

            <div class="pisonet-modal-header">
                <div>
                    <h2 class="pisonet-modal-title" id="renameModalTitle">Rename Pisonet</h2>
                    <p class="pisonet-modal-subtitle">Give this machine the name you actually use in your shop.</p>
                </div>

                <button type="button" class="pisonet-modal-close" id="closeRenameModal" aria-label="Close">×</button>
            </div>

            <form id="renameForm" novalidate>

                <div class="pisonet-modal-body">

                    <div class="pisonet-error" id="renameFormError"></div>

                    <input type="hidden" name="id" id="renameUnitId">

                    <div class="pisonet-form-group" style="margin-bottom:0;">
                        <label class="pisonet-form-label" for="renameUnitName">Pisonet Name</label>
                        <input
                            type="text"
                            class="pisonet-form-input"
                            id="renameUnitName"
                            name="unit_name"
                            maxlength="50"
                            autocomplete="off"
                            placeholder="Example: PC 01, Kids PC, Main PC"
                            required>
                        <div class="pisonet-rename-note">You can use any name up to 50 characters. The name will appear everywhere on this Pisonet page and in its harvest history.</div>
                    </div>

                </div>

                <div class="pisonet-modal-actions">
                    <button type="button" class="pisonet-secondary-button" id="cancelRenameModal">Cancel</button>
                    <button type="submit" class="pisonet-primary-button" id="saveRenameButton">Save Name</button>
                </div>

            </form>

        </section>

    </div>

    <!-- =========================================================
     DELETE PISONET MODAL
========================================================= -->

    <div class="pisonet-modal-backdrop" id="deletePisonetModal" aria-hidden="true">

        <section class="pisonet-modal" role="dialog" aria-modal="true" aria-labelledby="deletePisonetModalTitle">

            <div class="pisonet-modal-header">
                <div>
                    <h2 class="pisonet-modal-title" id="deletePisonetModalTitle">Delete Pisonet</h2>
                    <p class="pisonet-modal-subtitle">This action permanently removes the machine from your Pisonet list.</p>
                </div>

                <button type="button" class="pisonet-modal-close" id="closeDeletePisonetModal" aria-label="Close">×</button>
            </div>

            <div class="pisonet-modal-body">
                <div class="pisonet-delete-warning" id="deletePisonetWarning">
                    <div class="pisonet-delete-warning-icon">!</div>
                    <div>
                        <strong id="deletePisonetName">Pisonet</strong> will be deleted permanently.
                        Please make sure this is the correct machine.
                    </div>
                </div>
            </div>

            <div class="pisonet-modal-actions">
                <button type="button" class="pisonet-secondary-button" id="cancelDeletePisonetModal">Cancel</button>
                <button type="button" class="pisonet-danger-button" id="confirmDeletePisonetButton">Delete Pisonet</button>
            </div>

        </section>

    </div>

    <!-- =========================================================
     FEEDBACK POPUP
========================================================= -->

    <div class="pisonet-feedback-backdrop" id="pisonetFeedback" aria-hidden="true">
        <section class="pisonet-feedback" role="alertdialog" aria-modal="true" aria-labelledby="pisonetFeedbackTitle">
            <div class="pisonet-feedback-icon success" id="pisonetFeedbackIcon">✓</div>
            <h2 class="pisonet-feedback-title" id="pisonetFeedbackTitle">Success</h2>
            <p class="pisonet-feedback-message" id="pisonetFeedbackMessage">Done.</p>
            <button type="button" class="pisonet-feedback-button" id="closeFeedbackButton">Okay</button>
        </section>
    </div>

    <!-- =========================================================
     DELETE HARVEST MODAL
========================================================= -->

    <div class="pisonet-modal-backdrop" id="deleteHarvestModal" aria-hidden="true">

        <section class="pisonet-modal" role="dialog" aria-modal="true" aria-labelledby="deleteHarvestModalTitle">

            <div class="pisonet-modal-header">
                <div>
                    <h2 class="pisonet-modal-title" id="deleteHarvestModalTitle">Delete Harvest Record</h2>
                    <p class="pisonet-modal-subtitle">This will permanently remove the selected monthly harvest record.</p>
                </div>

                <button type="button" class="pisonet-modal-close" id="closeDeleteHarvestModal" aria-label="Close">×</button>
            </div>

            <div class="pisonet-modal-body">
                <div class="pisonet-delete-warning">
                    <div class="pisonet-delete-warning-icon">!</div>
                    <div>
                        <strong id="deleteHarvestUnitName">Pisonet</strong> · <span id="deleteHarvestMonth">Month</span><br>
                        Harvest amount: <strong id="deleteHarvestAmount">₱0.00</strong><br>
                        This action cannot be undone.
                    </div>
                </div>
            </div>

            <div class="pisonet-modal-actions">
                <button type="button" class="pisonet-secondary-button" id="cancelDeleteHarvestModal">Cancel</button>
                <button type="button" class="pisonet-danger-button" id="confirmDeleteHarvestButton">Delete Harvest</button>
            </div>

        </section>

    </div>

    <!-- =========================================================
     PISONET HISTORY MODAL
========================================================= -->

    <div class="pisonet-modal-backdrop" id="pisonetHistoryModal" aria-hidden="true">

        <section class="pisonet-modal pisonet-history-modal" role="dialog" aria-modal="true" aria-labelledby="pisonetHistoryModalTitle">

            <div class="pisonet-modal-header">
                <div>
                    <h2 class="pisonet-modal-title" id="pisonetHistoryModalTitle">Pisonet History</h2>
                    <p class="pisonet-modal-subtitle" id="pisonetHistoryModalSubtitle">Monthly harvest history for this machine.</p>
                </div>

                <button type="button" class="pisonet-modal-close" id="closePisonetHistoryModal" aria-label="Close">×</button>
            </div>

            <div class="pisonet-history-body" id="pisonetHistoryBody">
                <div class="pisonet-history-empty">Loading history...</div>
            </div>

            <div class="pisonet-history-footer">
                <span class="pisonet-history-count" id="pisonetHistoryCount">0 records</span>
                <button type="button" class="pisonet-secondary-button" id="closePisonetHistoryFooter">Close</button>
            </div>

        </section>

    </div>

    <!-- =========================================================
     RECORD HARVEST MODAL
========================================================= -->

    <div class="pisonet-modal-backdrop" id="harvestModal" aria-hidden="true">

        <section class="pisonet-modal" role="dialog" aria-modal="true" aria-labelledby="harvestModalTitle">

            <div class="pisonet-modal-header">
                <div>
                    <h2 class="pisonet-modal-title" id="harvestModalTitle">Record Monthly Harvest</h2>
                    <p class="pisonet-modal-subtitle" id="harvestModalSubtitle">Enter the amount physically collected from this Pisonet.</p>
                </div>

                <button type="button" class="pisonet-modal-close" id="closeHarvestModal" aria-label="Close">×</button>
            </div>

            <form id="harvestForm" novalidate>

                <div class="pisonet-modal-body">

                    <div class="pisonet-error" id="harvestFormError"></div>

                    <input type="hidden" name="unit_id" id="harvestUnitId">

                    <div class="pisonet-form-group">
                        <label class="pisonet-form-label" for="harvestUnitName">Pisonet</label>
                        <input type="text" class="pisonet-form-input" id="harvestUnitName" readonly>
                    </div>

                    <div class="pisonet-form-group">
                        <label class="pisonet-form-label" for="harvestMonth">Harvest Month</label>
                        <input
                            type="month"
                            class="pisonet-form-input"
                            id="harvestMonth"
                            name="harvest_month"
                            value="<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>"
                            required>
                        <div class="pisonet-form-help">One harvest record is kept per Pisonet for each month.</div>
                    </div>

                    <div class="pisonet-form-group">
                        <label class="pisonet-form-label" for="harvestAmount">Amount Collected</label>
                        <input
                            type="number"
                            class="pisonet-form-input"
                            id="harvestAmount"
                            name="amount"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            placeholder="0.00"
                            required>
                    </div>

                    <div class="pisonet-form-group" style="margin-bottom:0;">
                        <label class="pisonet-form-label" for="harvestRemarks">Remarks <span style="font-weight:500;color:#8a93a2;">(optional)</span></label>
                        <textarea
                            class="pisonet-form-textarea"
                            id="harvestRemarks"
                            name="remarks"
                            maxlength="500"
                            placeholder="Example: Monthly collection, machine emptied, etc."></textarea>
                    </div>

                </div>

                <div class="pisonet-modal-actions">
                    <button type="button" class="pisonet-secondary-button" id="cancelHarvestModal">Cancel</button>
                    <button type="submit" class="pisonet-primary-button" id="saveHarvestButton">Save Harvest</button>
                </div>

            </form>

        </section>

    </div>

    <script>
        (function() {
            'use strict';

            const feedback = document.getElementById('pisonetFeedback');
            const feedbackIcon = document.getElementById('pisonetFeedbackIcon');
            const feedbackTitle = document.getElementById('pisonetFeedbackTitle');
            const feedbackMessage = document.getElementById('pisonetFeedbackMessage');
            const closeFeedbackButton = document.getElementById('closeFeedbackButton');

            const renameModal = document.getElementById('renameModal');
            const renameForm = document.getElementById('renameForm');
            const closeRenameButton = document.getElementById('closeRenameModal');
            const cancelRenameButton = document.getElementById('cancelRenameModal');
            const saveRenameButton = document.getElementById('saveRenameButton');
            const renameErrorBox = document.getElementById('renameFormError');
            const addPisonetModal = document.getElementById('addPisonetModal');
            const addPisonetForm = document.getElementById('addPisonetForm');
            const closeAddPisonetButton = document.getElementById('closeAddPisonetModal');
            const cancelAddPisonetButton = document.getElementById('cancelAddPisonetModal');
            const saveAddPisonetButton = document.getElementById('saveAddPisonetButton');
            const addPisonetErrorBox = document.getElementById('addPisonetFormError');
            const newPisonetName = document.getElementById('newPisonetName');
            const newPisonetStatus = document.getElementById('newPisonetStatus');

            const deletePisonetModal = document.getElementById('deletePisonetModal');
            const closeDeletePisonetButton = document.getElementById('closeDeletePisonetModal');
            const cancelDeletePisonetButton = document.getElementById('cancelDeletePisonetModal');
            const confirmDeletePisonetButton = document.getElementById('confirmDeletePisonetButton');
            const deletePisonetName = document.getElementById('deletePisonetName');

            const deleteHarvestModal = document.getElementById('deleteHarvestModal');
            const closeDeleteHarvestButton = document.getElementById('closeDeleteHarvestModal');
            const cancelDeleteHarvestButton = document.getElementById('cancelDeleteHarvestModal');
            const confirmDeleteHarvestButton = document.getElementById('confirmDeleteHarvestButton');
            const deleteHarvestUnitName = document.getElementById('deleteHarvestUnitName');
            const deleteHarvestMonth = document.getElementById('deleteHarvestMonth');
            const deleteHarvestAmount = document.getElementById('deleteHarvestAmount');


            const renameUnitId = document.getElementById('renameUnitId');
            const renameUnitName = document.getElementById('renameUnitName');

            const modal = document.getElementById('harvestModal');
            const form = document.getElementById('harvestForm');
            const closeButton = document.getElementById('closeHarvestModal');
            const cancelButton = document.getElementById('cancelHarvestModal');
            const saveButton = document.getElementById('saveHarvestButton');
            const errorBox = document.getElementById('harvestFormError');
            const unitIdInput = document.getElementById('harvestUnitId');
            const unitNameInput = document.getElementById('harvestUnitName');
            const monthInput = document.getElementById('harvestMonth');
            const amountInput = document.getElementById('harvestAmount');
            const remarksInput = document.getElementById('harvestRemarks');

            const historyModal = document.getElementById('pisonetHistoryModal');
            const historyBody = document.getElementById('pisonetHistoryBody');
            const historyTitle = document.getElementById('pisonetHistoryModalTitle');
            const historySubtitle = document.getElementById('pisonetHistoryModalSubtitle');
            const historyCount = document.getElementById('pisonetHistoryCount');
            const closeHistoryButton = document.getElementById('closePisonetHistoryModal');
            const closeHistoryFooterButton = document.getElementById('closePisonetHistoryFooter');

            if (!modal || !form) {
                return;
            }

            let lastTrigger = null;
            let lastRenameTrigger = null;
            let lastDeleteTrigger = null;
            let lastDeleteHarvestTrigger = null;
            let deleteUnitId = '';
            let deleteHarvestId = '';
            let feedbackTimer = null;

            function showFeedback(type, title, message, autoCloseMs) {
                if (!feedback) {
                    if (type !== 'success' && typeof window.showToast === 'function') {
                        window.showToast(message, 'error');
                    }
                    return;
                }

                window.clearTimeout(feedbackTimer);

                feedbackIcon.className = 'pisonet-feedback-icon ' + (type || 'info');
                feedbackIcon.textContent = type === 'success' ? '✓' : (type === 'error' ? '!' : 'i');
                feedbackTitle.textContent = title || 'Notice';
                feedbackMessage.textContent = message || '';

                feedback.classList.add('is-open');
                feedback.setAttribute('aria-hidden', 'false');

                if (autoCloseMs) {
                    feedbackTimer = window.setTimeout(closeFeedback, autoCloseMs);
                }
            }

            function closeFeedback() {
                window.clearTimeout(feedbackTimer);
                feedback.classList.remove('is-open');
                feedback.setAttribute('aria-hidden', 'true');
            }


            function showError(message) {
                errorBox.textContent = message || 'Unable to save this harvest.';
                errorBox.classList.add('is-visible');
            }

            function hideError() {
                errorBox.textContent = '';
                errorBox.classList.remove('is-visible');
            }

            function openModal(trigger) {
                lastTrigger = trigger;
                hideError();

                unitIdInput.value = trigger.dataset.unitId || '';
                unitNameInput.value = trigger.dataset.unitName || '';
                monthInput.value = trigger.dataset.harvestMonth || '<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>';
                amountInput.value = trigger.dataset.amount || '';
                remarksInput.value = trigger.dataset.remarks || '';

                const hasAmount = parseFloat(trigger.dataset.amount || '0') > 0;
                document.getElementById('harvestModalTitle').textContent = hasAmount ?
                    'Edit Monthly Harvest' :
                    'Record Monthly Harvest';

                document.getElementById('harvestModalSubtitle').textContent = hasAmount ?
                    'Update the recorded amount collected from this Pisonet.' :
                    'Enter the amount physically collected from this Pisonet.';

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('pisonet-modal-open');

                setTimeout(function() {
                    amountInput.focus();
                    amountInput.select();
                }, 40);
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('pisonet-modal-open');

                if (lastTrigger) {
                    lastTrigger.focus();
                }
            }

            function showAddPisonetError(message) {
                addPisonetErrorBox.textContent = message || 'Unable to add the Pisonet.';
                addPisonetErrorBox.classList.add('is-visible');
            }

            function hideAddPisonetError() {
                addPisonetErrorBox.textContent = '';
                addPisonetErrorBox.classList.remove('is-visible');
            }

            function openAddPisonetModal() {
                hideAddPisonetError();
                newPisonetName.value = '<?= htmlspecialchars($nextUnitName, ENT_QUOTES, 'UTF-8') ?>';
                newPisonetStatus.value = 'working';
                addPisonetModal.classList.add('is-open');
                addPisonetModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('pisonet-modal-open');

                setTimeout(function() {
                    newPisonetName.focus();
                    newPisonetName.select();
                }, 40);
            }

            function closeAddPisonetModal() {
                addPisonetModal.classList.remove('is-open');
                addPisonetModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('pisonet-modal-open');
            }

            function showRenameError(message) {
                renameErrorBox.textContent = message || 'Unable to rename this Pisonet.';
                renameErrorBox.classList.add('is-visible');
            }

            function hideRenameError() {
                renameErrorBox.textContent = '';
                renameErrorBox.classList.remove('is-visible');
            }

            function openRenameModal(trigger) {
                lastRenameTrigger = trigger;
                hideRenameError();

                renameUnitId.value = trigger.dataset.unitId || '';
                renameUnitName.value = trigger.dataset.unitName || '';

                renameModal.classList.add('is-open');
                renameModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('pisonet-modal-open');

                setTimeout(function() {
                    renameUnitName.focus();
                    renameUnitName.select();
                }, 40);
            }

            function closeRenameModal() {
                renameModal.classList.remove('is-open');
                renameModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('pisonet-modal-open');

                if (lastRenameTrigger) {
                    lastRenameTrigger.focus();
                }
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function openDeletePisonetModal(trigger) {
                lastDeleteTrigger = trigger;
                deleteUnitId = trigger.dataset.unitId || '';
                const unitName = trigger.dataset.unitName || 'this Pisonet';
                const hasHistory = trigger.dataset.hasHistory === '1';

                deletePisonetName.textContent = unitName;

                const warning = document.getElementById('deletePisonetWarning');

                if (warning) {
                    warning.querySelector('div:nth-child(2)').innerHTML = hasHistory ?
                        '<strong id="deletePisonetName">' + escapeHtml(unitName) + '</strong> will be deleted permanently, <strong>including its harvest history</strong>. This cannot be undone.' :
                        '<strong id="deletePisonetName">' + escapeHtml(unitName) + '</strong> will be deleted permanently. Please make sure this is the correct machine.';
                }

                deletePisonetModal.classList.add('is-open');
                deletePisonetModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('pisonet-modal-open');

                setTimeout(function() {
                    confirmDeletePisonetButton.focus();
                }, 40);
            }

            function closeDeletePisonetModal() {
                deletePisonetModal.classList.remove('is-open');
                deletePisonetModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('pisonet-modal-open');

                if (lastDeleteTrigger) {
                    lastDeleteTrigger.focus();
                }
            }

            function openDeleteHarvestModal(trigger) {
                lastDeleteHarvestTrigger = trigger;
                deleteHarvestId = trigger.dataset.id || '';
                deleteHarvestUnitName.textContent = trigger.dataset.unitName || 'Pisonet';
                deleteHarvestMonth.textContent = trigger.dataset.month || 'Selected month';
                deleteHarvestAmount.textContent = '₱' + Number(trigger.dataset.amount || 0).toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                deleteHarvestModal.classList.add('is-open');
                deleteHarvestModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('pisonet-modal-open');

                setTimeout(function() {
                    confirmDeleteHarvestButton.focus();
                }, 40);
            }

            function closeDeleteHarvestModal() {
                deleteHarvestModal.classList.remove('is-open');
                deleteHarvestModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('pisonet-modal-open');

                if (lastDeleteHarvestTrigger) {
                    lastDeleteHarvestTrigger.focus();
                }
            }

            function closePisonetHistoryModal() {
                if (!historyModal) return;
                historyModal.classList.remove('is-open');
                historyModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('pisonet-modal-open');
            }

            function formatHistoryPeso(amount) {
                return '₱' + Number(amount || 0).toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function historyEscapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function renderPisonetHistory(records) {
                if (!historyBody || !historyCount) return;

                historyCount.textContent = records.length === 1 ? '1 record' : records.length + ' records';

                if (!records.length) {
                    historyBody.innerHTML = '<div class="pisonet-history-empty">No harvest history has been recorded for this Pisonet yet.</div>';
                    return;
                }

                historyBody.innerHTML = `
            <div class="pisonet-table-wrap">
                <table class="pisonet-history-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Amount</th>
                            <th>Remarks</th>
                            <th>Recorded By</th>
                            <th>Recorded</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${records.map(record => {
                            const amount = Number(record.amount || 0);
                            const rawMonth = String(record.harvest_month || '');
                            const monthText = rawMonth
                                ? new Date(rawMonth + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long' })
                                : '—';
                            const recordedAt = record.harvested_at
                                ? new Date(String(record.harvested_at).replace(' ', 'T')).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' })
                                : '—';
                            return ` <
                    tr data - history - row = "${Number(record.id)}" >
                    <
                    td > < strong > $ {
                        historyEscapeHtml(monthText)
                    } < /strong></td >
                    <
                    td class = "history-amount" > $ {
                        formatHistoryPeso(amount)
                    } < /td> <
                    td > < span class = "pisonet-muted" > $ {
                        record.remarks ? historyEscapeHtml(record.remarks) : '—'
                    } < /span></td >
                    <
                    td > $ {
                        historyEscapeHtml(record.recorded_by || 'System User')
                    } < /td> <
                    td > < span class = "pisonet-muted" > $ {
                        historyEscapeHtml(recordedAt)
                    } < /span></td >
                    <
                    td >
                    <
                    div class = "pisonet-history-actions" >
                    <
                    button type = "button"
                class = "pisonet-mini-button pisonet-mini-secondary history-edit-harvest"
                data - unit - id = "${Number(record.unit_id)}"
                data - unit - name = "${historyEscapeHtml(record.unit_name || '')}"
                data - harvest - month = "${historyEscapeHtml(rawMonth)}"
                data - amount = "${amount.toFixed(2)}"
                data - remarks = "${historyEscapeHtml(record.remarks || '')}" >
                    Edit <
                    /button> <
                    button type = "button"
                class = "pisonet-mini-button pisonet-mini-danger history-delete-harvest"
                data - id = "${Number(record.id)}"
                data - unit - name = "${historyEscapeHtml(record.unit_name || '')}"
                data - month = "${historyEscapeHtml(monthText)}"
                data - amount = "${amount.toFixed(2)}" >
                    Delete <
                    /button> <
                    /div> <
                    /td> <
                    /tr>
                `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;

                historyBody.querySelectorAll('.history-edit-harvest').forEach(button => {
                    button.addEventListener('click', function() {
                        closePisonetHistoryModal();
                        openModal(button);
                    });
                });

                historyBody.querySelectorAll('.history-delete-harvest').forEach(button => {
                    button.addEventListener('click', function() {
                        openDeleteHarvestModal(button);
                    });
                });
            }

            async function openPisonetHistoryModal(button) {
                if (!historyModal || !historyBody) return;

                const unitId = button.dataset.unitId || '';
                const unitName = button.dataset.unitName || 'Pisonet';

                historyTitle.textContent = unitName + ' — Harvest History';
                historySubtitle.textContent = 'All recorded monthly harvests for this machine.';
                historyBody.innerHTML = '<div class="pisonet-history-empty">Loading history...</div>';
                historyCount.textContent = 'Loading...';

                historyModal.classList.add('is-open');
                historyModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('pisonet-modal-open');

                try {
                    const response = await fetch('history.php?id=' + encodeURIComponent(unitId), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Unable to load Pisonet history.');
                    }

                    renderPisonetHistory(data.records || []);
                } catch (error) {
                    historyBody.innerHTML = '<div class="pisonet-history-empty">' + historyEscapeHtml(error.message || 'Unable to load Pisonet history.') + '</div>';
                    historyCount.textContent = 'Unable to load';
                }
            }

            const addPisonetOpenButtons = [
                document.getElementById('openAddPisonetModal'),
                document.getElementById('openAddPisonetEmpty')
            ].filter(Boolean);

            addPisonetOpenButtons.forEach(function(button) {
                button.addEventListener('click', openAddPisonetModal);
            });

            closeAddPisonetButton.addEventListener('click', closeAddPisonetModal);
            cancelAddPisonetButton.addEventListener('click', closeAddPisonetModal);

            addPisonetModal.addEventListener('click', function(event) {
                if (event.target === addPisonetModal) {
                    closeAddPisonetModal();
                }
            });

            addPisonetForm.addEventListener('submit', async function(event) {
                event.preventDefault();
                hideAddPisonetError();

                const unitName = newPisonetName.value.trim();
                const status = newPisonetStatus.value;

                if (!unitName) {
                    showAddPisonetError('Please enter a Pisonet name.');
                    newPisonetName.focus();
                    return;
                }

                if (unitName.length > 50) {
                    showAddPisonetError('The Pisonet name must not exceed 50 characters.');
                    newPisonetName.focus();
                    return;
                }

                if (!['working', 'maintenance', 'offline'].includes(status)) {
                    showAddPisonetError('Please select a valid Pisonet status.');
                    newPisonetStatus.focus();
                    return;
                }

                saveAddPisonetButton.disabled = true;
                saveAddPisonetButton.textContent = 'Adding...';

                try {
                    const response = await fetch('add_unit.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: new URLSearchParams({
                            unit_name: unitName,
                            status: status
                        })
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Unable to add the Pisonet.');
                    }

                    closeAddPisonetModal();
                    showFeedback('success', 'Pisonet Added', data.message || 'Pisonet added successfully.', 900);

                    window.setTimeout(function() {
                        window.location.href = 'index.php?month=<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>';
                    }, 980);

                } catch (error) {
                    showAddPisonetError(error.message || 'Unable to add the Pisonet.');
                } finally {
                    saveAddPisonetButton.disabled = false;
                    saveAddPisonetButton.textContent = 'Add Pisonet';
                }
            });

            document.querySelectorAll('.open-harvest-modal').forEach(function(button) {
                button.addEventListener('click', function() {
                    openModal(button);
                });
            });

            document.querySelectorAll('.open-history-modal').forEach(function(button) {
                button.addEventListener('click', function() {
                    openPisonetHistoryModal(button);
                });
            });

            document.querySelectorAll('.open-rename-modal').forEach(function(button) {
                button.addEventListener('click', function() {
                    openRenameModal(button);
                });
            });

            document.querySelectorAll('.delete-pisonet-button').forEach(function(button) {
                button.addEventListener('click', function() {
                    openDeletePisonetModal(button);
                });
            });

            document.querySelectorAll('.delete-harvest-button').forEach(function(button) {
                button.addEventListener('click', function() {
                    openDeleteHarvestModal(button);
                });
            });

            closeFeedbackButton.addEventListener('click', closeFeedback);

            closeDeletePisonetButton.addEventListener('click', closeDeletePisonetModal);
            cancelDeletePisonetButton.addEventListener('click', closeDeletePisonetModal);

            deletePisonetModal.addEventListener('click', function(event) {
                if (event.target === deletePisonetModal) {
                    closeDeletePisonetModal();
                }
            });

            confirmDeletePisonetButton.addEventListener('click', async function() {
                if (!deleteUnitId) {
                    closeDeletePisonetModal();
                    showFeedback('error', 'Delete Failed', 'The Pisonet could not be identified.', 0);
                    return;
                }

                confirmDeletePisonetButton.disabled = true;
                confirmDeletePisonetButton.textContent = 'Deleting...';

                try {
                    const response = await fetch('delete_unit.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: new URLSearchParams({
                            id: deleteUnitId
                        })
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Unable to delete the Pisonet.');
                    }

                    closeDeletePisonetModal();
                    showFeedback('success', 'Pisonet Deleted', data.message || 'Pisonet deleted successfully.', 900);

                    window.setTimeout(function() {
                        window.location.href = 'index.php?month=<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>';
                    }, 980);

                } catch (error) {
                    closeDeletePisonetModal();
                    showFeedback('error', 'Delete Failed', error.message || 'Unable to delete the Pisonet.', 0);
                } finally {
                    confirmDeletePisonetButton.disabled = false;
                    confirmDeletePisonetButton.textContent = 'Delete Pisonet';
                }
            });

            closeDeleteHarvestButton.addEventListener('click', closeDeleteHarvestModal);
            cancelDeleteHarvestButton.addEventListener('click', closeDeleteHarvestModal);

            deleteHarvestModal.addEventListener('click', function(event) {
                if (event.target === deleteHarvestModal) {
                    closeDeleteHarvestModal();
                }
            });

            confirmDeleteHarvestButton.addEventListener('click', async function() {
                if (!deleteHarvestId) {
                    closeDeleteHarvestModal();
                    showFeedback('error', 'Delete Failed', 'The harvest record could not be identified.', 0);
                    return;
                }

                confirmDeleteHarvestButton.disabled = true;
                confirmDeleteHarvestButton.textContent = 'Deleting...';

                try {
                    const response = await fetch('delete_harvest.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: new URLSearchParams({
                            id: deleteHarvestId
                        })
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Unable to delete the harvest record.');
                    }

                    closeDeleteHarvestModal();
                    showFeedback('success', 'Harvest Deleted', data.message || 'Harvest record deleted successfully.', 900);

                    window.setTimeout(function() {
                        window.location.href = 'index.php?month=<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>';
                    }, 980);

                } catch (error) {
                    closeDeleteHarvestModal();
                    showFeedback('error', 'Delete Failed', error.message || 'Unable to delete the harvest record.', 0);
                } finally {
                    confirmDeleteHarvestButton.disabled = false;
                    confirmDeleteHarvestButton.textContent = 'Delete Harvest';
                }
            });

            document.querySelectorAll('.pisonet-status-control').forEach(function(select) {
                select.addEventListener('change', async function() {
                    const unitId = select.dataset.id;
                    const status = select.value;
                    const previousValue = select.dataset.previousValue || status;
                    const unitCard = select.closest('.pisonet-unit-card');
                    const statusBadge = unitCard ? unitCard.querySelector('.pisonet-status-badge') : null;

                    select.disabled = true;

                    try {
                        const response = await fetch('update_status.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                            },
                            body: new URLSearchParams({
                                id: unitId,
                                status: status
                            })
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Unable to update the Pisonet status.');
                        }

                        select.dataset.previousValue = status;

                        showFeedback('success', 'Status Updated', data.message || 'Pisonet status updated.', 900);

                        window.setTimeout(function() {
                            window.location.reload();
                        }, 980);

                    } catch (error) {
                        select.value = previousValue;

                        showFeedback('error', 'Status Update Failed', error.message || 'Unable to update the Pisonet status.');

                        if (statusBadge) {
                            statusBadge.focus?.();
                        }
                    } finally {
                        select.disabled = false;
                    }
                });
            });

            renameForm.addEventListener('submit', async function(event) {
                event.preventDefault();
                hideRenameError();

                const id = renameUnitId.value.trim();
                const unitName = renameUnitName.value.trim();

                if (!id) {
                    showRenameError('Invalid Pisonet unit.');
                    return;
                }

                if (!unitName) {
                    showRenameError('Please enter a Pisonet name.');
                    renameUnitName.focus();
                    return;
                }

                if (unitName.length > 50) {
                    showRenameError('The Pisonet name must not exceed 50 characters.');
                    renameUnitName.focus();
                    return;
                }

                saveRenameButton.disabled = true;
                saveRenameButton.textContent = 'Saving...';

                try {
                    const response = await fetch('rename_unit.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: new URLSearchParams({
                            id: id,
                            unit_name: unitName
                        })
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Unable to rename the Pisonet.');
                    }

                    closeRenameModal();
                    showFeedback('success', 'Pisonet Renamed', data.message || 'Pisonet name updated successfully.', 950);

                    window.setTimeout(function() {
                        window.location.reload();
                    }, 1030);

                } catch (error) {
                    showRenameError(error.message || 'Unable to rename the Pisonet.');
                } finally {
                    saveRenameButton.disabled = false;
                    saveRenameButton.textContent = 'Save Name';
                }
            });

            form.addEventListener('submit', async function(event) {
                event.preventDefault();
                hideError();

                const unitId = unitIdInput.value.trim();
                const harvestMonth = monthInput.value.trim();
                const amount = amountInput.value.trim();
                const remarks = remarksInput.value.trim();

                if (!unitId) {
                    showError('Please select a Pisonet.');
                    return;
                }

                if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(harvestMonth)) {
                    showError('Please select a valid harvest month.');
                    monthInput.focus();
                    return;
                }

                const numericAmount = Number(amount);

                if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
                    showError('Enter an amount greater than ₱0.00.');
                    amountInput.focus();
                    return;
                }

                if (remarks.length > 500) {
                    showError('Remarks must not exceed 500 characters.');
                    remarksInput.focus();
                    return;
                }

                saveButton.disabled = true;
                saveButton.textContent = 'Saving...';

                try {
                    const response = await fetch('save_harvest.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: new URLSearchParams({
                            unit_id: unitId,
                            harvest_month: harvestMonth,
                            amount: numericAmount.toFixed(2),
                            remarks: remarks
                        })
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Unable to save the harvest.');
                    }

                    closeModal();
                    showFeedback('success', 'Harvest Saved', data.message || 'Monthly harvest saved successfully.', 900);

                    const targetMonth = encodeURIComponent(harvestMonth);
                    window.setTimeout(function() {
                        window.location.href = 'index.php?month=' + targetMonth;
                    }, 980);

                } catch (error) {
                    showError(error.message || 'Unable to save the harvest.');
                } finally {
                    saveButton.disabled = false;
                    saveButton.textContent = 'Save Harvest';
                }
            });

            closeButton.addEventListener('click', closeModal);
            cancelButton.addEventListener('click', closeModal);

            if (closeHistoryButton) {
                closeHistoryButton.addEventListener('click', closePisonetHistoryModal);
            }

            if (closeHistoryFooterButton) {
                closeHistoryFooterButton.addEventListener('click', closePisonetHistoryModal);
            }

            if (historyModal) {
                historyModal.addEventListener('click', function(event) {
                    if (event.target === historyModal) {
                        closePisonetHistoryModal();
                    }
                });
            }
            closeRenameButton.addEventListener('click', closeRenameModal);
            cancelRenameButton.addEventListener('click', closeRenameModal);

            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            renameModal.addEventListener('click', function(event) {
                if (event.target === renameModal) {
                    closeRenameModal();
                }
            });

            feedback.addEventListener('click', function(event) {
                if (event.target === feedback) {
                    closeFeedback();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key !== 'Escape') {
                    return;
                }

                if (feedback.classList.contains('is-open')) {
                    closeFeedback();
                    return;
                }

                if (addPisonetModal.classList.contains('is-open')) {
                    closeAddPisonetModal();
                    return;
                }

                if (renameModal.classList.contains('is-open')) {
                    closeRenameModal();
                    return;
                }

                if (deletePisonetModal.classList.contains('is-open')) {
                    closeDeletePisonetModal();
                    return;
                }

                if (deleteHarvestModal.classList.contains('is-open')) {
                    closeDeleteHarvestModal();
                    return;
                }

                if (historyModal && historyModal.classList.contains('is-open')) {
                    closePisonetHistoryModal();
                    return;
                }

                if (modal.classList.contains('is-open')) {
                    closeModal();
                }
            });

            amountInput.addEventListener('input', hideError);
            monthInput.addEventListener('change', hideError);
            remarksInput.addEventListener('input', hideError);
            renameUnitName.addEventListener('input', hideRenameError);
            newPisonetName.addEventListener('input', hideAddPisonetError);
            newPisonetStatus.addEventListener('change', hideAddPisonetError);
        })();
    </script>

</body>

</html>
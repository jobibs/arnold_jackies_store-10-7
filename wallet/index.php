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
$activeMenu = 'wallet';
$currentSection = 'wallet';

$today = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| TODAY'S WALLET SUMMARY
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(
            CASE
                WHEN transaction_type = 'cash_in'
                THEN wallet_amount
                ELSE 0
            END
        ), 0) AS cash_in,

        COALESCE(SUM(
            CASE
                WHEN transaction_type = 'cash_out'
                THEN wallet_amount
                ELSE 0
            END
        ), 0) AS cash_out,

        COALESCE(SUM(service_fee), 0) AS fees,

        COUNT(*) AS transactions

    FROM wallet_transactions

    WHERE DATE(transaction_date) = ?
");

$stmt->execute([$today]);

$summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'cash_in' => 0,
    'cash_out' => 0,
    'fees' => 0,
    'transactions' => 0
];

/*
|--------------------------------------------------------------------------
| RECENT TRANSACTIONS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->query("
    SELECT
        wt.*,
        u.full_name
    FROM wallet_transactions wt
    LEFT JOIN users u
        ON u.id = wt.user_id
    ORDER BY
        wt.transaction_date DESC,
        wt.id DESC
    LIMIT 100
");

$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

function wallet_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Wallet - Arnold & Jackie's Store
    </title>

    <link
        rel="stylesheet"
        href="<?= wallet_h($basePath) ?>assets/css/admin.css">

    <style>
        /* =========================================================
           WALLET PAGE — MODERN UI
           ========================================================= */

        .wallet-page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 24px;
        }

        .wallet-page-kicker {
            margin-bottom: 7px;
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .wallet-page-title {
            margin: 0;
            color: #111827;
            font-size: 30px;
            font-weight: 800;
            line-height: 1.12;
            letter-spacing: -.045em;
        }

        .wallet-page-subtitle {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .wallet-header-actions {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap;
        }

        .wallet-header-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 15px;
            border-radius: 11px;
            border: 1px solid #dbe2ea;
            background: #fff;
            color: #334155;
            text-decoration: none;
            font-size: 12px;
            font-weight: 750;
            transition:
                transform .16s ease,
                border-color .16s ease,
                background .16s ease,
                box-shadow .16s ease;
        }

        .wallet-header-button:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #111827;
            transform: translateY(-1px);
        }

        .wallet-header-button.primary {
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
            box-shadow: 0 7px 18px rgba(37, 99, 235, .16);
        }

        .wallet-header-button.primary:hover {
            border-color: #1d4ed8;
            background: #1d4ed8;
            color: #fff;
            box-shadow: 0 9px 22px rgba(37, 99, 235, .20);
        }

        .wallet-header-button .button-icon {
            margin-right: 7px;
            font-size: 15px;
            line-height: 1;
        }

        .wallet-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .wallet-kpi {
            position: relative;
            min-width: 0;
            overflow: hidden;
            padding: 18px;
            border: 1px solid #e7ebf0;
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 2px 7px rgba(15, 23, 42, .035);
            transition:
                transform .18s ease,
                box-shadow .18s ease,
                border-color .18s ease;
        }

        .wallet-kpi:hover {
            transform: translateY(-2px);
            border-color: #dce3ea;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .07);
        }

        .wallet-kpi::after {
            content: "";
            position: absolute;
            right: -24px;
            bottom: -34px;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: rgba(37, 99, 235, .055);
        }

        .wallet-kpi-label {
            color: #7b8494;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .045em;
            text-transform: uppercase;
        }

        .wallet-kpi-value {
            margin-top: 9px;
            color: #111827;
            font-size: 25px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -.035em;
        }

        .wallet-kpi-meta {
            margin-top: 7px;
            color: #94a3b8;
            font-size: 11px;
        }

        .wallet-kpi-icon {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 14px;
            font-weight: 800;
        }

        .wallet-table-card {
            overflow: hidden;
            border: 1px solid #e7ebf0;
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 2px 7px rgba(15, 23, 42, .035);
        }

        .wallet-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f4;
        }

        .wallet-table-heading {
            margin: 0;
            color: #111827;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .wallet-table-caption {
            margin-top: 4px;
            color: #94a3b8;
            font-size: 11px;
        }

        .wallet-count-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
        }

        .wallet-table-wrap {
            overflow-x: auto;
        }

        .wallet-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        .wallet-table th {
            padding: 11px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #edf0f4;
            color: #7b8494;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .055em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .wallet-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f2f5;
            color: #334155;
            font-size: 12px;
            vertical-align: middle;
        }

        .wallet-table tbody tr {
            transition: background .14s ease;
        }

        .wallet-table tbody tr:hover {
            background: #fafbfc;
        }

        .wallet-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .wallet-date {
            color: #64748b;
            white-space: nowrap;
        }

        .wallet-number {
            color: #111827;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .015em;
            white-space: nowrap;
        }

        .wallet-provider {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 9px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            color: #334155;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .wallet-type-badge {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .035em;
            text-transform: uppercase;
        }

        .wallet-type-in {
            background: #ecfdf3;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .wallet-type-out {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }

        .wallet-money {
            color: #111827;
            font-weight: 750;
            white-space: nowrap;
        }

        .wallet-fee {
            color: #2563eb;
            font-weight: 750;
            white-space: nowrap;
        }

        .wallet-flow-positive {
            color: #15803d;
            font-weight: 800;
            white-space: nowrap;
        }

        .wallet-flow-negative {
            color: #dc2626;
            font-weight: 800;
            white-space: nowrap;
        }

        .wallet-customer {
            color: #475569;
            white-space: nowrap;
        }

        .wallet-empty {
            padding: 58px 20px !important;
            text-align: center;
        }

        .wallet-empty-icon {
            width: 42px;
            height: 42px;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            background: #f1f5f9;
            color: #94a3b8;
            font-size: 17px;
            font-weight: 800;
        }

        .wallet-empty-title {
            color: #475569;
            font-size: 13px;
            font-weight: 800;
        }

        .wallet-empty-text {
            margin-top: 4px;
            color: #94a3b8;
            font-size: 11px;
        }

        /* =========================================================
           WALLET TRANSACTION MODAL
           ========================================================= */

        .wallet-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1080;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, .58);
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition:
                opacity .24s ease,
                visibility 0s linear .24s;
        }

        .wallet-modal-backdrop.is-open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transition:
                opacity .24s ease,
                visibility 0s linear 0s;
        }

        .wallet-modal {
            width: min(820px, 100%);
            max-height: min(850px, calc(100vh - 48px));
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, .92);
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(15, 23, 42, .22);
            transform: translateY(14px) scale(.975);
            opacity: .96;
            transition:
                transform .28s cubic-bezier(.22, 1, .36, 1),
                opacity .18s ease;
        }

        .wallet-modal-backdrop.is-open .wallet-modal {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .wallet-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 24px 18px;
            border-bottom: 1px solid #edf0f4;
        }

        .wallet-modal-eyebrow {
            margin-bottom: 5px;
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .wallet-modal-title {
            margin: 0;
            color: #111827;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -.035em;
        }

        .wallet-modal-subtitle {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .wallet-modal-close {
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

        .wallet-modal-close:hover {
            background: #f1f5f9;
            color: #111827;
            transform: translateY(-1px);
        }

        .wallet-modal-body {
            overflow-y: auto;
            padding: 22px 24px 24px;
        }

        .wallet-modal-error {
            display: none;
            margin-bottom: 17px;
            padding: 11px 13px;
            border: 1px solid #fecaca;
            border-radius: 10px;
            background: #fff7f7;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 650;
            line-height: 1.45;
        }

        .wallet-modal-error.is-visible {
            display: block;
            animation: walletModalErrorIn .18s ease both;
        }

        @keyframes walletModalErrorIn {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .wallet-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px 16px;
        }

        .wallet-field.full {
            grid-column: 1 / -1;
        }

        .wallet-label {
            display: flex;
            align-items: center;
            gap: 5px;
            min-height: 18px;
            margin: 0 0 7px;
            color: #334155;
            font-size: 12px;
            font-weight: 750;
            line-height: 1.35;
        }

        .wallet-label-required::after {
            content: "*";
            color: #ef4444;
            font-weight: 800;
        }

        .wallet-label-optional {
            color: #94a3b8;
            font-size: 11px;
            font-weight: 500;
        }

        .wallet-control,
        .wallet-select {
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
            line-height: 44px;
            box-sizing: border-box;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .02);
            transition:
                border-color .16s ease,
                box-shadow .16s ease,
                background .16s ease;
        }

        .wallet-control::placeholder {
            color: #a1aab8;
            opacity: 1;
        }

        .wallet-control:hover,
        .wallet-select:hover {
            border-color: #cbd5e1;
        }

        .wallet-control:focus,
        .wallet-select:focus {
            border-color: #2563eb;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
        }

        .wallet-select {
            line-height: normal;
            appearance: auto;
            padding-right: 10px;
            cursor: pointer;
        }

        .wallet-choice-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
        }

        .wallet-choice {
            position: relative;
        }

        .wallet-choice input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .wallet-choice-button {
            width: 100%;
            min-height: 52px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 11px;
            padding: 0 13px;
            border: 1px solid #dbe2ea;
            border-radius: 11px;
            background: #fff;
            color: #475569;
            font: inherit;
            font-size: 12px;
            font-weight: 750;
            cursor: pointer;
            text-align: left;
            transition:
                border-color .16s ease,
                background .16s ease,
                box-shadow .16s ease,
                transform .16s ease;
        }

        .wallet-choice-button:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            transform: translateY(-1px);
        }

        .wallet-choice input:checked+.wallet-choice-button {
            border-color: #2563eb;
            background: #eff6ff;
            color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .07);
        }

        .wallet-choice-icon {
            width: 28px;
            height: 28px;
            flex: 0 0 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 10px;
            font-weight: 850;
        }

        .wallet-choice input:checked+.wallet-choice-button .wallet-choice-icon {
            background: #dbeafe;
            color: #2563eb;
        }

        .wallet-input-group {
            display: flex;
            width: 100%;
            height: 44px;
        }

        .wallet-input-prefix {
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
            font-weight: 800;
            box-sizing: border-box;
        }

        .wallet-input-group .wallet-control {
            flex: 1;
            border-radius: 0 10px 10px 0;
        }

        .wallet-input-group:focus-within .wallet-input-prefix {
            border-color: #2563eb;
            background: #eff6ff;
            color: #2563eb;
        }

        .wallet-fee-control {
            background: #f8fafc;
            color: #2563eb;
            font-weight: 800;
        }

        .wallet-help {
            margin: 6px 1px 0;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.4;
        }

        .wallet-flow-card {
            margin-top: 17px;
            padding: 15px 16px;
            border: 1px solid #e7ebf0;
            border-radius: 12px;
            background: #f8fafc;
        }

        .wallet-flow-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .wallet-flow-label {
            color: #475569;
            font-size: 12px;
            font-weight: 750;
        }

        .wallet-flow-amount {
            color: #111827;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -.02em;
            white-space: nowrap;
        }

        .wallet-flow-note {
            margin-top: 4px;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.4;
        }

        .wallet-modal-actions {
            position: sticky;
            bottom: -24px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 9px;
            margin: 22px -24px -24px;
            padding: 16px 24px;
            border-top: 1px solid #edf0f4;
            background: rgba(255, 255, 255, .96);
            backdrop-filter: blur(10px);
        }

        .wallet-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 15px;
            border-radius: 10px;
            border: 1px solid #dbe2ea;
            background: #fff;
            color: #475569;
            font: inherit;
            font-size: 12px;
            font-weight: 750;
            cursor: pointer;
            transition:
                transform .16s ease,
                border-color .16s ease,
                background .16s ease,
                box-shadow .16s ease;
        }

        .wallet-action-button:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #111827;
        }

        .wallet-action-button.primary {
            min-width: 128px;
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
            box-shadow: 0 5px 14px rgba(37, 99, 235, .16);
        }

        .wallet-action-button.primary:hover {
            border-color: #1d4ed8;
            background: #1d4ed8;
            box-shadow: 0 7px 18px rgba(37, 99, 235, .20);
            transform: translateY(-1px);
        }

        .wallet-action-button.primary:disabled {
            opacity: .65;
            cursor: wait;
            transform: none;
            box-shadow: none;
        }

        body.wallet-modal-open {
            overflow: hidden;
        }

        @media (max-width: 1100px) {
            .wallet-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .wallet-page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .wallet-header-actions {
                width: 100%;
            }

            .wallet-header-button {
                flex: 1 1 auto;
            }

            .wallet-form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .wallet-field.full {
                grid-column: auto;
            }

            .wallet-modal-backdrop {
                align-items: flex-end;
                padding: 0;
            }

            .wallet-modal {
                width: 100%;
                max-height: 94vh;
                border-radius: 20px 20px 0 0;
                transform: translateY(24px);
            }

            .wallet-modal-backdrop.is-open .wallet-modal {
                transform: translateY(0);
            }

            .wallet-modal-header,
            .wallet-modal-body {
                padding-left: 18px;
                padding-right: 18px;
            }

            .wallet-modal-actions {
                margin-left: -18px;
                margin-right: -18px;
                padding-left: 18px;
                padding-right: 18px;
            }
        }

        @media (max-width: 560px) {
            .wallet-page-title {
                font-size: 26px;
            }

            .wallet-kpi-grid {
                grid-template-columns: 1fr;
            }

            .wallet-kpi-value {
                font-size: 23px;
            }

            .wallet-choice-grid {
                grid-template-columns: 1fr;
            }

            .wallet-modal-title {
                font-size: 19px;
            }

            .wallet-flow-top {
                align-items: flex-start;
                flex-direction: column;
                gap: 4px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .wallet-modal-backdrop,
            .wallet-modal,
            .wallet-kpi,
            .wallet-header-button,
            .wallet-choice-button,
            .wallet-action-button {
                transition: none !important;
            }
        }
    </style>

</head>

<body>

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="main">

        <?php require_once $basePath . 'includes/topbar.php'; ?>

        <main class="content">

            <div class="wallet-page-header">

                <div>
                    <div class="wallet-page-kicker">
                        Digital Wallet
                    </div>

                    <h1 class="wallet-page-title">
                        GCash &amp; Maya
                    </h1>

                    <p class="wallet-page-subtitle">
                        Track cash-ins, cash-outs and service fees in one place.
                    </p>
                </div>

                <div class="wallet-header-actions">

                    <a
                        href="fees.php"
                        class="wallet-header-button">
                        Fee Settings
                    </a>

                    <button
                        type="button"
                        class="wallet-header-button primary"
                        id="openWalletTransactionModal">
                        <span class="button-icon">+</span>
                        New Transaction
                    </button>

                </div>

            </div>

            <div class="wallet-kpi-grid">

                <div class="wallet-kpi">
                    <div class="wallet-kpi-icon">↓</div>
                    <div class="wallet-kpi-label">Today's Cash In</div>
                    <div class="wallet-kpi-value">
                        ₱<?= number_format((float)$summary['cash_in'], 2) ?>
                    </div>
                    <div class="wallet-kpi-meta">
                        Wallet value received from customers
                    </div>
                </div>

                <div class="wallet-kpi">
                    <div class="wallet-kpi-icon">↑</div>
                    <div class="wallet-kpi-label">Today's Cash Out</div>
                    <div class="wallet-kpi-value">
                        ₱<?= number_format((float)$summary['cash_out'], 2) ?>
                    </div>
                    <div class="wallet-kpi-meta">
                        Wallet value released to customers
                    </div>
                </div>

                <div class="wallet-kpi">
                    <div class="wallet-kpi-icon">₱</div>
                    <div class="wallet-kpi-label">Today's Service Fees</div>
                    <div class="wallet-kpi-value">
                        ₱<?= number_format((float)$summary['fees'], 2) ?>
                    </div>
                    <div class="wallet-kpi-meta">
                        Income from wallet transactions
                    </div>
                </div>

                <div class="wallet-kpi">
                    <div class="wallet-kpi-icon">#</div>
                    <div class="wallet-kpi-label">Transactions Today</div>
                    <div class="wallet-kpi-value">
                        <?= number_format((int)$summary['transactions']) ?>
                    </div>
                    <div class="wallet-kpi-meta">
                        GCash and Maya activity recorded today
                    </div>
                </div>

            </div>

            <section class="wallet-table-card">

                <div class="wallet-table-header">

                    <div>
                        <h2 class="wallet-table-heading">
                            Recent Wallet Transactions
                        </h2>

                        <div class="wallet-table-caption">
                            Latest 100 wallet transactions
                        </div>
                    </div>

                    <div class="wallet-count-pill">
                        <?= number_format(count($transactions)) ?> records
                    </div>

                </div>

                <div class="wallet-table-wrap">

                    <table class="wallet-table">

                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction #</th>
                                <th>Provider</th>
                                <th>Type</th>
                                <th>Customer</th>
                                <th>Wallet Amount</th>
                                <th>Fee</th>
                                <th>Cash Flow</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if ($transactions): ?>

                                <?php foreach ($transactions as $t): ?>

                                    <?php
                                    $cashFlow = $t['transaction_type'] === 'cash_in'
                                        ? (float)$t['total_received']
                                        : -(float)$t['total_paid'];

                                    $isCashIn = $t['transaction_type'] === 'cash_in';
                                    ?>

                                    <tr>

                                        <td class="wallet-date">
                                            <?= date(
                                                'M d, Y h:i A',
                                                strtotime($t['transaction_date'])
                                            ) ?>
                                        </td>

                                        <td class="wallet-number">
                                            <?= wallet_h($t['transaction_number']) ?>
                                        </td>

                                        <td>
                                            <span class="wallet-provider">
                                                <?= strtoupper(wallet_h($t['provider'])) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="wallet-type-badge <?= $isCashIn
                                                                                ? 'wallet-type-in'
                                                                                : 'wallet-type-out' ?>">
                                                <?= strtoupper(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $t['transaction_type']
                                                    )
                                                ) ?>
                                            </span>
                                        </td>

                                        <td class="wallet-customer">
                                            <?= $t['customer_name']
                                                ? wallet_h($t['customer_name'])
                                                : '—' ?>
                                        </td>

                                        <td class="wallet-money">
                                            ₱<?= number_format(
                                                    (float)$t['wallet_amount'],
                                                    2
                                                ) ?>
                                        </td>

                                        <td class="wallet-fee">
                                            ₱<?= number_format(
                                                    (float)$t['service_fee'],
                                                    2
                                                ) ?>
                                        </td>

                                        <td class="<?= $cashFlow >= 0
                                                        ? 'wallet-flow-positive'
                                                        : 'wallet-flow-negative' ?>">
                                            <?= $cashFlow >= 0 ? '+' : '−' ?>₱<?= number_format(
                                                                                    abs($cashFlow),
                                                                                    2
                                                                                ) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td
                                        colspan="8"
                                        class="wallet-empty">

                                        <div class="wallet-empty-icon">
                                            ₱
                                        </div>

                                        <div class="wallet-empty-title">
                                            No wallet transactions yet
                                        </div>

                                        <div class="wallet-empty-text">
                                            Create your first GCash or Maya transaction to get started.
                                        </div>

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </main>

    </div>

    <!-- =========================================================
         NEW WALLET TRANSACTION MODAL
         ========================================================= -->

    <div
        class="wallet-modal-backdrop"
        id="walletTransactionModal"
        aria-hidden="true">

        <section
            class="wallet-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="walletTransactionModalTitle">

            <div class="wallet-modal-header">

                <div>

                    <div class="wallet-modal-eyebrow">
                        Wallet
                    </div>

                    <h2
                        class="wallet-modal-title"
                        id="walletTransactionModalTitle">
                        New Transaction
                    </h2>

                    <p class="wallet-modal-subtitle">
                        Record a GCash or Maya cash-in / cash-out transaction.
                    </p>

                </div>

                <button
                    type="button"
                    class="wallet-modal-close"
                    id="closeWalletTransactionModal"
                    aria-label="Close wallet transaction modal">
                    ×
                </button>

            </div>

            <div class="wallet-modal-body">

                <div
                    class="wallet-modal-error"
                    id="walletTransactionModalError"
                    role="alert"></div>

                <form
                    method="post"
                    action="transaction.php"
                    id="walletTransactionForm">

                    <div class="wallet-form-grid">

                        <div class="wallet-field">

                            <label class="wallet-label wallet-label-required">
                                Provider
                            </label>

                            <div class="wallet-choice-grid">

                                <label class="wallet-choice">
                                    <input
                                        type="radio"
                                        name="provider_choice"
                                        value="gcash"
                                        checked>

                                    <span class="wallet-choice-button">
                                        <span class="wallet-choice-icon">
                                            G
                                        </span>
                                        <span>GCash</span>
                                    </span>
                                </label>

                                <label class="wallet-choice">
                                    <input
                                        type="radio"
                                        name="provider_choice"
                                        value="maya">

                                    <span class="wallet-choice-button">
                                        <span class="wallet-choice-icon">
                                            M
                                        </span>
                                        <span>Maya</span>
                                    </span>
                                </label>

                            </div>

                            <input
                                type="hidden"
                                name="provider"
                                id="walletProvider"
                                value="gcash">

                        </div>

                        <div class="wallet-field">

                            <label class="wallet-label wallet-label-required">
                                Transaction Type
                            </label>

                            <div class="wallet-choice-grid">

                                <label class="wallet-choice">
                                    <input
                                        type="radio"
                                        name="transaction_type_choice"
                                        value="cash_in"
                                        checked>

                                    <span class="wallet-choice-button">
                                        <span class="wallet-choice-icon">
                                            ↓
                                        </span>
                                        <span>Cash In</span>
                                    </span>
                                </label>

                                <label class="wallet-choice">
                                    <input
                                        type="radio"
                                        name="transaction_type_choice"
                                        value="cash_out">

                                    <span class="wallet-choice-button">
                                        <span class="wallet-choice-icon">
                                            ↑
                                        </span>
                                        <span>Cash Out</span>
                                    </span>
                                </label>

                            </div>

                            <input
                                type="hidden"
                                name="transaction_type"
                                id="walletTransactionType"
                                value="cash_in">

                        </div>

                        <div class="wallet-field">

                            <label
                                for="walletCustomer"
                                class="wallet-label">
                                Customer Name
                                <span class="wallet-label-optional">
                                    (optional)
                                </span>
                            </label>

                            <input
                                type="text"
                                class="wallet-control"
                                id="walletCustomer"
                                name="customer_name"
                                placeholder="e.g. Juan Dela Cruz"
                                autocomplete="off">

                        </div>

                        <div class="wallet-field">

                            <label
                                for="walletReference"
                                class="wallet-label">
                                Reference Number
                                <span class="wallet-label-optional">
                                    (optional)
                                </span>
                            </label>

                            <input
                                type="text"
                                class="wallet-control"
                                id="walletReference"
                                name="reference_number"
                                placeholder="Enter reference number"
                                autocomplete="off">

                        </div>

                        <div class="wallet-field">

                            <label
                                for="walletAmount"
                                class="wallet-label wallet-label-required">
                                Wallet Amount
                            </label>

                            <div class="wallet-input-group">

                                <span class="wallet-input-prefix">
                                    ₱
                                </span>

                                <input
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="wallet-control"
                                    id="walletAmount"
                                    name="wallet_amount"
                                    placeholder="0.00"
                                    inputmode="decimal"
                                    required>

                            </div>

                        </div>

                        <div class="wallet-field">

                            <label
                                for="walletFee"
                                class="wallet-label">
                                Service Fee
                            </label>

                            <div class="wallet-input-group">

                                <span class="wallet-input-prefix">
                                    ₱
                                </span>

                                <input
                                    type="text"
                                    class="wallet-control wallet-fee-control"
                                    id="walletFee"
                                    value="0.00"
                                    readonly>

                            </div>

                            <div class="wallet-help">
                                Automatically pulled from active fee rules.
                            </div>

                        </div>

                        <div class="wallet-field full">

                            <label
                                for="walletRemarks"
                                class="wallet-label">
                                Remarks
                                <span class="wallet-label-optional">
                                    (optional)
                                </span>
                            </label>

                            <input
                                type="text"
                                class="wallet-control"
                                id="walletRemarks"
                                name="remarks"
                                placeholder="Add a note for this transaction"
                                autocomplete="off">

                        </div>

                    </div>

                    <div class="wallet-flow-card">

                        <div class="wallet-flow-top">

                            <div>
                                <div
                                    class="wallet-flow-label"
                                    id="walletFlowLabel">
                                    Customer pays store
                                </div>

                                <div
                                    class="wallet-flow-note"
                                    id="walletFlowNote">
                                    Wallet amount + service fee.
                                </div>
                            </div>

                            <div
                                class="wallet-flow-amount"
                                id="walletFlowAmount">
                                ₱0.00
                            </div>

                        </div>

                    </div>

                    <div class="wallet-modal-actions">

                        <button
                            type="button"
                            class="wallet-action-button"
                            id="cancelWalletTransaction">
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="wallet-action-button primary"
                            id="saveWalletTransaction">
                            Save Transaction
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </div>

    <script>
        (() => {
            'use strict';

            const modal =
                document.getElementById('walletTransactionModal');

            const openButton =
                document.getElementById('openWalletTransactionModal');

            const closeButton =
                document.getElementById('closeWalletTransactionModal');

            const cancelButton =
                document.getElementById('cancelWalletTransaction');

            const form =
                document.getElementById('walletTransactionForm');

            const errorBox =
                document.getElementById('walletTransactionModalError');

            const providerInput =
                document.getElementById('walletProvider');

            const typeInput =
                document.getElementById('walletTransactionType');

            const amountInput =
                document.getElementById('walletAmount');

            const feeInput =
                document.getElementById('walletFee');

            const flowLabel =
                document.getElementById('walletFlowLabel');

            const flowNote =
                document.getElementById('walletFlowNote');

            const flowAmount =
                document.getElementById('walletFlowAmount');

            const saveButton =
                document.getElementById('saveWalletTransaction');

            const providerRadios = [...document.querySelectorAll('input[name="provider_choice"]')];

            const typeRadios = [...document.querySelectorAll('input[name="transaction_type_choice"]')];

            let lastFocusedElement = null;
            let feeRequestId = 0;

            function setError(message = '') {
                if (!message) {
                    errorBox.textContent = '';
                    errorBox.classList.remove('is-visible');
                    return;
                }

                errorBox.textContent = message;
                errorBox.classList.add('is-visible');
            }

            function openModal() {
                lastFocusedElement = document.activeElement;

                setError('');

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('wallet-modal-open');

                requestAnimationFrame(() => {
                    amountInput.focus();
                });
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('wallet-modal-open');

                if (
                    lastFocusedElement &&
                    document.contains(lastFocusedElement)
                ) {
                    lastFocusedElement.focus();
                }
            }

            function getSelectedValue(radios) {
                const selected =
                    radios.find(radio => radio.checked);

                return selected ? selected.value : '';
            }

            function updateFlow() {
                const type = typeInput.value;
                const amount =
                    parseFloat(amountInput.value) || 0;

                const fee =
                    parseFloat(feeInput.value) || 0;

                if (type === 'cash_in') {
                    flowLabel.textContent =
                        'Customer pays store';

                    flowNote.textContent =
                        'Wallet amount + service fee.';

                    flowAmount.textContent =
                        '₱' + (amount + fee).toLocaleString(
                            undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }
                        );

                    return;
                }

                flowLabel.textContent =
                    'Store gives customer';

                flowNote.textContent =
                    'Customer pays the service fee separately.';

                flowAmount.textContent =
                    '₱' + amount.toLocaleString(
                        undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }
                    );
            }

            async function calculateFee() {
                const requestId = ++feeRequestId;

                const provider = providerInput.value;
                const type = typeInput.value;
                const amount =
                    parseFloat(amountInput.value) || 0;

                if (amount <= 0) {
                    feeInput.value = '0.00';
                    updateFlow();
                    return;
                }

                feeInput.value = '...';

                try {
                    const url =
                        'fee_api.php?provider=' +
                        encodeURIComponent(provider) +
                        '&transaction_type=' +
                        encodeURIComponent(type) +
                        '&amount=' +
                        encodeURIComponent(amount);

                    const response = await fetch(url, {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();

                    if (requestId !== feeRequestId) {
                        return;
                    }

                    const fee =
                        Number(data.fee || 0);

                    feeInput.value =
                        fee.toFixed(2);

                    updateFlow();

                } catch (error) {

                    if (requestId !== feeRequestId) {
                        return;
                    }

                    feeInput.value = '0.00';
                    updateFlow();
                }
            }

            providerRadios.forEach(radio => {
                radio.addEventListener('change', () => {
                    providerInput.value = getSelectedValue(providerRadios);
                    calculateFee();
                });
            });

            typeRadios.forEach(radio => {
                radio.addEventListener('change', () => {
                    typeInput.value = getSelectedValue(typeRadios);
                    calculateFee();
                });
            });

            amountInput.addEventListener('input', updateFlow);
            amountInput.addEventListener('input', calculateFee);

            openButton.addEventListener('click', openModal);
            closeButton.addEventListener('click', closeModal);
            cancelButton.addEventListener('click', closeModal);

            modal.addEventListener('click', event => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', event => {
                if (
                    event.key === 'Escape' &&
                    modal.classList.contains('is-open')
                ) {
                    closeModal();
                }
            });

            form.addEventListener('submit', async event => {
                event.preventDefault();

                setError('');

                const amount =
                    parseFloat(amountInput.value) || 0;

                if (amount <= 0) {
                    setError(
                        'Transaction amount must be greater than zero.'
                    );
                    amountInput.focus();
                    return;
                }

                saveButton.disabled = true;
                saveButton.textContent = 'Saving...';

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: new FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const data =
                        await response.json();

                    if (!data.success) {
                        throw new Error(
                            data.message ||
                            'Unable to save the transaction.'
                        );
                    }

                    closeModal();

                    form.reset();

                    providerInput.value = 'gcash';
                    typeInput.value = 'cash_in';

                    providerRadios.forEach(radio => {
                        radio.checked =
                            radio.value === 'gcash';
                    });

                    typeRadios.forEach(radio => {
                        radio.checked =
                            radio.value === 'cash_in';
                    });

                    feeInput.value = '0.00';
                    updateFlow();

                    if (typeof window.showToast === 'function') {
                        window.showToast(
                            data.message ||
                            'Wallet transaction saved successfully.',
                            'success'
                        );
                    }

                    setTimeout(() => {
                        window.location.reload();
                    }, 500);

                } catch (error) {

                    setError(
                        error.message ||
                        'Unable to save the transaction.'
                    );

                } finally {

                    saveButton.disabled = false;
                    saveButton.textContent = 'Save Transaction';
                }
            });

            updateFlow();
        })();
    </script>

</body>

</html>
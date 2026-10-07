-- Run this once in phpMyAdmin inside the arnold_jackies_store database.

CREATE TABLE IF NOT EXISTS wallet_fee_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider ENUM('gcash','maya') NOT NULL,
    transaction_type ENUM('cash_in','cash_out') NOT NULL,
    min_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_amount DECIMAL(12,2) DEFAULT NULL,
    fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_number VARCHAR(40) NOT NULL UNIQUE,
    provider ENUM('gcash','maya') NOT NULL,
    transaction_type ENUM('cash_in','cash_out') NOT NULL,
    customer_name VARCHAR(150) DEFAULT NULL,
    reference_number VARCHAR(100) DEFAULT NULL,
    wallet_amount DECIMAL(12,2) NOT NULL,
    service_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_received DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    remarks VARCHAR(255) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wallet_date (transaction_date),
    INDEX idx_wallet_provider (provider),
    INDEX idx_wallet_type (transaction_type)
);

-- These are starter fee rules only. Change them to the actual rates
-- used by Arnold & Jackie's Store.
INSERT INTO wallet_fee_rules (provider, transaction_type, min_amount, max_amount, fee)
SELECT 'gcash','cash_in',0,500,10
WHERE NOT EXISTS (
    SELECT 1 FROM wallet_fee_rules WHERE provider='gcash' AND transaction_type='cash_in'
);

INSERT INTO wallet_fee_rules (provider, transaction_type, min_amount, max_amount, fee)
SELECT 'gcash','cash_out',0,500,15
WHERE NOT EXISTS (
    SELECT 1 FROM wallet_fee_rules WHERE provider='gcash' AND transaction_type='cash_out'
);

INSERT INTO wallet_fee_rules (provider, transaction_type, min_amount, max_amount, fee)
SELECT 'maya','cash_in',0,500,10
WHERE NOT EXISTS (
    SELECT 1 FROM wallet_fee_rules WHERE provider='maya' AND transaction_type='cash_in'
);

INSERT INTO wallet_fee_rules (provider, transaction_type, min_amount, max_amount, fee)
SELECT 'maya','cash_out',0,500,15
WHERE NOT EXISTS (
    SELECT 1 FROM wallet_fee_rules WHERE provider='maya' AND transaction_type='cash_out'
);

-- =========================================================
-- Arnold & Jackie's Store Database Backup
-- Database: arnold_jackies_store
-- Created: 2026-10-06 04:47:57
-- =========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;


-- =========================================================
-- Table: categories
-- =========================================================

DROP TABLE IF EXISTS `categories`;

CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `category_name`, `created_at`) VALUES ('23', 'Aray mo', '2026-10-03 11:16:27');
INSERT INTO `categories` (`id`, `category_name`, `created_at`) VALUES ('24', 'aw aw', '2026-10-03 11:16:31');


-- =========================================================
-- Table: expenses
-- =========================================================

DROP TABLE IF EXISTS `expenses`;

CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `expense_name` varchar(150) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `expense_date` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- Table: pisowifi
-- =========================================================

DROP TABLE IF EXISTS `pisowifi`;

CREATE TABLE `pisowifi` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `box_name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pisowifi` (`id`, `box_name`, `location`, `description`, `status`, `created_at`, `updated_at`) VALUES ('3', 'PisoWifi #3', 'Lola', NULL, 'active', '2026-09-28 11:12:21', '2026-09-28 11:12:21');
INSERT INTO `pisowifi` (`id`, `box_name`, `location`, `description`, `status`, `created_at`, `updated_at`) VALUES ('4', 'PisoWifi #4', 'Tita Marie', NULL, 'active', '2026-09-28 11:12:36', '2026-09-28 11:12:36');


-- =========================================================
-- Table: pisowifi_earnings
-- =========================================================

DROP TABLE IF EXISTS `pisowifi_earnings`;

CREATE TABLE `pisowifi_earnings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pisowifi_id` int(10) unsigned NOT NULL,
  `earning_month` tinyint(3) unsigned NOT NULL,
  `earning_year` smallint(5) unsigned NOT NULL,
  `gross_earnings` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pisowifi_month` (`pisowifi_id`,`earning_month`,`earning_year`),
  CONSTRAINT `fk_pisowifi_earnings_box` FOREIGN KEY (`pisowifi_id`) REFERENCES `pisowifi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =========================================================
-- Table: products
-- =========================================================

DROP TABLE IF EXISTS `products`;

CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_code` varchar(50) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock` decimal(12,3) NOT NULL DEFAULT 0.000,
  `reorder_level` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(30) NOT NULL DEFAULT 'pc',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_code` (`product_code`),
  KEY `fk_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('1', '123141', 'Test', NULL, '20.00', '30.00', '2.000', '5.000', 'pc', '0', '2026-09-27 21:54:02', '2026-09-29 09:38:21');
INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('2', 'asdass', 'asdsa', NULL, '0.00', '1231.00', '111.000', '0.000', 'pc', '0', '2026-09-28 01:10:23', '2026-09-28 09:19:05');
INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('3', NULL, 'asdda', NULL, '0.00', '0.00', '0.000', '0.000', 'pc', '0', '2026-09-28 10:25:14', '2026-09-28 10:25:17');
INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('4', NULL, 'Test', NULL, '10.00', '20.00', '1.000', '1.000', 'pc', '0', '2026-10-03 10:50:08', '2026-10-03 10:51:19');
INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('5', NULL, 'asdas', '23', '20.00', '22.00', '2.000', '0.000', 'bottle', '0', '2026-10-03 11:17:01', '2026-10-03 11:28:02');
INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('6', NULL, 'dsadaasd', '24', '111.00', '222.00', '1.000', '0.000', 'box', '0', '2026-10-03 11:17:13', '2026-10-03 11:35:22');
INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('7', NULL, 'asdas', '23', '1.00', '11.00', '2.000', '0.000', 'sack', '0', '2026-10-03 11:32:24', '2026-10-03 11:32:27');
INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('8', NULL, 'dasdasd', '23', '0.00', '0.00', '0.000', '0.000', 'sack', '0', '2026-10-03 11:41:33', '2026-10-03 11:48:57');
INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('9', NULL, 'asdasd', '23', '0.00', '0.00', '11.000', '0.000', 'pack', '0', '2026-10-03 11:42:13', '2026-10-03 11:42:24');
INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('10', NULL, 'Test', '23', '12.00', '20.00', '1.000', '0.000', 'pc', '0', '2026-10-03 11:49:08', '2026-10-03 13:07:58');
INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('11', NULL, 'Test', '23', '111.00', '2222.00', '2.000', '0.000', 'box', '0', '2026-10-03 13:05:05', '2026-10-03 13:08:38');
INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `cost_price`, `selling_price`, `stock`, `reorder_level`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES ('12', NULL, 'Test', '24', '12.00', '122.00', '3.000', '0.000', 'box', '0', '2026-10-03 13:08:21', '2026-10-03 13:36:35');


-- =========================================================
-- Table: sale_items
-- =========================================================

DROP TABLE IF EXISTS `sale_items`;

CREATE TABLE `sale_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `cost_price` decimal(12,2) NOT NULL,
  `selling_price` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `profit` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sale_items_sale` (`sale_id`),
  KEY `fk_sale_items_product` (`product_id`),
  CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `product_name`, `quantity`, `cost_price`, `selling_price`, `subtotal`, `profit`) VALUES ('1', '4', '4', 'Test', '1.000', '10.00', '20.00', '20.00', '10.00');


-- =========================================================
-- Table: sales
-- =========================================================

DROP TABLE IF EXISTS `sales`;

CREATE TABLE `sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_number` varchar(30) NOT NULL,
  `sale_date` datetime NOT NULL DEFAULT current_timestamp(),
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_profit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','gcash','maya') NOT NULL DEFAULT 'cash',
  `amount_received` decimal(12,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_number` (`sale_number`),
  KEY `fk_sales_user` (`user_id`),
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sales` (`id`, `sale_number`, `sale_date`, `subtotal`, `discount`, `total`, `total_cost`, `total_profit`, `payment_method`, `amount_received`, `change_amount`, `user_id`, `created_at`) VALUES ('1', 'SALE-20260927155419-126', '2026-09-27 21:54:19', '30.00', '0.00', '30.00', '20.00', '10.00', 'cash', '50.00', '20.00', '2', '2026-09-27 21:54:19');
INSERT INTO `sales` (`id`, `sale_number`, `sale_date`, `subtotal`, `discount`, `total`, `total_cost`, `total_profit`, `payment_method`, `amount_received`, `change_amount`, `user_id`, `created_at`) VALUES ('2', 'SALE-20260928032316-197', '2026-09-28 09:23:16', '30.00', '0.00', '30.00', '20.00', '10.00', 'cash', '30.00', '0.00', '2', '2026-09-28 09:23:16');
INSERT INTO `sales` (`id`, `sale_number`, `sale_date`, `subtotal`, `discount`, `total`, `total_cost`, `total_profit`, `payment_method`, `amount_received`, `change_amount`, `user_id`, `created_at`) VALUES ('3', 'SALE-20260928044039-435', '2026-09-28 10:40:39', '30.00', '0.00', '30.00', '20.00', '10.00', 'cash', '30.00', '0.00', '2', '2026-09-28 10:40:39');
INSERT INTO `sales` (`id`, `sale_number`, `sale_date`, `subtotal`, `discount`, `total`, `total_cost`, `total_profit`, `payment_method`, `amount_received`, `change_amount`, `user_id`, `created_at`) VALUES ('4', 'SALE-20261003045016-495', '2026-10-03 10:50:16', '20.00', '0.00', '20.00', '10.00', '10.00', 'cash', '50.00', '30.00', '2', '2026-10-03 10:50:16');


-- =========================================================
-- Table: stock_movements
-- =========================================================

DROP TABLE IF EXISTS `stock_movements`;

CREATE TABLE `stock_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `movement_type` enum('stock_in','sale','adjustment','return') NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_stock_product` (`product_id`),
  CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('1', '1', 'stock_in', '5.000', NULL, 'Initial stock', '2026-09-27 21:54:02');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('2', '1', 'sale', '-1.000', '1', 'Sale SALE-20260927155419-126', '2026-09-27 21:54:19');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('3', '2', 'stock_in', '111.000', NULL, 'Initial stock', '2026-09-28 01:10:23');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('4', '1', 'sale', '-1.000', '2', 'Sale SALE-20260928032316-197', '2026-09-28 09:23:16');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('5', '1', 'sale', '-1.000', '3', 'Sale SALE-20260928044039-435', '2026-09-28 10:40:39');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('6', '4', 'stock_in', '2.000', NULL, 'Initial stock', '2026-10-03 10:50:08');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('7', '4', 'sale', '-1.000', '4', 'Sale SALE-20261003045016-495', '2026-10-03 10:50:16');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('8', '5', 'stock_in', '2.000', NULL, 'Initial stock', '2026-10-03 11:17:01');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('9', '6', 'stock_in', '1.000', NULL, 'Initial stock', '2026-10-03 11:17:13');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('10', '7', 'stock_in', '2.000', NULL, 'Initial stock', '2026-10-03 11:32:24');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('11', '9', 'stock_in', '11.000', NULL, 'Stock added', '2026-10-03 11:42:22');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('12', '10', 'stock_in', '1.000', NULL, 'Stock added', '2026-10-03 11:54:05');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('13', '11', 'stock_in', '2.000', NULL, 'Initial stock', '2026-10-03 13:05:05');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('14', '12', 'stock_in', '2.000', NULL, 'Initial stock', '2026-10-03 13:08:21');
INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `remarks`, `created_at`) VALUES ('15', '12', 'stock_in', '1.000', NULL, 'Stock added', '2026-10-03 13:13:09');


-- =========================================================
-- Table: store_settings
-- =========================================================

DROP TABLE IF EXISTS `store_settings`;

CREATE TABLE `store_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_name` varchar(150) NOT NULL,
  `store_address` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email_address` varchar(150) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `store_settings` (`id`, `store_name`, `store_address`, `contact_number`, `email_address`, `updated_at`) VALUES ('1', 'TEST', 'TEST', '09946949003', 'test@gmail.com', '2026-10-03 13:50:24');


-- =========================================================
-- Table: system_settings
-- =========================================================

DROP TABLE IF EXISTS `system_settings`;

CREATE TABLE `system_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `currency` varchar(20) NOT NULL DEFAULT 'PHP',
  `currency_symbol` varchar(10) NOT NULL DEFAULT '₱',
  `date_format` varchar(50) NOT NULL DEFAULT 'F d, Y',
  `timezone` varchar(100) NOT NULL DEFAULT 'Asia/Manila',
  `decimal_places` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `default_payment_method` varchar(20) NOT NULL DEFAULT 'cash',
  `low_stock_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`id`, `currency`, `currency_symbol`, `date_format`, `timezone`, `decimal_places`, `default_payment_method`, `low_stock_notifications`, `updated_at`) VALUES ('1', 'PHP', '₱', 'd F Y', 'Asia/Manila', '2', 'cash', '1', '2026-10-06 10:30:42');


-- =========================================================
-- Table: users
-- =========================================================

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','cashier','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `is_active`, `created_at`) VALUES ('2', 'admin', '$2y$10$dTH5m61pIvTkUKAJeBtlieSQcDNTY1e5tL9w5dolHu2VrnSahXCv.', 'Administrator', 'admin', '1', '2026-09-27 21:02:39');
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `is_active`, `created_at`) VALUES ('3', 'admin1', '$2y$10$GrG9Tr.yUs8YYAnSCzKQYOJ.CL/X.ea0NjhSpeMWBNBDeiwKj.gP6', 'Dylan', 'cashier', '0', '2026-09-27 22:43:06');
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `is_active`, `created_at`) VALUES ('4', '123456', '$2y$10$JyFWahJjm5vDUQgQ8FV8.ejsVONpBYLz9xlusrsOuCtWZ0sZR667i', 'testuser', 'staff', '0', '2026-09-27 22:57:03');
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `is_active`, `created_at`) VALUES ('5', 'joseph', '$2y$10$FbEUJIn8zE5ya8NBlm2LietMy.PMJ6f5jQ5pJWuZoXAbcJB.jPqcy', 'AKO TO', 'admin', '1', '2026-10-03 16:29:52');


-- =========================================================
-- Table: wallet_fee_rules
-- =========================================================

DROP TABLE IF EXISTS `wallet_fee_rules`;

CREATE TABLE `wallet_fee_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` enum('gcash','maya') NOT NULL,
  `transaction_type` enum('cash_in','cash_out') NOT NULL,
  `min_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_amount` decimal(12,2) DEFAULT NULL,
  `fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('1', 'gcash', 'cash_in', '0.00', '500.00', '10.00', '0', '2026-09-27 21:14:28');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('2', 'gcash', 'cash_out', '0.00', '500.00', '15.00', '0', '2026-09-27 21:14:28');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('3', 'maya', 'cash_in', '0.00', '500.00', '10.00', '0', '2026-09-27 21:14:29');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('4', 'maya', 'cash_out', '0.00', '500.00', '15.00', '0', '2026-09-27 21:14:29');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('5', 'gcash', 'cash_in', '1.00', '100.00', '5.00', '0', '2026-09-27 21:31:15');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('6', 'gcash', 'cash_in', '101.00', '300.00', '10.00', '0', '2026-09-27 21:31:15');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('7', 'gcash', 'cash_in', '301.00', '500.00', '15.00', '0', '2026-09-27 21:31:15');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('8', 'gcash', 'cash_in', '501.00', '1000.00', '20.00', '0', '2026-09-27 21:31:15');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('9', 'maya', 'cash_in', '1.00', '100.00', '5.00', '0', '2026-09-27 21:31:15');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('10', 'maya', 'cash_in', '101.00', '300.00', '10.00', '0', '2026-09-27 21:31:15');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('11', 'maya', 'cash_in', '301.00', '500.00', '15.00', '0', '2026-09-27 21:31:15');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('12', 'maya', 'cash_in', '501.00', '1000.00', '20.00', '0', '2026-09-27 21:31:15');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('20', 'gcash', 'cash_in', '10.00', NULL, '5.00', '0', '2026-09-27 21:32:00');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('21', 'gcash', 'cash_in', '1.00', '50.00', '5.00', '0', '2026-09-27 21:32:44');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('22', 'gcash', 'cash_in', '0.00', NULL, '0.00', '0', '2026-09-27 21:32:46');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('23', 'gcash', 'cash_in', '51.00', '90.00', '8.00', '0', '2026-09-27 21:33:02');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('24', 'gcash', 'cash_in', '0.00', NULL, '0.00', '0', '2026-09-27 21:33:05');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('25', 'gcash', 'cash_in', '91.00', '500.00', '10.00', '0', '2026-09-27 21:33:21');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('26', 'gcash', 'cash_in', '0.00', NULL, '0.00', '0', '2026-09-28 09:08:36');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('27', 'gcash', 'cash_in', '0.00', NULL, '0.00', '0', '2026-09-30 13:35:04');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('28', 'gcash', 'cash_in', '0.00', NULL, '0.00', '0', '2026-09-30 13:58:18');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('29', 'gcash', 'cash_in', '0.00', NULL, '0.00', '0', '2026-09-30 13:58:19');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('30', 'gcash', 'cash_in', '0.00', NULL, '0.00', '0', '2026-09-30 13:58:19');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('31', 'gcash', 'cash_in', '0.00', NULL, '0.00', '0', '2026-09-30 13:58:20');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('32', 'gcash', 'cash_in', '0.00', NULL, '0.00', '0', '2026-09-30 13:58:20');
INSERT INTO `wallet_fee_rules` (`id`, `provider`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `is_active`, `created_at`) VALUES ('33', 'gcash', 'cash_in', '0.00', NULL, '0.00', '0', '2026-09-30 13:58:20');


-- =========================================================
-- Table: wallet_rates
-- =========================================================

DROP TABLE IF EXISTS `wallet_rates`;

CREATE TABLE `wallet_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `wallet_type` enum('gcash','maya') NOT NULL,
  `transaction_type` enum('cash_in','cash_out') NOT NULL,
  `min_amount` decimal(12,2) NOT NULL,
  `max_amount` decimal(12,2) DEFAULT NULL,
  `fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `wallet_rates` (`id`, `wallet_type`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `created_at`) VALUES ('1', 'gcash', 'cash_in', '1.00', '100.00', '5.00', '2026-09-27 21:00:33');
INSERT INTO `wallet_rates` (`id`, `wallet_type`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `created_at`) VALUES ('2', 'gcash', 'cash_in', '101.00', '300.00', '10.00', '2026-09-27 21:00:33');
INSERT INTO `wallet_rates` (`id`, `wallet_type`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `created_at`) VALUES ('3', 'gcash', 'cash_in', '301.00', '500.00', '15.00', '2026-09-27 21:00:33');
INSERT INTO `wallet_rates` (`id`, `wallet_type`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `created_at`) VALUES ('4', 'gcash', 'cash_in', '501.00', '1000.00', '20.00', '2026-09-27 21:00:33');
INSERT INTO `wallet_rates` (`id`, `wallet_type`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `created_at`) VALUES ('5', 'maya', 'cash_in', '1.00', '100.00', '5.00', '2026-09-27 21:00:33');
INSERT INTO `wallet_rates` (`id`, `wallet_type`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `created_at`) VALUES ('6', 'maya', 'cash_in', '101.00', '300.00', '10.00', '2026-09-27 21:00:33');
INSERT INTO `wallet_rates` (`id`, `wallet_type`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `created_at`) VALUES ('7', 'maya', 'cash_in', '301.00', '500.00', '15.00', '2026-09-27 21:00:33');
INSERT INTO `wallet_rates` (`id`, `wallet_type`, `transaction_type`, `min_amount`, `max_amount`, `fee`, `created_at`) VALUES ('8', 'maya', 'cash_in', '501.00', '1000.00', '20.00', '2026-09-27 21:00:33');


-- =========================================================
-- Table: wallet_transactions
-- =========================================================

DROP TABLE IF EXISTS `wallet_transactions`;

CREATE TABLE `wallet_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(30) NOT NULL,
  `provider` enum('gcash','maya') NOT NULL,
  `transaction_type` enum('cash_in','cash_out') NOT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `wallet_amount` decimal(12,2) NOT NULL,
  `service_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_received` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `store_profit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL,
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_number` (`transaction_number`),
  KEY `fk_wallet_user` (`user_id`),
  CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
SET AUTOCOMMIT = 1;

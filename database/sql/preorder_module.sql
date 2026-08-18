-- Vehicle POS Pre-Order Management Module
-- Safe, additive schema for phpMyAdmin / shared hosting.
-- This script does not drop, truncate, or reset any existing table or data.

SET @schema_name = DATABASE();

SET @statement = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='payments' AND COLUMN_NAME='reference_no') = 0,
    'ALTER TABLE `payments` ADD COLUMN `reference_no` VARCHAR(191) NULL AFTER `payment_method`',
    'SELECT 1'
);
PREPARE preorder_stmt FROM @statement; EXECUTE preorder_stmt; DEALLOCATE PREPARE preorder_stmt;

SET @statement = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='payments' AND COLUMN_NAME='user_id') = 0,
    'ALTER TABLE `payments` ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `customer_id`, ADD INDEX `payments_user_id_foreign` (`user_id`), ADD CONSTRAINT `payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE preorder_stmt FROM @statement; EXECUTE preorder_stmt; DEALLOCATE PREPARE preorder_stmt;

CREATE TABLE IF NOT EXISTS `pre_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pre_order_number` VARCHAR(30) NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `store_id` BIGINT UNSIGNED NULL,
  `sale_id` BIGINT UNSIGNED NULL,
  `pre_order_date` DATE NOT NULL,
  `document_type` VARCHAR(30) NOT NULL DEFAULT 'quotation',
  `vehicle_name` VARCHAR(255) NOT NULL,
  `registration_number` VARCHAR(255) NULL,
  `chassis_number` VARCHAR(255) NULL,
  `vehicle_description` TEXT NULL,
  `vehicle_image` VARCHAR(255) NULL,
  `instructions` TEXT NULL,
  `notes` TEXT NULL,
  `expected_delivery_date` DATE NULL,
  `bill_discount_type` VARCHAR(20) NOT NULL DEFAULT 'fixed',
  `bill_discount_value` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `rounding_adjustment` DECIMAL(20,4) NOT NULL DEFAULT 0,
  `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `held_cheque_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `due_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `payment_status` VARCHAR(20) NOT NULL DEFAULT 'unpaid',
  `completed_at` TIMESTAMP NULL,
  `completed_by` BIGINT UNSIGNED NULL,
  `cancelled_at` TIMESTAMP NULL,
  `cancelled_by` BIGINT UNSIGNED NULL,
  `cancellation_reason` TEXT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pre_orders_pre_order_number_unique` (`pre_order_number`),
  UNIQUE KEY `pre_orders_sale_id_unique` (`sale_id`),
  KEY `pre_orders_customer_id_foreign` (`customer_id`),
  KEY `pre_orders_store_id_foreign` (`store_id`),
  KEY `pre_orders_completed_by_foreign` (`completed_by`),
  KEY `pre_orders_cancelled_by_foreign` (`cancelled_by`),
  KEY `pre_orders_created_by_foreign` (`created_by`),
  KEY `pre_orders_updated_by_foreign` (`updated_by`),
  KEY `pre_orders_status_pre_order_date_index` (`status`,`pre_order_date`),
  KEY `pre_orders_payment_status_pre_order_date_index` (`payment_status`,`pre_order_date`),
  KEY `pre_orders_expected_delivery_date_index` (`expected_delivery_date`),
  CONSTRAINT `pre_orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pre_orders_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pre_orders_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pre_orders_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pre_orders_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pre_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pre_orders_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pre_order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pre_order_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NULL,
  `product_price_id` BIGINT UNSIGNED NULL,
  `original_product_name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `quoted_price` DECIMAL(15,2) NOT NULL,
  `final_price` DECIMAL(15,2) NOT NULL,
  `discount_type` VARCHAR(20) NOT NULL DEFAULT 'fixed',
  `discount_value` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `gross_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `sync_status` VARCHAR(20) NOT NULL DEFAULT 'unlinked',
  `tax_snapshot` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL CHECK (JSON_VALID(`tax_snapshot`)),
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `pre_order_items_pre_order_id_sync_status_index` (`pre_order_id`,`sync_status`),
  KEY `pre_order_items_product_id_foreign` (`product_id`),
  KEY `pre_order_items_product_price_id_foreign` (`product_price_id`),
  KEY `pre_order_items_original_product_name_index` (`original_product_name`),
  CONSTRAINT `pre_order_items_pre_order_id_foreign` FOREIGN KEY (`pre_order_id`) REFERENCES `pre_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pre_order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pre_order_items_product_price_id_foreign` FOREIGN KEY (`product_price_id`) REFERENCES `product_prices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pre_order_activities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pre_order_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(60) NOT NULL,
  `description` TEXT NOT NULL,
  `old_values` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL CHECK (JSON_VALID(`old_values`)),
  `new_values` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL CHECK (JSON_VALID(`new_values`)),
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `pre_order_activities_pre_order_id_created_at_index` (`pre_order_id`,`created_at`),
  KEY `pre_order_activities_user_id_foreign` (`user_id`),
  CONSTRAINT `pre_order_activities_pre_order_id_foreign` FOREIGN KEY (`pre_order_id`) REFERENCES `pre_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pre_order_activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add module permissions to the existing JSON role-permission architecture.
DROP PROCEDURE IF EXISTS `grant_preorder_permission`;
DELIMITER $$
CREATE PROCEDURE `grant_preorder_permission`(IN role_names TEXT, IN permission_name VARCHAR(100))
BEGIN
  UPDATE `roles`
     SET `permissions` = JSON_ARRAY_APPEND(COALESCE(`permissions`, JSON_ARRAY()), '$', permission_name)
   WHERE FIND_IN_SET(
           CONVERT(LOWER(TRIM(`name`)) USING utf8mb4) COLLATE utf8mb4_unicode_ci,
           CONVERT(role_names USING utf8mb4) COLLATE utf8mb4_unicode_ci
         ) > 0
     AND JSON_CONTAINS(COALESCE(`permissions`, JSON_ARRAY()), JSON_QUOTE(permission_name)) = 0;
END$$
DELIMITER ;

CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_view');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_create');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_edit');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_cancel');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_complete');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_reopen');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_payment_view');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_payment_create');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_payment_edit');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_sync_product');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_change_price');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_print_quotation');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_print_invoice');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_view_cost');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_view_profit');
CALL grant_preorder_permission('admin,super admin,superadmin,super_admin,manager', 'preorder_reports');

CALL grant_preorder_permission('cashier', 'preorder_view');
CALL grant_preorder_permission('cashier', 'preorder_create');
CALL grant_preorder_permission('cashier', 'preorder_edit');
CALL grant_preorder_permission('cashier', 'preorder_payment_view');
CALL grant_preorder_permission('cashier', 'preorder_payment_create');
CALL grant_preorder_permission('cashier', 'preorder_print_quotation');
CALL grant_preorder_permission('cashier', 'preorder_print_invoice');

DROP PROCEDURE IF EXISTS `grant_preorder_permission`;

-- End of additive Pre-Order module schema.

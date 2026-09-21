-- ============================================================================
--  GARMENT INVENTORY MANAGEMENT SYSTEM (GIMS)
--  Complete MySQL Schema + Sample Data
--  Target : MySQL 5.7+ / MariaDB 10.3+  (XAMPP / phpMyAdmin ready)
--  Charset: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = "+05:30";

CREATE DATABASE IF NOT EXISTS `garment_inventory`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `garment_inventory`;

-- ============================================================================
--  SECTION 1 : AUTHENTICATION / RBAC
-- ============================================================================

DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `sales_return_items`;
DROP TABLE IF EXISTS `sales_returns`;
DROP TABLE IF EXISTS `deliveries`;
DROP TABLE IF EXISTS `invoice_items`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `sales_order_items`;
DROP TABLE IF EXISTS `sales_orders`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `qc_defects`;
DROP TABLE IF EXISTS `qc_inspections`;
DROP TABLE IF EXISTS `production_outputs`;
DROP TABLE IF EXISTS `production_materials`;
DROP TABLE IF EXISTS `production_orders`;
DROP TABLE IF EXISTS `bom_items`;
DROP TABLE IF EXISTS `bom`;
DROP TABLE IF EXISTS `purchase_return_items`;
DROP TABLE IF EXISTS `purchase_returns`;
DROP TABLE IF EXISTS `goods_received_items`;
DROP TABLE IF EXISTS `goods_received`;
DROP TABLE IF EXISTS `purchase_order_items`;
DROP TABLE IF EXISTS `purchase_orders`;
DROP TABLE IF EXISTS `supplier_contacts`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `stock_transfer_items`;
DROP TABLE IF EXISTS `stock_transfers`;
DROP TABLE IF EXISTS `stock_adjustment_items`;
DROP TABLE IF EXISTS `stock_adjustments`;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `stock`;
DROP TABLE IF EXISTS `warehouse_locations`;
DROP TABLE IF EXISTS `warehouses`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- ---------------------------------------------------------------------------
-- roles
-- ---------------------------------------------------------------------------
CREATE TABLE `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)  NOT NULL,
  `slug`        VARCHAR(100)  NOT NULL,
  `description` VARCHAR(255)  DEFAULT NULL,
  `is_system`   TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- permissions
-- ---------------------------------------------------------------------------
CREATE TABLE `permissions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `slug`       VARCHAR(120) NOT NULL,
  `module`     VARCHAR(60)  NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- role_permissions
-- ---------------------------------------------------------------------------
CREATE TABLE `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150) NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password`      VARCHAR(255) NOT NULL,
  `phone`         VARCHAR(30)  DEFAULT NULL,
  `avatar`        VARCHAR(255) DEFAULT NULL,
  `role_id`       INT UNSIGNED NOT NULL,
  `status`        ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `last_login`    DATETIME     DEFAULT NULL,
  `login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`  DATETIME     DEFAULT NULL,
  `reset_token`   VARCHAR(120) DEFAULT NULL,
  `reset_expires` DATETIME     DEFAULT NULL,
  `remember_token` VARCHAR(120) DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_deleted` (`deleted_at`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- user_roles (secondary / multi-role support)
-- ---------------------------------------------------------------------------
CREATE TABLE `user_roles` (
  `user_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `idx_ur_role` (`role_id`),
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- activity_logs
-- ---------------------------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `action`      VARCHAR(120) NOT NULL,
  `module`      VARCHAR(60)  NOT NULL,
  `record_id`   INT UNSIGNED DEFAULT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `user_agent`  VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_user` (`user_id`),
  KEY `idx_logs_module` (`module`),
  KEY `idx_logs_created` (`created_at`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
--  SECTION 2 : HR / ORGANISATION
-- ============================================================================

-- ---------------------------------------------------------------------------
-- departments
-- ---------------------------------------------------------------------------
CREATE TABLE `departments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `code`        VARCHAR(30)  NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_departments_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- employees
-- ---------------------------------------------------------------------------
CREATE TABLE `employees` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_no`       VARCHAR(40)  NOT NULL,
  `full_name`         VARCHAR(150) NOT NULL,
  `nic`               VARCHAR(30)  DEFAULT NULL,
  `gender`            ENUM('male','female','other') DEFAULT NULL,
  `dob`               DATE         DEFAULT NULL,
  `phone`             VARCHAR(30)  DEFAULT NULL,
  `email`             VARCHAR(190) DEFAULT NULL,
  `address`           VARCHAR(400) DEFAULT NULL,
  `department_id`     INT UNSIGNED DEFAULT NULL,
  `position`          VARCHAR(120) DEFAULT NULL,
  `join_date`         DATE         DEFAULT NULL,
  `salary`            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `emergency_contact` VARCHAR(120) DEFAULT NULL,
  `image`             VARCHAR(255) DEFAULT NULL,
  `status`            ENUM('active','inactive','resigned','terminated') NOT NULL DEFAULT 'active',
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employees_no` (`employee_no`),
  KEY `idx_employees_dept` (`department_id`),
  KEY `idx_employees_status` (`status`),
  KEY `idx_employees_name` (`full_name`),
  CONSTRAINT `fk_employees_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- attendance
-- ---------------------------------------------------------------------------
CREATE TABLE `attendance` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `att_date`    DATE         NOT NULL,
  `check_in`    TIME         DEFAULT NULL,
  `check_out`   TIME         DEFAULT NULL,
  `status`      ENUM('present','absent','late','half_day','leave') NOT NULL DEFAULT 'present',
  `remarks`     VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_attendance_emp_date` (`employee_id`,`att_date`),
  KEY `idx_attendance_date` (`att_date`),
  KEY `idx_attendance_status` (`status`),
  CONSTRAINT `fk_attendance_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
--  SECTION 3 : CATALOGUE
-- ============================================================================

-- ---------------------------------------------------------------------------
-- categories  (self-referencing => categories + sub categories)
-- ---------------------------------------------------------------------------
CREATE TABLE `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `slug`        VARCHAR(170) NOT NULL,
  `parent_id`   INT UNSIGNED DEFAULT NULL,
  `description` VARCHAR(400) DEFAULT NULL,
  `image`       VARCHAR(255) DEFAULT NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`  DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`),
  KEY `idx_categories_status` (`status`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- suppliers
-- ---------------------------------------------------------------------------
CREATE TABLE `suppliers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_name`   VARCHAR(190) NOT NULL,
  `contact_person` VARCHAR(150) DEFAULT NULL,
  `phone`          VARCHAR(30)  DEFAULT NULL,
  `email`          VARCHAR(190) DEFAULT NULL,
  `address`        VARCHAR(400) DEFAULT NULL,
  `city`           VARCHAR(100) DEFAULT NULL,
  `country`        VARCHAR(100) DEFAULT 'Sri Lanka',
  `tax_number`     VARCHAR(60)  DEFAULT NULL,
  `payment_terms`  VARCHAR(120) DEFAULT NULL,
  `bank_details`   VARCHAR(400) DEFAULT NULL,
  `opening_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_suppliers_name` (`company_name`),
  KEY `idx_suppliers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- supplier_contacts
-- ---------------------------------------------------------------------------
CREATE TABLE `supplier_contacts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_id` INT UNSIGNED NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `position`    VARCHAR(120) DEFAULT NULL,
  `phone`       VARCHAR(30)  DEFAULT NULL,
  `email`       VARCHAR(190) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_supplier_contacts_supplier` (`supplier_id`),
  CONSTRAINT `fk_supplier_contacts_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- warehouses
-- ---------------------------------------------------------------------------
CREATE TABLE `warehouses` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `code`       VARCHAR(40)  NOT NULL,
  `address`    VARCHAR(400) DEFAULT NULL,
  `city`       VARCHAR(100) DEFAULT NULL,
  `country`    VARCHAR(100) DEFAULT 'Sri Lanka',
  `manager_id` INT UNSIGNED DEFAULT NULL,
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `email`      VARCHAR(190) DEFAULT NULL,
  `is_default` TINYINT(1)   NOT NULL DEFAULT 0,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_warehouses_code` (`code`),
  KEY `idx_warehouses_manager` (`manager_id`),
  CONSTRAINT `fk_warehouses_manager` FOREIGN KEY (`manager_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- warehouse_locations (racks + bins)
-- ---------------------------------------------------------------------------
CREATE TABLE `warehouse_locations` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `rack`         VARCHAR(50)  DEFAULT NULL,
  `bin`          VARCHAR(50)  DEFAULT NULL,
  `code`         VARCHAR(80)  NOT NULL,
  `description`  VARCHAR(255) DEFAULT NULL,
  `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wh_loc_code` (`code`),
  KEY `idx_wh_loc_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_wh_loc_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- products
-- ---------------------------------------------------------------------------
CREATE TABLE `products` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku`            VARCHAR(80)  NOT NULL,
  `product_code`   VARCHAR(80)  NOT NULL,
  `name`           VARCHAR(190) NOT NULL,
  `product_type`   ENUM('raw_material','fabric','thread','button','zipper','label','packaging','finished_garment','accessory') NOT NULL DEFAULT 'raw_material',
  `category_id`    INT UNSIGNED DEFAULT NULL,
  `sub_category_id` INT UNSIGNED DEFAULT NULL,
  `brand`          VARCHAR(120) DEFAULT NULL,
  `description`    TEXT         DEFAULT NULL,
  `unit`           VARCHAR(30)  NOT NULL DEFAULT 'pcs',
  `supplier_id`    INT UNSIGNED DEFAULT NULL,
  `cost_price`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `selling_price`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `min_stock`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `max_stock`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `reorder_level`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `barcode`        VARCHAR(120) DEFAULT NULL,
  `qr_code`        VARCHAR(255) DEFAULT NULL,
  `image`          VARCHAR(255) DEFAULT NULL,
  `has_variants`   TINYINT(1)   NOT NULL DEFAULT 0,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_by`     INT UNSIGNED DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_sku` (`sku`),
  UNIQUE KEY `uk_products_code` (`product_code`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_type` (`product_type`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_subcategory` (`sub_category_id`),
  KEY `idx_products_supplier` (`supplier_id`),
  KEY `idx_products_barcode` (`barcode`),
  KEY `idx_products_status` (`status`),
  CONSTRAINT `fk_products_category`    FOREIGN KEY (`category_id`)     REFERENCES `categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_products_subcategory` FOREIGN KEY (`sub_category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_products_supplier`    FOREIGN KEY (`supplier_id`)     REFERENCES `suppliers`(`id`)  ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_products_creator`     FOREIGN KEY (`created_by`)      REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- product_variants
--   NOTE: `variant_id = 0` is used across the system to mean "no variant".
-- ---------------------------------------------------------------------------
CREATE TABLE `product_variants` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED NOT NULL,
  `size`          VARCHAR(30)  DEFAULT NULL,
  `color`         VARCHAR(50)  DEFAULT NULL,
  `sku`           VARCHAR(100) NOT NULL,
  `barcode`       VARCHAR(120) DEFAULT NULL,
  `cost_price`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_variants_sku` (`sku`),
  KEY `idx_variants_product` (`product_id`),
  KEY `idx_variants_barcode` (`barcode`),
  CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- product_images
-- ---------------------------------------------------------------------------
CREATE TABLE `product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_images_product` (`product_id`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
--  SECTION 4 : INVENTORY
-- ============================================================================

-- ---------------------------------------------------------------------------
-- stock  (current balance per product / variant / warehouse)
-- ---------------------------------------------------------------------------
CREATE TABLE `stock` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED NOT NULL,
  `variant_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `warehouse_id`  INT UNSIGNED NOT NULL,
  `location_id`   INT UNSIGNED DEFAULT NULL,
  `quantity`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `reserved_qty`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `damaged_qty`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `rejected_qty`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_stock_combo` (`product_id`,`variant_id`,`warehouse_id`),
  KEY `idx_stock_warehouse` (`warehouse_id`),
  KEY `idx_stock_product` (`product_id`),
  CONSTRAINT `fk_stock_product`   FOREIGN KEY (`product_id`)   REFERENCES `products`(`id`)   ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_location`  FOREIGN KEY (`location_id`)  REFERENCES `warehouse_locations`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- stock_movements  (immutable ledger)
-- ---------------------------------------------------------------------------
CREATE TABLE `stock_movements` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`     INT UNSIGNED NOT NULL,
  `variant_id`     INT UNSIGNED NOT NULL DEFAULT 0,
  `warehouse_id`   INT UNSIGNED NOT NULL,
  `movement_type`  ENUM('opening','purchase_in','sale_out','production_in','production_out',
                        'adjustment_in','adjustment_out','transfer_in','transfer_out',
                        'return_in','return_out','damage_out') NOT NULL,
  `direction`      ENUM('in','out') NOT NULL,
  `reference_type` VARCHAR(60)  DEFAULT NULL,
  `reference_id`   INT UNSIGNED DEFAULT NULL,
  `reference_no`   VARCHAR(80)  DEFAULT NULL,
  `quantity`       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `balance_after`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `unit_cost`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `notes`          VARCHAR(400) DEFAULT NULL,
  `created_by`     INT UNSIGNED DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mov_product` (`product_id`),
  KEY `idx_mov_warehouse` (`warehouse_id`),
  KEY `idx_mov_type` (`movement_type`),
  KEY `idx_mov_created` (`created_at`),
  KEY `idx_mov_reference` (`reference_type`,`reference_id`),
  CONSTRAINT `fk_mov_product`   FOREIGN KEY (`product_id`)   REFERENCES `products`(`id`)   ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mov_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mov_user`      FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- stock_adjustments
-- ---------------------------------------------------------------------------
CREATE TABLE `stock_adjustments` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no`   VARCHAR(60)  NOT NULL,
  `warehouse_id`   INT UNSIGNED NOT NULL,
  `adjustment_date` DATE        NOT NULL,
  `adjustment_type` ENUM('increase','decrease','damage','recount') NOT NULL DEFAULT 'recount',
  `reason`         VARCHAR(255) DEFAULT NULL,
  `status`         ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `notes`          VARCHAR(400) DEFAULT NULL,
  `created_by`     INT UNSIGNED DEFAULT NULL,
  `approved_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_adjust_ref` (`reference_no`),
  KEY `idx_adjust_warehouse` (`warehouse_id`),
  KEY `idx_adjust_status` (`status`),
  CONSTRAINT `fk_adjust_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_adjust_creator`   FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_adjust_approver`  FOREIGN KEY (`approved_by`)  REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_adjustment_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `adjustment_id` INT UNSIGNED NOT NULL,
  `product_id`    INT UNSIGNED NOT NULL,
  `variant_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `system_qty`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `counted_qty`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `difference_qty` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `unit_cost`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `notes`         VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_adj_items_adj` (`adjustment_id`),
  KEY `idx_adj_items_product` (`product_id`),
  CONSTRAINT `fk_adj_items_adj`     FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_adj_items_product` FOREIGN KEY (`product_id`)    REFERENCES `products`(`id`)          ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- stock_transfers
-- ---------------------------------------------------------------------------
CREATE TABLE `stock_transfers` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_no`       VARCHAR(60)  NOT NULL,
  `from_warehouse_id` INT UNSIGNED NOT NULL,
  `to_warehouse_id`   INT UNSIGNED NOT NULL,
  `transfer_date`     DATE         NOT NULL,
  `status`            ENUM('draft','pending','in_transit','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes`             VARCHAR(400) DEFAULT NULL,
  `requested_by`      INT UNSIGNED DEFAULT NULL,
  `approved_by`       INT UNSIGNED DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_transfer_no` (`transfer_no`),
  KEY `idx_transfer_from` (`from_warehouse_id`),
  KEY `idx_transfer_to` (`to_warehouse_id`),
  KEY `idx_transfer_status` (`status`),
  CONSTRAINT `fk_transfer_from`     FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transfer_to`       FOREIGN KEY (`to_warehouse_id`)   REFERENCES `warehouses`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transfer_requester` FOREIGN KEY (`requested_by`)     REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_transfer_approver`  FOREIGN KEY (`approved_by`)      REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_transfer_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_id` INT UNSIGNED NOT NULL,
  `product_id`  INT UNSIGNED NOT NULL,
  `variant_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `quantity`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `received_qty` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `notes`       VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transfer_items_transfer` (`transfer_id`),
  KEY `idx_transfer_items_product` (`product_id`),
  CONSTRAINT `fk_transfer_items_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_transfer_items_product`  FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
--  SECTION 5 : PURCHASING
-- ============================================================================

CREATE TABLE `purchase_orders` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `po_number`     VARCHAR(60)  NOT NULL,
  `supplier_id`   INT UNSIGNED NOT NULL,
  `warehouse_id`  INT UNSIGNED NOT NULL,
  `order_date`    DATE         NOT NULL,
  `expected_date` DATE         DEFAULT NULL,
  `subtotal`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `discount`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `tax`           DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `shipping`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total`         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `paid_amount`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `notes`         VARCHAR(500) DEFAULT NULL,
  `status`        ENUM('draft','pending','approved','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
  `created_by`    INT UNSIGNED DEFAULT NULL,
  `approved_by`   INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_po_number` (`po_number`),
  KEY `idx_po_supplier` (`supplier_id`),
  KEY `idx_po_warehouse` (`warehouse_id`),
  KEY `idx_po_status` (`status`),
  KEY `idx_po_date` (`order_date`),
  CONSTRAINT `fk_po_supplier`  FOREIGN KEY (`supplier_id`)  REFERENCES `suppliers`(`id`)  ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_po_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_po_creator`   FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_po_approver`  FOREIGN KEY (`approved_by`)  REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_order_items` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `po_id`        INT UNSIGNED NOT NULL,
  `product_id`   INT UNSIGNED NOT NULL,
  `variant_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `quantity`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `received_qty` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `unit_price`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `discount`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `tax`          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total`        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_po_items_po` (`po_id`),
  KEY `idx_po_items_product` (`product_id`),
  CONSTRAINT `fk_po_items_po`      FOREIGN KEY (`po_id`)      REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_po_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `goods_received` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grn_number`   VARCHAR(60)  NOT NULL,
  `po_id`        INT UNSIGNED DEFAULT NULL,
  `supplier_id`  INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `received_date` DATE        NOT NULL,
  `invoice_no`   VARCHAR(80)  DEFAULT NULL,
  `notes`        VARCHAR(500) DEFAULT NULL,
  `status`       ENUM('draft','completed','cancelled') NOT NULL DEFAULT 'draft',
  `created_by`   INT UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grn_number` (`grn_number`),
  KEY `idx_grn_po` (`po_id`),
  KEY `idx_grn_supplier` (`supplier_id`),
  KEY `idx_grn_date` (`received_date`),
  CONSTRAINT `fk_grn_po`        FOREIGN KEY (`po_id`)        REFERENCES `purchase_orders`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_grn_supplier`  FOREIGN KEY (`supplier_id`)  REFERENCES `suppliers`(`id`)       ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_grn_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)      ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_grn_creator`   FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`)           ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `goods_received_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grn_id`      INT UNSIGNED NOT NULL,
  `po_item_id`  BIGINT UNSIGNED DEFAULT NULL,
  `product_id`  INT UNSIGNED NOT NULL,
  `variant_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `quantity`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `rejected_qty` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `unit_cost`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `notes`       VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_grn_items_grn` (`grn_id`),
  KEY `idx_grn_items_product` (`product_id`),
  CONSTRAINT `fk_grn_items_grn`     FOREIGN KEY (`grn_id`)     REFERENCES `goods_received`(`id`)       ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_grn_items_po_item` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_grn_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)              ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_returns` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `return_no`    VARCHAR(60)  NOT NULL,
  `po_id`        INT UNSIGNED DEFAULT NULL,
  `supplier_id`  INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `return_date`  DATE         NOT NULL,
  `reason`       VARCHAR(400) DEFAULT NULL,
  `total`        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`       ENUM('draft','pending','approved','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_by`   INT UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_purchase_return_no` (`return_no`),
  KEY `idx_pr_supplier` (`supplier_id`),
  CONSTRAINT `fk_pr_po`        FOREIGN KEY (`po_id`)        REFERENCES `purchase_orders`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pr_supplier`  FOREIGN KEY (`supplier_id`)  REFERENCES `suppliers`(`id`)       ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pr_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)      ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pr_creator`   FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`)           ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_return_items` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `return_id`  INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `quantity`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `unit_cost`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_pr_items_return` (`return_id`),
  KEY `idx_pr_items_product` (`product_id`),
  CONSTRAINT `fk_pr_items_return`  FOREIGN KEY (`return_id`)  REFERENCES `purchase_returns`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pr_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)          ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
--  SECTION 6 : PRODUCTION + BOM
-- ============================================================================

CREATE TABLE `bom` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `version`    VARCHAR(30)  NOT NULL DEFAULT '1.0',
  `notes`      VARCHAR(500) DEFAULT NULL,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bom_product` (`product_id`),
  KEY `idx_bom_status` (`status`),
  CONSTRAINT `fk_bom_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bom_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bom_items` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bom_id`       INT UNSIGNED NOT NULL,
  `material_id`  INT UNSIGNED NOT NULL,
  `quantity`     DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `unit`         VARCHAR(30)  NOT NULL DEFAULT 'pcs',
  `wastage_pct`  DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `notes`        VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bom_items_bom` (`bom_id`),
  KEY `idx_bom_items_material` (`material_id`),
  CONSTRAINT `fk_bom_items_bom`      FOREIGN KEY (`bom_id`)      REFERENCES `bom`(`id`)      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bom_items_material` FOREIGN KEY (`material_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `production_orders` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`        VARCHAR(60)  NOT NULL,
  `product_id`      INT UNSIGNED NOT NULL,
  `variant_id`      INT UNSIGNED NOT NULL DEFAULT 0,
  `bom_id`          INT UNSIGNED DEFAULT NULL,
  `quantity`        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `size`            VARCHAR(30)  DEFAULT NULL,
  `color`           VARCHAR(50)  DEFAULT NULL,
  `warehouse_id`    INT UNSIGNED NOT NULL,
  `start_date`      DATE         DEFAULT NULL,
  `expected_date`   DATE         DEFAULT NULL,
  `actual_date`     DATE         DEFAULT NULL,
  `supervisor_id`   INT UNSIGNED DEFAULT NULL,
  `produced_qty`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`          ENUM('planned','in_progress','paused','completed','cancelled') NOT NULL DEFAULT 'planned',
  `notes`           VARCHAR(500) DEFAULT NULL,
  `created_by`      INT UNSIGNED DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_production_order_no` (`order_no`),
  KEY `idx_prod_product` (`product_id`),
  KEY `idx_prod_bom` (`bom_id`),
  KEY `idx_prod_warehouse` (`warehouse_id`),
  KEY `idx_prod_status` (`status`),
  KEY `idx_prod_dates` (`start_date`,`expected_date`),
  CONSTRAINT `fk_prod_product`    FOREIGN KEY (`product_id`)    REFERENCES `products`(`id`)       ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_bom`        FOREIGN KEY (`bom_id`)        REFERENCES `bom`(`id`)            ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_warehouse`  FOREIGN KEY (`warehouse_id`)  REFERENCES `warehouses`(`id`)     ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `employees`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_creator`    FOREIGN KEY (`created_by`)    REFERENCES `users`(`id`)          ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `production_materials` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `production_order_id` INT UNSIGNED NOT NULL,
  `material_id`         INT UNSIGNED NOT NULL,
  `required_qty`        DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `issued_qty`          DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `unit`                VARCHAR(30)  NOT NULL DEFAULT 'pcs',
  `unit_cost`           DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`              ENUM('pending','partially_issued','issued','returned') NOT NULL DEFAULT 'pending',
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prod_mat_order` (`production_order_id`),
  KEY `idx_prod_mat_material` (`material_id`),
  CONSTRAINT `fk_prod_mat_order`    FOREIGN KEY (`production_order_id`) REFERENCES `production_orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_mat_material` FOREIGN KEY (`material_id`)         REFERENCES `products`(`id`)          ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `production_outputs` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `production_order_id` INT UNSIGNED NOT NULL,
  `product_id`          INT UNSIGNED NOT NULL,
  `variant_id`          INT UNSIGNED NOT NULL DEFAULT 0,
  `quantity`            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `passed_qty`          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `rejected_qty`        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `warehouse_id`        INT UNSIGNED NOT NULL,
  `output_date`         DATE         NOT NULL,
  `status`              ENUM('pending','qc_pending','completed','rejected') NOT NULL DEFAULT 'pending',
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prod_out_order` (`production_order_id`),
  KEY `idx_prod_out_product` (`product_id`),
  CONSTRAINT `fk_prod_out_order`     FOREIGN KEY (`production_order_id`) REFERENCES `production_orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_out_product`   FOREIGN KEY (`product_id`)          REFERENCES `products`(`id`)          ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_out_warehouse` FOREIGN KEY (`warehouse_id`)        REFERENCES `warehouses`(`id`)        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
--  SECTION 7 : QUALITY CONTROL
-- ============================================================================

CREATE TABLE `qc_inspections` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inspection_no`       VARCHAR(60)  NOT NULL,
  `production_order_id` INT UNSIGNED DEFAULT NULL,
  `product_id`          INT UNSIGNED NOT NULL,
  `variant_id`          INT UNSIGNED NOT NULL DEFAULT 0,
  `batch_no`            VARCHAR(60)  DEFAULT NULL,
  `inspector_id`        INT UNSIGNED DEFAULT NULL,
  `inspection_date`     DATE         NOT NULL,
  `inspected_qty`       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `passed_qty`          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `failed_qty`          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`              ENUM('pending','passed','failed','partially_passed') NOT NULL DEFAULT 'pending',
  `remarks`             VARCHAR(500) DEFAULT NULL,
  `created_by`          INT UNSIGNED DEFAULT NULL,
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_qc_inspection_no` (`inspection_no`),
  KEY `idx_qc_prod_order` (`production_order_id`),
  KEY `idx_qc_product` (`product_id`),
  KEY `idx_qc_status` (`status`),
  KEY `idx_qc_date` (`inspection_date`),
  CONSTRAINT `fk_qc_prod_order` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_qc_product`    FOREIGN KEY (`product_id`)          REFERENCES `products`(`id`)          ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_qc_inspector`  FOREIGN KEY (`inspector_id`)        REFERENCES `employees`(`id`)         ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_qc_creator`    FOREIGN KEY (`created_by`)          REFERENCES `users`(`id`)             ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `qc_defects` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inspection_id` INT UNSIGNED NOT NULL,
  `defect_type`   ENUM('stitching','fabric','color','size','print','button','zipper','packaging','other') NOT NULL DEFAULT 'other',
  `description`   VARCHAR(400) DEFAULT NULL,
  `quantity`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `severity`      ENUM('minor','major','critical') NOT NULL DEFAULT 'minor',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qc_defects_inspection` (`inspection_id`),
  KEY `idx_qc_defects_type` (`defect_type`),
  CONSTRAINT `fk_qc_defects_inspection` FOREIGN KEY (`inspection_id`) REFERENCES `qc_inspections`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
--  SECTION 8 : SALES
-- ============================================================================

CREATE TABLE `customers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(190) NOT NULL,
  `company`       VARCHAR(190) DEFAULT NULL,
  `phone`         VARCHAR(30)  DEFAULT NULL,
  `email`         VARCHAR(190) DEFAULT NULL,
  `address`       VARCHAR(400) DEFAULT NULL,
  `city`          VARCHAR(100) DEFAULT NULL,
  `country`       VARCHAR(100) DEFAULT 'Sri Lanka',
  `tax_number`    VARCHAR(60)  DEFAULT NULL,
  `credit_limit`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `balance`       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customers_name` (`name`),
  KEY `idx_customers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sales_orders` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`      VARCHAR(60)  NOT NULL,
  `customer_id`   INT UNSIGNED NOT NULL,
  `warehouse_id`  INT UNSIGNED NOT NULL,
  `order_date`    DATE         NOT NULL,
  `delivery_date` DATE         DEFAULT NULL,
  `subtotal`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `discount`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `tax`           DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `shipping`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total`         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `paid_amount`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `notes`         VARCHAR(500) DEFAULT NULL,
  `status`        ENUM('draft','pending','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'draft',
  `created_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_so_number` (`order_no`),
  KEY `idx_so_customer` (`customer_id`),
  KEY `idx_so_warehouse` (`warehouse_id`),
  KEY `idx_so_status` (`status`),
  KEY `idx_so_date` (`order_date`),
  CONSTRAINT `fk_so_customer`  FOREIGN KEY (`customer_id`)  REFERENCES `customers`(`id`)  ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_so_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_so_creator`   FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sales_order_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `so_id`         INT UNSIGNED NOT NULL,
  `product_id`    INT UNSIGNED NOT NULL,
  `variant_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `description`   VARCHAR(255) DEFAULT NULL,
  `quantity`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `delivered_qty` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `unit_price`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `discount`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `tax`           DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total`         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_so_items_so` (`so_id`),
  KEY `idx_so_items_product` (`product_id`),
  CONSTRAINT `fk_so_items_so`      FOREIGN KEY (`so_id`)      REFERENCES `sales_orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_so_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)     ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoices` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no`   VARCHAR(60)  NOT NULL,
  `so_id`        INT UNSIGNED DEFAULT NULL,
  `customer_id`  INT UNSIGNED NOT NULL,
  `invoice_date` DATE         NOT NULL,
  `due_date`     DATE         DEFAULT NULL,
  `subtotal`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `discount`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `tax`          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total`        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `paid_amount`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`       ENUM('draft','unpaid','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
  `notes`        VARCHAR(500) DEFAULT NULL,
  `created_by`   INT UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invoice_no` (`invoice_no`),
  KEY `idx_inv_customer` (`customer_id`),
  KEY `idx_inv_so` (`so_id`),
  KEY `idx_inv_status` (`status`),
  KEY `idx_inv_date` (`invoice_date`),
  CONSTRAINT `fk_inv_so`       FOREIGN KEY (`so_id`)       REFERENCES `sales_orders`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoice_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`  INT UNSIGNED NOT NULL,
  `product_id`  INT UNSIGNED NOT NULL,
  `variant_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `description` VARCHAR(255) DEFAULT NULL,
  `quantity`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `unit_price`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `discount`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `tax`         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total`       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_inv_items_invoice` (`invoice_id`),
  KEY `idx_inv_items_product` (`product_id`),
  CONSTRAINT `fk_inv_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `deliveries` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `delivery_no`   VARCHAR(60)  NOT NULL,
  `so_id`         INT UNSIGNED DEFAULT NULL,
  `customer_id`   INT UNSIGNED NOT NULL,
  `warehouse_id`  INT UNSIGNED NOT NULL,
  `delivery_date` DATE         NOT NULL,
  `address`       VARCHAR(400) DEFAULT NULL,
  `driver`        VARCHAR(120) DEFAULT NULL,
  `vehicle_no`    VARCHAR(60)  DEFAULT NULL,
  `status`        ENUM('pending','dispatched','delivered','failed','cancelled') NOT NULL DEFAULT 'pending',
  `notes`         VARCHAR(400) DEFAULT NULL,
  `created_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_delivery_no` (`delivery_no`),
  KEY `idx_del_so` (`so_id`),
  KEY `idx_del_customer` (`customer_id`),
  CONSTRAINT `fk_del_so`        FOREIGN KEY (`so_id`)        REFERENCES `sales_orders`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_del_customer`  FOREIGN KEY (`customer_id`)  REFERENCES `customers`(`id`)    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_del_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)   ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_del_creator`   FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`)        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sales_returns` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `return_no`    VARCHAR(60)  NOT NULL,
  `so_id`        INT UNSIGNED DEFAULT NULL,
  `customer_id`  INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `return_date`  DATE         NOT NULL,
  `reason`       VARCHAR(400) DEFAULT NULL,
  `total`        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`       ENUM('pending','approved','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_by`   INT UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sales_return_no` (`return_no`),
  KEY `idx_sr_customer` (`customer_id`),
  CONSTRAINT `fk_sr_so`        FOREIGN KEY (`so_id`)        REFERENCES `sales_orders`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_customer`  FOREIGN KEY (`customer_id`)  REFERENCES `customers`(`id`)    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)   ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_creator`   FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`)        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sales_return_items` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `return_id`  INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `quantity`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `unit_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_sr_items_return` (`return_id`),
  KEY `idx_sr_items_product` (`product_id`),
  CONSTRAINT `fk_sr_items_return`  FOREIGN KEY (`return_id`)  REFERENCES `sales_returns`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
--  SECTION 9 : SYSTEM
-- ============================================================================

CREATE TABLE `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `title`      VARCHAR(190) NOT NULL,
  `message`    VARCHAR(500) DEFAULT NULL,
  `type`       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `module`     VARCHAR(60)  DEFAULT NULL,
  `link`       VARCHAR(255) DEFAULT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_read` (`is_read`),
  KEY `idx_notif_created` (`created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(120) NOT NULL,
  `setting_value` TEXT         DEFAULT NULL,
  `setting_group` VARCHAR(60)  NOT NULL DEFAULT 'general',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`setting_key`),
  KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
--  SAMPLE DATA
-- ============================================================================

-- ------------------------------ ROLES --------------------------------------
INSERT INTO `roles` (`id`,`name`,`slug`,`description`,`is_system`) VALUES
(1,'Super Admin','super_admin','Full unrestricted access to the entire system',1),
(2,'Admin','admin','Administrative access except destructive system settings',1),
(3,'Inventory Manager','inventory_manager','Manages products, stock and warehouses',0),
(4,'Warehouse Manager','warehouse_manager','Manages warehouse stock and transfers',0),
(5,'Purchase Manager','purchase_manager','Manages suppliers and purchasing',0),
(6,'Production Manager','production_manager','Manages BOM and production orders',0),
(7,'QC Manager','qc_manager','Manages quality control inspections',0),
(8,'Sales Manager','sales_manager','Manages customers, sales and invoices',0),
(9,'HR Manager','hr_manager','Manages employees and attendance',0),
(10,'Employee','employee','Read-only access to dashboards and own data',0);

-- --------------------------- PERMISSIONS -----------------------------------
INSERT INTO `permissions` (`id`,`name`,`slug`,`module`) VALUES
(1,'Dashboard View','dashboard.view','dashboard'),
(2,'Product View','product.view','product'),
(3,'Product Create','product.create','product'),
(4,'Product Edit','product.edit','product'),
(5,'Product Delete','product.delete','product'),
(6,'Inventory View','inventory.view','inventory'),
(7,'Inventory Manage','inventory.manage','inventory'),
(8,'Warehouse Manage','warehouse.manage','warehouse'),
(9,'Supplier Manage','supplier.manage','supplier'),
(10,'Purchase Manage','purchase.manage','purchase'),
(11,'Production Manage','production.manage','production'),
(12,'BOM Manage','bom.manage','production'),
(13,'QC Manage','qc.manage','quality'),
(14,'Sales Manage','sales.manage','sales'),
(15,'Customer Manage','customer.manage','sales'),
(16,'Employee Manage','employee.manage','employee'),
(17,'Attendance Manage','attendance.manage','employee'),
(18,'Reports View','reports.view','reports'),
(19,'Users Manage','users.manage','users'),
(20,'Settings Manage','settings.manage','settings'),
(21,'Notification View','notification.view','notification'),
(22,'Barcode Manage','barcode.manage','barcode');

-- ------------------------ ROLE_PERMISSIONS ---------------------------------
-- Super Admin → everything
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Admin → everything except settings deletion scope is kept, so all too
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `slug` <> 'settings.manage';

-- Inventory Manager
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES
(3,1),(3,2),(3,3),(3,4),(3,5),(3,6),(3,7),(3,8),(3,18),(3,21),(3,22);

-- Warehouse Manager
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES
(4,1),(4,2),(4,6),(4,7),(4,8),(4,18),(4,21),(4,22);

-- Purchase Manager
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES
(5,1),(5,2),(5,6),(5,9),(5,10),(5,18),(5,21);

-- Production Manager
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES
(6,1),(6,2),(6,6),(6,11),(6,12),(6,18),(6,21);

-- QC Manager
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES
(7,1),(7,2),(7,6),(7,13),(7,18),(7,21);

-- Sales Manager
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES
(8,1),(8,2),(8,6),(8,14),(8,15),(8,18),(8,21);

-- HR Manager
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES
(9,1),(9,16),(9,17),(9,18),(9,21);

-- Employee
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES
(10,1),(10,2),(10,6),(10,21);

-- ------------------------------ USERS --------------------------------------
-- Default password for every sample account is:  password
INSERT INTO `users` (`id`,`name`,`email`,`password`,`phone`,`role_id`,`status`,`created_at`) VALUES
(1,'Super Administrator','admin@garment.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+94 77 100 1000',1,'active',NOW()),
(2,'Nimal Perera','inventory@garment.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+94 77 100 1001',3,'active',NOW()),
(3,'Sunil Fernando','purchase@garment.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+94 77 100 1002',5,'active',NOW()),
(4,'Kamal Silva','production@garment.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+94 77 100 1003',6,'active',NOW()),
(5,'Dilani Jayawardena','qc@garment.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+94 77 100 1004',7,'active',NOW()),
(6,'Ruwan Weerasinghe','sales@garment.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+94 77 100 1005',8,'active',NOW()),
(7,'Hasini Rathnayake','hr@garment.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+94 77 100 1006',9,'active',NOW()),
(8,'Chamara Bandara','warehouse@garment.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+94 77 100 1007',4,'active',NOW());

INSERT INTO `user_roles` (`user_id`,`role_id`) VALUES
(1,1),(2,3),(3,5),(4,6),(5,7),(6,8),(7,9),(8,4);

-- --------------------------- DEPARTMENTS -----------------------------------
INSERT INTO `departments` (`id`,`name`,`code`,`description`) VALUES
(1,'Production','PROD','Cutting, sewing, finishing and packing operations'),
(2,'Warehouse','WHS','Raw material and finished goods storage'),
(3,'Quality Control','QC','Inspection and defect analysis'),
(4,'Purchasing','PUR','Supplier and procurement management'),
(5,'Sales','SAL','Customer orders and distribution'),
(6,'Human Resources','HR','Employee administration'),
(7,'Finance','FIN','Accounts, payroll and costing'),
(8,'Management','MGT','Executive management');

-- ---------------------------- EMPLOYEES ------------------------------------
INSERT INTO `employees` (`id`,`employee_no`,`full_name`,`nic`,`gender`,`dob`,`phone`,`email`,`address`,`department_id`,`position`,`join_date`,`salary`,`emergency_contact`,`status`) VALUES
(1,'EMP-0001','Kamal Silva','198512304567','male','1985-04-12','+94 71 234 5678','kamal.silva@garment.local','No 12, Temple Road, Gampaha',1,'Production Manager','2015-02-01',185000.00,'Nadeeka Silva - +94 71 234 0000','active'),
(2,'EMP-0002','Chamara Bandara','198803209876','male','1988-09-20','+94 71 345 6789','chamara.b@garment.local','45/A, Lake View, Kelaniya',2,'Warehouse Manager','2016-06-15',145000.00,'Anoma Bandara - +94 71 345 0000','active'),
(3,'EMP-0003','Dilani Jayawardena','199201155432','female','1992-01-15','+94 71 456 7890','dilani.j@garment.local','78, Flower Road, Colombo 07',3,'QC Manager','2018-03-05',135000.00,'Rohan J - +94 71 456 0000','active'),
(4,'EMP-0004','Sunil Fernando','198307118765','male','1983-07-11','+94 71 567 8901','sunil.f@garment.local','23, Main Street, Negombo',4,'Purchase Manager','2014-01-20',165000.00,'Kumari F - +94 71 567 0000','active'),
(5,'EMP-0005','Ruwan Weerasinghe','199005252345','male','1990-05-25','+94 71 678 9012','ruwan.w@garment.local','90, Beach Road, Mount Lavinia',5,'Sales Manager','2017-08-12',155000.00,'Iresha W - +94 71 678 0000','active'),
(6,'EMP-0006','Hasini Rathnayake','199407301234','female','1994-07-30','+94 71 789 0123','hasini.r@garment.local','56, Garden Lane, Kandy',6,'HR Manager','2019-02-18',130000.00,'Sunil R - +94 71 789 0000','active'),
(7,'EMP-0007','Nimal Perera','198911143210','male','1989-11-14','+94 71 890 1234','nimal.p@garment.local','34, Station Road, Ragama',1,'Production Supervisor','2016-09-01',95000.00,'Sandya P - +94 71 890 0000','active'),
(8,'EMP-0008','Sachini Gunasekara','199603086789','female','1996-03-08','+94 71 901 2345','sachini.g@garment.local','67, Hill Street, Nuwara Eliya',3,'QC Inspector','2020-05-10',72000.00,'Nimal G - +94 71 901 0000','active'),
(9,'EMP-0009','Tharindu Alwis','199509214567','male','1995-09-21','+94 71 012 3456','tharindu.a@garment.local','12, Cross Road, Panadura',2,'Store Keeper','2019-11-25',68000.00,'Chandra A - +94 71 012 0000','active'),
(10,'EMP-0010','Menaka Dias','199712058901','female','1997-12-05','+94 71 123 4567','menaka.d@garment.local','89, Park Avenue, Dehiwala',1,'Machine Operator','2021-01-15',55000.00,'Sunil D - +94 71 123 0000','active'),
(11,'EMP-0011','Ashan Kuruppu','199804173456','male','1998-04-17','+94 71 234 1111','ashan.k@garment.local','5, Rose Lane, Ja-Ela',1,'Cutting Operator','2021-07-01',58000.00,'Priya K - +94 71 234 1111','active'),
(12,'EMP-0012','Ishara Madushani','199910229012','female','1999-10-22','+94 71 345 2222','ishara.m@garment.local','77, Sunflower Road, Kadawatha',1,'Finishing Operator','2022-02-14',52000.00,'Kumara M - +94 71 345 2222','active');

-- --------------------------- WAREHOUSES ------------------------------------
INSERT INTO `warehouses` (`id`,`name`,`code`,`address`,`city`,`country`,`manager_id`,`phone`,`email`,`is_default`,`status`) VALUES
(1,'Main Raw Material Store','WH-RM-01','Industrial Zone, Block A','Gampaha','Sri Lanka',2,'+94 33 222 3344','rm.store@garment.local',1,'active'),
(2,'Finished Goods Warehouse','WH-FG-01','Industrial Zone, Block C','Gampaha','Sri Lanka',2,'+94 33 222 3355','fg.store@garment.local',0,'active'),
(3,'Production Floor Store','WH-PF-01','Factory Floor, Building 2','Gampaha','Sri Lanka',2,'+94 33 222 3366','pf.store@garment.local',0,'active');

INSERT INTO `warehouse_locations` (`warehouse_id`,`rack`,`bin`,`code`,`description`) VALUES
(1,'R01','B01','WH-RM-01-R01-B01','Fabric rolls rack 1'),
(1,'R01','B02','WH-RM-01-R01-B02','Fabric rolls rack 1'),
(1,'R02','B01','WH-RM-01-R02-B01','Thread and trim storage'),
(1,'R03','B01','WH-RM-01-R03-B01','Accessories and buttons'),
(2,'A01','B01','WH-FG-01-A01-B01','Finished garments pallet A1'),
(2,'A01','B02','WH-FG-01-A01-B02','Finished garments pallet A2'),
(3,'P01','B01','WH-PF-01-P01-B01','WIP buffer location');

-- ---------------------------- CATEGORIES -----------------------------------
INSERT INTO `categories` (`id`,`name`,`slug`,`parent_id`,`description`,`status`) VALUES
(1,'Raw Materials','raw-materials',NULL,'All production input materials','active'),
(2,'Fabric','fabric',1,'Knitted and woven fabric rolls','active'),
(3,'Thread','thread',1,'Sewing and embroidery threads','active'),
(4,'Accessories','accessories',1,'Buttons, zippers, labels and trims','active'),
(5,'Packaging','packaging',1,'Poly bags, cartons and tags','active'),
(6,'Finished Garments','finished-garments',NULL,'Ready to sell garments','active'),
(7,'T-Shirts','t-shirts',6,'Casual and printed t-shirts','active'),
(8,'Polo Shirts','polo-shirts',6,'Collar polo shirts','active'),
(9,'Hoodies & Sweatshirts','hoodies-sweatshirts',6,'Fleece hoodies and sweats','active'),
(10,'Denim','denim',6,'Denim jeans and jackets','active'),
(11,'Kids Wear','kids-wear',6,'Children clothing range','active');

-- ---------------------------- SUPPLIERS ------------------------------------
INSERT INTO `suppliers` (`id`,`company_name`,`contact_person`,`phone`,`email`,`address`,`city`,`country`,`tax_number`,`payment_terms`,`bank_details`,`status`) VALUES
(1,'Lanka Textile Mills (Pvt) Ltd','Ajith Kumara','+94 11 234 5678','sales@lankatextile.lk','120, Industrial Estate','Biyagama','Sri Lanka','VAT-114567890','30 Days Credit','Commercial Bank - 8001234567','active'),
(2,'Colombo Fabric House','Ravi Chandran','+94 11 345 6789','orders@colombofabric.lk','45, Main Street','Colombo 11','Sri Lanka','VAT-223456789','15 Days Credit','Sampath Bank - 1002345678','active'),
(3,'Global Trim Solutions','Nadeesha Perera','+94 11 456 7890','info@globaltrim.lk','88, Lake Drive','Kelaniya','Sri Lanka','VAT-334567891','Cash on Delivery','HNB - 2003456789','active'),
(4,'Asian Thread Industries','Mohamed Rizwan','+94 11 567 8901','sales@asianthread.lk','22, Factory Road','Ekala','Sri Lanka','VAT-445678912','45 Days Credit','Seylan Bank - 3004567890','active'),
(5,'PackWell Packaging Ltd','Saman Silva','+94 11 678 9012','hello@packwell.lk','9, Industrial Park','Kadawatha','Sri Lanka','VAT-556789123','30 Days Credit','NSB - 4005678912','active'),
(6,'Premium Denim Importers','John Fernando','+94 11 789 0123','import@premiumdenim.lk','67, Port Road','Colombo 15','Sri Lanka','VAT-667891234','60 Days Credit','DFCC Bank - 5006789123','active');

INSERT INTO `supplier_contacts` (`supplier_id`,`name`,`position`,`phone`,`email`) VALUES
(1,'Ajith Kumara','Sales Manager','+94 11 234 5678','ajith@lankatextile.lk'),
(1,'Priyantha Silva','Account Manager','+94 11 234 5679','priyantha@lankatextile.lk'),
(2,'Ravi Chandran','Director','+94 11 345 6789','ravi@colombofabric.lk'),
(4,'Mohamed Rizwan','Export Manager','+94 11 567 8901','rizwan@asianthread.lk');

-- ----------------------------- PRODUCTS ------------------------------------
INSERT INTO `products`
(`id`,`sku`,`product_code`,`name`,`product_type`,`category_id`,`sub_category_id`,`brand`,`description`,`unit`,`supplier_id`,`cost_price`,`selling_price`,`min_stock`,`max_stock`,`reorder_level`,`barcode`,`has_variants`,`status`,`created_by`) VALUES
-- FABRIC
(1,'FAB-COT-001','PC-FAB-001','Cotton Single Jersey Fabric 180GSM','fabric',1,2,'Lanka Textile','100% cotton single jersey knit fabric, 180 GSM, 180cm width','Meter',1,850.00,0.00,500,10000,800,'2000000000011',0,'active',1),
(2,'FAB-COT-002','PC-FAB-002','Cotton Pique Fabric 220GSM','fabric',1,2,'Lanka Textile','100% cotton pique knit fabric for polo shirts','Meter',1,980.00,0.00,400,8000,600,'2000000000028',0,'active',1),
(3,'FAB-POL-001','PC-FAB-003','Polyester Interlock Fabric 150GSM','fabric',1,2,'Colombo Fabric','Moisture wicking polyester interlock','Meter',2,720.00,0.00,300,6000,500,'2000000000035',0,'active',1),
(4,'FAB-DEN-001','PC-FAB-004','Denim Fabric 12oz Indigo','fabric',1,2,'Premium Denim','Heavy weight indigo denim 12oz','Meter',6,1450.00,0.00,300,5000,450,'2000000000042',0,'active',1),
(5,'FAB-FLC-001','PC-FAB-005','Fleece Brushed Fabric 320GSM','fabric',1,2,'Lanka Textile','Brushed back fleece for hoodies','Meter',1,1280.00,0.00,250,4000,400,'2000000000059',0,'active',1),
-- THREAD
(6,'THR-POL-001','PC-THR-001','Polyester Sewing Thread 40/2 - White','thread',1,3,'Asian Thread','High tenacity polyester thread 5000m cone','Cone',4,420.00,0.00,100,2000,150,'2000000000066',0,'active',1),
(7,'THR-POL-002','PC-THR-002','Polyester Sewing Thread 40/2 - Black','thread',1,3,'Asian Thread','High tenacity polyester thread 5000m cone','Cone',4,420.00,0.00,100,2000,150,'2000000000073',0,'active',1),
(8,'THR-COT-001','PC-THR-003','Cotton Sewing Thread 60/3 - Natural','thread',1,3,'Asian Thread','Mercerised cotton thread for topstitching','Cone',4,380.00,0.00,80,1500,120,'2000000000080',0,'active',1),
-- BUTTONS
(9,'BTN-4H-18L','PC-BTN-001','4-Hole Plastic Button 18L White','button',1,4,'Global Trim','18 ligne 4-hole polyester button','Piece',3,3.50,0.00,5000,200000,8000,'2000000000097',0,'active',1),
(10,'BTN-4H-24L','PC-BTN-002','4-Hole Plastic Button 24L Black','button',1,4,'Global Trim','24 ligne 4-hole polyester button','Piece',3,4.20,0.00,5000,200000,8000,'2000000000103',0,'active',1),
(11,'BTN-SNP-15L','PC-BTN-003','Snap Button 15L Nickel','button',1,4,'Global Trim','Nickel plated snap fastener','Piece',3,8.50,0.00,2000,80000,3000,'2000000000110',0,'active',1),
-- ZIPPERS
(12,'ZPR-NYL-005','PC-ZPR-001','Nylon Zipper 5 inch Black','zipper',1,4,'Global Trim','No.3 nylon coil zipper, closed end','Piece',3,22.00,0.00,2000,60000,3000,'2000000000127',0,'active',1),
(13,'ZPR-MET-010','PC-ZPR-002','Metal Zipper 10 inch Antique Brass','zipper',1,4,'Global Trim','No.5 metal zipper antique brass finish','Piece',3,58.00,0.00,1000,30000,1500,'2000000000134',0,'active',1),
-- LABELS
(14,'LBL-WOV-001','PC-LBL-001','Woven Main Label - Brand','label',1,4,'Global Trim','Damask woven main brand label','Piece',3,6.50,0.00,5000,150000,8000,'2000000000141',0,'active',1),
(15,'LBL-CAR-001','PC-LBL-002','Printed Care Label','label',1,4,'Global Trim','Satin printed care and composition label','Piece',3,2.80,0.00,8000,250000,12000,'2000000000158',0,'active',1),
(16,'LBL-SIZ-001','PC-LBL-003','Printed Size Label','label',1,4,'Global Trim','Satin printed size label','Piece',3,1.90,0.00,8000,250000,12000,'2000000000165',0,'active',1),
-- PACKAGING
(17,'PKG-POL-001','PC-PKG-001','Poly Bag 12x16 Clear','packaging',1,5,'PackWell','LDPE clear poly bag with self seal','Piece',5,7.50,0.00,5000,200000,10000,'2000000000172',0,'active',1),
(18,'PKG-POL-002','PC-PKG-002','Poly Bag 16x20 Clear','packaging',1,5,'PackWell','LDPE clear poly bag with self seal','Piece',5,11.00,0.00,4000,150000,8000,'2000000000189',0,'active',1),
(19,'PKG-CAR-001','PC-PKG-003','Carton Box 60x40x40','packaging',1,5,'PackWell','5-ply corrugated export carton','Piece',5,285.00,0.00,300,10000,500,'2000000000196',0,'active',1),
(20,'PKG-TAG-001','PC-PKG-004','Hang Tag with String','packaging',1,5,'PackWell','Cardboard hang tag with barb string','Piece',5,9.00,0.00,5000,150000,8000,'2000000000202',0,'active',1),
-- ACCESSORIES
(21,'ACC-ELS-001','PC-ACC-001','Elastic Band 1 inch','accessory',1,4,'Global Trim','Woven elastic band 25mm width','Meter',3,32.00,0.00,1000,40000,2000,'2000000000219',0,'active',1),
(22,'ACC-FUS-001','PC-ACC-002','Fusible Interlining 50GSM','accessory',1,4,'Global Trim','Woven fusible interlining for collars','Meter',3,78.00,0.00,800,25000,1200,'2000000000226',0,'active',1),
-- FINISHED GARMENTS
(23,'TS-BAS-001','PC-FG-001','Basic Crew Neck T-Shirt','finished_garment',6,7,'Own Brand','180 GSM cotton crew neck short sleeve t-shirt','Pcs',NULL,780.00,1750.00,200,20000,400,'2000000000233',1,'active',1),
(24,'TS-PRT-002','PC-FG-002','Printed Graphic T-Shirt','finished_garment',6,7,'Own Brand','Cotton t-shirt with front screen print','Pcs',NULL,890.00,2100.00,150,15000,300,'2000000000240',1,'active',1),
(25,'PL-CLS-001','PC-FG-003','Classic Pique Polo Shirt','finished_garment',6,8,'Own Brand','220 GSM cotton pique polo with 3 button placket','Pcs',NULL,1150.00,2750.00,150,12000,250,'2000000000257',1,'active',1),
(26,'HD-ZIP-001','PC-FG-004','Zip-Up Fleece Hoodie','finished_garment',6,9,'Own Brand','320 GSM brushed fleece full zip hoodie','Pcs',NULL,1850.00,4200.00,100,8000,200,'2000000000264',1,'active',1),
(27,'DN-SLM-001','PC-FG-005','Slim Fit Denim Jeans','finished_garment',6,10,'Own Brand','12oz indigo slim fit 5 pocket denim jeans','Pcs',NULL,1980.00,4650.00,80,6000,150,'2000000000271',1,'active',1),
(28,'KD-TSH-001','PC-FG-006','Kids Cotton T-Shirt','finished_garment',6,11,'Own Brand','Kids 160 GSM cotton t-shirt','Pcs',NULL,520.00,1250.00,120,10000,250,'2000000000288',1,'active',1),
(29,'LG-JOG-001','PC-FG-007','Fleece Jogger Pants','finished_garment',6,9,'Own Brand','280 GSM fleece jogger with elastic waist','Pcs',NULL,1350.00,3100.00,100,7000,180,'2000000000295',1,'active',1),
(30,'SH-CRT-001','PC-FG-008','Cotton Casual Shirt','finished_garment',6,8,'Own Brand','Yarn dyed cotton long sleeve casual shirt','Pcs',NULL,1420.00,3350.00,90,6000,160,'2000000000301',1,'active',1);

-- ------------------------ PRODUCT VARIANTS ---------------------------------
INSERT INTO `product_variants` (`product_id`,`size`,`color`,`sku`,`barcode`,`cost_price`,`selling_price`,`status`) VALUES
-- TS-BAS-001 (23)
(23,'S','Black','TS-BAS-001-BLK-S','2100000000018',780.00,1750.00,'active'),
(23,'M','Black','TS-BAS-001-BLK-M','2100000000025',780.00,1750.00,'active'),
(23,'L','Black','TS-BAS-001-BLK-L','2100000000032',780.00,1750.00,'active'),
(23,'XL','Black','TS-BAS-001-BLK-XL','2100000000049',780.00,1750.00,'active'),
(23,'S','White','TS-BAS-001-WHT-S','2100000000056',780.00,1750.00,'active'),
(23,'M','White','TS-BAS-001-WHT-M','2100000000063',780.00,1750.00,'active'),
(23,'L','White','TS-BAS-001-WHT-L','2100000000070',780.00,1750.00,'active'),
(23,'XL','White','TS-BAS-001-WHT-XL','2100000000087',780.00,1750.00,'active'),
-- TS-PRT-002 (24)
(24,'S','Navy','TS-PRT-002-NVY-S','2100000000094',890.00,2100.00,'active'),
(24,'M','Navy','TS-PRT-002-NVY-M','2100000000100',890.00,2100.00,'active'),
(24,'L','Navy','TS-PRT-002-NVY-L','2100000000117',890.00,2100.00,'active'),
(24,'XL','Navy','TS-PRT-002-NVY-XL','2100000000124',890.00,2100.00,'active'),
(24,'M','Red','TS-PRT-002-RED-M','2100000000131',890.00,2100.00,'active'),
(24,'L','Red','TS-PRT-002-RED-L','2100000000148',890.00,2100.00,'active'),
-- PL-CLS-001 (25)
(25,'S','Navy','PL-CLS-001-NVY-S','2100000000155',1150.00,2750.00,'active'),
(25,'M','Navy','PL-CLS-001-NVY-M','2100000000162',1150.00,2750.00,'active'),
(25,'L','Navy','PL-CLS-001-NVY-L','2100000000179',1150.00,2750.00,'active'),
(25,'XL','Navy','PL-CLS-001-NVY-XL','2100000000186',1150.00,2750.00,'active'),
(25,'M','White','PL-CLS-001-WHT-M','2100000000193',1150.00,2750.00,'active'),
(25,'L','White','PL-CLS-001-WHT-L','2100000000209',1150.00,2750.00,'active'),
-- HD-ZIP-001 (26)
(26,'M','Grey','HD-ZIP-001-GRY-M','2100000000216',1850.00,4200.00,'active'),
(26,'L','Grey','HD-ZIP-001-GRY-L','2100000000223',1850.00,4200.00,'active'),
(26,'XL','Grey','HD-ZIP-001-GRY-XL','2100000000230',1850.00,4200.00,'active'),
(26,'L','Black','HD-ZIP-001-BLK-L','2100000000247',1850.00,4200.00,'active'),
-- DN-SLM-001 (27)
(27,'30','Indigo','DN-SLM-001-IND-30','2100000000254',1980.00,4650.00,'active'),
(27,'32','Indigo','DN-SLM-001-IND-32','2100000000261',1980.00,4650.00,'active'),
(27,'34','Indigo','DN-SLM-001-IND-34','2100000000278',1980.00,4650.00,'active'),
(27,'36','Indigo','DN-SLM-001-IND-36','2100000000285',1980.00,4650.00,'active'),
-- KD-TSH-001 (28)
(28,'2-3Y','Blue','KD-TSH-001-BLU-23','2100000000292',520.00,1250.00,'active'),
(28,'4-5Y','Blue','KD-TSH-001-BLU-45','2100000000308',520.00,1250.00,'active'),
(28,'6-7Y','Blue','KD-TSH-001-BLU-67','2100000000315',520.00,1250.00,'active'),
-- LG-JOG-001 (29)
(29,'M','Charcoal','LG-JOG-001-CHR-M','2100000000322',1350.00,3100.00,'active'),
(29,'L','Charcoal','LG-JOG-001-CHR-L','2100000000339',1350.00,3100.00,'active'),
(29,'XL','Charcoal','LG-JOG-001-CHR-XL','2100000000346',1350.00,3100.00,'active'),
-- SH-CRT-001 (30)
(30,'M','Sky Blue','SH-CRT-001-SKY-M','2100000000353',1420.00,3350.00,'active'),
(30,'L','Sky Blue','SH-CRT-001-SKY-L','2100000000360',1420.00,3350.00,'active'),
(30,'XL','Sky Blue','SH-CRT-001-SKY-XL','2100000000377',1420.00,3350.00,'active');

-- ------------------------------- STOCK -------------------------------------
INSERT INTO `stock` (`product_id`,`variant_id`,`warehouse_id`,`quantity`,`reserved_qty`,`damaged_qty`,`rejected_qty`) VALUES
-- Raw materials in Main RM Store
(1,0,1,2400.00,0,0,0),
(2,0,1,1650.00,0,0,0),
(3,0,1,980.00,0,0,0),
(4,0,1,760.00,0,0,0),
(5,0,1,640.00,0,0,0),
(6,0,1,320.00,0,0,0),
(7,0,1,285.00,0,0,0),
(8,0,1,190.00,0,0,0),
(9,0,1,42000.00,0,0,0),
(10,0,1,36500.00,0,0,0),
(11,0,1,9800.00,0,0,0),
(12,0,1,12500.00,0,0,0),
(13,0,1,4200.00,0,0,0),
(14,0,1,28000.00,0,0,0),
(15,0,1,46000.00,0,0,0),
(16,0,1,51000.00,0,0,0),
(17,0,1,32000.00,0,0,0),
(18,0,1,18500.00,0,0,0),
(19,0,1,1250.00,0,0,0),
(20,0,1,26000.00,0,0,0),
(21,0,1,6400.00,0,0,0),
(22,0,1,3100.00,0,0,0),
-- Production floor buffer
(1,0,3,300.00,0,0,0),
(6,0,3,40.00,0,0,0),
(9,0,3,4000.00,0,0,0),
-- Finished goods in FG warehouse (variant based)
(23,1,2,420.00,0,0,0),
(23,2,2,680.00,0,0,0),
(23,3,2,750.00,0,0,0),
(23,4,2,310.00,0,0,0),
(23,5,2,380.00,0,0,0),
(23,6,2,590.00,0,0,0),
(23,7,2,640.00,0,0,0),
(23,8,2,275.00,0,0,0),
(24,9,2,180.00,0,0,0),
(24,10,2,260.00,0,0,0),
(24,11,2,310.00,0,0,0),
(24,12,2,140.00,0,0,0),
(24,13,2,95.00,0,0,0),
(24,14,2,120.00,0,0,0),
(25,15,2,160.00,0,0,0),
(25,16,2,240.00,0,0,0),
(25,17,2,285.00,0,0,0),
(25,18,2,130.00,0,0,0),
(25,19,2,150.00,0,0,0),
(25,20,2,175.00,0,0,0),
(26,21,2,85.00,0,0,0),
(26,22,2,140.00,0,0,0),
(26,23,2,95.00,0,0,0),
(26,24,2,110.00,0,0,0),
(27,25,2,60.00,0,0,0),
(27,26,2,110.00,0,0,0),
(27,27,2,95.00,0,0,0),
(27,28,2,48.00,0,0,0),
(28,29,2,210.00,0,0,0),
(28,30,2,265.00,0,0,0),
(28,31,2,180.00,0,0,0),
(29,32,2,120.00,0,0,0),
(29,33,2,165.00,0,0,0),
(29,34,2,90.00,0,0,0),
(30,35,2,75.00,0,0,0),
(30,36,2,130.00,0,0,0),
(30,37,2,88.00,0,0,0);

-- -------------------------- STOCK MOVEMENTS --------------------------------
INSERT INTO `stock_movements`
(`product_id`,`variant_id`,`warehouse_id`,`movement_type`,`direction`,`reference_type`,`reference_id`,`reference_no`,`quantity`,`balance_after`,`unit_cost`,`notes`,`created_by`,`created_at`) VALUES
(1,0,1,'opening','in','opening',NULL,'OPENING-2024',2000.00,2000.00,850.00,'Opening balance load',1,DATE_SUB(NOW(), INTERVAL 120 DAY)),
(2,0,1,'opening','in','opening',NULL,'OPENING-2024',1500.00,1500.00,980.00,'Opening balance load',1,DATE_SUB(NOW(), INTERVAL 120 DAY)),
(9,0,1,'opening','in','opening',NULL,'OPENING-2024',40000.00,40000.00,3.50,'Opening balance load',1,DATE_SUB(NOW(), INTERVAL 120 DAY)),
(1,0,1,'purchase_in','in','goods_received',1,'GRN-2024-0001',500.00,2500.00,850.00,'Received against PO-2024-0001',3,DATE_SUB(NOW(), INTERVAL 60 DAY)),
(6,0,1,'purchase_in','in','goods_received',1,'GRN-2024-0001',350.00,350.00,420.00,'Received against PO-2024-0001',3,DATE_SUB(NOW(), INTERVAL 60 DAY)),
(9,0,1,'purchase_in','in','goods_received',2,'GRN-2024-0002',5000.00,45000.00,3.50,'Received against PO-2024-0002',3,DATE_SUB(NOW(), INTERVAL 45 DAY)),
(23,1,2,'production_in','in','production_order',1,'PRD-2024-0001',450.00,450.00,780.00,'Production output T-Shirt Black S',4,DATE_SUB(NOW(), INTERVAL 30 DAY)),
(23,2,2,'production_in','in','production_order',1,'PRD-2024-0001',700.00,700.00,780.00,'Production output T-Shirt Black M',4,DATE_SUB(NOW(), INTERVAL 30 DAY)),
(23,3,2,'production_in','in','production_order',1,'PRD-2024-0001',780.00,780.00,780.00,'Production output T-Shirt Black L',4,DATE_SUB(NOW(), INTERVAL 30 DAY)),
(1,0,1,'production_out','out','production_order',1,'PRD-2024-0001',1200.00,2300.00,850.00,'Material issued to production',4,DATE_SUB(NOW(), INTERVAL 32 DAY)),
(6,0,1,'production_out','out','production_order',1,'PRD-2024-0001',30.00,320.00,420.00,'Thread issued to production',4,DATE_SUB(NOW(), INTERVAL 32 DAY)),
(23,2,2,'sale_out','out','invoice',1,'INV-2024-0001',20.00,680.00,780.00,'Sold to customer',6,DATE_SUB(NOW(), INTERVAL 20 DAY)),
(25,16,2,'sale_out','out','invoice',2,'INV-2024-0002',15.00,240.00,1150.00,'Sold to customer',6,DATE_SUB(NOW(), INTERVAL 15 DAY)),
(5,0,1,'purchase_in','in','goods_received',3,'GRN-2024-0003',650.00,650.00,1280.00,'Fleece fabric received',3,DATE_SUB(NOW(), INTERVAL 10 DAY)),
(7,0,1,'adjustment_in','in','stock_adjustment',1,'ADJ-2024-0001',5.00,285.00,420.00,'Recount surplus',2,DATE_SUB(NOW(), INTERVAL 5 DAY)),
(13,0,1,'damage_out','out','stock_adjustment',1,'ADJ-2024-0001',2.00,4200.00,58.00,'Damaged in storage',2,DATE_SUB(NOW(), INTERVAL 5 DAY));

-- -------------------------- STOCK ADJUSTMENTS ------------------------------
INSERT INTO `stock_adjustments` (`id`,`reference_no`,`warehouse_id`,`adjustment_date`,`adjustment_type`,`reason`,`status`,`notes`,`created_by`,`approved_by`) VALUES
(1,'ADJ-2024-0001',1,DATE_SUB(CURDATE(), INTERVAL 5 DAY),'recount','Monthly cycle count variance','approved','Variance found during physical count',2,1);

INSERT INTO `stock_adjustment_items` (`adjustment_id`,`product_id`,`variant_id`,`system_qty`,`counted_qty`,`difference_qty`,`unit_cost`,`notes`) VALUES
(1,7,0,280.00,285.00,5.00,420.00,'Surplus thread cones'),
(1,13,0,4202.00,4200.00,-2.00,58.00,'Damaged metal zippers');

-- --------------------------- STOCK TRANSFERS -------------------------------
INSERT INTO `stock_transfers` (`id`,`transfer_no`,`from_warehouse_id`,`to_warehouse_id`,`transfer_date`,`status`,`notes`,`requested_by`,`approved_by`) VALUES
(1,'TRF-2024-0001',1,3,DATE_SUB(CURDATE(), INTERVAL 32 DAY),'completed','Material issue to production floor',4,1),
(2,'TRF-2024-0002',1,3,DATE_SUB(CURDATE(), INTERVAL 7 DAY),'completed','Fabric top-up for next batch',4,1);

INSERT INTO `stock_transfer_items` (`transfer_id`,`product_id`,`variant_id`,`quantity`,`received_qty`,`notes`) VALUES
(1,1,0,1200.00,1200.00,'Cotton jersey for t-shirt batch'),
(1,6,0,30.00,30.00,'White polyester thread'),
(2,1,0,300.00,300.00,'Top-up fabric');

-- ---------------------------- PURCHASE ORDERS ------------------------------
INSERT INTO `purchase_orders` (`id`,`po_number`,`supplier_id`,`warehouse_id`,`order_date`,`expected_date`,`subtotal`,`discount`,`tax`,`shipping`,`total`,`paid_amount`,`notes`,`status`,`created_by`,`approved_by`) VALUES
(1,'PO-2024-0001',1,1,DATE_SUB(CURDATE(), INTERVAL 70 DAY),DATE_SUB(CURDATE(), INTERVAL 60 DAY),595000.00,5000.00,29500.00,0.00,619500.00,619500.00,'Cotton fabric and thread replenishment','received',3,1),
(2,'PO-2024-0002',3,1,DATE_SUB(CURDATE(), INTERVAL 55 DAY),DATE_SUB(CURDATE(), INTERVAL 45 DAY),17500.00,0.00,875.00,1500.00,19875.00,19875.00,'Button replenishment','received',3,1),
(3,'PO-2024-0003',1,1,DATE_SUB(CURDATE(), INTERVAL 15 DAY),DATE_SUB(CURDATE(), INTERVAL 5 DAY),832000.00,0.00,41600.00,0.00,873600.00,0.00,'Fleece fabric bulk order','partially_received',3,1),
(4,'PO-2024-0004',4,1,CURDATE(),DATE_ADD(CURDATE(), INTERVAL 14 DAY),126000.00,0.00,6300.00,0.00,132300.00,0.00,'Sewing thread order','pending',3,NULL),
(5,'PO-2024-0005',5,1,CURDATE(),DATE_ADD(CURDATE(), INTERVAL 10 DAY),85500.00,1500.00,4200.00,1200.00,89400.00,0.00,'Packaging materials','approved',3,1);

INSERT INTO `purchase_order_items` (`po_id`,`product_id`,`variant_id`,`quantity`,`received_qty`,`unit_price`,`discount`,`tax`,`total`) VALUES
(1,1,0,500.00,500.00,850.00,5000.00,0.00,420000.00),
(1,6,0,350.00,350.00,420.00,0.00,0.00,147000.00),
(1,8,0,70.00,70.00,400.00,0.00,0.00,28000.00),
(2,9,0,5000.00,5000.00,3.50,0.00,0.00,17500.00),
(3,5,0,650.00,650.00,1280.00,0.00,0.00,832000.00),
(4,6,0,150.00,0.00,420.00,0.00,0.00,63000.00),
(4,7,0,150.00,0.00,420.00,0.00,0.00,63000.00),
(5,17,0,5000.00,0.00,7.50,0.00,0.00,37500.00),
(5,19,0,150.00,0.00,285.00,0.00,0.00,42750.00),
(5,20,0,1000.00,0.00,9.00,0.00,0.00,9000.00);

-- ---------------------------- GOODS RECEIVED -------------------------------
INSERT INTO `goods_received` (`id`,`grn_number`,`po_id`,`supplier_id`,`warehouse_id`,`received_date`,`invoice_no`,`notes`,`status`,`created_by`) VALUES
(1,'GRN-2024-0001',1,1,1,DATE_SUB(CURDATE(), INTERVAL 60 DAY),'INV-LTM-8821','Full receipt of PO-2024-0001','completed',3),
(2,'GRN-2024-0002',2,3,1,DATE_SUB(CURDATE(), INTERVAL 45 DAY),'INV-GTS-4410','Full receipt of PO-2024-0002','completed',3),
(3,'GRN-2024-0003',3,1,1,DATE_SUB(CURDATE(), INTERVAL 10 DAY),'INV-LTM-9102','Partial receipt - fleece fabric only','completed',3);

INSERT INTO `goods_received_items` (`grn_id`,`po_item_id`,`product_id`,`variant_id`,`quantity`,`rejected_qty`,`unit_cost`,`notes`) VALUES
(1,1,1,0,500.00,0.00,850.00,'Good condition'),
(1,2,6,0,350.00,0.00,420.00,'Good condition'),
(1,3,8,0,70.00,0.00,400.00,'Good condition'),
(2,4,9,0,5000.00,0.00,3.50,'Good condition'),
(3,5,5,0,650.00,0.00,1280.00,'Good condition');

-- --------------------------- PURCHASE RETURNS ------------------------------
INSERT INTO `purchase_returns` (`id`,`return_no`,`po_id`,`supplier_id`,`warehouse_id`,`return_date`,`reason`,`total`,`status`,`created_by`) VALUES
(1,'PRT-2024-0001',3,1,1,DATE_SUB(CURDATE(), INTERVAL 8 DAY),'Shade variation in fleece fabric roll',12800.00,'completed',3);

INSERT INTO `purchase_return_items` (`return_id`,`product_id`,`variant_id`,`quantity`,`unit_cost`,`total`) VALUES
(1,5,0,10.00,1280.00,12800.00);

-- --------------------------------- BOM -------------------------------------
INSERT INTO `bom` (`id`,`product_id`,`name`,`version`,`notes`,`status`,`created_by`) VALUES
(1,23,'Basic Crew Neck T-Shirt BOM','1.0','Standard BOM for 1 unit of finished t-shirt','active',4),
(2,24,'Printed Graphic T-Shirt BOM','1.0','Includes screen printing consumables','active',4),
(3,25,'Classic Pique Polo Shirt BOM','1.0','Three button placket polo','active',4),
(4,26,'Zip-Up Fleece Hoodie BOM','1.0','Full zip hoodie with metal zipper','active',4),
(5,27,'Slim Fit Denim Jeans BOM','1.0','5 pocket slim fit jeans','active',4),
(6,28,'Kids Cotton T-Shirt BOM','1.0','Kids t-shirt standard BOM','active',4);

INSERT INTO `bom_items` (`bom_id`,`material_id`,`quantity`,`unit`,`wastage_pct`,`notes`) VALUES
-- T-Shirt
(1,1,1.5000,'Meter',5.00,'Main body fabric'),
(1,6,100.0000,'Meter',3.00,'Sewing thread'),
(1,14,1.0000,'Piece',0.00,'Main brand label'),
(1,15,1.0000,'Piece',0.00,'Care label'),
(1,16,1.0000,'Piece',0.00,'Size label'),
(1,17,1.0000,'Piece',2.00,'Poly bag'),
(1,20,1.0000,'Piece',0.00,'Hang tag'),
-- Printed T-Shirt
(2,1,1.5500,'Meter',5.00,'Fabric for print panel'),
(2,7,110.0000,'Meter',3.00,'Black thread'),
(2,14,1.0000,'Piece',0.00,'Main label'),
(2,15,1.0000,'Piece',0.00,'Care label'),
(2,16,1.0000,'Piece',0.00,'Size label'),
(2,17,1.0000,'Piece',2.00,'Poly bag'),
(2,20,1.0000,'Piece',0.00,'Hang tag'),
-- Polo
(3,2,1.7000,'Meter',5.00,'Pique fabric'),
(3,6,130.0000,'Meter',3.00,'Thread'),
(3,9,3.0000,'Piece',2.00,'Front buttons'),
(3,14,1.0000,'Piece',0.00,'Main label'),
(3,15,1.0000,'Piece',0.00,'Care label'),
(3,16,1.0000,'Piece',0.00,'Size label'),
(3,17,1.0000,'Piece',2.00,'Poly bag'),
-- Hoodie
(4,5,1.9000,'Meter',6.00,'Fleece fabric'),
(4,7,180.0000,'Meter',3.00,'Thread'),
(4,13,1.0000,'Piece',1.00,'Metal zipper'),
(4,21,0.6000,'Meter',4.00,'Elastic band'),
(4,14,1.0000,'Piece',0.00,'Main label'),
(4,15,1.0000,'Piece',0.00,'Care label'),
(4,16,1.0000,'Piece',0.00,'Size label'),
(4,18,1.0000,'Piece',2.00,'Large poly bag'),
-- Denim Jeans
(5,4,1.6000,'Meter',7.00,'Denim fabric'),
(5,7,150.0000,'Meter',3.00,'Thread'),
(5,10,1.0000,'Piece',1.00,'Waist button'),
(5,13,1.0000,'Piece',1.00,'Fly zipper'),
(5,14,1.0000,'Piece',0.00,'Main label'),
(5,15,1.0000,'Piece',0.00,'Care label'),
(5,16,1.0000,'Piece',0.00,'Size label'),
(5,18,1.0000,'Piece',2.00,'Poly bag'),
-- Kids T-Shirt
(6,1,0.9000,'Meter',5.00,'Kids body fabric'),
(6,6,60.0000,'Meter',3.00,'Thread'),
(6,14,1.0000,'Piece',0.00,'Main label'),
(6,15,1.0000,'Piece',0.00,'Care label'),
(6,16,1.0000,'Piece',0.00,'Size label'),
(6,17,1.0000,'Piece',2.00,'Poly bag');

-- ------------------------- PRODUCTION ORDERS -------------------------------
INSERT INTO `production_orders` (`id`,`order_no`,`product_id`,`variant_id`,`bom_id`,`quantity`,`size`,`color`,`warehouse_id`,`start_date`,`expected_date`,`actual_date`,`supervisor_id`,`produced_qty`,`status`,`notes`,`created_by`) VALUES
(1,'PRD-2024-0001',23,0,1,2000.00,'Mixed','Black','Mixed',2,DATE_SUB(CURDATE(), INTERVAL 40 DAY),DATE_SUB(CURDATE(), INTERVAL 25 DAY),DATE_SUB(CURDATE(), INTERVAL 26 DAY),7,1930.00,'completed','Black t-shirt bulk batch',4),
(2,'PRD-2024-0002',25,0,3,1200.00,'Mixed','Navy','Mixed',2,DATE_SUB(CURDATE(), INTERVAL 20 DAY),DATE_SUB(CURDATE(), INTERVAL 5 DAY),DATE_SUB(CURDATE(), INTERVAL 6 DAY),7,1150.00,'completed','Navy polo batch',4),
(3,'PRD-2024-0003',26,0,4,900.00,'Mixed','Grey','Mixed',2,DATE_SUB(CURDATE(), INTERVAL 10 DAY),DATE_ADD(CURDATE(), INTERVAL 5 DAY),NULL,7,420.00,'in_progress','Grey hoodie batch',4),
(4,'PRD-2024-0004',24,0,2,1500.00,'Mixed','Navy','Mixed',2,DATE_ADD(CURDATE(), INTERVAL 2 DAY),DATE_ADD(CURDATE(), INTERVAL 18 DAY),NULL,7,0.00,'planned','Printed t-shirt batch',4),
(5,'PRD-2024-0005',27,0,5,600.00,'Mixed','Indigo','Mixed',2,DATE_SUB(CURDATE(), INTERVAL 5 DAY),DATE_ADD(CURDATE(), INTERVAL 12 DAY),NULL,7,180.00,'paused','Denim batch paused for fabric',4);

INSERT INTO `production_materials` (`production_order_id`,`material_id`,`required_qty`,`issued_qty`,`unit`,`unit_cost`,`status`) VALUES
(1,1,3150.0000,3150.0000,'Meter',850.00,'issued'),
(1,6,206000.0000,206000.0000,'Meter',420.00,'issued'),
(1,14,2000.0000,2000.0000,'Piece',6.50,'issued'),
(1,15,2000.0000,2000.0000,'Piece',2.80,'issued'),
(1,16,2000.0000,2000.0000,'Piece',1.90,'issued'),
(2,2,2142.0000,2142.0000,'Meter',980.00,'issued'),
(2,6,163800.0000,163800.0000,'Meter',420.00,'issued'),
(2,9,3672.0000,3672.0000,'Piece',3.50,'issued'),
(3,5,1812.0000,1500.0000,'Meter',1280.00,'partially_issued'),
(3,7,166860.0000,166860.0000,'Meter',420.00,'issued'),
(3,13,909.0000,909.0000,'Piece',58.00,'issued'),
(4,1,2441.2500,0.0000,'Meter',850.00,'pending'),
(5,4,1027.2000,600.0000,'Meter',1450.00,'partially_issued');

INSERT INTO `production_outputs` (`production_order_id`,`product_id`,`variant_id`,`quantity`,`passed_qty`,`rejected_qty`,`warehouse_id`,`output_date`,`status`) VALUES
(1,23,1,450.00,442.00,8.00,2,DATE_SUB(CURDATE(), INTERVAL 28 DAY),'completed'),
(1,23,2,700.00,690.00,10.00,2,DATE_SUB(CURDATE(), INTERVAL 27 DAY),'completed'),
(1,23,3,780.00,765.00,15.00,2,DATE_SUB(CURDATE(), INTERVAL 26 DAY),'completed'),
(2,25,15,420.00,412.00,8.00,2,DATE_SUB(CURDATE(), INTERVAL 10 DAY),'completed'),
(2,25,16,480.00,470.00,10.00,2,DATE_SUB(CURDATE(), INTERVAL 9 DAY),'completed'),
(2,25,17,250.00,244.00,6.00,2,DATE_SUB(CURDATE(), INTERVAL 8 DAY),'completed'),
(3,26,21,150.00,0.00,0.00,2,DATE_SUB(CURDATE(), INTERVAL 3 DAY),'qc_pending');

-- --------------------------- QC INSPECTIONS --------------------------------
INSERT INTO `qc_inspections` (`id`,`inspection_no`,`production_order_id`,`product_id`,`variant_id`,`batch_no`,`inspector_id`,`inspection_date`,`inspected_qty`,`passed_qty`,`failed_qty`,`status`,`remarks`,`created_by`) VALUES
(1,'QC-2024-0001',1,23,1,'BATCH-TS-BLK-S',8,DATE_SUB(CURDATE(), INTERVAL 28 DAY),450.00,442.00,8.00,'partially_passed','Minor stitching defects on 8 pieces',5),
(2,'QC-2024-0002',1,23,2,'BATCH-TS-BLK-M',8,DATE_SUB(CURDATE(), INTERVAL 27 DAY),700.00,690.00,10.00,'partially_passed','Neck rib uneven on 10 pieces',5),
(3,'QC-2024-0003',1,23,3,'BATCH-TS-BLK-L',8,DATE_SUB(CURDATE(), INTERVAL 26 DAY),780.00,765.00,15.00,'partially_passed','Side seam puckering',5),
(4,'QC-2024-0004',2,25,15,'BATCH-PL-NVY-S',8,DATE_SUB(CURDATE(), INTERVAL 10 DAY),420.00,412.00,8.00,'partially_passed','Button hole size variation',5),
(5,'QC-2024-0005',2,25,16,'BATCH-PL-NVY-M',8,DATE_SUB(CURDATE(), INTERVAL 9 DAY),480.00,470.00,10.00,'partially_passed','Collar shape inconsistency',5),
(6,'QC-2024-0006',3,26,21,'BATCH-HD-GRY-M',8,DATE_SUB(CURDATE(), INTERVAL 1 DAY),150.00,0.00,0.00,'pending','Inspection scheduled',5);

INSERT INTO `qc_defects` (`inspection_id`,`defect_type`,`description`,`quantity`,`severity`) VALUES
(1,'stitching','Skip stitch on sleeve hem',5,'minor'),
(1,'fabric','Small hole near shoulder',3,'major'),
(2,'stitching','Uneven neck rib attachment',10,'minor'),
(3,'stitching','Side seam puckering',12,'minor'),
(3,'size','Body length out of tolerance',3,'major'),
(4,'button','Button hole too tight',8,'minor'),
(5,'stitching','Collar tip not sharp',10,'minor');

-- ------------------------------ CUSTOMERS ----------------------------------
INSERT INTO `customers` (`id`,`name`,`company`,`phone`,`email`,`address`,`city`,`country`,`tax_number`,`credit_limit`,`balance`,`status`) VALUES
(1,'Kasun Jayasuriya','Fashion Hub (Pvt) Ltd','+94 77 111 2233','orders@fashionhub.lk','120, Galle Road','Colombo 03','Sri Lanka','VAT-771122334',500000.00,125000.00,'active'),
(2,'Amali Perera','Trendy Wear Boutique','+94 77 222 3344','amali@trendywear.lk','45, Main Street','Kandy','Sri Lanka','VAT-772233445',250000.00,0.00,'active'),
(3,'Rizwan Mohamed','Metro Garments Trading','+94 77 333 4455','rizwan@metrogt.lk','78, Hospital Road','Negombo','Sri Lanka','VAT-773344556',800000.00,320000.00,'active'),
(4,'Sanduni Fernando','Little Stars Kids Wear','+94 77 444 5566','sanduni@littlestars.lk','12, Temple Lane','Galle','Sri Lanka','VAT-774455667',150000.00,45000.00,'active'),
(5,'Dinesh Kumar','Urban Style Clothing','+94 77 555 6677','dinesh@urbanstyle.lk','90, Beach Road','Mount Lavinia','Sri Lanka','VAT-775566778',600000.00,0.00,'active'),
(6,'Export Direct Ltd','Export Direct Ltd','+94 11 666 7788','purchasing@exportdirect.lk','200, Port Access Road','Colombo 15','Sri Lanka','VAT-776677889',1500000.00,540000.00,'active');

-- ----------------------------- SALES ORDERS --------------------------------
INSERT INTO `sales_orders` (`id`,`order_no`,`customer_id`,`warehouse_id`,`order_date`,`delivery_date`,`subtotal`,`discount`,`tax`,`shipping`,`total`,`paid_amount`,`notes`,`status`,`created_by`) VALUES
(1,'SO-2024-0001',1,2,DATE_SUB(CURDATE(), INTERVAL 25 DAY),DATE_SUB(CURDATE(), INTERVAL 20 DAY),35000.00,1000.00,1700.00,500.00,36200.00,36200.00,'Retail order','delivered',6),
(2,'SO-2024-0002',3,2,DATE_SUB(CURDATE(), INTERVAL 18 DAY),DATE_SUB(CURDATE(), INTERVAL 12 DAY),82500.00,0.00,4125.00,0.00,86625.00,50000.00,'Wholesale order','delivered',6),
(3,'SO-2024-0003',2,2,DATE_SUB(CURDATE(), INTERVAL 10 DAY),DATE_SUB(CURDATE(), INTERVAL 5 DAY),22000.00,500.00,1075.00,350.00,22925.00,22925.00,'Boutique order','delivered',6),
(4,'SO-2024-0004',6,2,DATE_SUB(CURDATE(), INTERVAL 5 DAY),DATE_ADD(CURDATE(), INTERVAL 5 DAY),186000.00,6000.00,9000.00,2500.00,191500.00,0.00,'Export order','confirmed',6),
(5,'SO-2024-0005',5,2,CURDATE(),DATE_ADD(CURDATE(), INTERVAL 7 DAY),42000.00,0.00,2100.00,0.00,44100.00,0.00,'New retail order','pending',6);

INSERT INTO `sales_order_items` (`so_id`,`product_id`,`variant_id`,`description`,`quantity`,`delivered_qty`,`unit_price`,`discount`,`tax`,`total`) VALUES
(1,23,2,'Basic Crew Neck T-Shirt Black M',10.00,10.00,1750.00,0.00,0.00,17500.00),
(1,23,5,'Basic Crew Neck T-Shirt White S',10.00,10.00,1750.00,1000.00,0.00,16500.00),
(2,25,16,'Classic Pique Polo Shirt Navy M',15.00,15.00,2750.00,0.00,0.00,41250.00),
(2,25,17,'Classic Pique Polo Shirt Navy L',15.00,15.00,2750.00,0.00,0.00,41250.00),
(3,24,10,'Printed Graphic T-Shirt Navy M',5.00,5.00,2100.00,500.00,0.00,10000.00),
(3,24,11,'Printed Graphic T-Shirt Navy L',6.00,6.00,2100.00,0.00,0.00,12600.00),
(4,26,21,'Zip-Up Fleece Hoodie Grey M',20.00,0.00,4200.00,3000.00,0.00,81000.00),
(4,26,22,'Zip-Up Fleece Hoodie Grey L',20.00,0.00,4200.00,3000.00,0.00,81000.00),
(4,29,32,'Fleece Jogger Pants Charcoal M',8.00,0.00,3100.00,0.00,0.00,24800.00),
(5,30,35,'Cotton Casual Shirt Sky Blue M',6.00,0.00,3350.00,0.00,0.00,20100.00),
(5,30,36,'Cotton Casual Shirt Sky Blue L',6.00,0.00,3350.00,0.00,0.00,20100.00),
(5,28,29,'Kids Cotton T-Shirt Blue 2-3Y',2.00,0.00,900.00,0.00,0.00,1800.00);

-- ------------------------------- INVOICES ----------------------------------
INSERT INTO `invoices` (`id`,`invoice_no`,`so_id`,`customer_id`,`invoice_date`,`due_date`,`subtotal`,`discount`,`tax`,`total`,`paid_amount`,`status`,`notes`,`created_by`) VALUES
(1,'INV-2024-0001',1,1,DATE_SUB(CURDATE(), INTERVAL 20 DAY),DATE_ADD(CURDATE(), INTERVAL 10 DAY),35000.00,1000.00,1700.00,36200.00,36200.00,'paid','Retail invoice',6),
(2,'INV-2024-0002',2,3,DATE_SUB(CURDATE(), INTERVAL 12 DAY),DATE_ADD(CURDATE(), INTERVAL 18 DAY),82500.00,0.00,4125.00,86625.00,50000.00,'partial','Wholesale invoice',6),
(3,'INV-2024-0003',3,2,DATE_SUB(CURDATE(), INTERVAL 5 DAY),DATE_ADD(CURDATE(), INTERVAL 25 DAY),22000.00,500.00,1075.00,22925.00,22925.00,'paid','Boutique invoice',6),
(4,'INV-2024-0004',4,6,DATE_SUB(CURDATE(), INTERVAL 1 DAY),DATE_ADD(CURDATE(), INTERVAL 29 DAY),186000.00,6000.00,9000.00,191500.00,0.00,'unpaid','Export invoice',6);

INSERT INTO `invoice_items` (`invoice_id`,`product_id`,`variant_id`,`description`,`quantity`,`unit_price`,`discount`,`tax`,`total`) VALUES
(1,23,2,'Basic Crew Neck T-Shirt Black M',10.00,1750.00,0.00,0.00,17500.00),
(1,23,5,'Basic Crew Neck T-Shirt White S',10.00,1750.00,1000.00,0.00,16500.00),
(2,25,16,'Classic Pique Polo Shirt Navy M',15.00,2750.00,0.00,0.00,41250.00),
(2,25,17,'Classic Pique Polo Shirt Navy L',15.00,2750.00,0.00,0.00,41250.00),
(3,24,10,'Printed Graphic T-Shirt Navy M',5.00,2100.00,500.00,0.00,10000.00),
(3,24,11,'Printed Graphic T-Shirt Navy L',6.00,2100.00,0.00,0.00,12600.00),
(4,26,21,'Zip-Up Fleece Hoodie Grey M',20.00,4200.00,3000.00,0.00,81000.00),
(4,26,22,'Zip-Up Fleece Hoodie Grey L',20.00,4200.00,3000.00,0.00,81000.00),
(4,29,32,'Fleece Jogger Pants Charcoal M',8.00,3100.00,0.00,0.00,24800.00);

-- ------------------------------ DELIVERIES ---------------------------------
INSERT INTO `deliveries` (`id`,`delivery_no`,`so_id`,`customer_id`,`warehouse_id`,`delivery_date`,`address`,`driver`,`vehicle_no`,`status`,`notes`,`created_by`) VALUES
(1,'DLV-2024-0001',1,1,2,DATE_SUB(CURDATE(), INTERVAL 20 DAY),'120, Galle Road, Colombo 03','Sunil Shantha','WP-CAB-1234','delivered','Delivered and signed',6),
(2,'DLV-2024-0002',2,3,2,DATE_SUB(CURDATE(), INTERVAL 12 DAY),'78, Hospital Road, Negombo','Sunil Shantha','WP-CAB-1234','delivered','Delivered and signed',6),
(3,'DLV-2024-0003',3,2,2,DATE_SUB(CURDATE(), INTERVAL 5 DAY),'45, Main Street, Kandy','Kamal Perera','WP-PRO-5678','delivered','Delivered and signed',6),
(4,'DLV-2024-0004',4,6,2,DATE_ADD(CURDATE(), INTERVAL 5 DAY),'200, Port Access Road, Colombo 15','To be assigned',NULL,'pending','Scheduled for export dispatch',6);

-- ---------------------------- SALES RETURNS --------------------------------
INSERT INTO `sales_returns` (`id`,`return_no`,`so_id`,`customer_id`,`warehouse_id`,`return_date`,`reason`,`total`,`status`,`created_by`) VALUES
(1,'SRT-2024-0001',1,1,2,DATE_SUB(CURDATE(), INTERVAL 15 DAY),'Two pieces had stitching defects',3500.00,'completed',6);

INSERT INTO `sales_return_items` (`return_id`,`product_id`,`variant_id`,`quantity`,`unit_price`,`total`) VALUES
(1,23,2,2.00,1750.00,3500.00);

-- ----------------------------- ATTENDANCE ----------------------------------
INSERT INTO `attendance` (`employee_id`,`att_date`,`check_in`,`check_out`,`status`,`remarks`) VALUES
(1,CURDATE(),'08:02:00','17:05:00','present',NULL),
(2,CURDATE(),'07:58:00','17:10:00','present',NULL),
(3,CURDATE(),'08:35:00','17:15:00','late','Traffic delay'),
(4,CURDATE(),'08:00:00','17:00:00','present',NULL),
(5,CURDATE(),'08:10:00','17:20:00','present',NULL),
(6,CURDATE(),'08:05:00','17:00:00','present',NULL),
(7,CURDATE(),'07:45:00','18:00:00','present','Overtime'),
(8,CURDATE(),'08:00:00','17:00:00','present',NULL),
(9,CURDATE(),'08:15:00','17:05:00','present',NULL),
(10,CURDATE(),'08:20:00','17:00:00','present',NULL),
(11,CURDATE(),NULL,NULL,'leave','Approved annual leave'),
(12,CURDATE(),'08:05:00','17:00:00','present',NULL),
(1,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'08:00:00','17:00:00','present',NULL),
(2,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'08:00:00','17:00:00','present',NULL),
(3,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'08:00:00','17:00:00','present',NULL),
(4,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'08:00:00','17:00:00','present',NULL),
(5,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'08:10:00','17:00:00','late','Late arrival'),
(6,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'08:00:00','17:00:00','present',NULL),
(7,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'08:00:00','17:00:00','present',NULL),
(8,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'08:00:00','17:00:00','present',NULL),
(9,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'08:00:00','17:00:00','present',NULL),
(10,DATE_SUB(CURDATE(), INTERVAL 1 DAY),NULL,NULL,'absent','No show'),
(11,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'08:00:00','17:00:00','present',NULL),
(12,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'08:00:00','17:00:00','present',NULL);

-- --------------------------- NOTIFICATIONS ---------------------------------
INSERT INTO `notifications` (`user_id`,`title`,`message`,`type`,`module`,`link`,`is_read`,`created_at`) VALUES
(NULL,'Low Stock Alert','Cotton Sewing Thread 60/3 has fallen below reorder level.','warning','inventory','/inventory/stock.php',0,DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(NULL,'New Purchase Order','Purchase order PO-2024-0004 has been created and awaits approval.','info','purchase','/purchases/purchase-orders.php',0,DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(NULL,'Production Completed','Production order PRD-2024-0002 has been completed.','success','production','/production/production-orders.php',0,DATE_SUB(NOW(), INTERVAL 1 DAY)),
(NULL,'QC Pending','QC inspection QC-2024-0006 is pending review.','warning','quality','/quality-control/qc-inspections.php',0,DATE_SUB(NOW(), INTERVAL 1 DAY)),
(NULL,'Out of Stock','Metal Zipper 10 inch Antique Brass is critically low.','danger','inventory','/inventory/stock.php',1,DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1,'Welcome to GIMS','Your garment inventory management system is ready to use.','info','system','/dashboard.php',1,DATE_SUB(NOW(), INTERVAL 30 DAY));

-- ------------------------------ SETTINGS -----------------------------------
INSERT INTO `settings` (`setting_key`,`setting_value`,`setting_group`) VALUES
('company_name','Garment Inventory Management System','general'),
('company_short_name','GIMS','general'),
('company_logo','','general'),
('company_address','Industrial Zone, Block A, Gampaha, Sri Lanka','general'),
('company_phone','+94 33 222 3344','general'),
('company_email','info@garment.local','general'),
('company_tax_number','VAT-112233445','general'),
('currency_symbol','Rs.','general'),
('currency_code','LKR','general'),
('tax_rate','5.00','general'),
('date_format','Y-m-d','general'),
('time_format','H:i','general'),
('low_stock_threshold','10','inventory'),
('allow_negative_stock','0','inventory'),
('default_warehouse_id','1','inventory'),
('records_per_page','15','general'),
('system_theme','light','appearance'),
('sidebar_theme','dark','appearance'),
('invoice_prefix','INV','numbering'),
('po_prefix','PO','numbering'),
('so_prefix','SO','numbering'),
('production_prefix','PRD','numbering'),
('maintenance_mode','0','system');

-- --------------------------- ACTIVITY LOGS ---------------------------------
INSERT INTO `activity_logs` (`user_id`,`action`,`module`,`record_id`,`description`,`ip_address`,`created_at`) VALUES
(1,'User Login','auth',1,'Super Administrator logged in','127.0.0.1',DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2,'Product Created','product',23,'Created product Basic Crew Neck T-Shirt','127.0.0.1',DATE_SUB(NOW(), INTERVAL 40 DAY)),
(3,'Purchase Order Created','purchase',1,'Created purchase order PO-2024-0001','127.0.0.1',DATE_SUB(NOW(), INTERVAL 70 DAY)),
(4,'Production Order Completed','production',1,'Completed production order PRD-2024-0001','127.0.0.1',DATE_SUB(NOW(), INTERVAL 26 DAY)),
(5,'QC Inspection Recorded','quality',3,'Recorded QC inspection QC-2024-0003','127.0.0.1',DATE_SUB(NOW(), INTERVAL 26 DAY)),
(6,'Invoice Created','sales',4,'Created invoice INV-2024-0004','127.0.0.1',DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2,'Stock Adjusted','inventory',1,'Stock adjustment ADJ-2024-0001 approved','127.0.0.1',DATE_SUB(NOW(), INTERVAL 5 DAY));

-- ============================================================================
--  END OF FILE
-- ============================================================================
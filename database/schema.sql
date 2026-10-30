-- MySQL Database Schema for N.A Fresh Fruits & Coconuts
-- Production-ready, fully normalized, strict constraints, transactions-ready

-- 1. Admin Users Table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'superadmin',
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Login Attempts Table (Security Throttling)
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Settings Table (Store & Delivery Settings)
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key_name` VARCHAR(60) NOT NULL UNIQUE,
    `value` TEXT NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Products Table
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL UNIQUE,
    `tagline` VARCHAR(255) NOT NULL DEFAULT 'Pure • Natural • Healthy',
    `description` TEXT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 40.00,
    `stock_quantity` INT NOT NULL DEFAULT 100,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `image_url` VARCHAR(255) NOT NULL,
    `gallery_json` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_availability` (`is_available`),
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Customers Table
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `address` TEXT NULL,
    `city` VARCHAR(80) NULL,
    `state` VARCHAR(80) NULL,
    `pincode` VARCHAR(10) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(30) NOT NULL UNIQUE,
    `customer_id` INT UNSIGNED NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_phone` VARCHAR(20) NOT NULL,
    `fulfilment_method` ENUM('delivery', 'pickup') NOT NULL DEFAULT 'delivery',
    `delivery_address` TEXT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `delivery_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('cod', 'online') NOT NULL DEFAULT 'cod',
    `payment_status` ENUM('pending', 'paid', 'refunded', 'failed') NOT NULL DEFAULT 'pending',
    `order_status` ENUM('pending', 'confirmed', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    `tracking_hash` VARCHAR(64) NOT NULL UNIQUE,
    `estimated_delivery` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `idempotency_key` VARCHAR(64) UNIQUE NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_order_num` (`order_number`),
    INDEX `idx_status` (`order_status`),
    INDEX `idx_customer_phone` (`customer_phone`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_tracking_hash` (`tracking_hash`),
    CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Order Items Table (Stores immutable historical price and product snapshot)
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `product_name` VARCHAR(150) NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Order Status History Table
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `old_status` VARCHAR(30) NULL,
    `new_status` VARCHAR(30) NOT NULL,
    `comment` VARCHAR(255) NULL,
    `changed_by` VARCHAR(50) NOT NULL DEFAULT 'System',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. OTP / Verification Table (Rate-limited, hash-stored)
CREATE TABLE IF NOT EXISTS `otp_verifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phone` VARCHAR(20) NOT NULL,
    `otp_code_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `verified` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_phone_expiry` (`phone`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- EasyShop E-Commerce — Production Database Deployment Package
-- Target Engine: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+ (InfinityFree Compatible)
-- Storage Engine: InnoDB (ACID Compliant)
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ==============================================================================

-- Disable foreign key checks during import to avoid ordering conflicts
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- Table 1: users
-- Stores customer accounts and administrative staff
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `role` ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 2: categories
-- Product departments and storefront navigation hierarchy
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 3: products
-- Catalog products with SKU, inventory tracking, and exact Pakistani Rupee pricing
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `product_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `original_price` DECIMAL(10, 2) DEFAULT NULL,
  `stock` INT UNSIGNED NOT NULL DEFAULT 0,
  `image` VARCHAR(255) NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `is_featured` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  KEY `idx_products_category_id` (`category_id`),
  KEY `idx_products_status` (`status`),
  KEY `idx_products_featured` (`is_featured`),
  KEY `idx_products_price` (`price`),
  CONSTRAINT `fk_products_category` 
    FOREIGN KEY (`category_id`) 
    REFERENCES `categories` (`category_id`) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 4: cart_items
-- Persistent shopping cart items per registered customer
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
  `cart_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cart_item_id`),
  UNIQUE KEY `uq_cart_user_product` (`user_id`, `product_id`),
  KEY `idx_cart_user_id` (`user_id`),
  KEY `idx_cart_product_id` (`product_id`),
  CONSTRAINT `fk_cart_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`user_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_product` 
    FOREIGN KEY (`product_id`) 
    REFERENCES `products` (`product_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 5: wishlist_items
-- Saved products list per customer
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `wishlist_items`;
CREATE TABLE `wishlist_items` (
  `wishlist_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`wishlist_id`),
  UNIQUE KEY `uq_wishlist_user_product` (`user_id`, `product_id`),
  KEY `idx_wishlist_user_id` (`user_id`),
  KEY `idx_wishlist_product_id` (`product_id`),
  CONSTRAINT `fk_wishlist_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`user_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_wishlist_product` 
    FOREIGN KEY (`product_id`) 
    REFERENCES `products` (`product_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 6: orders
-- Customer sales orders with Pakistani Rupee totals, shipping address, and stock safety
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `order_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `order_number` VARCHAR(50) NOT NULL,
  `subtotal` DECIMAL(10, 2) NOT NULL,
  `shipping_fee` DECIMAL(10, 2) NOT NULL DEFAULT 200.00,
  `total_amount` DECIMAL(10, 2) NOT NULL,
  `payment_method` ENUM('cod') NOT NULL DEFAULT 'cod',
  `payment_status` ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
  `order_status` ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  `is_stock_restored` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `shipping_name` VARCHAR(100) NOT NULL,
  `shipping_phone` VARCHAR(20) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uq_orders_number` (`order_number`),
  KEY `idx_orders_user_id` (`user_id`),
  KEY `idx_orders_status` (`order_status`),
  KEY `idx_orders_created_at` (`created_at`),
  CONSTRAINT `fk_orders_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`user_id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 7: order_items
-- Historical line-item snapshot of products sold within an order
-- Historical snapshot: stores product_name and price at transaction time
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `order_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(10, 2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_order_items_order_id` (`order_id`),
  KEY `idx_order_items_product_id` (`product_id`),
  CONSTRAINT `fk_order_items_order` 
    FOREIGN KEY (`order_id`) 
    REFERENCES `orders` (`order_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` 
    FOREIGN KEY (`product_id`) 
    REFERENCES `products` (`product_id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 8: reviews
-- Customer product reviews and 1-to-5 star ratings
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `review_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL,
  `comment` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  KEY `idx_reviews_user_id` (`user_id`),
  KEY `idx_reviews_product_id` (`product_id`),
  CONSTRAINT `fk_reviews_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`user_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_reviews_product` 
    FOREIGN KEY (`product_id`) 
    REFERENCES `products` (`product_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 9: contact_messages
-- Public contact inquiries
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `message_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('unread', 'read', 'replied') NOT NULL DEFAULT 'unread',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `idx_messages_status` (`status`),
  KEY `idx_messages_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- INITIAL PRODUCTION SEED DATA
-- ==============================================================================

-- 1. Default Administrator Account
-- Credentials:
-- Email:    admin@easyshop.pk
-- Password: Admin@12345
INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `phone`, `role`, `status`, `created_at`) VALUES
(1, 'EasyShop Administrator', 'admin@easyshop.pk', '$2y$10$Iz2PkwIPGpe5zWw4pUquoOdzMfT6NhQAxjYE4NbmYe2qlATBPY5qy', '+923001234567', 'admin', 'active', NOW())
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

-- 2. Core Catalog Categories
INSERT INTO `categories` (`category_id`, `category_name`, `slug`, `description`, `status`, `created_at`) VALUES
(1, 'Electronics', 'electronics', 'Audio, smart devices, and modern gadgets', 'active', NOW()),
(2, 'Fashion', 'fashion', 'Apparel, footwear, and casual urban wear', 'active', NOW()),
(3, 'Home & Living', 'home', 'Lighting, interior aesthetics, and home essentials', 'active', NOW()),
(4, 'Beauty & Personal Care', 'beauty', 'Skincare essentials and wellness treatments', 'active', NOW()),
(5, 'Sports & Fitness', 'sports', 'Workout accessories, fitness gear, and athletic equipment', 'active', NOW()),
(6, 'Accessories', 'accessories', 'Wallets, sunglasses, timepieces, and everyday carry', 'active', NOW())
ON DUPLICATE KEY UPDATE `slug` = VALUES(`slug`);

-- 3. Verified Initial Products
INSERT INTO `products` (`product_id`, `category_id`, `product_name`, `slug`, `sku`, `description`, `price`, `original_price`, `stock`, `image`, `status`, `is_featured`, `created_at`) VALUES
(1, 1, 'Wireless Earbuds Pro', 'wireless-earbuds-pro', 'ELEC-WEP-001', 'High-fidelity audio with active noise cancellation and 24-hour battery life.', 2499.00, 3199.00, 45, 'assets/images/product-1.svg', 'active', 1, NOW()),
(2, 1, 'Smart Watch Elite', 'smart-watch-elite', 'ELEC-SWE-002', 'Crisp AMOLED display with heart rate tracking, sleep monitoring, and 7-day battery.', 4999.00, 6500.00, 30, 'assets/images/product-2.svg', 'active', 1, NOW()),
(3, 6, 'Classic Leather Wallet', 'classic-leather-wallet', 'ACC-CLW-003', 'Handcrafted genuine leather bifold wallet with RFID blocking technology.', 1299.00, NULL, 60, 'assets/images/product-3.svg', 'active', 1, NOW()),
(4, 2, 'AirFlow Running Shoes', 'airflow-running-shoes', 'FASH-ARS-004', 'Breathable knit upper with responsive cushioned sole designed for high mileage.', 5499.00, 7999.00, 25, 'assets/images/product-4.svg', 'active', 1, NOW()),
(5, 2, 'Premium Cotton T-Shirt', 'premium-cotton-t-shirt', 'FASH-PCT-005', '100% combed ringspun cotton providing supreme comfort and durability.', 999.00, 1499.00, 100, 'assets/images/product-5.svg', 'active', 1, NOW()),
(6, 3, 'Nordic Table Lamp', 'nordic-table-lamp', 'HOME-NTL-006', 'Minimalist Scandinavian table lamp with touch-controlled dimming.', 2199.00, NULL, 20, 'assets/images/product-6.svg', 'active', 1, NOW()),
(7, 6, 'Polarized Sunglasses', 'polarized-sunglasses', 'ACC-PSG-007', 'UV400 protective polarized lenses in a lightweight corrosion-resistant frame.', 1799.00, 2499.00, 40, 'assets/images/product-7.svg', 'active', 1, NOW()),
(8, 6, 'Urban Explorer Backpack', 'urban-explorer-backpack', 'ACC-UEB-008', 'Water-resistant travel pack featuring a dedicated 15.6-inch laptop compartment.', 3499.00, NULL, 35, 'assets/images/product-8.svg', 'active', 1, NOW()),
(9, 4, 'Glow Face Serum', 'glow-face-serum', 'BEAU-GFS-009', 'Nourishing botanical formula with Vitamin C and Hyaluronic Acid.', 1599.00, NULL, 50, 'assets/images/product-9.svg', 'active', 0, NOW()),
(10, 5, 'ProGrip Yoga Mat', 'progrip-yoga-mat', 'SPRT-PYM-010', 'High-density eco-friendly TPE mat with alignment lines and non-slip texture.', 2299.00, NULL, 40, 'assets/images/product-10.svg', 'active', 0, NOW()),
(11, 2, 'Classic Denim Jacket', 'classic-denim-jacket', 'FASH-CDJ-011', 'Timeless trucker denim jacket in washed indigo with reinforced stitching.', 4299.00, 5999.00, 18, 'assets/images/product-11.svg', 'active', 0, NOW()),
(12, 1, 'SoundWave BT Speaker', 'soundwave-bt-speaker', 'ELEC-SWS-012', 'Compact 360-degree wireless speaker with rich bass and IPX7 waterproofing.', 3799.00, 4999.00, 22, 'assets/images/product-12.svg', 'active', 0, NOW())
ON DUPLICATE KEY UPDATE `sku` = VALUES(`sku`);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

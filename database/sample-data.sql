-- ==============================================================================
-- EasyShop Initial Seed / Sample Data
-- Populates categories, products, default admin, and test customer
-- ==============================================================================

USE `easyshop_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Users (Admin and Customer)
-- Passwords:
-- Admin:    Admin@12345
-- Customer: Customer@123
-- ------------------------------------------------------------------------------
INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `phone`, `role`, `created_at`) VALUES
(1, 'EasyShop Administrator', 'admin@easyshop.pk', '$2y$10$Iz2PkwIPGpe5zWw4pUquoOdzMfT6NhQAxjYE4NbmYe2qlATBPY5qy', '+923001234567', 'admin', NOW()),
(2, 'Ali Khan', 'customer@easyshop.pk', '$2y$10$9oZma2NgM.EOt51crML//ew19miyQvEWMlIN1xOa95nXeqOjD7OSa', '+923219876543', 'customer', NOW())
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

-- ------------------------------------------------------------------------------
-- 2. Categories
-- Matches the 6 categories showcased in the frontend
-- ------------------------------------------------------------------------------
INSERT INTO `categories` (`category_id`, `category_name`, `slug`, `description`, `status`, `created_at`) VALUES
(1, 'Electronics', 'electronics', 'Audio, smart devices, and modern gadgets', 'active', NOW()),
(2, 'Fashion', 'fashion', 'Apparel, footwear, and casual urban wear', 'active', NOW()),
(3, 'Home & Living', 'home', 'Lighting, interior aesthetics, and home essentials', 'active', NOW()),
(4, 'Beauty & Personal Care', 'beauty', 'Skincare essentials and wellness treatments', 'active', NOW()),
(5, 'Sports & Fitness', 'sports', 'Workout accessories, fitness gear, and athletic equipment', 'active', NOW()),
(6, 'Accessories', 'accessories', 'Wallets, sunglasses, timepieces, and everyday carry', 'active', NOW())
ON DUPLICATE KEY UPDATE `slug` = VALUES(`slug`);

-- ------------------------------------------------------------------------------
-- 3. Products
-- 12 realistic sample products synchronized with frontend cards & SVGs
-- ------------------------------------------------------------------------------
INSERT INTO `products` (`product_id`, `category_id`, `product_name`, `slug`, `sku`, `description`, `price`, `original_price`, `stock`, `image`, `status`, `is_featured`, `created_at`) VALUES
-- Featured Products (1 - 8)
(1, 1, 'Wireless Earbuds Pro', 'wireless-earbuds-pro', 'ELEC-WEP-001', 'High-fidelity audio with active noise cancellation and 24-hour battery life.', 2499.00, 3199.00, 45, 'assets/images/product-1.svg', 'active', 1, NOW()),
(2, 1, 'Smart Watch Elite', 'smart-watch-elite', 'ELEC-SWE-002', 'Crisp AMOLED display with heart rate tracking, sleep monitoring, and 7-day battery.', 4999.00, 6500.00, 30, 'assets/images/product-2.svg', 'active', 1, NOW()),
(3, 6, 'Classic Leather Wallet', 'classic-leather-wallet', 'ACC-CLW-003', 'Handcrafted genuine leather bifold wallet with RFID blocking technology.', 1299.00, NULL, 60, 'assets/images/product-3.svg', 'active', 1, NOW()),
(4, 2, 'AirFlow Running Shoes', 'airflow-running-shoes', 'FASH-ARS-004', 'Breathable knit upper with responsive cushioned sole designed for high mileage.', 5499.00, 7999.00, 25, 'assets/images/product-4.svg', 'active', 1, NOW()),
(5, 2, 'Premium Cotton T-Shirt', 'premium-cotton-t-shirt', 'FASH-PCT-005', '100% combed ringspun cotton providing supreme comfort and durability.', 999.00, 1499.00, 100, 'assets/images/product-5.svg', 'active', 1, NOW()),
(6, 3, 'Nordic Table Lamp', 'nordic-table-lamp', 'HOME-NTL-006', 'Minimalist Scandinavian table lamp with touch-controlled dimming.', 2199.00, NULL, 20, 'assets/images/product-6.svg', 'active', 1, NOW()),
(7, 6, 'Polarized Sunglasses', 'polarized-sunglasses', 'ACC-PSG-007', 'UV400 protective polarized lenses in a lightweight corrosion-resistant frame.', 1799.00, 2499.00, 40, 'assets/images/product-7.svg', 'active', 1, NOW()),
(8, 6, 'Urban Explorer Backpack', 'urban-explorer-backpack', 'ACC-UEB-008', 'Water-resistant travel pack featuring a dedicated 15.6-inch laptop compartment.', 3499.00, NULL, 35, 'assets/images/product-8.svg', 'active', 1, NOW()),

-- New Arrivals (9 - 12)
(9, 4, 'Glow Face Serum', 'glow-face-serum', 'BEAU-GFS-009', 'Nourishing botanical formula with Vitamin C and Hyaluronic Acid.', 1599.00, NULL, 50, 'assets/images/product-9.svg', 'active', 0, NOW()),
(10, 5, 'ProGrip Yoga Mat', 'progrip-yoga-mat', 'SPRT-PYM-010', 'High-density eco-friendly TPE mat with alignment lines and non-slip texture.', 2299.00, NULL, 40, 'assets/images/product-10.svg', 'active', 0, NOW()),
(11, 2, 'Classic Denim Jacket', 'classic-denim-jacket', 'FASH-CDJ-011', 'Timeless trucker denim jacket in washed indigo with reinforced stitching.', 4299.00, 5999.00, 18, 'assets/images/product-11.svg', 'active', 0, NOW()),
(12, 1, 'SoundWave BT Speaker', 'soundwave-bt-speaker', 'ELEC-SWS-012', 'Compact 360-degree wireless speaker with rich bass and IPX7 waterproofing.', 3799.00, 4999.00, 22, 'assets/images/product-12.svg', 'active', 0, NOW())
ON DUPLICATE KEY UPDATE `sku` = VALUES(`sku`);

-- ------------------------------------------------------------------------------
-- 4. Sample Order & Order Items
-- ------------------------------------------------------------------------------
INSERT INTO `orders` (`order_id`, `user_id`, `order_number`, `subtotal`, `shipping_fee`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `shipping_name`, `shipping_phone`, `shipping_address`, `created_at`) VALUES
(1, 2, 'ORD-202611-0001', 3798.00, 0.00, 3798.00, 'cod', 'pending', 'processing', 'Ali Khan', '+923219876543', 'House 42, Street 8, Sector F-7/2, Islamabad, Pakistan', NOW())
ON DUPLICATE KEY UPDATE `order_number` = VALUES(`order_number`);

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `product_name`, `price`, `quantity`, `subtotal`) VALUES
(1, 1, 1, 'Wireless Earbuds Pro', 2499.00, 1, 2499.00),
(2, 1, 3, 'Classic Leather Wallet', 1299.00, 1, 1299.00)
ON DUPLICATE KEY UPDATE `order_item_id` = VALUES(`order_item_id`);

-- ------------------------------------------------------------------------------
-- 5. Sample Product Reviews
-- ------------------------------------------------------------------------------
INSERT INTO `reviews` (`review_id`, `user_id`, `product_id`, `rating`, `comment`, `created_at`) VALUES
(1, 2, 1, 5, 'Crystal clear audio and the battery easily lasts two days of normal use. Highly recommended!', NOW()),
(2, 2, 6, 5, 'Looks very aesthetic on my study desk. Build quality is solid.', NOW())
ON DUPLICATE KEY UPDATE `review_id` = VALUES(`review_id`);

SET FOREIGN_KEY_CHECKS = 1;

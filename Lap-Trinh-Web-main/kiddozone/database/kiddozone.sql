-- KiddoZone: cấu trúc bảng + dữ liệu mẫu. Nhập bằng phpMyAdmin (tab Import) hoặc: mysql -u root < kiddozone.sql
-- Tài khoản mẫu:  admin@kiddozone.test / Admin@123   |   khach1@kiddozone.test, khach2@..., khach3@... / Khach@123

CREATE DATABASE IF NOT EXISTS kiddozone CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kiddozone;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS wishlists, reviews, cart_items, carts, order_items, orders, product_images, products, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    role ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = bị khóa',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    status TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    price DECIMAL(12,0) NOT NULL,
    sale_price DECIMAL(12,0) NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    description MEDIUMTEXT NULL COMMENT 'HTML từ TinyMCE',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = ẩn',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_products_category (category_id),
    FULLTEXT KEY ft_products_name (name),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL COMMENT 'tên file trong uploads/, hoặc đường dẫn assets/... cho ảnh mẫu',
    is_main TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE COMMENT 'dạng DH0001',
    user_id INT UNSIGNED NOT NULL,
    receiver_name VARCHAR(100) NOT NULL,
    receiver_phone VARCHAR(20) NOT NULL,
    shipping_address VARCHAR(255) NOT NULL,
    note VARCHAR(500) NULL,
    payment_method ENUM('cod','bank_transfer') NOT NULL DEFAULT 'cod',
    total_amount DECIMAL(12,0) NOT NULL,
    status ENUM('pending','confirmed','shipping','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_orders_user (user_id),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL COMMENT 'NULL khi sản phẩm bị xóa',
    product_name VARCHAR(200) NOT NULL COMMENT 'tên lúc mua',
    price DECIMAL(12,0) NOT NULL COMMENT 'giá lúc mua',
    quantity INT UNSIGNED NOT NULL,
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE carts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cart_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_cart_product (cart_id, product_id),
    CONSTRAINT fk_cartitems_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cartitems_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment VARCHAR(1000) NULL,
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = bị ẩn',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review (user_id, product_id),
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE wishlists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wishlist (user_id, product_id),
    CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlists_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== DỮ LIỆU MẪU =====================

INSERT INTO users (id, full_name, email, password, phone, address, role, status) VALUES
(1, 'Quản trị viên', 'admin@kiddozone.test',  '$2y$10$rArQ5SEKGm4EL.tBgKNF/.L8Syx7SkuPAm8o6eCPx.3oyImRZXHbu', '0900000000', 'TP. Hồ Chí Minh', 'admin', 1),
(2, 'Nguyễn Văn An', 'khach1@kiddozone.test', '$2y$10$RlrS6OaaPvP..btvfA33iOJjkv/UU/NjGqTknk2x7ktUIBgizKQL6', '0911111111', '12 Lê Lợi, Q.1, TP. HCM', 'customer', 1),
(3, 'Trần Thị Bình', 'khach2@kiddozone.test', '$2y$10$RlrS6OaaPvP..btvfA33iOJjkv/UU/NjGqTknk2x7ktUIBgizKQL6', '0922222222', '45 Nguyễn Huệ, Q.1, TP. HCM', 'customer', 1),
(4, 'Lê Hoàng Cường', 'khach3@kiddozone.test', '$2y$10$RlrS6OaaPvP..btvfA33iOJjkv/UU/NjGqTknk2x7ktUIBgizKQL6', '0933333333', '78 Trần Hưng Đạo, Q.5, TP. HCM', 'customer', 1);

INSERT INTO categories (id, name, description, status) VALUES
(1, 'Đồ chơi giáo dục', 'Phát triển tư duy và kỹ năng cho bé', 1),
(2, 'Lắp ráp và xếp hình', 'Lego, khối xây dựng, puzzle', 1),
(3, 'Búp bê và thú bông', 'Bạn đồng hành mềm mại', 1),
(4, 'Xe và mô hình', 'Xe điều khiển, mô hình sưu tầm', 1),
(5, 'Đồ chơi vận động', 'Vui chơi ngoài trời, rèn luyện thể chất', 1);

INSERT INTO products (id, category_id, name, price, sale_price, stock, description, is_featured) VALUES
(1, 1, 'Bộ xếp hình chữ cái gỗ',            120000, NULL,   50, '<p>Bộ chữ cái bằng gỗ an toàn, giúp bé làm quen mặt chữ.</p>', 1),
(2, 1, 'Bảng chữ số nam châm',                95000, 79000,  40, '<p>Bảng số nam châm nhiều màu, học đếm vui nhộn.</p>', 0),
(3, 1, 'Bộ đồ chơi khoa học mini',           185000, NULL,   30, '<p>Các thí nghiệm khoa học đơn giản cho bé tò mò.</p>', 1),
(4, 1, 'Thẻ học thông minh 150 thẻ',          89000, NULL,   60, '<p>150 thẻ hình ảnh song ngữ Anh - Việt.</p>', 0),
(5, 2, 'Lego xe cứu hỏa thành phố',          350000, 320000, 25, '<p>Bộ lắp ráp xe cứu hỏa 250 chi tiết.</p>', 1),
(6, 2, 'Bộ khối xây dựng 100 chi tiết',      210000, NULL,   35, '<p>Khối xây dựng nhiều hình dạng, nhựa ABS an toàn.</p>', 0),
(7, 2, 'Robot lắp ráp biến hình',            275000, NULL,   20, '<p>Robot biến hình thành xe, lắp ráp tự do.</p>', 1),
(8, 2, 'Tranh ghép Puzzle 200 mảnh',          99000, NULL,   45, '<p>Puzzle tranh thế giới động vật.</p>', 0),
(9, 3, 'Gấu bông Teddy 40cm',                180000, 150000, 30, '<p>Gấu bông mềm mịn, an toàn cho bé.</p>', 1),
(10, 3, 'Búp bê công chúa',                  220000, NULL,   25, '<p>Búp bê công chúa kèm váy và phụ kiện.</p>', 0),
(11, 3, 'Thỏ bông tai dài',                  130000, NULL,   40, '<p>Thỏ bông tai dài đáng yêu.</p>', 0),
(12, 3, 'Nhà búp bê mini',                   450000, 399000, 12, '<p>Nhà búp bê gỗ 3 tầng kèm nội thất.</p>', 1),
(13, 4, 'Xe ô tô điều khiển từ xa',          320000, NULL,   22, '<p>Xe địa hình điều khiển từ xa, pin sạc.</p>', 1),
(14, 4, 'Mô hình xe đua hợp kim',            125000, NULL,   50, '<p>Mô hình xe đua tỉ lệ 1:32, đánh cót.</p>', 0),
(15, 4, 'Tàu hỏa chạy pin',                  199000, NULL,   28, '<p>Tàu hỏa kèm đường ray, có âm thanh.</p>', 0),
(16, 4, 'Máy bay mô hình lắp ráp',           240000, 210000, 18, '<p>Mô hình máy bay dân dụng lắp ráp.</p>', 0),
(17, 5, 'Bóng rổ mini trẻ em',               160000, NULL,   30, '<p>Bóng rổ mini kèm khung gắn cửa.</p>', 0),
(18, 5, 'Xe scooter 3 bánh',                 520000, 480000, 15, '<p>Scooter 3 bánh có đèn, chịu tải 50kg.</p>', 1),
(19, 5, 'Bộ ném vòng',                        85000, NULL,   55, '<p>Trò chơi ném vòng trong nhà và ngoài trời.</p>', 0),
(20, 5, 'Dây nhảy trẻ em',                    45000, NULL,   80, '<p>Dây nhảy có tay cầm chống trượt.</p>', 0);

-- Mỗi sản phẩm mẫu có 1 ảnh đại diện là placeholder (thay bằng ảnh thật khi Admin upload).
INSERT INTO product_images (product_id, image_path, is_main)
SELECT id, 'assets/img/placeholder.svg', 1 FROM products;

INSERT INTO orders (id, code, user_id, receiver_name, receiver_phone, shipping_address, note, payment_method, total_amount, status, created_at) VALUES
(1,  'DH0001', 2, 'Nguyễn Văn An',  '0911111111', '12 Lê Lợi, Q.1, TP. HCM',        NULL,              'cod',           270000, 'completed', '2026-10-01 09:15:00'),
(2,  'DH0002', 2, 'Nguyễn Văn An',  '0911111111', '12 Lê Lợi, Q.1, TP. HCM',        'Giao giờ hành chính', 'bank_transfer', 320000, 'completed', '2026-10-05 14:30:00'),
(3,  'DH0003', 3, 'Trần Thị Bình',  '0922222222', '45 Nguyễn Huệ, Q.1, TP. HCM',    NULL,              'cod',           320000, 'completed', '2026-10-08 10:00:00'),
(4,  'DH0004', 3, 'Trần Thị Bình',  '0922222222', '45 Nguyễn Huệ, Q.1, TP. HCM',    NULL,              'cod',           363000, 'completed', '2026-10-12 16:45:00'),
(5,  'DH0005', 4, 'Lê Hoàng Cường', '0933333333', '78 Trần Hưng Đạo, Q.5, TP. HCM', NULL,              'bank_transfer', 480000, 'completed', '2026-10-15 11:20:00'),
(6,  'DH0006', 4, 'Lê Hoàng Cường', '0933333333', '78 Trần Hưng Đạo, Q.5, TP. HCM', NULL,              'cod',           250000, 'shipping',  '2026-11-02 08:10:00'),
(7,  'DH0007', 2, 'Nguyễn Văn An',  '0911111111', '12 Lê Lợi, Q.1, TP. HCM',        NULL,              'cod',           220000, 'confirmed', '2026-11-03 19:00:00'),
(8,  'DH0008', 3, 'Trần Thị Bình',  '0922222222', '45 Nguyễn Huệ, Q.1, TP. HCM',    'Gọi trước khi giao', 'cod',        250000, 'pending',   '2026-11-04 13:25:00'),
(9,  'DH0009', 4, 'Lê Hoàng Cường', '0933333333', '78 Trần Hưng Đạo, Q.5, TP. HCM', NULL,              'cod',           275000, 'cancelled', '2026-10-20 09:50:00'),
(10, 'DH0010', 2, 'Nguyễn Văn An',  '0911111111', '12 Lê Lợi, Q.1, TP. HCM',        NULL,              'bank_transfer', 399000, 'pending',   '2026-11-05 21:05:00');

INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES
(1, 1,  'Bộ xếp hình chữ cái gỗ',       120000, 1),
(1, 9,  'Gấu bông Teddy 40cm',          150000, 1),
(2, 5,  'Lego xe cứu hỏa thành phố',    320000, 1),
(3, 13, 'Xe ô tô điều khiển từ xa',     320000, 1),
(4, 3,  'Bộ đồ chơi khoa học mini',     185000, 1),
(4, 4,  'Thẻ học thông minh 150 thẻ',    89000, 2),
(5, 18, 'Xe scooter 3 bánh',            480000, 1),
(6, 14, 'Mô hình xe đua hợp kim',       125000, 2),
(7, 10, 'Búp bê công chúa',             220000, 1),
(8, 17, 'Bóng rổ mini trẻ em',          160000, 1),
(8, 20, 'Dây nhảy trẻ em',               45000, 2),
(9, 7,  'Robot lắp ráp biến hình',      275000, 1),
(10, 12,'Nhà búp bê mini',              399000, 1);

-- Đánh giá chỉ cho sản phẩm thuộc đơn đã hoàn thành.
INSERT INTO reviews (user_id, product_id, rating, comment, status) VALUES
(2, 1,  5, 'Gỗ chắc, màu đẹp, bé nhà mình rất thích.', 1),
(2, 9,  4, 'Gấu mềm, hơi nhỏ hơn mong đợi.', 1),
(2, 5,  5, 'Lắp ráp vui, chi tiết đầy đủ.', 1),
(3, 13, 4, 'Xe chạy tốt, pin hơi nhanh hết.', 1),
(3, 3,  5, 'Bé thích thí nghiệm, hướng dẫn dễ hiểu.', 1),
(3, 4,  4, 'Thẻ in rõ, chất liệu dày.', 1),
(4, 18, 5, 'Scooter chắc chắn, đèn đẹp.', 1);

INSERT INTO wishlists (user_id, product_id) VALUES (2, 12), (2, 18), (3, 7), (4, 5);

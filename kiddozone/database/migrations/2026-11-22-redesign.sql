-- Nâng cấp CSDL KiddoZone đang chạy (giữ nguyên dữ liệu cũ) cho bản giao diện mới.
-- Dùng khi KHÔNG muốn import lại kiddozone.sql từ đầu (ví dụ trên hosting đã có đơn hàng thật).
-- Chạy 1 lần duy nhất: phpMyAdmin > chọn database > tab Import > chọn file này.
--
-- Thêm:
--   products.age_min / age_max            : lọc theo độ tuổi, trợ lý chọn quà
--   products.flash_sale_*                 : flash sale có đồng hồ đếm ngược
--   coupons                               : mã giảm giá
--   orders.coupon_code / discount_amount  : ghi nhận mã đã dùng
--   orders.admin_seen                     : thông báo đơn mới cho Admin
--   order_status_history                  : dòng thời gian trạng thái đơn hàng

ALTER TABLE products
    ADD COLUMN age_min TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'tuổi nhỏ nhất phù hợp' AFTER stock,
    ADD COLUMN age_max TINYINT UNSIGNED NOT NULL DEFAULT 12 COMMENT 'tuổi lớn nhất phù hợp' AFTER age_min,
    ADD COLUMN flash_sale_price DECIMAL(12,0) NULL COMMENT 'giá flash sale' AFTER sale_price,
    ADD COLUMN flash_sale_start DATETIME NULL AFTER flash_sale_price,
    ADD COLUMN flash_sale_end DATETIME NULL AFTER flash_sale_start;

CREATE TABLE IF NOT EXISTS coupons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    value DECIMAL(12,0) NOT NULL COMMENT 'phần trăm (1-100) hoặc số tiền',
    min_order DECIMAL(12,0) NOT NULL DEFAULT 0,
    max_discount DECIMAL(12,0) NULL COMMENT 'giảm tối đa (chỉ dùng với percent)',
    usage_limit INT UNSIGNED NULL COMMENT 'NULL = không giới hạn',
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE orders
    ADD COLUMN coupon_code VARCHAR(30) NULL AFTER payment_method,
    ADD COLUMN discount_amount DECIMAL(12,0) NOT NULL DEFAULT 0 AFTER coupon_code,
    ADD COLUMN admin_seen TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = đơn mới Admin chưa xem' AFTER status;

-- Đơn cũ coi như Admin đã xem.
UPDATE orders SET admin_seen = 1;

CREATE TABLE IF NOT EXISTS order_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    status ENUM('pending','confirmed','shipping','completed','cancelled') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_history_order (order_id),
    CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Lịch sử cho đơn cũ: mốc đặt hàng, và trạng thái hiện tại (nếu đã khác "pending").
INSERT INTO order_status_history (order_id, status, created_at)
SELECT id, 'pending', created_at FROM orders;
INSERT INTO order_status_history (order_id, status, created_at)
SELECT id, status, created_at FROM orders WHERE status <> 'pending';

-- Mã giảm giá mẫu
INSERT INTO coupons (code, description, type, value, min_order, max_discount, usage_limit, starts_at, ends_at) VALUES
('KIDDO10', 'Giảm 10% tối đa 50.000đ cho đơn từ 200.000đ', 'percent', 10, 200000, 50000, NULL, NULL, NULL),
('CHAOBE50', 'Giảm 50.000đ cho đơn từ 500.000đ', 'fixed', 50000, 500000, NULL, 100, NULL, NULL),
('FLASH20', 'Giảm 20% tối đa 80.000đ, chỉ 20 lượt', 'percent', 20, 0, 80000, 20, NULL, NULL);

<?php
// Cấu hình chung. Chỉnh DB_* cho khớp máy của bạn (XAMPP mặc định: root, không mật khẩu).
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'kiddozone');
define('DB_USER', 'root');
define('DB_PASS', '');

// Đường dẫn gốc của website tính từ domain, ví dụ '/kiddozone'. Để null để tự dò.
define('BASE_URL_OVERRIDE', null);

define('ITEMS_PER_PAGE_SHOP', 12);   // phân trang phía khách
define('ITEMS_PER_PAGE_ADMIN', 10);  // phân trang trong admin
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2MB mỗi ảnh

date_default_timezone_set('Asia/Ho_Chi_Minh');

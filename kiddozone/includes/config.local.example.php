<?php
/**
 * File mẫu cấu hình riêng theo từng máy hoặc hosting (InfinityFree / shared hosting).
 * Sao chép file này thành "config.local.php" cùng thư mục includes/ và điền thông số thực tế.
 * Lưu ý: "config.local.php" đã được loại trừ trong .gitignore nên không bị đẩy lên GitHub.
 */

define('DB_HOST', 'sqlXXX.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_xxxxxxxx_kiddozone');
define('DB_USER', 'if0_xxxxxxxx');
define('DB_PASS', 'mat_khau_mysql_cua_ban');

// Để null để website tự dò đường dẫn gốc, hoặc đặt '' nếu trỏ thẳng domain vào thư mục kiddozone/
define('BASE_URL_OVERRIDE', null);

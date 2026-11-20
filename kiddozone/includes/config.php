<?php
// Cấu hình chung. Chỉnh DB_* trong config.local.php hoặc biến môi trường (Deploy Render/InfinityFree).
// XAMPP / Laragon mặc định: host 127.0.0.1, user root, mật khẩu rỗng.
$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

$env = static function (string $key, $default = null) {
    $val = getenv($key);
    return ($val !== false && $val !== '') ? $val : $default;
};

defined('DB_HOST') || define('DB_HOST', (string)$env('DB_HOST', '127.0.0.1'));
defined('DB_PORT') || define('DB_PORT', (string)$env('DB_PORT', '3306'));
defined('DB_NAME') || define('DB_NAME', (string)$env('DB_NAME', 'kiddozone'));
defined('DB_USER') || define('DB_USER', (string)$env('DB_USER', 'root'));
defined('DB_PASS') || define('DB_PASS', (string)(getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''));

// Đường dẫn gốc của website tính từ domain, ví dụ '/kiddozone' hoặc '' khi deploy ở root domain. Để null để tự dò.
defined('BASE_URL_OVERRIDE') || define('BASE_URL_OVERRIDE', $env('BASE_URL_OVERRIDE', null));

defined('ITEMS_PER_PAGE_SHOP')  || define('ITEMS_PER_PAGE_SHOP', 12);   // phân trang phía khách
defined('ITEMS_PER_PAGE_ADMIN') || define('ITEMS_PER_PAGE_ADMIN', 10);  // phân trang trong admin
defined('UPLOAD_DIR')           || define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
defined('UPLOAD_MAX_BYTES')     || define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2MB mỗi ảnh

date_default_timezone_set('Asia/Ho_Chi_Minh');

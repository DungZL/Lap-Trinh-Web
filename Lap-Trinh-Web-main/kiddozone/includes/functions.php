<?php
// Hàm dùng chung cho trang khách và admin.

/** Escape dữ liệu khi in ra HTML (chống XSS). */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Đường dẫn gốc website, ví dụ "/Lap-Trinh-Web/kiddozone" (không có dấu / cuối). */
function base_url(): string
{
    static $base = null;
    if ($base === null) {
        if (BASE_URL_OVERRIDE !== null) {
            $base = rtrim(BASE_URL_OVERRIDE, '/');
        } else {
            $root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
            $app  = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: '');
            $base = ($root !== '' && strpos($app, $root) === 0) ? rtrim(substr($app, strlen($root)), '/') : '';
        }
    }
    return $base;
}

/** url('products.php?page=2') => "/kiddozone/products.php?page=2" */
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    // "/abc" là đường dẫn tuyệt đối từ domain (đã gồm base), còn lại tính từ gốc website.
    $target = (preg_match('#^https?://#', $path) || ($path !== '' && $path[0] === '/')) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** Giữ lại giá trị form sau khi lỗi. */
function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

/* ---------- Flash message ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Lấy và xóa toàn bộ flash message. */
function flash_pull(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/* ---------- Định dạng ---------- */

function format_price($amount): string
{
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

/** Giá đang bán: ưu tiên sale_price nếu có. */
function effective_price(array $product): float
{
    return ($product['sale_price'] !== null && (float)$product['sale_price'] > 0)
        ? (float)$product['sale_price']
        : (float)$product['price'];
}

/* ---------- Phân trang ---------- */

/**
 * Tính phân trang. Dùng: [$offset, $pages, $page] = paginate($total, 12);
 * rồi "LIMIT $perPage OFFSET $offset" (ép kiểu int, an toàn).
 */
function paginate(int $total, int $perPage, ?int $page = null): array
{
    $page  = $page ?? (int)($_GET['page'] ?? 1);
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return [($page - 1) * $perPage, $pages, $page];
}

/** Tạo URL cho trang $page, giữ nguyên các tham số lọc/tìm kiếm hiện có. */
function page_url(int $page): string
{
    $params = $_GET;
    $params['page'] = $page;
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    return $path . '?' . http_build_query($params);
}

/** In thanh phân trang Bootstrap 5. */
function render_pagination(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav aria-label="Phân trang"><ul class="pagination justify-content-center">';
    $html .= '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e(page_url(max(1, $page - 1))) . '">&laquo;</a></li>';
    for ($i = 1; $i <= $pages; $i++) {
        $html .= '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="' . e(page_url($i)) . '">' . $i . '</a></li>';
    }
    $html .= '<li class="page-item' . ($page >= $pages ? ' disabled' : '') . '"><a class="page-link" href="' . e(page_url(min($pages, $page + 1))) . '">&raquo;</a></li>';
    return $html . '</ul></nav>';
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Chèn vào mọi <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Gọi ở đầu mọi xử lý POST. Sai hoặc thiếu token thì dừng, không đổi dữ liệu. */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(419);
        exit('Phiên làm việc không hợp lệ (CSRF). Vui lòng tải lại trang và thử lại.');
    }
}

/* ---------- Upload ảnh ---------- */

/**
 * Kiểm tra và lưu 1 file ảnh. Trả về tên file đã lưu (tên ngẫu nhiên) hoặc ném Exception.
 * $file là một phần tử của $_FILES (đã tách từng file nếu upload nhiều).
 */
function save_uploaded_image(array $file): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Tải ảnh thất bại.');
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Ảnh vượt quá 2MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Chỉ nhận ảnh JPG, PNG hoặc WEBP.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        throw new RuntimeException('Không lưu được ảnh.');
    }
    return $name;
}

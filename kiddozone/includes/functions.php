<?php
// Hàm dùng chung cho trang khách và admin.

/** Escape dữ liệu khi in ra HTML (chống XSS). */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Đường dẫn gốc website, ví dụ "/Lap-Trinh-Web/kiddozone" (không có dấu / cuối).
 * Tự dò từ URL của file đang chạy, nên đúng cả khi thư mục dự án là junction/symlink
 * (ví dụ htdocs\Lap-Trinh-Web trỏ về Desktop) hoặc được đổi tên.
 */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    if (BASE_URL_OVERRIDE !== null) {
        return $base = rtrim(BASE_URL_OVERRIDE, '/');
    }

    $norm = fn(string $p): string => str_replace('\\', '/', $p);
    $app  = $norm(realpath(dirname(__DIR__)) ?: '');

    // Cách 1: đường dẫn file đang chạy, tính từ thư mục dự án (ví dụ "/admin/categories/index.php"),
    // rồi cắt phần đó khỏi SCRIPT_NAME (ví dụ "/Lap-Trinh-Web/kiddozone/admin/categories/index.php").
    $script     = $norm(realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '');
    $scriptName = $norm($_SERVER['SCRIPT_NAME'] ?? '');
    if ($app !== '' && $script !== '' && stripos($script, $app . '/') === 0) {
        $relative = substr($script, strlen($app)); // bắt đầu bằng "/"
        if (strlen($scriptName) >= strlen($relative)
            && strcasecmp(substr($scriptName, -strlen($relative)), $relative) === 0) {
            return $base = rtrim(substr($scriptName, 0, -strlen($relative)), '/');
        }
    }

    // Cách 2 (dự phòng): so thư mục dự án với DOCUMENT_ROOT.
    $root = $norm(realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    $base = ($root !== '' && stripos($app, $root) === 0) ? rtrim(substr($app, strlen($root)), '/') : '';
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

/** Giỏ session lưu số lượng theo ID sản phẩm, không lưu dữ liệu giá do client gửi lên. */
function session_cart(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        return [];
    }

    $items = [];
    foreach ($cart as $productId => $quantity) {
        $id = filter_var($productId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $qty = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id !== false && $qty !== false) {
            $items[$id] = $qty;
        }
    }
    $_SESSION['cart'] = $items;
    return $items;
}

/** Lấy ID giỏ hàng trong CSDL theo user_id, tùy chọn tạo mới nếu chưa có. */
function user_cart_id(int $userId, bool $create = false): ?int
{
    $id = db_query('SELECT id FROM carts WHERE user_id = ?', [$userId])->fetchColumn();
    if ($id !== false) {
        return (int)$id;
    }
    if (!$create) {
        return null;
    }
    db_query(
        'INSERT INTO carts (user_id) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
        [$userId]
    );
    return (int)db()->lastInsertId();
}

/** Lấy giỏ hàng hiện tại dạng [product_id => quantity] (từ CSDL nếu là khách hàng, từ session nếu là khách vãng lai). */
function cart_items(): array
{
    if (function_exists('is_logged_in') && is_logged_in() && !is_admin()) {
        $rows = db_query(
            'SELECT ci.product_id, ci.quantity
             FROM cart_items ci
             JOIN carts c ON c.id = ci.cart_id
             WHERE c.user_id = ?
             ORDER BY ci.id ASC',
            [(int)current_user()['id']]
        )->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $pid = (int)$row['product_id'];
            $qty = (int)$row['quantity'];
            if ($pid > 0 && $qty > 0) {
                $items[$pid] = $qty;
            }
        }
        return $items;
    }

    return session_cart();
}

function cart_count(): int
{
    return array_sum(cart_items());
}

function cart_item_count(): int
{
    return cart_count();
}

/** Đặt số lượng 1 sản phẩm trong giỏ (<= 0 thì xóa khỏi giỏ). */
function cart_set_item(int $productId, int $quantity): void
{
    if ($productId <= 0) {
        return;
    }
    if (function_exists('is_logged_in') && is_logged_in() && !is_admin()) {
        $cartId = user_cart_id((int)current_user()['id'], $quantity > 0);
        if ($cartId === null) {
            return;
        }
        if ($quantity <= 0) {
            db_query('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?', [$cartId, $productId]);
        } else {
            db_query(
                'INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
                [$cartId, $productId, $quantity]
            );
        }
        db_query('UPDATE carts SET updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$cartId]);
        return;
    }

    $cart = session_cart();
    if ($quantity <= 0) {
        unset($cart[$productId]);
    } else {
        $cart[$productId] = $quantity;
    }
    $_SESSION['cart'] = $cart;
}

function cart_remove_item(int $productId): void
{
    cart_set_item($productId, 0);
}

function cart_clear(?int $userId = null): void
{
    $uid = $userId;
    if ($uid === null && function_exists('is_logged_in') && is_logged_in() && !is_admin()) {
        $uid = (int)current_user()['id'];
    }
    if ($uid !== null && $uid > 0) {
        db_query(
            'DELETE ci FROM cart_items ci
             JOIN carts c ON c.id = ci.cart_id
             WHERE c.user_id = ?',
            [$uid]
        );
    }
    unset($_SESSION['cart']);
}

/** Gộp giỏ hàng session vào giỏ lưu trong CSDL của khách hàng khi đăng nhập (F19). */
function merge_session_cart(int $userId): void
{
    $sessionItems = session_cart();
    if (!$sessionItems) {
        return;
    }

    $cartId = user_cart_id($userId, true);
    if ($cartId === null) {
        return;
    }

    $existingRows = db_query(
        'SELECT product_id, quantity FROM cart_items WHERE cart_id = ?',
        [$cartId]
    )->fetchAll();

    $existing = [];
    foreach ($existingRows as $row) {
        $existing[(int)$row['product_id']] = (int)$row['quantity'];
    }

    foreach ($sessionItems as $productId => $addQty) {
        $prod = db_query(
            'SELECT p.stock
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.status = 1 AND c.status = 1',
            [$productId]
        )->fetch();

        if (!$prod || (int)$prod['stock'] <= 0) {
            continue;
        }

        $mergedQty = min((int)$prod['stock'], ($existing[$productId] ?? 0) + $addQty);
        if ($mergedQty > 0) {
            db_query(
                'INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
                [$cartId, $productId, $mergedQty]
            );
            $existing[$productId] = $mergedQty;
        }
    }

    db_query('UPDATE carts SET updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$cartId]);
    unset($_SESSION['cart']);
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

/* ---------- Lọc HTML mô tả (TinyMCE) ---------- */

/**
 * Lọc HTML mô tả (từ TinyMCE) trước khi lưu hoặc in ra: bỏ script/iframe/form/style...,
 * bỏ mọi thuộc tính ngoài danh sách cho phép, bỏ link javascript:/data:.
 */
function sanitize_description_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (!class_exists('DOMDocument')) {
        return nl2br(e(strip_tags($html))); // không có extension DOM thì hiện dạng chữ thường
    }

    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5',
        'blockquote', 'a', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'span', 'div', 'hr',
    ];
    $dropTags = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea',
        'select', 'link', 'meta', 'svg', 'math', 'base',
    ];
    $allowedAttrs   = ['href', 'src', 'alt', 'title', 'colspan', 'rowspan', 'width', 'height'];
    $allowedSchemes = ['http', 'https', 'mailto'];

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $dom->getElementsByTagName('div')->item(0);
    if (!$root) {
        return '';
    }

    $clean = function (DOMNode $node) use (&$clean, $allowedTags, $dropTags, $allowedAttrs, $allowedSchemes): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!($child instanceof DOMElement)) {
                continue;
            }
            $tag = strtolower($child->tagName);

            if (in_array($tag, $dropTags, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!in_array($tag, $allowedTags, true)) {
                // Thẻ lạ: bỏ thẻ nhưng giữ nội dung bên trong.
                $clean($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $allowedAttrs, true)) {
                    $child->removeAttribute($attr->name);
                    continue;
                }
                if ($name === 'href' || $name === 'src') {
                    $val = strtolower(preg_replace('/[\x00-\x20]+/', '', $attr->value));
                    if (preg_match('/^([a-z][a-z0-9+.\-]*):/', $val, $m) && !in_array($m[1], $allowedSchemes, true)) {
                        $child->removeAttribute($attr->name); // ví dụ javascript:, data:
                    }
                }
            }

            if ($tag === 'a') {
                $child->setAttribute('rel', 'noopener noreferrer');
            } elseif ($tag === 'img') {
                $child->setAttribute('class', 'img-fluid');
                $child->setAttribute('loading', 'lazy');
            } elseif ($tag === 'table') {
                $child->setAttribute('class', 'table table-bordered');
            }
            $clean($child);
        }
    };
    $clean($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return $out;
}

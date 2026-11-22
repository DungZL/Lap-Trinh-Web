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

/** Linh vật gấu KiddoZone (SVG tự vẽ, dùng cho logo, favicon, banner). */
function mascot_svg(string $class = 'size-8', string $mood = 'happy', bool $wave = false): string
{
    // $mood: happy (cười), sad (buồn - giỏ trống, 404), wow (ngạc nhiên). $wave: thêm tay vẫy.
    $mouths = [
        'happy' => '<path d="M27.5 46.5q4.5 3.4 9 0" stroke="#1c2540" stroke-width="2.2" fill="none" stroke-linecap="round"/>',
        'sad'   => '<path d="M27.5 48.6q4.5 -3.2 9 0" stroke="#1c2540" stroke-width="2.2" fill="none" stroke-linecap="round"/>',
        'wow'   => '<ellipse cx="32" cy="47.6" rx="2.6" ry="3.1" fill="#1c2540"/>',
    ];
    $paw = $wave
        ? '<g class="kz-paw"><ellipse cx="55" cy="51" rx="6.8" ry="6.2" fill="#dd8f3e" stroke="#b86a22" stroke-width="1"/>'
          . '<ellipse cx="55.4" cy="52.6" rx="2.8" ry="2.2" fill="#f9d7b0"/><circle cx="51.8" cy="48.4" r="1.3" fill="#f9d7b0"/>'
          . '<circle cx="55.2" cy="47.2" r="1.3" fill="#f9d7b0"/><circle cx="58.6" cy="48.4" r="1.3" fill="#f9d7b0"/></g>'
        : '';
    return '<svg class="kz-mascot ' . e($class) . '" viewBox="0 0 64 64" aria-hidden="true" focusable="false">'
        . '<g class="kz-ear-l"><circle cx="15" cy="16" r="10" fill="#c9782a"/><circle cx="15" cy="16" r="5" fill="#f7c89b"/></g>'
        . '<g class="kz-ear-r"><circle cx="49" cy="16" r="10" fill="#c9782a"/><circle cx="49" cy="16" r="5" fill="#f7c89b"/></g>'
        . '<circle cx="32" cy="35" r="23" fill="#dd8f3e"/>'
        . '<ellipse cx="32" cy="44" rx="12" ry="9.5" fill="#f9d7b0"/>'
        . '<g class="kz-eyes"><circle cx="23.5" cy="31" r="3.2" fill="#1c2540"/><circle cx="40.5" cy="31" r="3.2" fill="#1c2540"/>'
        . '<circle cx="24.6" cy="29.9" r="1" fill="#fff"/><circle cx="41.6" cy="29.9" r="1" fill="#fff"/></g>'
        . ($mood === 'sad' ? '<path d="M19.5 27.2l6.5 -2.4M44.5 27.2l-6.5 -2.4" stroke="#1c2540" stroke-width="1.8" stroke-linecap="round"/>' : '')
        . '<ellipse cx="32" cy="40" rx="4.4" ry="3.2" fill="#1c2540"/>'
        . ($mouths[$mood] ?? $mouths['happy'])
        . '<circle cx="18" cy="41" r="3" fill="#ff9797" opacity=".55"/><circle cx="46" cy="41" r="3" fill="#ff9797" opacity=".55"/>'
        . $paw
        . '</svg>';
}

/**
 * Đường lượn sóng nối hai khối màu. $class đặt màu (text-*) và vị trí (absolute top-0 ...).
 * $flip = true: lật ngược (dùng ở cạnh dưới).
 */
function wave_svg(string $class, bool $flip = false): string
{
    return '<svg class="pointer-events-none block w-full ' . e($class) . ($flip ? ' rotate-180' : '') . '" viewBox="0 0 1440 64" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
        . '<path fill="currentColor" d="M0,0 L1440,0 L1440,28 C1320,58 1200,62 1080,40 C960,18 840,8 720,26 C600,44 480,62 360,48 C240,34 120,14 0,30 Z"/></svg>';
}

/** Ô icon lớn cho trạng thái trống / thông báo (thay cho emoji). $tone: sky, sun, coral, mint, grape. */
function empty_icon(string $icon, string $tone = 'sky'): string
{
    $tones = [
        'sky'   => 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300',
        'sun'   => 'bg-sun-100 text-sun-800 dark:bg-sun-500/15 dark:text-sun-300',
        'coral' => 'bg-coral-100 text-coral-700 dark:bg-coral-500/15 dark:text-coral-300',
        'mint'  => 'bg-mint-100 text-mint-700 dark:bg-mint-500/15 dark:text-mint-300',
        'grape' => 'bg-grape-100 text-grape-600 dark:bg-grape-500/15 dark:text-grape-300',
    ];
    return '<span class="grid size-24 place-items-center rounded-full text-5xl ' . ($tones[$tone] ?? $tones['sky']) . '" aria-hidden="true">'
        . '<i class="ph-fill ' . e($icon) . '" aria-hidden="true"></i></span>';
}

/** Yêu cầu gửi bằng fetch() của assets/js/app.js (có header X-Requested-With). */
function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

/** Trả JSON rồi dừng. Bỏ mọi nội dung lỡ in ra trước đó để phản hồi luôn là JSON thuần. */
function json_response(array $data, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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

/** Flash sale đang diễn ra? (cần các cột flash_sale_price, flash_sale_start, flash_sale_end) */
function flash_active(array $product): bool
{
    $price = $product['flash_sale_price'] ?? null;
    if ($price === null || (float)$price <= 0 || empty($product['flash_sale_start']) || empty($product['flash_sale_end'])) {
        return false;
    }
    $now = time();
    return strtotime((string)$product['flash_sale_start']) <= $now && $now < strtotime((string)$product['flash_sale_end']);
}

/** Giá đang bán: ưu tiên giá flash sale (khi đang diễn ra), rồi tới sale_price, cuối cùng là giá gốc. */
function effective_price(array $product): float
{
    if (flash_active($product)) {
        return (float)$product['flash_sale_price'];
    }
    return (($product['sale_price'] ?? null) !== null && (float)$product['sale_price'] > 0)
        ? (float)$product['sale_price']
        : (float)$product['price'];
}

/** Biểu thức SQL tính giá đang bán của bảng products (bí danh p), dùng để lọc/sắp xếp theo giá. */
function final_price_sql(): string
{
    return "(CASE
        WHEN p.flash_sale_price IS NOT NULL AND p.flash_sale_price > 0
             AND p.flash_sale_start <= NOW() AND p.flash_sale_end > NOW() THEN p.flash_sale_price
        WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 THEN p.sale_price
        ELSE p.price END)";
}

/** Các cột giá của sản phẩm (bí danh p) cần cho effective_price(). */
function product_price_columns(): string
{
    return 'p.price, p.sale_price, p.flash_sale_price, p.flash_sale_start, p.flash_sale_end';
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

/** In thanh phân trang (Tailwind, class .pager trong assets/src/app.css). */
function render_pagination(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pager mt-8" aria-label="Phân trang">';
    $html .= '<a class="' . ($page <= 1 ? 'is-disabled' : '') . '" href="' . e(page_url(max(1, $page - 1))) . '" aria-label="Trang trước"><i class="ph-bold ph-caret-left" aria-hidden="true"></i></a>';
    for ($i = 1; $i <= $pages; $i++) {
        // Nhiều trang thì chỉ hiện trang đầu, cuối và 2 trang quanh trang hiện tại.
        if ($pages > 7 && $i !== 1 && $i !== $pages && abs($i - $page) > 1) {
            if ($i === 2 || $i === $pages - 1) {
                $html .= '<span class="is-disabled">…</span>';
            }
            continue;
        }
        $html .= '<a class="' . ($i === $page ? 'is-active' : '') . '" href="' . e(page_url($i)) . '"' . ($i === $page ? ' aria-current="page"' : '') . '>' . $i . '</a>';
    }
    $html .= '<a class="' . ($page >= $pages ? 'is-disabled' : '') . '" href="' . e(page_url(min($pages, $page + 1))) . '" aria-label="Trang sau"><i class="ph-bold ph-caret-right" aria-hidden="true"></i></a>';
    return $html . '</nav>';
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

/** Gọi ở đầu mọi xử lý POST. Sai, thiếu hoặc chưa có token trong phiên thì dừng, không đổi dữ liệu. */
function csrf_verify(): void
{
    $expected = $_SESSION['csrf_token'] ?? '';
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($expected) || $expected === '' || !is_string($sent) || $sent === '' || !hash_equals($expected, $sent)) {
        $msg = 'Phiên làm việc không hợp lệ (CSRF). Vui lòng tải lại trang và thử lại.';
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => $msg], 403);
        }
        http_response_code(403);
        exit($msg);
    }
}

/* ---------- Upload ảnh ---------- */

/**
 * Kiểm tra và lưu 1 file ảnh. Nếu ảnh vượt quá 2MB (hoặc là AVIF) và server có GD,
 * tự động thu gọn kích thước/nén xuống <= 2MB. Trả về tên file đã lưu hoặc ném Exception.
 * $file là một phần tử của $_FILES (đã tách từng file nếu upload nhiều).
 */
function save_uploaded_image(array $file): string
{
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Ảnh vượt quá dung lượng cho phép của máy chủ.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Tải ảnh thất bại.');
    }
    if ((int)$file['size'] > UPLOAD_RAW_MAX_BYTES) {
        throw new RuntimeException('Ảnh gốc vượt quá 15MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $canAvif = extension_loaded('gd') && function_exists('imagecreatefromavif');
    if (!isset($allowed[$mime]) && !($mime === 'image/avif' && $canAvif)) {
        throw new RuntimeException('Chỉ nhận ảnh JPG, PNG hoặc WEBP.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    if (isset($allowed[$mime]) && (int)$file['size'] <= UPLOAD_MAX_BYTES) {
        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
            throw new RuntimeException('Không lưu được ảnh.');
        }
        return $name;
    }

    if (!extension_loaded('gd')) {
        throw new RuntimeException('Ảnh vượt quá 2MB. Hãy bật JavaScript để nén tự động trên trình duyệt hoặc bật extension=gd trong php.ini.');
    }

    return compress_uploaded_image($file['tmp_name'], $mime, UPLOAD_DIR);
}

/** Nén ảnh bằng GD (dự phòng khi trình duyệt không nén trước) để kích thước <= UPLOAD_MAX_BYTES. */
function compress_uploaded_image(string $tmpPath, string $mime, string $destDir): string
{
    $info = @getimagesize($tmpPath);
    if (!$info || empty($info[0]) || empty($info[1])) {
        throw new RuntimeException('Không đọc được kích thước ảnh.');
    }
    $w = (int)$info[0];
    $h = (int)$info[1];
    if ($w * $h > 50_000_000) {
        throw new RuntimeException('Độ phân giải ảnh quá lớn.');
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmpPath),
        'image/png'  => @imagecreatefrompng($tmpPath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
        'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($tmpPath) : false,
        default      => false,
    };
    if (!$src) {
        throw new RuntimeException('Không đọc được dữ liệu ảnh.');
    }

    $useWebp = $mime !== 'image/jpeg' && function_exists('imagewebp');
    $ext = $useWebp ? 'webp' : 'jpg';
    $destDir = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR;
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = $destDir . $name;

    try {
        foreach ([1920, 1440, 1080, 800] as $maxDim) {
            $scale = min(1.0, $maxDim / max($w, $h));
            $newW = max(1, (int)round($w * $scale));
            $newH = max(1, (int)round($h * $scale));

            $dst = imagecreatetruecolor($newW, $newH);
            if (!$dst) {
                continue;
            }
            if ($useWebp) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $clear = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefilledrectangle($dst, 0, 0, $newW, $newH, $clear);
            } else {
                $white = imagecolorallocate($dst, 255, 255, 255);
                imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

            foreach ([85, 75, 65, 55] as $q) {
                $ok = $useWebp ? @imagewebp($dst, $destPath, $q) : @imagejpeg($dst, $destPath, $q);
                clearstatcache(true, $destPath);
                if ($ok && is_file($destPath) && filesize($destPath) <= UPLOAD_MAX_BYTES) {
                    unset($dst, $src);
                    return $name;
                }
            }
            unset($dst);
        }
    } finally {
        unset($src);
    }

    if (is_file($destPath)) {
        @unlink($destPath);
    }
    throw new RuntimeException('Không thể nén ảnh xuống dưới 2MB. Vui lòng chọn ảnh nhỏ hơn.');
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
                $child->setAttribute('loading', 'lazy'); // kiểu dáng ảnh/bảng do .prose-kz lo
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

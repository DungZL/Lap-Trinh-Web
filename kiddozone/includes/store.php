<?php
// Logic bán hàng dùng chung cho trang thường và API (assets/js/app.js gọi qua fetch):
// truy vấn sản phẩm, giỏ hàng, mã giảm giá, trạng thái đơn hàng, mã QR chuyển khoản.

require_once __DIR__ . '/reviews.php';

/* ---------- Sản phẩm ---------- */

/** Ảnh sản phẩm lưu trong uploads/. Sản phẩm chưa có ảnh dùng 1 ảnh mặc định assets/img/placeholder.svg. */
function product_image_url(?string $path): string
{
    if (!$path) {
        return url('assets/img/placeholder.svg');
    }
    return str_starts_with($path, 'assets/') ? url($path) : url('uploads/' . $path);
}

/**
 * Phần SELECT dùng chung để in thẻ sản phẩm (bảng products bí danh p, categories bí danh c).
 * Gồm giá, flash sale, tồn kho, độ tuổi, điểm đánh giá và ảnh chính.
 */
function product_card_columns(): string
{
    return 'p.id, p.name, ' . product_price_columns() . ', p.stock, p.age_min, p.age_max,
            c.name AS category_name,
            ' . review_rating_columns() . ',
            (SELECT pi.image_path FROM product_images pi
              WHERE pi.product_id = p.id
              ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image,
            (SELECT pi2.image_path FROM product_images pi2
              WHERE pi2.product_id = p.id
              ORDER BY pi2.is_main DESC, pi2.id ASC LIMIT 1 OFFSET 1) AS image2';
}

/** Dữ liệu sản phẩm gọn cho JSON (tìm kiếm, xem nhanh, chọn quà). */
function product_public_json(array $p): array
{
    $price = (float)$p['price'];
    $final = effective_price($p);
    return [
        'id'          => (int)$p['id'],
        'name'        => $p['name'],
        'category'    => $p['category_name'] ?? '',
        'price'       => $price,
        'final'       => $final,
        'price_text'  => format_price($price),
        'final_text'  => format_price($final),
        'on_sale'     => $final < $price,
        'percent'     => $final < $price && $price > 0 ? (int)round((1 - $final / $price) * 100) : 0,
        'flash'       => flash_active($p),
        'stock'       => (int)$p['stock'],
        'age'         => age_label((int)($p['age_min'] ?? 0), (int)($p['age_max'] ?? 12)),
        'rating'      => isset($p['avg_rating']) && $p['avg_rating'] !== null ? (float)$p['avg_rating'] : 0,
        'reviews'     => (int)($p['review_count'] ?? 0),
        'image'       => product_image_url($p['image'] ?? null),
        'url'         => url('product.php?id=' . (int)$p['id']),
    ];
}

/** "3 - 8 tuổi", "Từ 6 tuổi", "Mọi lứa tuổi" */
function age_label(int $min, int $max): string
{
    if ($min <= 0 && $max >= 12) {
        return 'Mọi lứa tuổi';
    }
    if ($max >= 12) {
        return 'Từ ' . $min . ' tuổi';
    }
    return $min . ' - ' . $max . ' tuổi';
}

/** Nhóm tuổi cho bộ lọc và trợ lý chọn quà: khóa => [nhãn, tuổi từ, tuổi đến, icon Phosphor]. */
function age_groups(): array
{
    return [
        '0-2'  => ['0 - 2 tuổi', 0, 2, 'ph-baby'],
        '3-5'  => ['3 - 5 tuổi', 3, 5, 'ph-puzzle-piece'],
        '6-8'  => ['6 - 8 tuổi', 6, 8, 'ph-rocket-launch'],
        '9-12' => ['9 - 12 tuổi', 9, 12, 'ph-target'],
    ];
}

/** Sản phẩm đang flash sale (hết giờ thì không còn). */
function flash_sale_products(int $limit = 8): array
{
    return db_query(
        'SELECT ' . product_card_columns() . '
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.status = 1 AND c.status = 1
           AND p.flash_sale_price IS NOT NULL AND p.flash_sale_price > 0
           AND p.flash_sale_start <= NOW() AND p.flash_sale_end > NOW()
         ORDER BY p.flash_sale_end ASC, p.id ASC
         LIMIT ' . max(1, $limit)
    )->fetchAll();
}

/* ---------- Giỏ hàng ---------- */

/**
 * Thêm / sửa / xóa 1 sản phẩm trong giỏ (dùng chung cho cart.php và api/cart.php).
 * Trả về ['ok' => bool, 'type' => success|warning|danger, 'message' => string].
 */
function cart_apply(string $action, $productId, $quantity): array
{
    if (is_admin()) {
        return ['ok' => false, 'type' => 'warning', 'message' => 'Tài khoản Admin không sử dụng chức năng giỏ hàng.'];
    }
    $pid = filter_var($productId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $qty = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($pid === false) {
        return ['ok' => false, 'type' => 'danger', 'message' => 'Thông tin sản phẩm không hợp lệ.'];
    }

    if ($action === 'remove') {
        cart_remove_item($pid);
        return ['ok' => true, 'type' => 'success', 'message' => 'Đã xóa sản phẩm khỏi giỏ hàng.'];
    }
    if (!in_array($action, ['add', 'update'], true) || $qty === false) {
        return ['ok' => false, 'type' => 'danger', 'message' => 'Thao tác hoặc số lượng không hợp lệ.'];
    }

    $product = db_query(
        'SELECT p.id, p.stock
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.id = ? AND p.status = 1 AND c.status = 1',
        [$pid]
    )->fetch();
    if (!$product) {
        return ['ok' => false, 'type' => 'danger', 'message' => 'Sản phẩm không tồn tại hoặc không còn được bán.'];
    }
    $stock = (int)$product['stock'];
    if ($stock < 1) {
        return ['ok' => false, 'type' => 'danger', 'message' => 'Sản phẩm hiện đã hết hàng.'];
    }

    $cart = cart_items();
    $newQty = $action === 'add' ? ($cart[$pid] ?? 0) + $qty : $qty;
    if ($newQty > $stock) {
        cart_set_item($pid, $stock);
        return ['ok' => true, 'type' => 'warning', 'message' => 'Số lượng vượt quá tồn kho nên đã được giới hạn về mức tối đa (' . $stock . ' sản phẩm).'];
    }
    cart_set_item($pid, $newQty);
    return ['ok' => true, 'type' => 'success', 'message' => $action === 'add' ? 'Đã thêm sản phẩm vào giỏ hàng.' : 'Đã cập nhật số lượng.'];
}

/**
 * Giỏ hàng kèm thông tin sản phẩm: ['lines' => [...], 'subtotal' => float, 'count' => int].
 * Mỗi dòng: product_id, quantity, available, product (mảng hoặc null), price, line_total, problem.
 */
function cart_details(): array
{
    $cart = cart_items();
    $lines = [];
    $subtotal = 0.0;
    if (!$cart) {
        return ['lines' => [], 'subtotal' => 0.0, 'count' => 0];
    }

    $marks = implode(',', array_fill(0, count($cart), '?'));
    $rows = db_query(
        "SELECT p.id, p.name, " . product_price_columns() . ", p.stock, p.status, c.status AS category_status,
                (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.id IN ($marks)",
        array_keys($cart)
    )->fetchAll();
    $byId = [];
    foreach ($rows as $row) {
        $byId[(int)$row['id']] = $row;
    }

    foreach ($cart as $pid => $qty) {
        $p = $byId[$pid] ?? null;
        $available = $p && (int)$p['status'] === 1 && (int)$p['category_status'] === 1;
        $price = $available ? effective_price($p) : 0.0;
        $problem = null;
        if (!$available) {
            $problem = 'Sản phẩm không còn khả dụng.';
        } elseif ((int)$p['stock'] <= 0) {
            $problem = 'Sản phẩm đã hết hàng.';
        } elseif ($qty > (int)$p['stock']) {
            $problem = 'Chỉ còn ' . (int)$p['stock'] . ' sản phẩm trong kho.';
        }
        $lineTotal = $price * $qty;
        $subtotal += $lineTotal;
        $lines[] = [
            'product_id' => $pid,
            'quantity'   => $qty,
            'available'  => $available,
            'product'    => $p,
            'price'      => $price,
            'line_total' => $lineTotal,
            'problem'    => $problem,
        ];
    }
    return ['lines' => $lines, 'subtotal' => $subtotal, 'count' => array_sum($cart)];
}

/** Giỏ hàng dạng JSON cho ngăn kéo giỏ hàng (assets/js/app.js). */
function cart_json(): array
{
    $d = cart_details();
    $items = [];
    foreach ($d['lines'] as $line) {
        $p = $line['product'];
        $items[] = [
            'id'         => $line['product_id'],
            'name'       => $p['name'] ?? ('Sản phẩm #' . $line['product_id']),
            'image'      => product_image_url($p['image'] ?? null),
            'url'        => url('product.php?id=' . $line['product_id']),
            'quantity'   => $line['quantity'],
            'max'        => max(1, (int)($p['stock'] ?? 1)),
            'price_text' => format_price($line['price']),
            'line_text'  => format_price($line['line_total']),
            'available'  => $line['available'],
            'problem'    => $line['problem'],
        ];
    }
    $coupon = coupon_current($d['subtotal']);
    $discount = $coupon['ok'] ? $coupon['discount'] : 0.0;
    return [
        'items'         => $items,
        'count'         => $d['count'],
        'subtotal'      => $d['subtotal'],
        'subtotal_text' => format_price($d['subtotal']),
        'coupon'        => $coupon['code'] ? ['code' => $coupon['code'], 'ok' => $coupon['ok'], 'message' => $coupon['message']] : null,
        'discount_text' => format_price($discount),
        'total_text'    => format_price(max(0, $d['subtotal'] - $discount)),
    ];
}

/* ---------- Mã giảm giá ---------- */

function coupon_find(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '' || strlen($code) > 30) {
        return null;
    }
    $row = db_query('SELECT * FROM coupons WHERE code = ?', [$code])->fetch();
    return $row ?: null;
}

/** Kiểm tra mã với tạm tính. Trả về ['ok' => bool, 'discount' => float, 'message' => string]. */
function coupon_evaluate(?array $coupon, float $subtotal): array
{
    if (!$coupon || (int)$coupon['status'] !== 1) {
        return ['ok' => false, 'discount' => 0.0, 'message' => 'Mã giảm giá không tồn tại hoặc đã ngừng áp dụng.'];
    }
    $now = time();
    if ($coupon['starts_at'] !== null && strtotime($coupon['starts_at']) > $now) {
        return ['ok' => false, 'discount' => 0.0, 'message' => 'Mã giảm giá chưa đến thời gian áp dụng.'];
    }
    if ($coupon['ends_at'] !== null && strtotime($coupon['ends_at']) <= $now) {
        return ['ok' => false, 'discount' => 0.0, 'message' => 'Mã giảm giá đã hết hạn.'];
    }
    if ($coupon['usage_limit'] !== null && (int)$coupon['used_count'] >= (int)$coupon['usage_limit']) {
        return ['ok' => false, 'discount' => 0.0, 'message' => 'Mã giảm giá đã hết lượt sử dụng.'];
    }
    if ($subtotal < (float)$coupon['min_order']) {
        return ['ok' => false, 'discount' => 0.0, 'message' => 'Đơn hàng cần tối thiểu ' . format_price($coupon['min_order']) . ' để dùng mã này.'];
    }

    if ($coupon['type'] === 'percent') {
        $discount = round($subtotal * (float)$coupon['value'] / 100);
        if ($coupon['max_discount'] !== null && (float)$coupon['max_discount'] > 0) {
            $discount = min($discount, (float)$coupon['max_discount']);
        }
    } else {
        $discount = (float)$coupon['value'];
    }
    $discount = min($discount, $subtotal);
    return ['ok' => true, 'discount' => $discount, 'message' => 'Đã áp dụng mã, giảm ' . format_price($discount) . '.'];
}

/** Mã đang lưu trong phiên, kiểm tra lại với tạm tính hiện tại. */
function coupon_current(float $subtotal): array
{
    $code = $_SESSION['coupon_code'] ?? '';
    if (!is_string($code) || $code === '') {
        return ['code' => null, 'ok' => false, 'discount' => 0.0, 'message' => '', 'coupon' => null];
    }
    $coupon = coupon_find($code);
    return ['code' => $code, 'coupon' => $coupon] + coupon_evaluate($coupon, $subtotal);
}

/** Mô tả ngắn: "Giảm 10% (tối đa 50.000 ₫)" */
function coupon_summary(array $c): string
{
    $text = $c['type'] === 'percent'
        ? 'Giảm ' . (int)$c['value'] . '%' . ($c['max_discount'] ? ' (tối đa ' . format_price($c['max_discount']) . ')' : '')
        : 'Giảm ' . format_price($c['value']);
    if ((float)$c['min_order'] > 0) {
        $text .= ', đơn từ ' . format_price($c['min_order']);
    }
    return $text;
}

/* ---------- Đơn hàng ---------- */

/** Trạng thái đơn: khóa => [nhãn, màu badge, icon Phosphor]. */
function order_statuses(): array
{
    return [
        'pending'   => ['Chờ xác nhận', 'sun', 'ph-hourglass-medium'],
        'confirmed' => ['Đã xác nhận', 'sky', 'ph-seal-check'],
        'shipping'  => ['Đang giao', 'grape', 'ph-truck'],
        'completed' => ['Đã giao', 'mint', 'ph-gift'],
        'cancelled' => ['Đã hủy', 'ink', 'ph-x-circle'],
    ];
}

function order_status_badge(string $status): string
{
    [$label, $color, $icon] = order_statuses()[$status] ?? ['Không xác định', 'ink', 'ph-question'];
    return '<span class="badge badge-' . $color . '"><i class="ph-bold ' . $icon . '" aria-hidden="true"></i>' . e($label) . '</span>';
}

function payment_labels(): array
{
    return [
        'cod'           => 'Thanh toán khi nhận hàng (COD)',
        'bank_transfer' => 'Chuyển khoản ngân hàng (VietQR)',
    ];
}

/** Ghi 1 mốc vào dòng thời gian đơn hàng. */
function order_history_add(int $orderId, string $status): void
{
    db_query('INSERT INTO order_status_history (order_id, status) VALUES (?, ?)', [$orderId, $status]);
}

/** Các mốc đã qua: [status => thời điểm]. */
function order_history(int $orderId): array
{
    $rows = db_query(
        'SELECT status, created_at FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC, id ASC',
        [$orderId]
    )->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $map[$row['status']] = $row['created_at'];
    }
    return $map;
}

/** In dòng thời gian trạng thái đơn hàng (#8). */
function render_order_timeline(array $order): void
{
    $history = order_history((int)$order['id']);
    $statuses = order_statuses();
    $flow = ['pending', 'confirmed', 'shipping', 'completed'];
    $cancelled = $order['status'] === 'cancelled';
    if ($cancelled) {
        // Đơn hủy: hiện các mốc đã qua rồi tới mốc "Đã hủy".
        $flow = array_values(array_filter($flow, fn($s) => isset($history[$s])));
        $flow[] = 'cancelled';
    }
    $currentIndex = array_search($order['status'], $flow, true);
    $currentIndex = $currentIndex === false ? 0 : $currentIndex;
    $history += [$order['status'] => $history[$order['status']] ?? $order['created_at']];
    if (!isset($history['pending'])) {
        $history['pending'] = $order['created_at'];
    }
    $total = count($flow);
    $progress = $total > 1 ? ($currentIndex / ($total - 1)) * 100 : 100;
    ?>
    <ol class="relative grid gap-6 sm:gap-0" style="grid-template-columns: repeat(<?= $total ?>, minmax(0, 1fr));" data-timeline>
        <div class="pointer-events-none absolute top-6 hidden h-1.5 rounded-full bg-ink-100 sm:block dark:bg-ink-800" aria-hidden="true"
             style="left: calc(50% / <?= $total ?>); right: calc(50% / <?= $total ?>);">
            <div class="h-full rounded-full <?= $cancelled ? 'bg-ink-300' : 'bg-gradient-to-r from-sun-400 via-sky-400 to-mint-400' ?> origin-left" style="width: <?= round($progress, 2) ?>%" data-timeline-bar></div>
        </div>
        <?php foreach ($flow as $i => $status): ?>
            <?php
            [$label, $color, $icon] = $statuses[$status];
            $done = $i <= $currentIndex;
            $current = $i === $currentIndex;
            $time = $done && isset($history[$status]) ? date('H:i d/m', strtotime($history[$status])) : null;
            // Viết đủ tên class để Tailwind quét được (không ghép chuỗi).
            $doneDots = [
                'pending'   => 'bg-sun-400 text-ink-900 shadow-soft',
                'confirmed' => 'bg-sky-600 text-white shadow-pop',
                'shipping'  => 'bg-grape-500 text-white shadow-soft',
                'completed' => 'bg-mint-500 text-white shadow-soft',
                'cancelled' => 'bg-ink-500 text-white',
            ];
            $dot = $done ? $doneDots[$status] : 'bg-white text-muted ring-2 ring-ink-100 dark:bg-ink-900 dark:ring-ink-700';
            ?>
            <li class="relative z-10 flex items-center gap-4 sm:flex-col sm:gap-2 sm:text-center" data-timeline-step>
                <span class="grid size-12 shrink-0 place-items-center rounded-full text-2xl transition <?= $dot ?> <?= $current && !$cancelled && $status !== 'completed' ? 'animate-pulse' : '' ?>">
                    <i class="ph-fill <?= e($icon) ?>" aria-hidden="true"></i>
                </span>
                <span>
                    <span class="block font-bold <?= $done ? 'text-ink-800 dark:text-white' : 'text-muted dark:text-ink-600' ?>"><?= e($label) ?></span>
                    <span class="block text-xs text-muted"><?= $time ? e($time) : 'Đang chờ' ?></span>
                </span>
            </li>
        <?php endforeach; ?>
    </ol>
    <?php
}

/** Ảnh mã VietQR cho đơn chuyển khoản (#7). Nội dung chuyển khoản là mã đơn để đối soát. */
function vietqr_url(float $amount, string $orderCode): string
{
    return 'https://img.vietqr.io/image/' . rawurlencode(BANK_ID) . '-' . rawurlencode(BANK_ACCOUNT_NO) . '-compact2.png?'
        . http_build_query([
            'amount'      => (int)round($amount),
            'addInfo'     => 'KIDDOZONE ' . $orderCode,
            'accountName' => BANK_ACCOUNT_NAME,
        ]);
}

/** Khối thanh toán VietQR: hiện khi đơn chuyển khoản còn chờ xác nhận. */
function render_vietqr_box(array $order): void
{
    if (($order['payment_method'] ?? '') !== 'bank_transfer' || !in_array($order['status'], ['pending', 'confirmed'], true)) {
        return;
    }
    ?>
    <div class="card overflow-hidden p-0" data-reveal>
        <div class="grid gap-6 p-6 sm:grid-cols-[auto_1fr] sm:items-center">
            <div class="relative mx-auto w-52 rounded-3xl bg-white p-3 shadow-soft ring-4 ring-sky-100">
                <img src="<?= e(vietqr_url((float)$order['total_amount'], $order['code'])) ?>" alt="Mã VietQR thanh toán đơn <?= e($order['code']) ?>"
                     class="aspect-square w-full rounded-2xl object-contain" loading="lazy" width="208" height="208">
                <span class="absolute -top-3 -right-3 grid size-10 place-items-center rounded-full bg-sun-400 text-xl text-ink-900 shadow-chunky" aria-hidden="true"><i class="ph-fill ph-lightning" aria-hidden="true"></i></span>
            </div>
            <div>
                <span class="badge badge-sky mb-2"><i class="ph-bold ph-qr-code" aria-hidden="true"></i> Quét mã để thanh toán</span>
                <h3 class="text-xl font-semibold text-ink-900 dark:text-white">Chuyển khoản nhanh bằng VietQR</h3>
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-300">Mở app ngân hàng bất kỳ, quét mã. Số tiền và nội dung đã điền sẵn, bạn chỉ cần xác nhận.</p>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-2xl bg-ink-50 p-3 dark:bg-ink-800"><dt class="text-xs text-muted">Ngân hàng</dt><dd class="font-bold"><?= e(BANK_ID) ?></dd></div>
                    <div class="rounded-2xl bg-ink-50 p-3 dark:bg-ink-800"><dt class="text-xs text-muted">Số tài khoản</dt><dd class="font-bold"><?= e(BANK_ACCOUNT_NO) ?></dd></div>
                    <div class="rounded-2xl bg-ink-50 p-3 dark:bg-ink-800"><dt class="text-xs text-muted">Số tiền</dt><dd class="font-bold text-price"><?= e(format_price($order['total_amount'])) ?></dd></div>
                    <div class="rounded-2xl bg-ink-50 p-3 dark:bg-ink-800"><dt class="text-xs text-muted">Nội dung</dt><dd class="font-bold">KIDDOZONE <?= e($order['code']) ?></dd></div>
                </dl>
            </div>
        </div>
    </div>
    <?php
}

/* ---------- Form sản phẩm (Admin) ---------- */

/** Chuỗi từ ô datetime-local ("2026-11-22T14:30") -> "2026-11-22 14:30:00", rỗng/sai -> null. */
function parse_datetime_local(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value) ?: DateTime::createFromFormat('Y-m-d H:i:s', $value);
    return $dt ? $dt->format('Y-m-d H:i:s') : null;
}

/**
 * Đọc và kiểm tra độ tuổi (#3) và flash sale (#5) từ form sản phẩm.
 * Trả về ['error' => string|null, 'data' => [age_min, age_max, flash_sale_price, flash_sale_start, flash_sale_end]].
 */
function product_extra_input(array $post, int $price): array
{
    $opt = ['options' => ['min_range' => 0, 'max_range' => 12]];
    $ageMin = filter_var($post['age_min'] ?? 0, FILTER_VALIDATE_INT, $opt);
    $ageMax = filter_var($post['age_max'] ?? 12, FILTER_VALIDATE_INT, $opt);

    $flashRaw = preg_replace('/\D/', '', (string)($post['flash_sale_price'] ?? ''));
    $flashPrice = $flashRaw === '' ? null : (int)$flashRaw;
    $flashStart = parse_datetime_local($post['flash_sale_start'] ?? null);
    $flashEnd = parse_datetime_local($post['flash_sale_end'] ?? null);

    $data = [
        'age_min'          => $ageMin === false ? 0 : $ageMin,
        'age_max'          => $ageMax === false ? 12 : $ageMax,
        'flash_sale_price' => $flashPrice,
        'flash_sale_start' => $flashPrice === null ? null : $flashStart,
        'flash_sale_end'   => $flashPrice === null ? null : $flashEnd,
    ];

    $error = null;
    if ($ageMin === false || $ageMax === false || $ageMin > $ageMax) {
        $error = 'Độ tuổi phù hợp không hợp lệ (0 - 12 tuổi, "từ" không lớn hơn "đến").';
    } elseif ($flashPrice !== null) {
        if ($flashPrice <= 0 || $flashPrice >= $price) {
            $error = 'Giá flash sale phải lớn hơn 0 và nhỏ hơn giá bán.';
        } elseif ($flashStart === null || $flashEnd === null) {
            $error = 'Vui lòng nhập thời gian bắt đầu và kết thúc flash sale.';
        } elseif (strtotime($flashEnd) <= strtotime($flashStart)) {
            $error = 'Thời gian kết thúc flash sale phải sau thời gian bắt đầu.';
        }
    }
    return ['error' => $error, 'data' => $data];
}


/** Thanh 3 bước mua hàng: 1 Giỏ hàng, 2 Thanh toán, 3 Hoàn tất. $current: bước đang ở. */
function render_checkout_steps(int $current): void
{
    $steps = [1 => ['Giỏ hàng', 'ph-shopping-cart-simple'], 2 => ['Thanh toán', 'ph-credit-card'], 3 => ['Hoàn tất', 'ph-gift']];
    ?>
    <ol class="flex items-center gap-1.5 text-xs font-extrabold sm:gap-2" aria-label="Các bước mua hàng">
        <?php foreach ($steps as $n => [$label, $icon]): ?>
            <?php $done = $n < $current; $on = $n === $current; ?>
            <?php if ($n > 1): ?>
                <li aria-hidden="true" class="relative h-1 w-6 overflow-hidden rounded-full bg-ink-200 sm:w-10 dark:bg-ink-700">
                    <span class="absolute inset-y-0 left-0 rounded-full bg-mint-500 <?= $n <= $current ? 'w-full' : 'w-0' ?>" <?= $n === $current ? 'data-step-fill' : '' ?>></span>
                </li>
            <?php endif; ?>
            <li class="flex items-center gap-1.5 <?= $done ? 'text-ok' : ($on ? 'text-sky-600 dark:text-sky-300' : 'text-muted') ?>" <?= $on ? 'aria-current="step"' : '' ?>>
                <span class="grid size-8 place-items-center rounded-full text-sm <?= $done ? 'bg-mint-500 text-white' : ($on ? 'bg-sky-600 text-white shadow-pop ring-4 ring-sky-100 dark:ring-sky-500/20' : 'bg-ink-100 dark:bg-ink-800') ?>">
                    <i class="<?= $done ? 'ph-bold ph-check' : 'ph-fill ' . $icon ?>" aria-hidden="true"></i>
                </span>
                <span class="<?= $on ? '' : 'max-sm:sr-only' ?>"><?= $label ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
    <?php
}

/** Khối "không tìm thấy" có linh vật (trang 404, sản phẩm không tồn tại). */
function render_not_found(string $title, string $message): void
{
    ?>
    <section class="relative mx-auto flex max-w-2xl flex-col items-center px-4 py-10 text-center sm:py-16">
        <div class="relative">
            <span class="absolute inset-0 -z-10 scale-125 rounded-full bg-sun-200/70 blur-2xl dark:bg-sun-500/15" aria-hidden="true"></span>
            <p class="flex items-center justify-center font-display text-[7rem] leading-none font-bold text-ink-900 sm:text-[9rem] dark:text-white" aria-hidden="true">
                4<span class="mx-1 inline-block animate-float"><?= mascot_svg('size-28 sm:size-36', 'sad') ?></span>4
            </p>
        </div>
        <h1 class="mt-6 font-display text-3xl font-bold sm:text-4xl"><?= e($title) ?></h1>
        <p class="mt-2 max-w-md text-muted"><?= e($message) ?></p>
        <form class="relative mt-8 w-full max-w-md" action="<?= e(url('products.php')) ?>" method="get" role="search">
            <label for="nf-search" class="sr-only">Tìm đồ chơi</label>
            <i class="ph-bold ph-magnifying-glass pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-lg text-muted" aria-hidden="true"></i>
            <input id="nf-search" type="search" name="q" maxlength="100" class="input rounded-full! py-3.5! pr-32 pl-11" placeholder="Bé đang tìm món gì?">
            <button type="submit" class="btn btn-primary absolute top-1/2 right-1.5 -translate-y-1/2">Tìm</button>
        </form>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
            <a class="btn btn-sun" href="<?= e(url('index.php')) ?>"><i class="ph-bold ph-house" aria-hidden="true"></i> Về trang chủ</a>
            <a class="btn btn-ghost" href="<?= e(url('products.php')) ?>"><i class="ph-bold ph-storefront" aria-hidden="true"></i> Xem tất cả đồ chơi</a>
        </div>
    </section>
    <?php
}

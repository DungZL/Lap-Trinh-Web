<?php
// Đặt hàng (F07): kiểm tra đăng nhập, form giao hàng, áp mã giảm giá (#6), tạo đơn trong transaction,
// ghi dòng thời gian (#8), màn hình thành công có mã VietQR (#7).
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product_card.php';

require_customer();

$userId = (int)current_user()['id'];
if (session_cart()) {
    merge_session_cart($userId);
}

$paymentLabels = payment_labels();

/* ---------- Màn hình đặt hàng thành công (05-sequence-dat-hang.drawio bước 13-14) ---------- */
$successCode = isset($_GET['code']) && is_string($_GET['code']) ? trim($_GET['code']) : '';
if ($successCode !== '') {
    $order = db_query(
        'SELECT id, code, receiver_name, receiver_phone, shipping_address, note, payment_method, coupon_code, discount_amount,
                total_amount, status, created_at
         FROM orders WHERE code = ? AND user_id = ?',
        [$successCode, $userId]
    )->fetch();

    if ($order) {
        $orderItems = db_query('SELECT product_name, price, quantity FROM order_items WHERE order_id = ? ORDER BY id', [(int)$order['id']])->fetchAll();

        $pageTitle = 'Đặt hàng thành công';
        require __DIR__ . '/includes/header.php';
        ?>
        <div class="mx-auto max-w-4xl space-y-6" data-celebrate>
            <div class="flex justify-center"><?php render_checkout_steps(3); ?></div>
            <div class="card relative overflow-hidden p-8 text-center sm:p-12">
                <div class="bg-dots absolute inset-0 opacity-60"></div>
                <div class="relative">
                    <div class="mx-auto grid size-24 animate-pop place-items-center rounded-full bg-mint-500 text-5xl text-white shadow-[0_0_0_12px_var(--color-mint-100)] dark:shadow-[0_0_0_12px_rgb(46_204_154/0.15)]">
                        <i class="ph-bold ph-check" aria-hidden="true"></i>
                    </div>
                    <h1 class="mt-6 font-display text-4xl font-bold">Đặt hàng thành công!</h1>
                    <p class="mx-auto mt-2 max-w-md text-ink-500 dark:text-ink-300">Cảm ơn bạn đã mua sắm tại KiddoZone. Chúng tôi sẽ sớm xác nhận và giao quà tới bé.</p>
                    <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-sky-50 px-5 py-2 font-extrabold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                        Mã đơn hàng: <span class="font-display text-xl"><?= e($order['code']) ?></span>
                    </p>
                </div>
            </div>

            <div class="card p-6 sm:p-8"><?php render_order_timeline($order); ?></div>

            <?php render_vietqr_box($order); ?>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="card p-6">
                    <h2 class="mb-4 font-display text-xl font-semibold">Giao tới</h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex gap-3"><i class="ph-fill ph-user-circle text-xl text-sky-600" aria-hidden="true"></i><div><dt class="text-xs text-muted">Người nhận</dt><dd class="font-bold"><?= e($order['receiver_name']) ?> · <?= e($order['receiver_phone']) ?></dd></div></div>
                        <div class="flex gap-3"><i class="ph-fill ph-map-pin text-xl text-price" aria-hidden="true"></i><div><dt class="text-xs text-muted">Địa chỉ</dt><dd class="font-bold"><?= e($order['shipping_address']) ?></dd></div></div>
                        <div class="flex gap-3"><i class="ph-fill ph-wallet text-xl text-ok" aria-hidden="true"></i><div><dt class="text-xs text-muted">Thanh toán</dt><dd class="font-bold"><?= e($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?></dd></div></div>
                        <?php if (!empty($order['note'])): ?>
                            <div class="flex gap-3"><i class="ph-fill ph-note text-xl text-sun-500" aria-hidden="true"></i><div><dt class="text-xs text-muted">Ghi chú</dt><dd class="font-bold"><?= e($order['note']) ?></dd></div></div>
                        <?php endif; ?>
                    </dl>
                </div>
                <div class="card p-6">
                    <h2 class="mb-4 font-display text-xl font-semibold">Sản phẩm</h2>
                    <ul class="space-y-2 text-sm">
                        <?php foreach ($orderItems as $item): ?>
                            <li class="flex justify-between gap-3"><span><?= e($item['product_name']) ?> <span class="text-muted">× <?= (int)$item['quantity'] ?></span></span><b><?= e(format_price((float)$item['price'] * (int)$item['quantity'])) ?></b></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ((float)$order['discount_amount'] > 0): ?>
                        <p class="mt-3 flex justify-between border-t border-dashed border-ink-200 pt-3 text-sm text-ok dark:border-ink-700 dark:text-mint-300"><span>Mã <?= e($order['coupon_code']) ?></span><b>-<?= e(format_price($order['discount_amount'])) ?></b></p>
                    <?php endif; ?>
                    <p class="mt-3 flex items-center justify-between border-t border-ink-100 pt-3 dark:border-ink-800"><span class="font-extrabold">Tổng thanh toán</span><span class="font-display text-2xl font-bold text-price"><?= e(format_price($order['total_amount'])) ?></span></p>
                </div>
            </div>

            <div class="flex flex-wrap justify-center gap-3">
                <a class="btn btn-primary btn-lg" href="<?= e(url('account/orders.php?id=' . (int)$order['id'])) ?>"><i class="ph-bold ph-package" aria-hidden="true"></i> Theo dõi đơn hàng</a>
                <a class="btn btn-ghost btn-lg" href="<?= e(url('products.php')) ?>">Tiếp tục mua sắm</a>
            </div>
        </div>
        <?php
        require __DIR__ . '/includes/footer.php';
        exit;
    }

    flash('warning', 'Không tìm thấy đơn hàng.');
    redirect('account/orders.php');
}

/* ---------- Đọc giỏ hàng từ CSDL (05-sequence-dat-hang.drawio bước 4-5) ---------- */
$cartRows = db_query(
    'SELECT ca.id AS cart_id, ci.product_id, ci.quantity, p.name, ' . product_price_columns() . ', p.stock, p.status,
            c.status AS category_status,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image_path
     FROM cart_items ci
     JOIN carts ca ON ca.id = ci.cart_id
     LEFT JOIN products p ON p.id = ci.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE ca.user_id = ?
     ORDER BY ci.id ASC',
    [$userId]
)->fetchAll();

if (!$cartRows) {
    flash('warning', 'Giỏ hàng đang trống, vui lòng chọn sản phẩm trước khi thanh toán.');
    redirect('cart.php');
}

$cartId = (int)$cartRows[0]['cart_id'];
$userProfile = db_query('SELECT full_name, phone, address FROM users WHERE id = ?', [$userId])->fetch() ?: [];

$receiverName    = (string)($userProfile['full_name'] ?? '');
$receiverPhone   = (string)($userProfile['phone'] ?? '');
$shippingAddress = (string)($userProfile['address'] ?? '');
$paymentMethod   = 'cod';
$note            = '';
$errors          = [];

if (is_post()) {
    csrf_verify();

    $receiverName    = trim((string)($_POST['receiver_name'] ?? ''));
    $receiverPhone   = trim((string)($_POST['receiver_phone'] ?? ''));
    $shippingAddress = trim((string)($_POST['shipping_address'] ?? ''));
    $paymentMethod   = (string)($_POST['payment_method'] ?? '');
    $note            = trim((string)($_POST['note'] ?? ''));

    if ($receiverName === '' || mb_strlen($receiverName) > 100) {
        $errors[] = 'Họ tên người nhận không được để trống (tối đa 100 ký tự).';
    }
    if (!preg_match('/^[0-9+\s.-]{8,20}$/', $receiverPhone)) {
        $errors[] = 'Số điện thoại người nhận không hợp lệ.';
    }
    if ($shippingAddress === '' || mb_strlen($shippingAddress) > 255) {
        $errors[] = 'Địa chỉ giao hàng không được để trống (tối đa 255 ký tự).';
    }
    if (!array_key_exists($paymentMethod, $paymentLabels)) {
        $errors[] = 'Phương thức thanh toán không hợp lệ.';
    }
    if (mb_strlen($note) > 500) {
        $errors[] = 'Ghi chú tối đa 500 ký tự.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lockedItems = [];
            $subtotal = 0.0;

            foreach ($cartRows as $row) {
                $pid = (int)$row['product_id'];
                $qty = (int)$row['quantity'];

                $locked = db_query(
                    'SELECT p.id, p.name, ' . product_price_columns() . ', p.stock, p.status, c.status AS category_status
                     FROM products p
                     JOIN categories c ON c.id = p.category_id
                     WHERE p.id = ?
                     FOR UPDATE',
                    [$pid]
                )->fetch();

                if (!$locked || (int)$locked['status'] !== 1 || (int)$locked['category_status'] !== 1 || $qty <= 0 || (int)$locked['stock'] < $qty) {
                    $pdo->rollBack();
                    $prodName = $locked['name'] ?? ($row['name'] ?? ('#' . $pid));
                    flash('danger', 'Sản phẩm "' . $prodName . '" không đủ số lượng tồn kho. Vui lòng kiểm tra lại giỏ hàng.');
                    redirect('cart.php');
                }

                $unitPrice = effective_price($locked);
                $subtotal += $unitPrice * $qty;
                $lockedItems[] = [
                    'product_id'   => (int)$locked['id'],
                    'product_name' => $locked['name'],
                    'price'        => $unitPrice,
                    'quantity'     => $qty,
                ];
            }

            // Mã giảm giá: khóa dòng mã rồi kiểm tra lại với tạm tính thật (tránh vượt số lượt khi đặt cùng lúc).
            $couponCode = null;
            $discount = 0.0;
            $sessionCode = $_SESSION['coupon_code'] ?? '';
            if (is_string($sessionCode) && $sessionCode !== '') {
                $couponRow = db_query('SELECT * FROM coupons WHERE code = ? FOR UPDATE', [$sessionCode])->fetch() ?: null;
                $check = coupon_evaluate($couponRow, $subtotal);
                if (!$check['ok']) {
                    $pdo->rollBack();
                    unset($_SESSION['coupon_code']);
                    flash('warning', 'Mã ' . $sessionCode . ' không còn dùng được: ' . $check['message']);
                    redirect('checkout.php');
                }
                $couponCode = $couponRow['code'];
                $discount = $check['discount'];
                db_query('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [(int)$couponRow['id']]);
            }
            $totalAmount = max(0, $subtotal - $discount);

            $tempCode = 'TMP' . bin2hex(random_bytes(5));
            db_query(
                'INSERT INTO orders (code, user_id, receiver_name, receiver_phone, shipping_address, note, payment_method,
                                     coupon_code, discount_amount, total_amount, status, admin_seen)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", 0)',
                [$tempCode, $userId, $receiverName, $receiverPhone, $shippingAddress, $note !== '' ? $note : null,
                 $paymentMethod, $couponCode, $discount, $totalAmount]
            );

            $orderId   = (int)$pdo->lastInsertId();
            $orderCode = sprintf('DH%04d', $orderId);
            db_query('UPDATE orders SET code = ? WHERE id = ?', [$orderCode, $orderId]);
            order_history_add($orderId, 'pending');

            foreach ($lockedItems as $item) {
                db_query(
                    'INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)',
                    [$orderId, $item['product_id'], $item['product_name'], $item['price'], $item['quantity']]
                );
                db_query('UPDATE products SET stock = stock - ? WHERE id = ?', [$item['quantity'], $item['product_id']]);
            }

            db_query('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
            unset($_SESSION['cart'], $_SESSION['coupon_code']);

            $pdo->commit();

            redirect('checkout.php?code=' . urlencode($orderCode));
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }
    }
}

/* ---------- Tính tóm tắt để hiển thị ---------- */
$summaryTotal = 0.0;
foreach ($cartRows as &$row) {
    $row['available'] = $row['name'] !== null && (int)$row['status'] === 1 && (int)$row['category_status'] === 1;
    $row['unit'] = $row['available'] ? effective_price($row) : 0.0;
    $summaryTotal += $row['unit'] * (int)$row['quantity'];
}
unset($row);
$coupon = coupon_current($summaryTotal);
$discount = $coupon['ok'] ? $coupon['discount'] : 0.0;

$pageTitle = 'Thanh toán';
require __DIR__ . '/includes/header.php';
$fallbackImg = url('assets/img/placeholder.svg');
?>

<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <h1 class="font-display text-4xl font-bold text-ink-900 dark:text-white">Thanh toán</h1>
    <?php render_checkout_steps(2); ?>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger mb-6" role="alert">
        <i class="ph-fill ph-warning-circle text-lg" aria-hidden="true"></i>
        <ul class="list-disc pl-4"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('checkout.php')) ?>" id="checkoutForm" class="grid gap-8 lg:grid-cols-[1fr_26rem]" x-data="{ pay: '<?= e($paymentMethod) ?>', sending: false }" @submit="sending = true">
    <?= csrf_field() ?>
    <div class="space-y-6">
        <section class="card p-6 sm:p-8">
            <h2 class="mb-5 flex items-center gap-3 font-display text-xl font-semibold"><span class="grid size-9 place-items-center rounded-xl bg-sky-100 text-sky-600 dark:bg-sky-500/15"><i class="ph-fill ph-map-pin" aria-hidden="true"></i></span> Thông tin giao hàng</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="receiver_name">Họ tên người nhận</label>
                    <input type="text" id="receiver_name" name="receiver_name" class="input" maxlength="100" value="<?= e($receiverName) ?>" required autocomplete="name">
                </div>
                <div>
                    <label class="label" for="receiver_phone">Số điện thoại</label>
                    <input type="tel" id="receiver_phone" name="receiver_phone" class="input" maxlength="20" value="<?= e($receiverPhone) ?>" required autocomplete="tel">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="shipping_address">Địa chỉ giao hàng</label>
                    <input type="text" id="shipping_address" name="shipping_address" class="input" maxlength="255" value="<?= e($shippingAddress) ?>" required autocomplete="street-address" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="note">Ghi chú đơn hàng (tùy chọn)</label>
                    <textarea id="note" name="note" class="textarea" rows="2" maxlength="500" placeholder="Ví dụ: Giao giờ hành chính, gói quà giúp mình..."><?= e($note) ?></textarea>
                </div>
            </div>
        </section>

        <section class="card p-6 sm:p-8">
            <h2 class="mb-5 flex items-center gap-3 font-display text-xl font-semibold"><span class="grid size-9 place-items-center rounded-xl bg-mint-100 text-ok dark:bg-mint-500/15"><i class="ph-fill ph-wallet" aria-hidden="true"></i></span> Phương thức thanh toán</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <?php foreach (['cod' => ['ph-hand-coins', 'Trả tiền khi nhận hàng'], 'bank_transfer' => ['ph-qr-code', 'Quét mã VietQR sau khi đặt']] as $key => [$icon, $sub]): ?>
                    <label class="relative flex cursor-pointer items-center gap-4 rounded-3xl p-4 ring-2 transition"
                           :class="pay === '<?= $key ?>' ? 'bg-sky-50 ring-sky-400 dark:bg-sky-500/10' : 'ring-ink-100 hover:ring-sky-200 dark:ring-ink-700'">
                        <input class="sr-only" type="radio" name="payment_method" value="<?= e($key) ?>" x-model="pay" <?= $paymentMethod === $key ? 'checked' : '' ?>>
                        <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white text-2xl text-sky-600 shadow-soft dark:bg-ink-800"><i class="ph-fill <?= $icon ?>" aria-hidden="true"></i></span>
                        <span><b class="block"><?= e($paymentLabels[$key]) ?></b><span class="text-xs text-muted"><?= e($sub) ?></span></span>
                        <i class="ph-fill ph-check-circle absolute top-3 right-3 text-xl text-sky-600" aria-hidden="true" x-show="pay === '<?= $key ?>'" x-transition.scale></i>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="mt-4 flex items-start gap-2 rounded-2xl bg-sky-50 p-3 text-sm text-sky-700 dark:bg-sky-500/10 dark:text-sky-300" x-show="pay === 'bank_transfer'" x-transition x-cloak>
                <i class="ph-fill ph-info mt-0.5" aria-hidden="true"></i> Sau khi đặt hàng, bạn sẽ nhận được mã QR có sẵn số tiền và nội dung chuyển khoản. Chỉ cần mở app ngân hàng và quét.
            </p>
        </section>
    </div>

    <aside class="lg:sticky lg:top-40 lg:self-start">
        <div class="card p-6">
            <h2 class="font-display text-xl font-semibold">Đơn hàng (<?= array_sum(array_map(fn($r) => (int)$r['quantity'], $cartRows)) ?> sản phẩm)</h2>
            <ul class="mt-4 max-h-80 space-y-3 overflow-y-auto pr-1">
                <?php foreach ($cartRows as $row): ?>
                    <?php $qty = (int)$row['quantity']; ?>
                    <li class="flex items-center gap-3">
                        <span class="relative shrink-0">
                            <img src="<?= e(product_image_url($row['image_path'] ?? null)) ?>" alt="" class="size-14 rounded-2xl bg-ink-50 object-cover dark:bg-ink-800" data-fallback="<?= e($fallbackImg) ?>">
                            <span class="absolute -top-1.5 -right-1.5 grid size-5 place-items-center rounded-full bg-ink-800 text-[11px] font-extrabold text-white dark:bg-white dark:text-ink-900"><?= $qty ?></span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold"><?= e($row['name'] ?? ('Sản phẩm #' . (int)$row['product_id'])) ?></p>
                            <?php if (!$row['available'] || (int)$row['stock'] < $qty): ?>
                                <p class="text-xs text-price">Không đủ tồn kho (còn <?= (int)($row['stock'] ?? 0) ?>)</p>
                            <?php else: ?>
                                <p class="text-xs text-muted"><?= e(format_price($row['unit'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="text-sm font-extrabold"><?= e(format_price($row['unit'] * $qty)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <dl class="mt-5 space-y-2 border-t border-dashed border-ink-200 pt-4 text-sm dark:border-ink-700">
                <div class="flex justify-between"><dt class="text-ink-500">Tạm tính</dt><dd class="font-bold"><?= e(format_price($summaryTotal)) ?></dd></div>
                <?php if ($discount > 0): ?>
                    <div class="flex justify-between text-ok dark:text-mint-300"><dt><i class="ph-fill ph-ticket" aria-hidden="true"></i> Mã <?= e($coupon['code']) ?></dt><dd class="font-bold">-<?= e(format_price($discount)) ?></dd></div>
                <?php elseif ($coupon['code']): ?>
                    <div class="text-xs font-bold text-price">Mã <?= e($coupon['code']) ?>: <?= e($coupon['message']) ?></div>
                <?php else: ?>
                    <div class="text-xs text-muted">Có mã giảm giá? <a class="link" href="<?= e(url('cart.php')) ?>">Nhập ở giỏ hàng</a></div>
                <?php endif; ?>
                <div class="flex items-center justify-between pt-2"><dt class="font-extrabold">Tổng thanh toán</dt><dd class="font-display text-3xl font-bold text-price"><?= e(format_price(max(0, $summaryTotal - $discount))) ?></dd></div>
            </dl>

            <button type="submit" class="btn btn-sun btn-lg mt-5 w-full" :disabled="sending">
                <i class="ph-bold" aria-hidden="true" :class="sending ? 'ph-spinner-gap animate-spin' : 'ph-check-circle'"></i>
                <span x-text="sending ? 'Đang đặt hàng...' : 'Đặt hàng'">Đặt hàng</span>
            </button>
            <a href="<?= e(url('cart.php')) ?>" class="btn btn-ghost mt-2 w-full">Quay lại giỏ hàng</a>
            <p class="mt-4 flex items-center justify-center gap-1.5 text-xs text-muted"><i class="ph-fill ph-lock-key" aria-hidden="true"></i> Thông tin của bạn được bảo mật</p>
        </div>
    </aside>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>

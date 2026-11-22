<?php
// Lịch sử đơn hàng (F08): danh sách, chi tiết với dòng thời gian (#8), mã VietQR (#7), hủy đơn chờ xác nhận.
require_once __DIR__ . '/../includes/init.php';
require_customer();

$userId = (int)current_user()['id'];
$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$showOrder = array_key_exists('id', $_GET);

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $cancelOrderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($action !== 'cancel' || $cancelOrderId === false) {
        flash('danger', 'Yêu cầu không hợp lệ.');
        redirect('account/orders.php');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pendingOrder = db_query(
            "SELECT id FROM orders WHERE id = ? AND user_id = ? AND status = 'pending' FOR UPDATE",
            [$cancelOrderId, $userId]
        )->fetch();

        if (!$pendingOrder) {
            $pdo->rollBack();
            flash('warning', 'Không thể hủy đơn hàng này. Đơn có thể không tồn tại hoặc không còn chờ xác nhận.');
            redirect('account/orders.php');
        }

        $items = db_query(
            'SELECT product_id, SUM(quantity) AS quantity
             FROM order_items
             WHERE order_id = ? AND product_id IS NOT NULL
             GROUP BY product_id',
            [$cancelOrderId]
        )->fetchAll();
        foreach ($items as $item) {
            db_query('UPDATE products SET stock = stock + ? WHERE id = ?', [(int)$item['quantity'], (int)$item['product_id']]);
        }

        db_query(
            "UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'",
            [$cancelOrderId, $userId]
        );
        order_history_add($cancelOrderId, 'cancelled');
        $pdo->commit();
        flash('success', 'Đã hủy đơn hàng và hoàn lại tồn kho.');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    redirect('account/orders.php?id=' . (int)$cancelOrderId);
}

$paymentLabels = payment_labels();

$pageTitle = 'Lịch sử đơn hàng';
if ($showOrder) {
    $order = $orderId === false ? null : db_query(
        'SELECT id, code, receiver_name, receiver_phone, shipping_address, note, payment_method, coupon_code, discount_amount,
                total_amount, status, created_at
         FROM orders
         WHERE id = ? AND user_id = ?',
        [$orderId, $userId]
    )->fetch();
    if (!$order) {
        http_response_code(404);
    } else {
        $items = db_query(
            'SELECT oi.product_id, oi.product_name, oi.price, oi.quantity,
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = oi.product_id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image
             FROM order_items oi WHERE oi.order_id = ? ORDER BY oi.id',
            [$orderId]
        )->fetchAll();
        $pageTitle = 'Chi tiết đơn hàng ' . $order['code'];
    }
} else {
    $totalOrders = (int)db_query('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$userId])->fetchColumn();
    [$offset, $pages, $page] = paginate($totalOrders, ITEMS_PER_PAGE_SHOP);
    $orders = db_query(
        'SELECT o.id, o.code, o.total_amount, o.status, o.created_at,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                (SELECT oi.product_name FROM order_items oi WHERE oi.order_id = o.id ORDER BY oi.id LIMIT 1) AS first_item
         FROM orders o
         WHERE o.user_id = ?
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT ' . (int)ITEMS_PER_PAGE_SHOP . ' OFFSET ' . (int)$offset,
        [$userId]
    )->fetchAll();
}

$accountTab = 'orders';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/account_nav.php';
?>
<?php if ($showOrder): ?>
    <?php if (!$order): ?>
        <div class="card flex flex-col items-center px-6 py-14 text-center">
            <?= empty_icon('ph-receipt', 'sky') ?>
            <h2 class="mt-3 text-2xl font-semibold">Không tìm thấy đơn hàng</h2>
            <a class="btn btn-ghost mt-5" href="<?= e(url('account/orders.php')) ?>"><i class="ph-bold ph-arrow-left" aria-hidden="true"></i> Quay lại lịch sử đơn hàng</a>
        </div>
    <?php else: ?>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <a class="text-sm font-bold text-muted hover:text-sky-600" href="<?= e(url('account/orders.php')) ?>"><i class="ph-bold ph-arrow-left" aria-hidden="true"></i> Tất cả đơn hàng</a>
                <h2 class="mt-1 flex flex-wrap items-center gap-3 font-display text-3xl font-bold">Đơn <?= e($order['code']) ?> <?= order_status_badge($order['status']) ?></h2>
                <p class="text-sm text-muted">Đặt lúc <?= e(date('H:i, d/m/Y', strtotime($order['created_at']))) ?></p>
            </div>
            <?php if ($order['status'] === 'pending'): ?>
                <form method="post" action="<?= e(url('account/orders.php?id=' . (int)$order['id'])) ?>" onsubmit="return confirm('Bạn có chắc muốn hủy đơn hàng này?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                    <button class="btn btn-danger-ghost" type="submit"><i class="ph-bold ph-x-circle" aria-hidden="true"></i> Hủy đơn hàng</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="space-y-6">
            <section class="card p-6 sm:p-8">
                <h3 class="mb-6 font-display text-xl font-semibold">Hành trình đơn hàng</h3>
                <?php render_order_timeline($order); ?>
            </section>

            <?php render_vietqr_box($order); ?>

            <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                <section class="card p-6">
                    <h3 class="mb-4 font-display text-xl font-semibold">Sản phẩm</h3>
                    <ul class="divide-y divide-ink-100 dark:divide-ink-800">
                        <?php foreach ($items as $item): ?>
                            <li class="flex items-center gap-4 py-3">
                                <img src="<?= e(product_image_url($item['image'])) ?>" alt="" class="size-16 rounded-2xl bg-ink-50 object-cover dark:bg-ink-800" data-fallback="<?= e(url('assets/img/placeholder.svg')) ?>">
                                <div class="min-w-0 flex-1">
                                    <?php if ($item['product_id']): ?>
                                        <a class="font-bold hover:text-sky-600" href="<?= e(url('product.php?id=' . (int)$item['product_id'])) ?>"><?= e($item['product_name']) ?></a>
                                    <?php else: ?>
                                        <span class="font-bold"><?= e($item['product_name']) ?></span>
                                    <?php endif; ?>
                                    <p class="text-sm text-muted"><?= e(format_price($item['price'])) ?> × <?= (int)$item['quantity'] ?></p>
                                </div>
                                <span class="font-extrabold"><?= e(format_price((float)$item['price'] * (int)$item['quantity'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ((float)$order['discount_amount'] > 0): ?>
                        <p class="flex justify-between border-t border-dashed border-ink-200 pt-3 text-sm text-ok dark:border-ink-700 dark:text-mint-300"><span><i class="ph-fill ph-ticket" aria-hidden="true"></i> Mã <?= e($order['coupon_code']) ?></span><b>-<?= e(format_price($order['discount_amount'])) ?></b></p>
                    <?php endif; ?>
                    <p class="mt-3 flex items-center justify-between border-t border-ink-100 pt-3 dark:border-ink-800"><span class="font-extrabold">Tổng cộng</span><span class="font-display text-2xl font-bold text-price"><?= e(format_price($order['total_amount'])) ?></span></p>
                </section>

                <section class="card p-6">
                    <h3 class="mb-4 font-display text-xl font-semibold">Thông tin giao hàng</h3>
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-xs text-muted">Người nhận</dt><dd class="font-bold"><?= e($order['receiver_name']) ?></dd></div>
                        <div><dt class="text-xs text-muted">Số điện thoại</dt><dd class="font-bold"><?= e($order['receiver_phone']) ?></dd></div>
                        <div><dt class="text-xs text-muted">Địa chỉ</dt><dd class="font-bold"><?= e($order['shipping_address']) ?></dd></div>
                        <div><dt class="text-xs text-muted">Thanh toán</dt><dd class="font-bold"><?= e($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?></dd></div>
                        <?php if ($order['note']): ?>
                            <div><dt class="text-xs text-muted">Ghi chú</dt><dd class="font-bold"><?= e($order['note']) ?></dd></div>
                        <?php endif; ?>
                    </dl>
                </section>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php if (!$orders): ?>
        <div class="card flex flex-col items-center px-6 py-16 text-center">
            <?= empty_icon('ph-package', 'sun') ?>
            <h2 class="mt-4 text-2xl font-semibold">Bạn chưa có đơn hàng nào</h2>
            <a class="btn btn-primary mt-6" href="<?= e(url('products.php')) ?>">Bắt đầu mua sắm</a>
        </div>
    <?php else: ?>
        <ul class="space-y-3">
            <?php foreach ($orders as $o): ?>
                <li data-reveal>
                    <a href="<?= e(url('account/orders.php?id=' . (int)$o['id'])) ?>" class="card group flex flex-wrap items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-pop">
                        <span class="grid size-14 place-items-center rounded-2xl bg-sun-100 text-2xl text-sun-800 dark:bg-sun-500/10"><i class="ph-fill ph-package" aria-hidden="true"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 font-display text-lg font-semibold"><?= e($o['code']) ?> <?= order_status_badge($o['status']) ?></p>
                            <p class="truncate text-sm text-muted"><?= e($o['first_item'] ?? '') ?><?= (int)$o['item_count'] > 1 ? ' và ' . ((int)$o['item_count'] - 1) . ' sản phẩm khác' : '' ?> · <?= e(date('d/m/Y', strtotime($o['created_at']))) ?></p>
                        </div>
                        <span class="font-display text-xl font-bold"><?= e(format_price($o['total_amount'])) ?></span>
                        <i class="ph-bold ph-caret-right text-muted transition group-hover:translate-x-1" aria-hidden="true"></i>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= render_pagination($page, $pages) ?>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

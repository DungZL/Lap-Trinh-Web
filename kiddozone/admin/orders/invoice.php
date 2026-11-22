<?php
// Hóa đơn in được (#12): mở trang, bấm "In hóa đơn" hoặc Ctrl+P (có thể lưu thành PDF).
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$order = $id === false ? false : db_query(
    'SELECT o.*, u.email FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?',
    [$id]
)->fetch();
if (!$order) {
    flash('warning', 'Không tìm thấy đơn hàng.');
    redirect('admin/orders.php');
}
$items = db_query('SELECT product_name, price, quantity FROM order_items WHERE order_id = ? ORDER BY id', [$id])->fetchAll();
$subtotal = array_sum(array_map(fn($i) => (float)$i['price'] * (int)$i['quantity'], $items));

$pageTitle = 'Hóa đơn ' . $order['code'];
$titleSuffix = 'KiddoZone';
require __DIR__ . '/../../includes/head.php';
?>
<body class="bg-ink-100 py-8 print:bg-white print:py-0">
    <div class="no-print mx-auto mb-4 flex max-w-3xl justify-between px-4">
        <a href="<?= e(url('admin/orders.php?id=' . (int)$order['id'])) ?>" class="btn btn-ghost"><i class="ph-bold ph-arrow-left" aria-hidden="true"></i> Quay lại đơn hàng</a>
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="ph-bold ph-printer" aria-hidden="true"></i> In hóa đơn</button>
    </div>

    <article class="mx-auto max-w-3xl rounded-4xl bg-white p-10 text-ink-800 shadow-soft print:rounded-none print:p-0 print:shadow-none">
        <header class="flex items-start justify-between border-b-4 border-sun-400 pb-6">
            <div>
                <p class="flex items-center gap-2 font-display text-3xl font-bold"><?= mascot_svg('size-10') ?><span class="text-sky-600">Kiddo</span><span class="-ml-2 text-coral-600">Zone</span></p>
                <p class="mt-1 text-sm text-ink-500">Đồ chơi trẻ em · TP. Hồ Chí Minh · 0900 000 000 · hello@kiddozone.vn</p>
            </div>
            <div class="text-right">
                <p class="font-display text-2xl font-bold">HÓA ĐƠN</p>
                <p class="font-bold"><?= e($order['code']) ?></p>
                <p class="text-sm text-ink-500"><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></p>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-6 py-6 text-sm">
            <div>
                <p class="mb-1 text-xs font-extrabold tracking-wider text-ink-400 uppercase">Giao tới</p>
                <p class="font-bold"><?= e($order['receiver_name']) ?></p>
                <p><?= e($order['receiver_phone']) ?></p>
                <p><?= e($order['shipping_address']) ?></p>
            </div>
            <div class="text-right">
                <p class="mb-1 text-xs font-extrabold tracking-wider text-ink-400 uppercase">Thanh toán</p>
                <p class="font-bold"><?= e(payment_labels()[$order['payment_method']] ?? $order['payment_method']) ?></p>
                <p>Trạng thái: <?= e(order_statuses()[$order['status']][0] ?? $order['status']) ?></p>
                <p><?= e($order['email']) ?></p>
            </div>
        </section>

        <table class="w-full text-sm">
            <thead><tr class="border-y-2 border-ink-800 text-left"><th class="py-2">#</th><th class="py-2">Sản phẩm</th><th class="py-2 text-right">Đơn giá</th><th class="py-2 text-center">SL</th><th class="py-2 text-right">Thành tiền</th></tr></thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                    <tr class="border-b border-ink-100">
                        <td class="py-2"><?= $i + 1 ?></td>
                        <td class="py-2"><?= e($item['product_name']) ?></td>
                        <td class="py-2 text-right"><?= e(format_price($item['price'])) ?></td>
                        <td class="py-2 text-center"><?= (int)$item['quantity'] ?></td>
                        <td class="py-2 text-right"><?= e(format_price((float)$item['price'] * (int)$item['quantity'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <dl class="mt-4 ml-auto w-64 space-y-1 text-sm">
            <div class="flex justify-between"><dt>Tạm tính</dt><dd><?= e(format_price($subtotal)) ?></dd></div>
            <?php if ((float)$order['discount_amount'] > 0): ?>
                <div class="flex justify-between"><dt>Giảm giá (<?= e($order['coupon_code']) ?>)</dt><dd>-<?= e(format_price($order['discount_amount'])) ?></dd></div>
            <?php endif; ?>
            <div class="flex justify-between border-t-2 border-ink-800 pt-2 text-base font-extrabold"><dt>Tổng cộng</dt><dd><?= e(format_price($order['total_amount'])) ?></dd></div>
        </dl>

        <?php if ($order['note']): ?><p class="mt-6 rounded-2xl bg-ink-50 p-3 text-sm"><b>Ghi chú:</b> <?= e($order['note']) ?></p><?php endif; ?>
        <p class="mt-10 text-center text-sm text-ink-500">Cảm ơn quý khách đã mua sắm tại KiddoZone!</p>
    </article>
</body>
</html>

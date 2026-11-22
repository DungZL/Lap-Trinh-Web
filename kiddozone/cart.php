<?php
// Giỏ hàng (F06, F19): thêm, sửa số lượng, xóa; nhập mã giảm giá (#6).
// POST ở đây là đường dự phòng khi trình duyệt không chạy JS; bình thường app.js gọi api/cart.php.
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product_card.php';

if (is_post()) {
    csrf_verify();
    $result = cart_apply((string)($_POST['action'] ?? ''), $_POST['product_id'] ?? null, $_POST['quantity'] ?? null);
    flash($result['type'], $result['message']);
    redirect('cart.php');
}

$cart     = cart_details();
$coupon   = coupon_current($cart['subtotal']);
$discount = $coupon['ok'] ? $coupon['discount'] : 0.0;
$total    = max(0, $cart['subtotal'] - $discount);
$freeShipGoal = 500000;
$fallbackImg  = url('assets/img/placeholder.svg');

// Gợi ý mã đang dùng được
$suggestCoupons = db_query(
    'SELECT * FROM coupons WHERE status = 1
       AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at > NOW())
       AND (usage_limit IS NULL OR used_count < usage_limit)
     ORDER BY min_order ASC LIMIT 3'
)->fetchAll();

$pageTitle = 'Giỏ hàng';
require __DIR__ . '/includes/header.php';
?>
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="font-display text-4xl font-bold text-ink-900 dark:text-white">Giỏ hàng của bạn</h1>
        <p class="mt-1 text-muted"><?= $cart['count'] ?> sản phẩm trong giỏ</p>
    </div>
    <?php render_checkout_steps(1); ?>
</div>

<?php if (!$cart['lines']): ?>
    <div class="card mx-auto flex max-w-xl flex-col items-center px-6 py-16 text-center">
        <span class="grid size-32 place-items-center rounded-full bg-sun-100 dark:bg-sun-500/15"><?= mascot_svg('size-24', 'sad') ?></span>
        <h2 class="mt-4 text-3xl font-semibold">Giỏ hàng đang trống</h2>
        <p class="mt-1 text-muted">Còn rất nhiều đồ chơi vui đang chờ bé đó!</p>
        <a class="btn btn-primary btn-lg mt-6" href="<?= e(url('products.php')) ?>"><i class="ph-bold ph-storefront" aria-hidden="true"></i> Tiếp tục mua sắm</a>
    </div>
<?php else: ?>
    <div class="grid gap-8 lg:grid-cols-[1fr_24rem]">
        <ul class="space-y-4">
            <?php foreach ($cart['lines'] as $line): ?>
                <?php $p = $line['product']; $pid = (int)$line['product_id']; ?>
                <li data-reveal class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                    <?php if (!$line['available']): ?>
                        <div class="flex flex-1 items-center gap-3 text-price"><i class="ph-fill ph-warning-circle text-2xl" aria-hidden="true"></i> Sản phẩm #<?= $pid ?> không còn khả dụng.</div>
                    <?php else: ?>
                        <a href="<?= e(url('product.php?id=' . $pid)) ?>" class="shrink-0">
                            <img src="<?= e(product_image_url($p['image'] ?? null)) ?>" alt="<?= e($p['name']) ?>" data-fallback="<?= e($fallbackImg) ?>"
                                 class="size-24 rounded-3xl bg-gradient-to-br from-sun-100 to-sky-100 object-cover dark:from-ink-800 dark:to-ink-800">
                        </a>
                        <div class="min-w-0 flex-1">
                            <a class="font-extrabold hover:text-sky-600" href="<?= e(url('product.php?id=' . $pid)) ?>"><?= e($p['name']) ?></a>
                            <p class="mt-0.5 text-sm text-muted"><?= e(format_price($line['price'])) ?> / sản phẩm
                                <?php if (flash_active($p)): ?><span class="badge badge-sun ml-1"><i class="ph-fill ph-lightning" aria-hidden="true"></i> Flash</span><?php endif; ?>
                            </p>
                            <?php if ($line['problem']): ?><p class="mt-1 text-sm font-bold text-price"><?= e($line['problem']) ?></p><?php endif; ?>
                        </div>
                        <form method="post" action="<?= e(url('cart.php')) ?>" class="flex items-center gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?= $pid ?>">
                            <label class="sr-only" for="qty-<?= $pid ?>">Số lượng <?= e($p['name']) ?></label>
                            <input id="qty-<?= $pid ?>" type="number" name="quantity" value="<?= (int)$line['quantity'] ?>" min="1" max="<?= max(1, (int)$p['stock']) ?>"
                                   class="input w-20 text-center font-extrabold" data-autosubmit>
                            <noscript><button class="btn btn-ghost btn-sm" type="submit">Cập nhật</button></noscript>
                        </form>
                        <p class="w-32 text-right font-display text-xl font-bold text-ink-900 dark:text-white"><?= e(format_price($line['line_total'])) ?></p>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('cart.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= $pid ?>">
                        <button class="btn btn-danger-ghost btn-icon" type="submit" aria-label="Xóa khỏi giỏ" title="Xóa"><i class="ph-bold ph-trash" aria-hidden="true"></i></button>
                    </form>
                </li>
            <?php endforeach; ?>
            <li><a class="btn btn-ghost" href="<?= e(url('products.php')) ?>"><i class="ph-bold ph-arrow-left" aria-hidden="true"></i> Tiếp tục mua sắm</a></li>
        </ul>

        <!-- Tóm tắt -->
        <aside class="space-y-4 lg:sticky lg:top-40 lg:self-start">
            <div class="card p-6">
                <h2 class="font-display text-xl font-semibold">Tóm tắt đơn hàng</h2>

                <div class="mt-4 rounded-3xl bg-ink-50 p-4 dark:bg-ink-800">
                    <?php if ($cart['subtotal'] >= $freeShipGoal): ?>
                        <p class="text-sm font-bold text-ok"><i class="ph-fill ph-confetti" aria-hidden="true"></i> Đơn hàng được miễn phí giao hàng!</p>
                    <?php else: ?>
                        <p class="text-sm font-bold text-ink-500 dark:text-ink-300">Mua thêm <span class="text-price"><?= e(format_price($freeShipGoal - $cart['subtotal'])) ?></span> để miễn phí giao hàng</p>
                    <?php endif; ?>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-white dark:bg-ink-900"><div class="h-full rounded-full bg-gradient-to-r from-sun-400 to-mint-400" style="width: <?= min(100, round($cart['subtotal'] / $freeShipGoal * 100)) ?>%"></div></div>
                </div>

                <!-- Mã giảm giá (#6) -->
                <div class="mt-5" x-data="couponBox(<?= e(json_encode($coupon['ok'] ? $coupon['code'] : '')) ?>)">
                    <label for="coupon" class="label">Mã giảm giá</label>
                    <?php if ($coupon['ok']): ?>
                        <div class="flex items-center justify-between rounded-2xl border-2 border-dashed border-mint-300 bg-mint-50 px-4 py-3 dark:border-mint-500/40 dark:bg-mint-500/10">
                            <span class="flex items-center gap-2 font-extrabold text-ok dark:text-mint-300"><i class="ph-fill ph-ticket text-xl" aria-hidden="true"></i> <?= e($coupon['code']) ?></span>
                            <button type="button" class="text-sm font-bold text-muted hover:text-coral-500" @click="clear()" :disabled="loading">Bỏ mã</button>
                        </div>
                    <?php else: ?>
                        <?php if ($coupon['code']): ?>
                            <p class="mb-2 text-xs font-bold text-price">Mã <?= e($coupon['code']) ?>: <?= e($coupon['message']) ?></p>
                        <?php endif; ?>
                        <form class="flex gap-2" @submit.prevent="submit()">
                            <input id="coupon" class="input uppercase" x-model="code" placeholder="VD: KIDDO10" maxlength="30" autocomplete="off">
                            <button class="btn btn-primary" type="submit" :disabled="loading || !code.trim()">
                                <i class="ph-bold ph-spinner-gap animate-spin" aria-hidden="true" x-show="loading" x-cloak></i> Áp dụng
                            </button>
                        </form>
                        <p class="mt-2 text-xs font-bold" x-show="message" x-cloak :class="ok ? 'text-ok' : 'text-price'" x-text="message"></p>
                        <?php if ($suggestCoupons): ?>
                            <div class="mt-3 space-y-2">
                                <?php foreach ($suggestCoupons as $sc): ?>
                                    <button type="button" @click="code = '<?= e($sc['code']) ?>'; submit()"
                                            class="flex w-full items-center gap-3 rounded-2xl bg-sun-50 p-2.5 text-left ring-1 ring-sun-200 transition hover:-translate-y-0.5 hover:ring-sun-400 dark:bg-sun-500/10 dark:ring-sun-500/30">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-sun-400 text-ink-900"><i class="ph-fill ph-ticket" aria-hidden="true"></i></span>
                                        <span class="min-w-0 flex-1"><b class="block text-sm"><?= e($sc['code']) ?></b><span class="block truncate text-xs text-ink-500 dark:text-ink-300"><?= e(coupon_summary($sc)) ?></span></span>
                                        <span class="text-xs font-extrabold text-sky-600 dark:text-sky-300">Dùng</span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <dl class="mt-5 space-y-2 border-t border-dashed border-ink-200 pt-4 text-sm dark:border-ink-700">
                    <div class="flex justify-between"><dt class="text-ink-500">Tạm tính</dt><dd class="font-bold"><?= e(format_price($cart['subtotal'])) ?></dd></div>
                    <?php if ($discount > 0): ?>
                        <div class="flex justify-between text-ok dark:text-mint-300"><dt>Giảm giá</dt><dd class="font-bold">-<?= e(format_price($discount)) ?></dd></div>
                    <?php endif; ?>
                    <div class="flex items-center justify-between pt-2"><dt class="font-extrabold">Tổng cộng</dt><dd class="font-display text-3xl font-bold text-price"><?= e(format_price($total)) ?></dd></div>
                </dl>
                <a class="btn btn-sun btn-lg mt-5 w-full" href="<?= e(url('checkout.php')) ?>"><i class="ph-fill ph-lock-key" aria-hidden="true"></i> Tiến hành thanh toán</a>
            </div>
        </aside>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

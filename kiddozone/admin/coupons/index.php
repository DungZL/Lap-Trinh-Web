<?php
// Quản lý mã giảm giá (#6): danh sách, bật/tắt nhanh. Thêm/sửa ở form.php, xóa ở delete.php.
require_once __DIR__ . '/../../includes/init.php';
require_admin();

if (is_post()) {
    csrf_verify();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (($_POST['action'] ?? '') === 'toggle' && $id !== false) {
        db_query('UPDATE coupons SET status = 1 - status WHERE id = ?', [$id]);
        flash('success', 'Đã cập nhật trạng thái mã giảm giá.');
    }
    redirect('admin/coupons/index.php');
}

$total = (int)db_query('SELECT COUNT(*) FROM coupons')->fetchColumn();
[$offset, $pages, $page] = paginate($total, ITEMS_PER_PAGE_ADMIN);
$coupons = db_query(
    'SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.coupon_code = c.code) AS order_count,
            (SELECT COALESCE(SUM(o.discount_amount), 0) FROM orders o WHERE o.coupon_code = c.code AND o.status <> "cancelled") AS discount_total
     FROM coupons c ORDER BY c.status DESC, c.id DESC
     LIMIT ' . (int)ITEMS_PER_PAGE_ADMIN . ' OFFSET ' . (int)$offset
)->fetchAll();

/** Tình trạng hiện tại của mã: [nhãn, class badge]. */
function coupon_state(array $c): array
{
    $now = time();
    if ((int)$c['status'] !== 1) {
        return ['Đã tắt', 'badge-ink'];
    }
    if ($c['starts_at'] !== null && strtotime($c['starts_at']) > $now) {
        return ['Sắp diễn ra', 'badge-sky'];
    }
    if ($c['ends_at'] !== null && strtotime($c['ends_at']) <= $now) {
        return ['Hết hạn', 'badge-coral'];
    }
    if ($c['usage_limit'] !== null && (int)$c['used_count'] >= (int)$c['usage_limit']) {
        return ['Hết lượt', 'badge-coral'];
    }
    return ['Đang chạy', 'badge-mint'];
}

$pageTitle = 'Mã giảm giá';
$pageSubtitle = 'Tạo mã khuyến mãi để khách nhập khi thanh toán';
$pageActions = '<a href="' . e(url('admin/coupons/form.php')) . '" class="btn btn-primary"><i class="ph-bold ph-plus" aria-hidden="true"></i> Tạo mã mới</a>';
require __DIR__ . '/../admin_header.php';
?>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($coupons as $c): ?>
        <?php [$state, $badge] = coupon_state($c); $limit = $c['usage_limit'] !== null ? (int)$c['usage_limit'] : null; ?>
        <article data-reveal class="card relative overflow-hidden p-0 <?= (int)$c['status'] !== 1 ? 'opacity-70' : '' ?>">
            <!-- Vé giảm giá: hai khuyết tròn hai bên -->
            <div class="relative flex items-center gap-4 bg-gradient-to-r from-sun-300 to-coral-300 p-5 text-ink-900">
                <span class="grid size-14 place-items-center rounded-2xl bg-white/50 text-3xl backdrop-blur"><i class="ph-fill ph-ticket" aria-hidden="true"></i></span>
                <div class="min-w-0 flex-1">
                    <p class="font-display text-2xl leading-none font-bold tracking-wider"><?= e($c['code']) ?></p>
                    <p class="mt-1 text-sm font-bold"><?= e($c['type'] === 'percent' ? 'Giảm ' . (int)$c['value'] . '%' : 'Giảm ' . format_price($c['value'])) ?></p>
                </div>
                <span class="badge <?= $badge ?> bg-white!"><?= e($state) ?></span>
                <span class="absolute -bottom-3 -left-3 size-6 rounded-full bg-ink-50 dark:bg-ink-950"></span>
                <span class="absolute -right-3 -bottom-3 size-6 rounded-full bg-ink-50 dark:bg-ink-950"></span>
            </div>
            <div class="space-y-2 border-t-2 border-dashed border-ink-100 p-5 text-sm dark:border-ink-800">
                <p class="text-ink-500 dark:text-ink-300"><?= e($c['description'] ?: coupon_summary($c)) ?></p>
                <p class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-bold text-muted">
                    <span><i class="ph-bold ph-shopping-cart" aria-hidden="true"></i> Đơn từ <?= e(format_price($c['min_order'])) ?></span>
                    <?php if ($c['max_discount']): ?><span><i class="ph-bold ph-arrow-line-down" aria-hidden="true"></i> Tối đa <?= e(format_price($c['max_discount'])) ?></span><?php endif; ?>
                    <span><i class="ph-bold ph-calendar" aria-hidden="true"></i> <?= $c['ends_at'] ? 'Đến ' . e(date('d/m/Y H:i', strtotime($c['ends_at']))) : 'Không thời hạn' ?></span>
                </p>
                <div>
                    <div class="mb-1 flex justify-between text-xs font-bold"><span>Đã dùng <?= (int)$c['used_count'] ?><?= $limit !== null ? '/' . $limit : '' ?> lượt</span><span class="text-ok">Đã giảm <?= e(format_price($c['discount_total'])) ?></span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full bg-gradient-to-r from-sun-400 to-coral-400" style="width: <?= $limit ? min(100, round((int)$c['used_count'] / $limit * 100)) : min(100, (int)$c['used_count'] * 5) ?>%"></div></div>
                </div>
                <div class="flex gap-2 pt-2">
                    <form method="post" action="<?= e(url('admin/coupons/index.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm"><i class="ph-bold <?= (int)$c['status'] === 1 ? 'ph-pause' : 'ph-play' ?>" aria-hidden="true"></i> <?= (int)$c['status'] === 1 ? 'Tắt' : 'Bật' ?></button>
                    </form>
                    <a href="<?= e(url('admin/coupons/form.php?id=' . (int)$c['id'])) ?>" class="btn btn-ghost btn-sm"><i class="ph-bold ph-pencil-simple" aria-hidden="true"></i> Sửa</a>
                    <form method="post" action="<?= e(url('admin/coupons/delete.php')) ?>" class="ml-auto" data-confirm="Xóa mã <?= e($c['code']) ?>? Các đơn đã dùng mã vẫn giữ nguyên.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        <button type="submit" class="btn btn-danger-ghost btn-sm" aria-label="Xóa mã <?= e($c['code']) ?>"><i class="ph-bold ph-trash" aria-hidden="true"></i></button>
                    </form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$coupons): ?>
        <div class="card p-10 text-center md:col-span-2 xl:col-span-3">
            <div class="flex justify-center"><?= empty_icon('ph-ticket', 'sun') ?></div>
            <p class="mt-3 font-bold">Chưa có mã giảm giá nào.</p>
            <a href="<?= e(url('admin/coupons/form.php')) ?>" class="btn btn-primary mt-4">Tạo mã đầu tiên</a>
        </div>
    <?php endif; ?>
</div>
<?= render_pagination($page, $pages) ?>

<?php require __DIR__ . '/../admin_footer.php'; ?>

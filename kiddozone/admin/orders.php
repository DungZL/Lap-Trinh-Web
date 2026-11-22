<?php
// Quản lý đơn hàng (F12, F15): danh sách + tìm kiếm + lọc trạng thái, chi tiết, đổi trạng thái theo quy trình,
// dòng thời gian (#8), xuất CSV và in hóa đơn (#12), đánh dấu đã xem cho chuông thông báo (#13).
require_once __DIR__ . '/../includes/init.php';
require_admin();

$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$showOrder = array_key_exists('id', $_GET);
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$statusFilter = isset($_GET['status']) && is_string($_GET['status']) && isset(order_statuses()[$_GET['status']]) ? $_GET['status'] : '';
$requestedPage = isset($_GET['page']) && is_string($_GET['page'])
    ? filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$listPage = $requestedPage === false ? null : $requestedPage;

$statusLabels = order_statuses();
$nextStatus = [
    'pending'   => 'confirmed',
    'confirmed' => 'shipping',
    'shipping'  => 'completed',
];
$paymentLabels = payment_labels();

/** Tham số giữ lại khi quay về danh sách. */
$listParams = function () use ($search, $statusFilter, $listPage): array {
    $p = [];
    if ($search !== '') {
        $p['q'] = $search;
    }
    if ($statusFilter !== '') {
        $p['status'] = $statusFilter;
    }
    if ($listPage !== null) {
        $p['page'] = $listPage;
    }
    return $p;
};

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $postedOrderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $targetStatus = $_POST['status'] ?? '';

    if ($postedOrderId === false || !is_string($targetStatus)) {
        flash('danger', 'Thông tin cập nhật đơn hàng không hợp lệ.');
        redirect('admin/orders.php');
    }
    $back = 'admin/orders.php?' . http_build_query(['id' => (int)$postedOrderId] + $listParams());

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $currentOrder = db_query('SELECT id, status FROM orders WHERE id = ? FOR UPDATE', [$postedOrderId])->fetch();

        if (!$currentOrder) {
            $pdo->rollBack();
            flash('warning', 'Không tìm thấy đơn hàng.');
            redirect('admin/orders.php');
        }

        $currentStatus = $currentOrder['status'];
        $canCancel = in_array($currentStatus, ['pending', 'confirmed', 'shipping'], true);
        $isNextStatus = isset($nextStatus[$currentStatus]) && $targetStatus === $nextStatus[$currentStatus];
        $isCancellation = $targetStatus === 'cancelled' && $canCancel;

        if ($action !== 'update_status' || (!$isNextStatus && !$isCancellation)) {
            $pdo->rollBack();
            flash('warning', 'Không thể chuyển đơn hàng sang trạng thái đã chọn.');
            redirect($back);
        }

        if ($isCancellation) {
            $items = db_query(
                'SELECT product_id, SUM(quantity) AS quantity FROM order_items
                 WHERE order_id = ? AND product_id IS NOT NULL GROUP BY product_id',
                [$postedOrderId]
            )->fetchAll();
            foreach ($items as $item) {
                db_query('UPDATE products SET stock = stock + ? WHERE id = ?', [(int)$item['quantity'], (int)$item['product_id']]);
            }
        }

        db_query('UPDATE orders SET status = ? WHERE id = ? AND status = ?', [$targetStatus, $postedOrderId, $currentStatus]);
        order_history_add((int)$postedOrderId, $targetStatus);
        $pdo->commit();
        flash('success', $isCancellation ? 'Đã hủy đơn hàng và hoàn lại tồn kho.' : 'Đã chuyển đơn sang "' . $statusLabels[$targetStatus][0] . '".');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    redirect($back);
}

$pageTitle = 'Quản lý đơn hàng';
if ($showOrder) {
    $order = $orderId === false ? null : db_query(
        'SELECT o.*, u.full_name, u.email
         FROM orders o INNER JOIN users u ON u.id = o.user_id
         WHERE o.id = ?',
        [$orderId]
    )->fetch();

    if (!$order) {
        http_response_code(404);
    } else {
        if (!(int)$order['admin_seen']) {
            db_query('UPDATE orders SET admin_seen = 1 WHERE id = ?', [$orderId]); // tắt chấm đỏ thông báo (#13)
        }
        $items = db_query(
            'SELECT oi.product_id, oi.product_name, oi.price, oi.quantity,
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = oi.product_id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image
             FROM order_items oi WHERE oi.order_id = ? ORDER BY oi.id',
            [$orderId]
        )->fetchAll();
        $pageTitle = 'Đơn hàng ' . $order['code'];
        $pageActions = '<a href="' . e(url('admin/orders/invoice.php?id=' . (int)$order['id'])) . '" target="_blank" rel="noopener" class="btn btn-ghost"><i class="ph-bold ph-printer text-lg" aria-hidden="true"></i> In hóa đơn</a>';
    }
} else {
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(o.code LIKE ? OR o.receiver_name LIKE ? OR o.receiver_phone LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
        $term = '%' . addcslashes($search, '%_\\') . '%';
        array_push($params, $term, $term, $term, $term, $term);
    }
    if ($statusFilter !== '') {
        $where[] = 'o.status = ?';
        $params[] = $statusFilter;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $totalOrders = (int)db_query("SELECT COUNT(*) FROM orders o INNER JOIN users u ON u.id = o.user_id $whereSql", $params)->fetchColumn();
    [$offset, $pages, $page] = paginate($totalOrders, ITEMS_PER_PAGE_ADMIN);
    $orders = db_query(
        "SELECT o.id, o.code, o.receiver_name, o.receiver_phone, o.total_amount, o.status, o.created_at, o.admin_seen,
                o.payment_method, u.full_name, u.email
         FROM orders o INNER JOIN users u ON u.id = o.user_id
         $whereSql
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT " . (int)ITEMS_PER_PAGE_ADMIN . ' OFFSET ' . (int)$offset,
        $params
    )->fetchAll();

    $counts = array_fill_keys(array_keys($statusLabels), 0);
    foreach (db_query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status')->fetchAll() as $r) {
        $counts[$r['status']] = (int)$r['n'];
    }
    $exportQs = array_filter(['q' => $search, 'status' => $statusFilter], fn($v) => $v !== '');
    $pageSubtitle = 'Theo dõi và cập nhật trạng thái đơn hàng';
    $pageActions = '<a href="' . e(url('admin/orders/export.php' . ($exportQs ? '?' . http_build_query($exportQs) : ''))) . '" class="btn btn-mint"><i class="ph-bold ph-file-csv text-lg" aria-hidden="true"></i> Xuất Excel (CSV)</a>';
}

require __DIR__ . '/admin_header.php';
?>
<?php if ($showOrder): ?>
    <?php if (!$order): ?>
        <div class="card p-10 text-center">
            <div class="flex justify-center"><?= empty_icon('ph-receipt', 'sky') ?></div>
            <p class="mt-3 font-bold">Không tìm thấy đơn hàng.</p>
            <a class="btn btn-ghost mt-5" href="<?= e(url('admin/orders.php')) ?>">Quay lại danh sách đơn</a>
        </div>
    <?php else: ?>
        <?php $backUrl = 'admin/orders.php' . ($listParams() ? '?' . http_build_query($listParams()) : ''); ?>
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <a class="btn btn-ghost btn-sm" href="<?= e(url($backUrl)) ?>"><i class="ph-bold ph-arrow-left" aria-hidden="true"></i> Danh sách đơn</a>
            <?= order_status_badge($order['status']) ?>
            <span class="text-sm text-muted">Đặt lúc <?= e(date('H:i d/m/Y', strtotime($order['created_at']))) ?></span>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <section class="card p-6"><?php render_order_timeline($order); ?></section>

                <section class="card p-6">
                    <h2 class="mb-4 font-display text-xl font-semibold">Sản phẩm</h2>
                    <div class="overflow-x-auto">
                        <table class="table-kz">
                            <thead><tr><th>Sản phẩm</th><th class="text-right">Đơn giá</th><th class="text-center">SL</th><th class="text-right">Thành tiền</th></tr></thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><div class="flex items-center gap-3"><img src="<?= e(product_image_url($item['image'])) ?>" alt="" class="size-11 rounded-xl object-cover" data-fallback="<?= e(url('assets/img/placeholder.svg')) ?>"><b><?= e($item['product_name']) ?></b></div></td>
                                        <td class="text-right"><?= e(format_price($item['price'])) ?></td>
                                        <td class="text-center"><?= (int)$item['quantity'] ?></td>
                                        <td class="text-right font-bold"><?= e(format_price((float)$item['price'] * (int)$item['quantity'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <dl class="mt-4 ml-auto max-w-xs space-y-1 text-sm">
                        <?php if ((float)$order['discount_amount'] > 0): ?>
                            <div class="flex justify-between text-ok dark:text-mint-300"><dt>Mã <?= e($order['coupon_code']) ?></dt><dd class="font-bold">-<?= e(format_price($order['discount_amount'])) ?></dd></div>
                        <?php endif; ?>
                        <div class="flex items-center justify-between"><dt class="font-extrabold">Tổng cộng</dt><dd class="font-display text-2xl font-bold text-price"><?= e(format_price($order['total_amount'])) ?></dd></div>
                    </dl>
                </section>
            </div>

            <div class="space-y-6">
                <section class="card p-6">
                    <h2 class="mb-4 font-display text-xl font-semibold">Cập nhật trạng thái</h2>
                    <?php if (isset($nextStatus[$order['status']]) || in_array($order['status'], ['pending', 'confirmed', 'shipping'], true)): ?>
                        <div class="space-y-2">
                            <?php if (isset($nextStatus[$order['status']])): ?>
                                <?php [$nextLabel, , $nextIcon] = $statusLabels[$nextStatus[$order['status']]]; ?>
                                <form method="post" action="<?= e(url('admin/orders.php?' . http_build_query(['id' => (int)$order['id']] + $listParams()))) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <input type="hidden" name="status" value="<?= e($nextStatus[$order['status']]) ?>">
                                    <button class="btn btn-primary w-full" type="submit"><i class="ph-fill <?= e($nextIcon) ?> text-lg" aria-hidden="true"></i> Chuyển sang "<?= e($nextLabel) ?>"</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('admin/orders.php?' . http_build_query(['id' => (int)$order['id']] + $listParams()))) ?>" data-confirm="Hủy đơn chưa giao và hoàn lại tồn kho?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                <input type="hidden" name="status" value="cancelled">
                                <button class="btn btn-danger-ghost w-full" type="submit"><i class="ph-bold ph-x-circle" aria-hidden="true"></i> Hủy đơn</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p class="rounded-2xl bg-ink-50 p-4 text-sm text-ink-500 dark:bg-ink-800 dark:text-ink-300">Đơn đã <?= e(mb_strtolower($statusLabels[$order['status']][0])) ?>, không thể đổi trạng thái nữa.</p>
                    <?php endif; ?>
                </section>

                <section class="card p-6">
                    <h2 class="mb-4 font-display text-xl font-semibold">Khách hàng</h2>
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-xs text-muted">Tài khoản</dt><dd class="font-bold"><?= e($order['full_name']) ?> <span class="font-normal text-muted">(<?= e($order['email']) ?>)</span></dd></div>
                        <div><dt class="text-xs text-muted">Người nhận</dt><dd class="font-bold"><?= e($order['receiver_name']) ?> · <?= e($order['receiver_phone']) ?></dd></div>
                        <div><dt class="text-xs text-muted">Địa chỉ giao hàng</dt><dd class="font-bold"><?= e($order['shipping_address']) ?></dd></div>
                        <div><dt class="text-xs text-muted">Thanh toán</dt><dd class="font-bold"><?= e($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?></dd></div>
                        <?php if ($order['note']): ?>
                            <div><dt class="text-xs text-muted">Ghi chú</dt><dd class="rounded-xl bg-sun-50 p-2 font-bold dark:bg-sun-500/10"><?= e($order['note']) ?></dd></div>
                        <?php endif; ?>
                    </dl>
                </section>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <!-- Lọc theo trạng thái -->
    <div class="no-scrollbar mb-4 flex gap-2 overflow-x-auto">
        <?php
        $tab = 'flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-extrabold transition';
        $tabUrl = fn(string $s) => url('admin/orders.php' . (($q = array_filter(['q' => $search, 'status' => $s], fn($v) => $v !== '')) ? '?' . http_build_query($q) : ''));
        ?>
        <a href="<?= e($tabUrl('')) ?>" class="<?= $tab ?> <?= $statusFilter === '' ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-900' : 'bg-white text-ink-500 hover:text-ink-900 dark:bg-ink-900' ?>">Tất cả <span class="opacity-60"><?= array_sum($counts) ?></span></a>
        <?php foreach ($statusLabels as $key => [$label, , $icon]): ?>
            <a href="<?= e($tabUrl($key)) ?>" class="<?= $tab ?> <?= $statusFilter === $key ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-900' : 'bg-white text-ink-500 hover:text-ink-900 dark:bg-ink-900' ?>"><i class="ph-fill <?= e($icon) ?>" aria-hidden="true"></i> <?= e($label) ?> <span class="opacity-60"><?= $counts[$key] ?></span></a>
        <?php endforeach; ?>
    </div>

    <div class="card overflow-hidden">
        <form method="get" action="<?= e(url('admin/orders.php')) ?>" class="flex flex-wrap gap-2 border-b border-ink-100 p-4 dark:border-ink-800">
            <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
            <div class="relative min-w-60 flex-1">
                <i class="ph-bold ph-magnifying-glass absolute top-1/2 left-4 -translate-y-1/2 text-muted" aria-hidden="true"></i>
                <label class="sr-only" for="order-search">Tìm đơn hàng</label>
                <input class="input pl-11" id="order-search" type="search" name="q" value="<?= e($search) ?>" placeholder="Mã đơn, khách hàng, email hoặc số điện thoại">
            </div>
            <button class="btn btn-primary" type="submit">Tìm kiếm</button>
            <?php if ($search !== ''): ?><a class="btn btn-ghost" href="<?= e($tabUrl($statusFilter)) ?>">Xóa tìm kiếm</a><?php endif; ?>
        </form>
        <?php if (!$orders): ?>
            <p class="p-10 text-center text-muted"><?= $search === '' && $statusFilter === '' ? 'Chưa có đơn hàng.' : 'Không tìm thấy đơn hàng phù hợp.' ?></p>
        <?php else: ?>
            <div class="overflow-x-auto p-2">
                <table class="table-kz">
                    <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Ngày đặt</th><th>Thanh toán</th><th>Trạng thái</th><th class="text-right">Tổng tiền</th><th><span class="sr-only">Chi tiết</span></th></tr></thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <?php $detail = url('admin/orders.php?' . http_build_query(['id' => (int)$o['id']] + array_filter(['q' => $search, 'status' => $statusFilter], fn($v) => $v !== '') + ['page' => $page])); ?>
                            <tr>
                                <td><a href="<?= e($detail) ?>" class="flex items-center gap-2 font-extrabold hover:text-sky-600"><?php if (!(int)$o['admin_seen']): ?><span class="size-2 rounded-full bg-coral-400" title="Đơn mới chưa xem"></span><?php endif; ?><?= e($o['code']) ?></a></td>
                                <td><b><?= e($o['full_name']) ?></b><br><span class="text-xs text-muted"><?= e($o['email']) ?> · <?= e($o['receiver_phone']) ?></span></td>
                                <td class="whitespace-nowrap text-ink-500"><?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></td>
                                <td><span class="badge <?= $o['payment_method'] === 'bank_transfer' ? 'badge-sky' : 'badge-ink' ?>"><?= $o['payment_method'] === 'bank_transfer' ? 'Chuyển khoản' : 'COD' ?></span></td>
                                <td><?= order_status_badge($o['status']) ?></td>
                                <td class="text-right font-extrabold whitespace-nowrap"><?= e(format_price($o['total_amount'])) ?></td>
                                <td class="text-right"><a class="btn btn-ghost btn-sm" href="<?= e($detail) ?>">Xem <i class="ph-bold ph-arrow-right" aria-hidden="true"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?= render_pagination($page, $pages) ?>
<?php endif; ?>

<?php require __DIR__ . '/admin_footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$showOrder = array_key_exists('id', $_GET);
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$requestedPage = isset($_GET['page']) && is_string($_GET['page'])
    ? filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$listPage = $requestedPage === false ? null : $requestedPage;

$statusLabels = [
    'pending' => ['Chờ xác nhận', 'warning'],
    'confirmed' => ['Đã xác nhận', 'primary'],
    'shipping' => ['Đang giao', 'info'],
    'completed' => ['Đã giao', 'success'],
    'cancelled' => ['Đã hủy', 'secondary'],
];
$nextStatus = [
    'pending' => 'confirmed',
    'confirmed' => 'shipping',
    'shipping' => 'completed',
];
$paymentLabels = [
    'cod' => 'Thanh toán khi nhận hàng',
    'bank_transfer' => 'Chuyển khoản',
];

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $postedOrderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $targetStatus = $_POST['status'] ?? '';

    if ($postedOrderId === false || !is_string($targetStatus)) {
        flash('danger', 'Thông tin cập nhật đơn hàng không hợp lệ.');
        redirect('admin/orders.php');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $currentOrder = db_query(
            'SELECT id, status FROM orders WHERE id = ? FOR UPDATE',
            [$postedOrderId]
        )->fetch();

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
            $redirectParams = ['id' => (int)$postedOrderId];
            if ($search !== '') {
                $redirectParams['q'] = $search;
            }
            if ($listPage !== null) {
                $redirectParams['page'] = $listPage;
            }
            redirect('admin/orders.php?' . http_build_query($redirectParams));
        }

        if ($isCancellation) {
            $items = db_query(
                'SELECT product_id, SUM(quantity) AS quantity
                 FROM order_items
                 WHERE order_id = ? AND product_id IS NOT NULL
                 GROUP BY product_id',
                [$postedOrderId]
            )->fetchAll();
            foreach ($items as $item) {
                db_query(
                    'UPDATE products SET stock = stock + ? WHERE id = ?',
                    [(int)$item['quantity'], (int)$item['product_id']]
                );
            }
        }

        db_query(
            'UPDATE orders SET status = ? WHERE id = ? AND status = ?',
            [$targetStatus, $postedOrderId, $currentStatus]
        );
        $pdo->commit();
        flash('success', $isCancellation
            ? 'Đã hủy đơn hàng và hoàn lại tồn kho.'
            : 'Đã cập nhật trạng thái đơn hàng.');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    $redirectParams = ['id' => (int)$postedOrderId];
    if ($search !== '') {
        $redirectParams['q'] = $search;
    }
    if ($listPage !== null) {
        $redirectParams['page'] = $listPage;
    }
    redirect('admin/orders.php?' . http_build_query($redirectParams));
}

$pageTitle = 'Quản lý đơn hàng';
if ($showOrder) {
    $order = $orderId === false ? null : db_query(
        'SELECT o.id, o.code, o.user_id, o.receiver_name, o.receiver_phone, o.shipping_address, o.note,
                o.payment_method, o.total_amount, o.status, o.created_at, u.full_name, u.email
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         WHERE o.id = ?',
        [$orderId]
    )->fetch();

    if (!$order) {
        http_response_code(404);
    } else {
        $items = db_query(
            'SELECT product_name, price, quantity FROM order_items WHERE order_id = ? ORDER BY id',
            [$orderId]
        )->fetchAll();
        $pageTitle = 'Đơn hàng ' . $order['code'];
    }
} else {
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE o.code LIKE ? OR o.receiver_name LIKE ? OR o.receiver_phone LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?';
        $term = '%' . $search . '%';
        $params = [$term, $term, $term, $term, $term];
    }

    $totalOrders = (int)db_query(
        'SELECT COUNT(*)
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         ' . $where,
        $params
    )->fetchColumn();
    [$offset, $pages, $page] = paginate($totalOrders, 10);
    $orders = db_query(
        'SELECT o.id, o.code, o.receiver_name, o.receiver_phone, o.total_amount, o.status, o.created_at,
                u.full_name, u.email
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         ' . $where . '
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 10 OFFSET ' . (int)$offset,
        $params
    )->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>
<?php if ($showOrder): ?>
    <?php if (!$order): ?>
        <div class="alert alert-warning">Không tìm thấy đơn hàng.</div>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/orders.php')) ?>">Quay lại danh sách đơn</a>
    <?php else: ?>
        <?php [$statusLabel, $statusColor] = $statusLabels[$order['status']] ?? ['Không xác định', 'secondary']; ?>
        <?php
        $detailActionParams = ['id' => (int)$order['id']];
        if ($search !== '') {
            $detailActionParams['q'] = $search;
        }
        if ($listPage !== null) {
            $detailActionParams['page'] = $listPage;
        }
        $detailAction = 'admin/orders.php?' . http_build_query($detailActionParams);
        ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h1 class="h2 mb-1">Đơn hàng <?= e($order['code']) ?></h1>
                <span class="text-muted"><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></span>
            </div>
            <span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
        </div>
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">Thông tin khách hàng và giao hàng</h2>
                <dl class="row">
                    <dt class="col-sm-3">Tài khoản</dt>
                    <dd class="col-sm-9"><?= e($order['full_name']) ?> (<?= e($order['email']) ?>)</dd>
                    <dt class="col-sm-3">Người nhận</dt>
                    <dd class="col-sm-9"><?= e($order['receiver_name']) ?> · <?= e($order['receiver_phone']) ?></dd>
                    <dt class="col-sm-3">Địa chỉ giao hàng</dt>
                    <dd class="col-sm-9"><?= e($order['shipping_address']) ?></dd>
                    <dt class="col-sm-3">Thanh toán</dt>
                    <dd class="col-sm-9"><?= e($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?></dd>
                    <?php if ($order['note']): ?>
                        <dt class="col-sm-3">Ghi chú</dt>
                        <dd class="col-sm-9"><?= e($order['note']) ?></dd>
                    <?php endif; ?>
                </dl>

                <h2 class="h5 mt-4">Sản phẩm</h2>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Sản phẩm</th>
                                <th scope="col">Đơn giá</th>
                                <th scope="col">Số lượng</th>
                                <th scope="col">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= e($item['product_name']) ?></td>
                                    <td><?= e(format_price($item['price'])) ?></td>
                                    <td><?= (int)$item['quantity'] ?></td>
                                    <td><?= e(format_price((float)$item['price'] * (int)$item['quantity'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Tổng cộng</th>
                                <th><?= e(format_price($order['total_amount'])) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php if (isset($nextStatus[$order['status']]) || in_array($order['status'], ['pending', 'confirmed', 'shipping'], true)): ?>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <?php if (isset($nextStatus[$order['status']])): ?>
                            <?php [$nextLabel] = $statusLabels[$nextStatus[$order['status']]]; ?>
                            <form method="post" action="<?= e(url($detailAction)) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                <input type="hidden" name="status" value="<?= e($nextStatus[$order['status']]) ?>">
                                <button class="btn btn-primary" type="submit">Chuyển sang <?= e($nextLabel) ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array($order['status'], ['pending', 'confirmed', 'shipping'], true)): ?>
                            <form method="post" action="<?= e(url($detailAction)) ?>" onsubmit="return confirm('Hủy đơn chưa giao và hoàn lại tồn kho?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                <input type="hidden" name="status" value="cancelled">
                                <button class="btn btn-outline-danger" type="submit">Hủy đơn</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $backParams = [];
        if ($search !== '') {
            $backParams['q'] = $search;
        }
        if ($listPage !== null) {
            $backParams['page'] = $listPage;
        }
        $backUrl = 'admin/orders.php' . ($backParams ? '?' . http_build_query($backParams) : '');
        ?>
        <a class="btn btn-outline-secondary" href="<?= e(url($backUrl)) ?>">Quay lại danh sách đơn</a>
    <?php endif; ?>
<?php else: ?>
    <h1 class="h2 mb-4">Quản lý đơn hàng</h1>
    <form method="get" action="<?= e(url('admin/orders.php')) ?>" class="row g-2 mb-4">
        <div class="col-sm-8 col-md-6">
            <label class="visually-hidden" for="order-search">Tìm đơn hàng</label>
            <input class="form-control" id="order-search" type="search" name="q" value="<?= e($search) ?>" placeholder="Mã đơn, khách hàng, email hoặc số điện thoại">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit">Tìm kiếm</button>
            <?php if ($search !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/orders.php')) ?>">Xóa tìm kiếm</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if (!$orders): ?>
        <div class="alert alert-info"><?= $search === '' ? 'Chưa có đơn hàng.' : 'Không tìm thấy đơn hàng phù hợp.' ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th scope="col">Mã đơn</th>
                        <th scope="col">Khách hàng</th>
                        <th scope="col">Ngày đặt</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col">Tổng tiền</th>
                        <th scope="col"><span class="visually-hidden">Chi tiết</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php [$statusLabel, $statusColor] = $statusLabels[$order['status']] ?? ['Không xác định', 'secondary']; ?>
                        <tr>
                            <td><?= e($order['code']) ?></td>
                            <td>
                                <?= e($order['full_name']) ?><br>
                                <small class="text-muted"><?= e($order['email']) ?> · <?= e($order['receiver_phone']) ?></small>
                            </td>
                            <td><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                            <td><span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span></td>
                            <td><?= e(format_price($order['total_amount'])) ?></td>
                            <td>
                                <?php
                                $detailParams = ['id' => (int)$order['id']];
                                if ($search !== '') {
                                    $detailParams['q'] = $search;
                                }
                                $detailParams['page'] = $page;
                                ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?= e(url('admin/orders.php?' . http_build_query($detailParams))) ?>">Xem / cập nhật</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($page, $pages) ?>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

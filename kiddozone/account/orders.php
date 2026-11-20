<?php
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

$statusLabels = [
    'pending' => ['Chờ xác nhận', 'warning'],
    'confirmed' => ['Đã xác nhận', 'primary'],
    'shipping' => ['Đang giao', 'info'],
    'completed' => ['Đã giao', 'success'],
    'cancelled' => ['Đã hủy', 'secondary'],
];
$paymentLabels = [
    'cod' => 'Thanh toán khi nhận hàng',
    'bank_transfer' => 'Chuyển khoản',
];

$pageTitle = 'Lịch sử đơn hàng';
if ($showOrder) {
    $order = $orderId === false ? null : db_query(
        'SELECT id, code, receiver_name, receiver_phone, shipping_address, note, payment_method, total_amount, status, created_at
         FROM orders
         WHERE id = ? AND user_id = ?',
        [$orderId, $userId]
    )->fetch();
    if (!$order) {
        http_response_code(404);
    } else {
        $items = db_query(
            'SELECT product_name, price, quantity FROM order_items WHERE order_id = ? ORDER BY id',
            [$orderId]
        )->fetchAll();
        $pageTitle = 'Chi tiết đơn hàng ' . $order['code'];
    }
} else {
    $totalOrders = (int)db_query('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$userId])->fetchColumn();
    [$offset, $pages, $page] = paginate($totalOrders, ITEMS_PER_PAGE_SHOP);
    $orders = db_query(
        'SELECT id, code, total_amount, status, created_at
         FROM orders
         WHERE user_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT ' . (int)ITEMS_PER_PAGE_SHOP . ' OFFSET ' . (int)$offset,
        [$userId]
    )->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>
<?php if ($showOrder): ?>
    <?php if (!$order): ?>
        <div class="alert alert-warning">Không tìm thấy đơn hàng.</div>
        <a class="btn btn-outline-secondary" href="<?= e(url('account/orders.php')) ?>">Quay lại lịch sử đơn hàng</a>
    <?php else: ?>
        <?php [$statusLabel, $statusColor] = $statusLabels[$order['status']] ?? ['Không xác định', 'secondary']; ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h1 class="h2 mb-1">Đơn hàng <?= e($order['code']) ?></h1>
                <span class="text-muted"><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></span>
            </div>
            <span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
        </div>
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">Sản phẩm</h2>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Tên sản phẩm</th>
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
                <h2 class="h5 mt-4">Thông tin giao hàng</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Người nhận</dt>
                    <dd class="col-sm-9"><?= e($order['receiver_name']) ?></dd>
                    <dt class="col-sm-3">Số điện thoại</dt>
                    <dd class="col-sm-9"><?= e($order['receiver_phone']) ?></dd>
                    <dt class="col-sm-3">Địa chỉ</dt>
                    <dd class="col-sm-9"><?= e($order['shipping_address']) ?></dd>
                    <dt class="col-sm-3">Thanh toán</dt>
                    <dd class="col-sm-9"><?= e($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?></dd>
                    <?php if ($order['note']): ?>
                        <dt class="col-sm-3">Ghi chú</dt>
                        <dd class="col-sm-9"><?= e($order['note']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(url('account/orders.php')) ?>">Quay lại lịch sử đơn hàng</a>
            <?php if ($order['status'] === 'pending'): ?>
                <form method="post" action="<?= e(url('account/orders.php?id=' . (int)$order['id'])) ?>" onsubmit="return confirm('Bạn có chắc muốn hủy đơn hàng này?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                    <button class="btn btn-outline-danger" type="submit">Hủy đơn hàng</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <h1 class="h2 mb-4">Lịch sử đơn hàng</h1>
    <?php if (!$orders): ?>
        <div class="alert alert-info">Bạn chưa có đơn hàng nào.</div>
        <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Tiếp tục mua sắm</a>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th scope="col">Mã đơn</th>
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
                            <td><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                            <td><span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span></td>
                            <td><?= e(format_price($order['total_amount'])) ?></td>
                            <td><a class="btn btn-outline-primary btn-sm" href="<?= e(url('account/orders.php?id=' . (int)$order['id'])) ?>">Xem chi tiết</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($page, $pages) ?>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

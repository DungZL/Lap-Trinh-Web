<?php
// Đặt hàng (F07 - LTWCK-20): kiểm tra đăng nhập, form thông tin giao hàng, tạo đơn trong transaction.
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product_card.php';

require_customer();

$userId = (int)current_user()['id'];
if (session_cart()) {
    merge_session_cart($userId);
}

$paymentLabels = [
    'cod'           => 'Thanh toán khi nhận hàng (COD)',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
];

// Màn hình Đặt hàng thành công (theo 05-sequence-dat-hang.drawio bước 13-14)
$successCode = isset($_GET['code']) && is_string($_GET['code']) ? trim($_GET['code']) : '';
if ($successCode !== '') {
    $order = db_query(
        'SELECT id, code, receiver_name, receiver_phone, shipping_address, note, payment_method, total_amount, status, created_at
         FROM orders
         WHERE code = ? AND user_id = ?',
        [$successCode, $userId]
    )->fetch();

    if ($order) {
        $orderItems = db_query(
            'SELECT product_name, price, quantity FROM order_items WHERE order_id = ? ORDER BY id',
            [(int)$order['id']]
        )->fetchAll();

        $pageTitle = 'Đặt hàng thành công';
        require __DIR__ . '/includes/header.php';
        ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5 text-center">
                        <div class="text-success mb-3">
                            <i class="bi bi-check-circle-fill" style="font-size: 3.5rem;"></i>
                        </div>
                        <h1 class="h2 fw-bold mb-2">Đặt hàng thành công!</h1>
                        <p class="text-muted mb-4">
                            Cảm ơn bạn đã mua sắm tại KiddoZone. Mã đơn hàng của bạn là
                            <span class="badge text-bg-primary fs-6 ms-1"><?= e($order['code']) ?></span>
                        </p>

                        <div class="text-start border rounded p-3 mb-4 bg-light">
                            <div class="row g-2 small">
                                <div class="col-sm-6"><strong>Người nhận:</strong> <?= e($order['receiver_name']) ?></div>
                                <div class="col-sm-6"><strong>Điện thoại:</strong> <?= e($order['receiver_phone']) ?></div>
                                <div class="col-12"><strong>Địa chỉ giao hàng:</strong> <?= e($order['shipping_address']) ?></div>
                                <div class="col-sm-6"><strong>Thanh toán:</strong> <?= e($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?></div>
                                <div class="col-sm-6"><strong>Tổng tiền:</strong> <span class="text-danger fw-bold"><?= e(format_price($order['total_amount'])) ?></span></div>
                                <?php if (!empty($order['note'])): ?>
                                    <div class="col-12"><strong>Ghi chú:</strong> <?= e($order['note']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="table-responsive text-start mb-4">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th class="text-end">Đơn giá</th>
                                        <th class="text-center">SL</th>
                                        <th class="text-end">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                        <tr>
                                            <td><?= e($item['product_name']) ?></td>
                                            <td class="text-end"><?= e(format_price($item['price'])) ?></td>
                                            <td class="text-center"><?= (int)$item['quantity'] ?></td>
                                            <td class="text-end"><?= e(format_price((float)$item['price'] * (int)$item['quantity'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <a class="btn kz-btn-register" href="<?= e(url('account/orders.php?id=' . (int)$order['id'])) ?>">
                                <i class="bi bi-receipt me-1"></i> Xem chi tiết đơn hàng
                            </a>
                            <a class="btn btn-outline-secondary" href="<?= e(url('products.php')) ?>">
                                Tiếp tục mua sắm
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        require __DIR__ . '/includes/footer.php';
        exit;
    }

    flash('warning', 'Không tìm thấy đơn hàng.');
    redirect('account/orders.php');
}

// Đọc giỏ hàng từ CSDL (05-sequence-dat-hang.drawio bước 4-5)
$cartRows = db_query(
    'SELECT ca.id AS cart_id, ci.product_id, ci.quantity, p.name, p.price, p.sale_price, p.stock, p.status,
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
            $totalAmount = 0.0;

            foreach ($cartRows as $row) {
                $pid = (int)$row['product_id'];
                $qty = (int)$row['quantity'];

                $locked = db_query(
                    'SELECT p.id, p.name, p.price, p.sale_price, p.stock, p.status, c.status AS category_status
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
                $totalAmount += $unitPrice * $qty;
                $lockedItems[] = [
                    'product_id'   => (int)$locked['id'],
                    'product_name' => $locked['name'],
                    'price'        => $unitPrice,
                    'quantity'     => $qty,
                ];
            }

            $tempCode = 'TMP' . bin2hex(random_bytes(5));
            db_query(
                'INSERT INTO orders (code, user_id, receiver_name, receiver_phone, shipping_address, note, payment_method, total_amount, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending")',
                [
                    $tempCode,
                    $userId,
                    $receiverName,
                    $receiverPhone,
                    $shippingAddress,
                    $note !== '' ? $note : null,
                    $paymentMethod,
                    $totalAmount,
                ]
            );

            $orderId   = (int)$pdo->lastInsertId();
            $orderCode = sprintf('DH%04d', $orderId);
            db_query('UPDATE orders SET code = ? WHERE id = ?', [$orderCode, $orderId]);

            foreach ($lockedItems as $item) {
                db_query(
                    'INSERT INTO order_items (order_id, product_id, product_name, price, quantity)
                     VALUES (?, ?, ?, ?, ?)',
                    [$orderId, $item['product_id'], $item['product_name'], $item['price'], $item['quantity']]
                );
                db_query(
                    'UPDATE products SET stock = stock - ? WHERE id = ?',
                    [$item['quantity'], $item['product_id']]
                );
            }

            db_query('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
            unset($_SESSION['cart']);

            $pdo->commit();

            flash('success', 'Đặt hàng thành công! Mã đơn hàng của bạn là ' . $orderCode . '.');
            redirect('checkout.php?code=' . urlencode($orderCode));
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }
    }
}

$pageTitle = 'Thanh toán';
require __DIR__ . '/includes/header.php';
$fallbackImg = url('assets/img/placeholder.svg');
$summaryTotal = 0.0;
?>

<h1 class="h2 mb-4">Thanh toán đơn hàng</h1>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Thông tin giao hàng</h2>
                <form method="post" action="<?= e(url('checkout.php')) ?>" id="checkoutForm">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="receiver_name">Họ tên người nhận</label>
                        <input type="text" id="receiver_name" name="receiver_name" class="form-control"
                               maxlength="100" value="<?= e($receiverName) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="receiver_phone">Số điện thoại</label>
                        <input type="text" id="receiver_phone" name="receiver_phone" class="form-control"
                               maxlength="20" value="<?= e($receiverPhone) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="shipping_address">Địa chỉ giao hàng</label>
                        <input type="text" id="shipping_address" name="shipping_address" class="form-control"
                               maxlength="255" value="<?= e($shippingAddress) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">Phương thức thanh toán</label>
                        <?php foreach ($paymentLabels as $key => $label): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method"
                                       id="pm_<?= e($key) ?>" value="<?= e($key) ?>"
                                       <?= $paymentMethod === $key ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pm_<?= e($key) ?>"><?= e($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="note">Ghi chú đơn hàng (tùy chọn)</label>
                        <textarea id="note" name="note" class="form-control" rows="3"
                                  maxlength="500" placeholder="Ví dụ: Giao giờ hành chính..."><?= e($note) ?></textarea>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-lg kz-btn-register">
                            <i class="bi bi-check2-circle me-1"></i> Đặt hàng
                        </button>
                        <a href="<?= e(url('cart.php')) ?>" class="btn btn-lg btn-outline-secondary">Quay lại giỏ hàng</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Tóm tắt đơn hàng</h2>
                <ul class="list-group list-group-flush mb-3">
                    <?php foreach ($cartRows as $row): ?>
                        <?php
                        $qty = (int)$row['quantity'];
                        $available = $row['name'] !== null && (int)$row['status'] === 1 && (int)$row['category_status'] === 1;
                        $unitPrice = $available ? effective_price($row) : 0.0;
                        $subtotal = $unitPrice * $qty;
                        $summaryTotal += $subtotal;
                        ?>
                        <li class="list-group-item px-0 py-2">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= e(product_image_url($row['image_path'] ?? null)) ?>"
                                         alt="<?= e($row['name'] ?? '') ?>" width="48" height="48"
                                         class="object-fit-contain rounded border bg-light"
                                         data-fallback="<?= e($fallbackImg) ?>">
                                    <div>
                                        <div class="fw-semibold small"><?= e($row['name'] ?? ('Sản phẩm #' . (int)$row['product_id'])) ?></div>
                                        <small class="text-muted">SL: <?= $qty ?> &times; <?= e(format_price($unitPrice)) ?></small>
                                        <?php if (!$available || (int)$row['stock'] < $qty): ?>
                                            <div><small class="text-danger">Không đủ tồn kho (còn <?= (int)($row['stock'] ?? 0) ?>)</small></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="fw-semibold text-nowrap"><?= e(format_price($subtotal)) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                    <span class="fw-bold">Tổng thanh toán</span>
                    <span class="fs-4 fw-bold text-danger"><?= e(format_price($summaryTotal)) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

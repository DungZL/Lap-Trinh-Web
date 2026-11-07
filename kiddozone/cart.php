<?php
require_once __DIR__ . '/includes/init.php';

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!is_string($action) || $productId === false) {
        flash('danger', 'Thông tin sản phẩm không hợp lệ.');
        redirect('cart.php');
    }

    $cart = session_cart();
    if ($action === 'remove') {
        unset($cart[$productId]);
        $_SESSION['cart'] = $cart;
        flash('success', 'Đã xóa sản phẩm khỏi giỏ hàng.');
        redirect('cart.php');
    }

    if (!in_array($action, ['add', 'update'], true) || $quantity === false) {
        flash('danger', 'Thao tác hoặc số lượng không hợp lệ.');
        redirect('cart.php');
    }

    $product = db_query('SELECT id, stock FROM products WHERE id = ? AND status = 1', [$productId])->fetch();
    if (!$product) {
        flash('danger', 'Sản phẩm không tồn tại hoặc không còn được bán.');
        redirect('cart.php');
    }

    $newQuantity = $action === 'add' ? ($cart[$productId] ?? 0) + $quantity : $quantity;
    if ((int)$product['stock'] < 1 || $newQuantity > (int)$product['stock']) {
        flash('danger', 'Số lượng yêu cầu vượt quá tồn kho hiện tại.');
        redirect('cart.php');
    }

    $cart[$productId] = $newQuantity;
    $_SESSION['cart'] = $cart;
    flash('success', $action === 'add' ? 'Đã thêm sản phẩm vào giỏ hàng.' : 'Đã cập nhật số lượng.');
    redirect('cart.php');
}

$cart = session_cart();
$productsById = [];
if ($cart) {
    $placeholders = implode(', ', array_fill(0, count($cart), '?'));
    $products = db_query(
        "SELECT p.id, p.name, p.price, p.sale_price, p.stock, p.status,
                (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image_path
         FROM products p
         WHERE p.id IN ($placeholders)",
        array_keys($cart)
    )->fetchAll();
    foreach ($products as $product) {
        $productsById[(int)$product['id']] = $product;
    }
}

$pageTitle = 'Giỏ hàng';
require __DIR__ . '/includes/header.php';
$total = 0;
?>
<h1 class="h2 mb-4">Giỏ hàng</h1>
<?php if (!$cart): ?>
    <div class="alert alert-info">Giỏ hàng đang trống.</div>
    <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Tiếp tục mua sắm</a>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th scope="col">Sản phẩm</th>
                    <th scope="col">Đơn giá</th>
                    <th scope="col">Số lượng</th>
                    <th scope="col">Tạm tính</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart as $productId => $quantity): ?>
                    <?php $product = $productsById[$productId] ?? null; ?>
                    <tr>
                        <td>
                            <?php if (!$product || !(int)$product['status']): ?>
                                <span class="text-danger">Sản phẩm #<?= (int)$productId ?> không còn khả dụng.</span>
                            <?php else: ?>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if ($product['image_path']): ?>
                                        <img src="<?= e(url($product['image_path'])) ?>" alt="" width="64" height="64" class="object-fit-contain">
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-semibold"><?= e($product['name']) ?></div>
                                        <?php if ($quantity > (int)$product['stock']): ?>
                                            <small class="text-danger">Chỉ còn <?= (int)$product['stock'] ?> sản phẩm trong kho.</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <?php if (!$product || !(int)$product['status']): ?>
                            <td>—</td>
                            <td>—</td>
                            <td>—</td>
                        <?php else: ?>
                            <?php $price = effective_price($product); $subtotal = $price * $quantity; $total += $subtotal; ?>
                            <td><?= e(format_price($price)) ?></td>
                            <td>
                                <form method="post" action="<?= e(url('cart.php')) ?>" class="d-flex gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
                                    <input type="number" name="quantity" value="<?= (int)$quantity ?>" min="1" max="<?= max(1, (int)$product['stock']) ?>" class="form-control" style="width: 90px" aria-label="Số lượng <?= e($product['name']) ?>">
                                    <button class="btn btn-outline-primary btn-sm" type="submit">Cập nhật</button>
                                </form>
                            </td>
                            <td><?= e(format_price($subtotal)) ?></td>
                        <?php endif; ?>
                        <td>
                            <form method="post" action="<?= e(url('cart.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
                                <button class="btn btn-outline-danger btn-sm" type="submit">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Tổng cộng</th>
                    <th><?= e(format_price($total)) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('index.php')) ?>">Tiếp tục mua sắm</a>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product_card.php';

if (is_post()) {
    csrf_verify();
    if (is_admin()) {
        flash('warning', 'Tài khoản Admin không sử dụng chức năng giỏ hàng.');
        redirect('cart.php');
    }

    $action = $_POST['action'] ?? '';
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!is_string($action) || $productId === false) {
        flash('danger', 'Thông tin sản phẩm không hợp lệ.');
        redirect('cart.php');
    }

    $cart = cart_items();
    if ($action === 'remove') {
        cart_remove_item($productId);
        flash('success', 'Đã xóa sản phẩm khỏi giỏ hàng.');
        redirect('cart.php');
    }

    if (!in_array($action, ['add', 'update'], true) || $quantity === false) {
        flash('danger', 'Thao tác hoặc số lượng không hợp lệ.');
        redirect('cart.php');
    }

    $product = db_query(
        'SELECT p.id, p.stock
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.id = ? AND p.status = 1 AND c.status = 1',
        [$productId]
    )->fetch();
    if (!$product) {
        flash('danger', 'Sản phẩm không tồn tại hoặc không còn được bán.');
        redirect('cart.php');
    }

    $stock = (int)$product['stock'];
    if ($stock < 1) {
        flash('danger', 'Sản phẩm hiện đã hết hàng.');
        redirect('cart.php');
    }

    $newQuantity = $action === 'add' ? ($cart[$productId] ?? 0) + $quantity : $quantity;
    if ($newQuantity > $stock) {
        cart_set_item($productId, $stock);
        flash('warning', 'Số lượng vượt quá tồn kho nên đã được giới hạn về mức tối đa (' . $stock . ' sản phẩm).');
        redirect('cart.php');
    }

    cart_set_item($productId, $newQuantity);
    flash('success', $action === 'add' ? 'Đã thêm sản phẩm vào giỏ hàng.' : 'Đã cập nhật số lượng.');
    redirect('cart.php');
}

$cart = cart_items();
$productsById = [];
if ($cart) {
    $placeholders = implode(', ', array_fill(0, count($cart), '?'));
    $products = db_query(
        "SELECT p.id, p.name, p.price, p.sale_price, p.stock, p.status, c.status AS category_status,
                (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image_path
         FROM products p
         JOIN categories c ON c.id = p.category_id
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
$fallbackImg = url('assets/img/placeholder.svg');
?>
<h1 class="h2 mb-4">Giỏ hàng</h1>
<?php if (!$cart): ?>
    <div class="alert alert-info">Giỏ hàng đang trống.</div>
    <a class="btn kz-btn-register" href="<?= e(url('products.php')) ?>">Tiếp tục mua sắm</a>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle bg-white shadow-sm rounded">
            <thead class="table-light">
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
                    <?php
                    $product = $productsById[$productId] ?? null;
                    $available = $product && (int)$product['status'] === 1 && (int)$product['category_status'] === 1;
                    ?>
                    <tr>
                        <td>
                            <?php if (!$available): ?>
                                <span class="text-danger">Sản phẩm #<?= (int)$productId ?> không còn khả dụng.</span>
                            <?php else: ?>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= e(product_image_url($product['image_path'] ?? null)) ?>"
                                         alt="<?= e($product['name']) ?>" width="64" height="64"
                                         class="object-fit-contain rounded border bg-light"
                                         data-fallback="<?= e($fallbackImg) ?>">
                                    <div>
                                        <a class="fw-semibold text-decoration-none text-dark"
                                           href="<?= e(url('product.php?id=' . (int)$product['id'])) ?>">
                                            <?= e($product['name']) ?>
                                        </a>
                                        <?php if ((int)$product['stock'] <= 0): ?>
                                            <div><small class="text-danger">Sản phẩm đã hết hàng.</small></div>
                                        <?php elseif ($quantity > (int)$product['stock']): ?>
                                            <div><small class="text-danger">Chỉ còn <?= (int)$product['stock'] ?> sản phẩm trong kho.</small></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <?php if (!$available): ?>
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
                                    <input type="number" name="quantity" value="<?= (int)$quantity ?>" min="1"
                                           max="<?= max(1, (int)$product['stock']) ?>" class="form-control"
                                           style="width: 90px" aria-label="Số lượng <?= e($product['name']) ?>">
                                    <button class="btn btn-outline-primary btn-sm" type="submit">Cập nhật</button>
                                </form>
                            </td>
                            <td class="fw-semibold"><?= e(format_price($subtotal)) ?></td>
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
                    <th class="text-danger fs-5"><?= e(format_price($total)) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <a class="btn btn-outline-secondary" href="<?= e(url('products.php')) ?>">
            <i class="bi bi-arrow-left me-1"></i> Tiếp tục mua sắm
        </a>
        <a class="btn btn-lg kz-btn-register" href="<?= e(url('checkout.php')) ?>">
            <i class="bi bi-bag-check me-1"></i> Thanh toán
        </a>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

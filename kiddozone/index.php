<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'Sản phẩm';
$products = db_query(
    'SELECT p.id, p.name, p.price, p.sale_price, p.stock,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image_path
     FROM products p
     WHERE p.status = 1 AND p.stock > 0
     ORDER BY p.is_featured DESC, p.created_at DESC, p.id DESC
     LIMIT 12'
)->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Đồ chơi cho bé</h1>
        <p class="text-muted mb-0">Chọn sản phẩm và thêm vào giỏ hàng.</p>
    </div>
</div>
<?php if (!$products): ?>
    <p class="alert alert-info">Hiện chưa có sản phẩm đang bán.</p>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($products as $product): ?>
            <?php $price = effective_price($product); ?>
            <div class="col-6 col-md-4 col-lg-3">
                <article class="card h-100">
                    <?php if ($product['image_path']): ?>
                        <img src="<?= e(url($product['image_path'])) ?>" class="card-img-top" alt="<?= e($product['name']) ?>" style="height: 190px; object-fit: contain;">
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h2 class="h6 card-title"><?= e($product['name']) ?></h2>
                        <p class="fw-semibold mb-3"><?= e(format_price($price)) ?></p>
                        <form method="post" action="<?= e(url('cart.php')) ?>" class="mt-auto">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn btn-primary w-100" type="submit">
                                <i class="bi bi-cart-plus" aria-hidden="true"></i> Thêm vào giỏ
                            </button>
                        </form>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
// Trang chủ (task F03): banner, danh mục, sản phẩm nổi bật, sản phẩm mới nhất.
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product_card.php';

const HOME_PRODUCT_LIMIT = 8;

// Danh mục đang hiển thị kèm số sản phẩm đang bán.
$categories = db_query(
    'SELECT c.id, c.name, c.description, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.status = 1
     WHERE c.status = 1
     GROUP BY c.id, c.name, c.description
     ORDER BY c.id',
    []
)->fetchAll();

// Câu SELECT chung cho 2 danh sách sản phẩm (kèm ảnh chính, nếu không có ảnh chính thì lấy ảnh đầu tiên).
$productSelect =
    'SELECT p.id, p.name, p.price, p.sale_price, p.stock, c.name AS category_name,
            (SELECT pi.image_path FROM product_images pi
              WHERE pi.product_id = p.id
              ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.status = 1 AND c.status = 1';

$featured = db_query(
    $productSelect . ' AND p.is_featured = 1 ORDER BY p.created_at DESC, p.id DESC LIMIT ' . HOME_PRODUCT_LIMIT,
    []
)->fetchAll();

$newest = db_query(
    $productSelect . ' ORDER BY p.created_at DESC, p.id DESC LIMIT ' . HOME_PRODUCT_LIMIT,
    []
)->fetchAll();

$categoryIcons = ['bi-lightbulb', 'bi-bricks', 'bi-balloon-heart', 'bi-car-front', 'bi-bicycle'];

$pageTitle = 'Trang chủ';
require __DIR__ . '/includes/header.php';
?>

<!-- Banner -->
<section class="kz-hero mb-5">
    <div class="row align-items-center g-4">
        <div class="col-md-7">
            <span class="kz-hero-tag">Đồ chơi cho bé từ 0 đến 12 tuổi</span>
            <h1 class="kz-hero-title">Chơi vui mỗi ngày, bé học mỗi giờ</h1>
            <p class="lead mb-4">Đồ chơi an toàn, vui và hữu ích, được chọn lọc cho từng độ tuổi.</p>
            <a class="btn btn-lg kz-btn-register" href="<?= e(url('products.php')) ?>">Xem sản phẩm</a>
        </div>
        <div class="col-md-5 d-none d-md-flex justify-content-center" aria-hidden="true">
            <div class="kz-blocks">
                <span class="kz-block kz-block-1">K</span>
                <span class="kz-block kz-block-2">I</span>
                <span class="kz-block kz-block-3">D</span>
            </div>
        </div>
    </div>
</section>

<!-- Danh mục -->
<section class="mb-5">
    <h2 class="kz-section-title">Danh mục sản phẩm</h2>
    <?php if (!$categories): ?>
        <p class="text-muted">Chưa có danh mục nào.</p>
    <?php else: ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3">
            <?php foreach ($categories as $i => $c): ?>
                <div class="col">
                    <a class="kz-category kz-cat-<?= $i % 5 ?>" href="<?= e(url('products.php?category=' . (int)$c['id'])) ?>">
                        <i class="bi <?= e($categoryIcons[$i % count($categoryIcons)]) ?>"></i>
                        <span class="kz-category-name"><?= e($c['name']) ?></span>
                        <small><?= (int)$c['product_count'] ?> sản phẩm</small>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Sản phẩm nổi bật -->
<section class="mb-5">
    <div class="d-flex justify-content-between align-items-end mb-3">
        <h2 class="kz-section-title mb-0">Sản phẩm nổi bật</h2>
        <a href="<?= e(url('products.php')) ?>">Xem tất cả</a>
    </div>
    <?php if (!$featured): ?>
        <p class="text-muted">Chưa có sản phẩm nổi bật.</p>
    <?php else: ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
            <?php foreach ($featured as $p) { render_product_card($p); } ?>
        </div>
    <?php endif; ?>
</section>

<!-- Sản phẩm mới nhất -->
<section>
    <div class="d-flex justify-content-between align-items-end mb-3">
        <h2 class="kz-section-title mb-0">Sản phẩm mới nhất</h2>
        <a href="<?= e(url('products.php')) ?>">Xem tất cả</a>
    </div>
    <?php if (!$newest): ?>
        <p class="text-muted">Chưa có sản phẩm nào.</p>
    <?php else: ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
            <?php foreach ($newest as $p) { render_product_card($p); } ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
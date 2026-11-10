<?php
// Danh sách sản phẩm (task F04): lọc theo danh mục, tìm theo tên, sắp xếp, phân trang 12 sản phẩm/trang.
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product_card.php';

/* ---------- 1. Đọc và kiểm tra tham số trên URL ---------- */

$qRaw = $_GET['q'] ?? '';
$q = is_string($qRaw) ? mb_substr(trim($qRaw), 0, 100) : '';

$catRaw = $_GET['category'] ?? 0;
$categoryId = is_string($catRaw) ? (int)$catRaw : 0;

// sale=1: chỉ lấy sản phẩm đang giảm giá (mục "Khuyến mãi" trên menu).
$onSale = ($_GET['sale'] ?? '') === '1';

// Giá đang bán: ưu tiên giá giảm nếu có. Dùng khi sắp xếp theo giá.
$finalPrice = 'IF(p.sale_price IS NOT NULL AND p.sale_price > 0, p.sale_price, p.price)';

// Danh sách trắng kiểu sắp xếp: chỉ các khóa này mới được đưa vào ORDER BY.
$sortOptions = [
    'newest'     => ['Mới nhất',          'p.created_at DESC, p.id DESC'],
    'price_asc'  => ['Giá thấp đến cao',  $finalPrice . ' ASC, p.id DESC'],
    'price_desc' => ['Giá cao đến thấp',  $finalPrice . ' DESC, p.id DESC'],
    'name_asc'   => ['Tên A đến Z',       'p.name ASC, p.id DESC'],
];
$sortRaw = $_GET['sort'] ?? 'newest';
$sort = (is_string($sortRaw) && isset($sortOptions[$sortRaw])) ? $sortRaw : 'newest';

// Danh mục đang bật, dùng cho thanh chọn danh mục.
$categories = db_query('SELECT id, name FROM categories WHERE status = 1 ORDER BY id', [])->fetchAll();
$categoryNames = array_column($categories, 'name', 'id');
if (!isset($categoryNames[$categoryId])) {
    $categoryId = 0; // danh mục không tồn tại hoặc đang ẩn thì bỏ qua bộ lọc
}

/* ---------- 2. Truy vấn (mọi giá trị người dùng đều đi qua tham số ?) ---------- */

$where  = ['p.status = 1', 'c.status = 1'];
$params = [];

if ($q !== '') {
    $where[]  = 'p.name LIKE ?';
    $params[] = '%' . addcslashes($q, '%_\\') . '%'; // chặn ký tự đại diện của LIKE
}
if ($categoryId > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($onSale) {
    $where[] = 'p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price';
}
$whereSql = implode(' AND ', $where);

$total = (int)db_query(
    "SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE $whereSql",
    $params
)->fetchColumn();

[$offset, $pages, $page] = paginate($total, ITEMS_PER_PAGE_SHOP);

$products = db_query(
    "SELECT p.id, p.name, p.price, p.sale_price, p.stock, c.name AS category_name,
            (SELECT pi.image_path FROM product_images pi
              WHERE pi.product_id = p.id
              ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE $whereSql
     ORDER BY {$sortOptions[$sort][1]}
     LIMIT " . (int)ITEMS_PER_PAGE_SHOP . ' OFFSET ' . (int)$offset,
    $params
)->fetchAll();

/* ---------- 3. Chuẩn bị hiển thị ---------- */

/** Tạo link tới products.php, giữ các bộ lọc hiện có và cho phép ghi đè một vài giá trị (luôn về trang 1). */
$linkTo = function (array $override = []) use ($q, $categoryId, $sort, $onSale): string {
    $f = array_merge(['q' => $q, 'category' => $categoryId, 'sort' => $sort, 'sale' => $onSale], $override);
    $qs = [];
    if ($f['q'] !== '')          { $qs['q'] = $f['q']; }
    if ((int)$f['category'] > 0) { $qs['category'] = (int)$f['category']; }
    if ($f['sale'])              { $qs['sale'] = 1; }
    if ($f['sort'] !== 'newest') { $qs['sort'] = $f['sort']; }
    return url('products.php' . ($qs ? '?' . http_build_query($qs) : ''));
};

$hasFilter = ($q !== '' || $categoryId > 0 || $onSale || $sort !== 'newest');

$pageTitle = $categoryId > 0 ? $categoryNames[$categoryId] : ($onSale ? 'Khuyến mãi' : 'Sản phẩm');
require __DIR__ . '/includes/header.php';
?>

<h1 class="kz-section-title">Sản phẩm</h1>

<!-- Chọn danh mục -->
<ul class="kz-pills" aria-label="Danh mục">
    <li><a class="kz-pill <?= $categoryId === 0 ? 'active' : '' ?>" href="<?= e($linkTo(['category' => 0])) ?>">Tất cả</a></li>
    <?php foreach ($categories as $c): ?>
        <li>
            <a class="kz-pill <?= $categoryId === (int)$c['id'] ? 'active' : '' ?>"
               href="<?= e($linkTo(['category' => (int)$c['id']])) ?>"><?= e($c['name']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- Tìm theo tên và sắp xếp -->
<form class="kz-toolbar row g-2 align-items-center mb-3" method="get" action="<?= e(url('products.php')) ?>">
    <?php if ($categoryId > 0): ?>
        <input type="hidden" name="category" value="<?= $categoryId ?>">
    <?php endif; ?>
    <?php if ($onSale): ?>
        <input type="hidden" name="sale" value="1">
    <?php endif; ?>
    <div class="col-12 col-md">
        <input class="form-control" type="search" name="q" maxlength="100"
               placeholder="Tìm theo tên sản phẩm" aria-label="Tìm theo tên sản phẩm" value="<?= e($q) ?>">
    </div>
    <div class="col-8 col-md-auto">
        <select class="form-select" name="sort" aria-label="Sắp xếp" data-autosubmit>
            <?php foreach ($sortOptions as $key => $opt): ?>
                <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($opt[0]) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-4 col-md-auto">
        <button class="btn kz-btn-filter w-100" type="submit">Lọc</button>
    </div>
</form>

<p class="text-muted">
    Tìm thấy <strong><?= $total ?></strong> sản phẩm<?php if ($q !== ''): ?> cho từ khóa “<strong><?= e($q) ?></strong>”<?php endif; ?><?php if ($categoryId > 0): ?> trong <strong><?= e($categoryNames[$categoryId]) ?></strong><?php endif; ?><?php if ($onSale): ?> đang <strong>khuyến mãi</strong><?php endif; ?>.
    <?php if ($hasFilter): ?><a class="ms-1" href="<?= e(url('products.php')) ?>">Xóa bộ lọc</a><?php endif; ?>
</p>

<?php if (!$products): ?>
    <div class="text-center py-5">
        <i class="bi bi-emoji-frown fs-1 text-muted"></i>
        <p class="mt-2 mb-3">Không tìm thấy sản phẩm phù hợp.</p>
        <a class="btn kz-btn-register" href="<?= e(url('products.php')) ?>">Xem tất cả sản phẩm</a>
    </div>
<?php else: ?>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 mb-4">
        <?php foreach ($products as $p) { render_product_card($p); } ?>
    </div>
    <?= render_pagination($page, $pages) /* link phân trang giữ nguyên q, category, sort */ ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
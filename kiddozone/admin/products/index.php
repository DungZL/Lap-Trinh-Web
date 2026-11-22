<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$pageTitle = 'Quản lý sản phẩm';

$qRaw = $_GET['q'] ?? '';
$search = is_string($qRaw) ? trim(mb_substr($qRaw, 0, 100)) : '';

$catRaw = $_GET['category'] ?? '';
$categoryId = is_string($catRaw) ? (int)$catRaw : 0;

$categories = db_query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$where = [];
$params = [];
if ($search !== '') {
    $where[] = 'p.name LIKE ?';
    $params[] = '%' . addcslashes($search, '%_\\') . '%';
}
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Phân trang (TC16)
$total = (int)db_query(
    "SELECT COUNT(*) FROM products p $whereSql",
    $params
)->fetchColumn();

[$offset, $pages, $page] = paginate(
    $total,
    ITEMS_PER_PAGE_ADMIN
);

// Lấy sản phẩm, danh mục và ảnh chính
$products = db_query(
    "SELECT
        p.id,
        p.name,
        p.price,
        p.sale_price,
        p.stock,
        p.status,
        p.is_featured,
        p.age_min,
        p.age_max,
        p.flash_sale_price,
        p.flash_sale_start,
        p.flash_sale_end,
        c.name AS category_name,
        (
            SELECT pi.image_path
            FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_main DESC, pi.id ASC
            LIMIT 1
        ) AS image_path
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     $whereSql
     ORDER BY p.id DESC
     LIMIT " . (int)ITEMS_PER_PAGE_ADMIN .
     ' OFFSET ' . (int)$offset,
    $params
)->fetchAll();

$pageSubtitle = 'Tổng cộng ' . $total . ' sản phẩm';
$pageActions = '<a href="' . e(url('admin/products/create.php')) . '" class="btn btn-primary"><i class="ph-bold ph-plus" aria-hidden="true"></i> Thêm sản phẩm</a>';
require __DIR__ . '/../admin_header.php';
?>

<div class="card">
    <form method="get" action="<?= e(url('admin/products/index.php')) ?>" class="flex flex-wrap gap-2 border-b border-ink-100 p-4 dark:border-ink-800">
        <div class="relative min-w-60 flex-1">
            <i class="ph-bold ph-magnifying-glass absolute top-1/2 left-4 -translate-y-1/2 text-muted" aria-hidden="true"></i>
            <input type="search" name="q" class="input pl-11" maxlength="100" value="<?= e($search) ?>" placeholder="Tìm theo tên sản phẩm" aria-label="Tìm theo tên sản phẩm">
        </div>
        <select name="category" class="select w-auto" aria-label="Lọc danh mục" data-autosubmit>
            <option value="">Tất cả danh mục</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary"><i class="ph-bold ph-magnifying-glass" aria-hidden="true"></i> Tìm kiếm</button>
        <?php if ($search !== '' || $categoryId > 0): ?>
            <a href="<?= e(url('admin/products/index.php')) ?>" class="btn btn-ghost">Xóa lọc</a>
        <?php endif; ?>
    </form>

    <div class="overflow-x-auto p-2">
        <table class="table-kz">
            <thead><tr><th>Sản phẩm</th><th>Danh mục</th><th>Giá</th><th>Tồn kho</th><th>Trạng thái</th><th class="text-right">Thao tác</th></tr></thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <?php $final = effective_price($product); $flash = flash_active($product); $stock = (int)$product['stock']; ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <img src="<?= e(product_image_url($product['image_path'])) ?>" alt="" class="size-14 rounded-2xl object-cover" data-fallback="<?= e(url('assets/img/placeholder.svg')) ?>">
                                <div>
                                    <b class="block"><?= e($product['name']) ?></b>
                                    <span class="flex flex-wrap gap-1 pt-1">
                                        <span class="text-xs text-muted">#<?= (int)$product['id'] ?> · <?= e(age_label((int)$product['age_min'], (int)$product['age_max'])) ?></span>
                                        <?php if ((int)$product['is_featured'] === 1): ?><span class="badge badge-sun"><i class="ph-fill ph-star" aria-hidden="true"></i> Nổi bật</span><?php endif; ?>
                                        <?php if ($flash): ?><span class="badge badge-coral"><i class="ph-fill ph-lightning" aria-hidden="true"></i> Flash</span><?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="text-ink-500"><?= e($product['category_name']) ?></td>
                        <td class="whitespace-nowrap">
                            <b class="block <?= $final < (float)$product['price'] ? 'text-price' : '' ?>"><?= e(format_price($final)) ?></b>
                            <?php if ($final < (float)$product['price']): ?><span class="text-xs text-muted line-through"><?= e(format_price($product['price'])) ?></span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($stock === 0): ?>
                                <span class="badge bg-coral-600 text-white">Hết hàng</span>
                            <?php elseif ($stock <= LOW_STOCK_THRESHOLD): ?>
                                <span class="badge badge-sun"><i class="ph-fill ph-warning" aria-hidden="true"></i> <?= $stock ?></span>
                            <?php else: ?>
                                <b><?= $stock ?></b>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$product['status'] === 1 ? '<span class="badge badge-mint">Hiển thị</span>' : '<span class="badge badge-ink">Đang ẩn</span>' ?></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="<?= e(url('admin/products/edit.php?id=' . (int)$product['id'])) ?>" class="btn btn-ghost btn-sm"><i class="ph-bold ph-pencil-simple" aria-hidden="true"></i> Sửa</a>
                            <form action="<?= e(url('admin/products/delete.php')) ?>" method="post" class="inline" data-confirm="Bạn có chắc muốn xóa sản phẩm này?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                <button type="submit" class="btn btn-danger-ghost btn-sm" aria-label="Xóa <?= e($product['name']) ?>"><i class="ph-bold ph-trash" aria-hidden="true"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$products): ?>
                    <tr><td colspan="6" class="py-10 text-center text-muted">Chưa có sản phẩm nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= render_pagination($page, $pages) ?>

<?php require __DIR__ . '/../admin_footer.php'; ?>


<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$pageTitle = 'Quản lý sản phẩm';

// Phân trang
$total = (int) db_query(
    'SELECT COUNT(*) FROM products'
)->fetchColumn();

[$offset, $pages, $page] = paginate(
    $total,
    ITEMS_PER_PAGE_ADMIN
);

// Lấy sản phẩm, danh mục và ảnh chính
$products = db_query(
    'SELECT
        p.id,
        p.name,
        p.price,
        p.sale_price,
        p.stock,
        p.status,
        p.is_featured,
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
     ORDER BY p.id DESC
     LIMIT ' . (int) ITEMS_PER_PAGE_ADMIN .
     ' OFFSET ' . (int) $offset
)->fetchAll();

require __DIR__ . '/../admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Quản lý sản phẩm</h2>

    <a href="create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        Thêm sản phẩm
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Ảnh</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Giá</th>
                    <th>Tồn kho</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($products as $product): ?>
                    <?php
                    $path = $product['image_path'];

                    $imageUrl = !$path
                        ? url('assets/img/placeholder.svg')
                        : (str_starts_with($path, 'assets/')
                            ? url($path)
                            : url('uploads/' . $path));
                    ?>

                    <tr>
                        <td><?= (int) $product['id'] ?></td>

                        <td>
                            <img
                                src="<?= e($imageUrl) ?>"
                                alt="<?= e($product['name']) ?>"
                                width="65"
                                height="65"
                                style="object-fit:cover;border-radius:6px"
                            >
                        </td>

                        <td>
                            <strong><?= e($product['name']) ?></strong>

                            <?php if ((int)$product['is_featured'] === 1): ?>
                                <span class="badge bg-info">
                                    Nổi bật
                                </span>
                            <?php endif; ?>
                        </td>

                        <td><?= e($product['category_name']) ?></td>

                        <td>
                            <?php if ($product['sale_price'] !== null): ?>
                                <div class="text-danger fw-bold">
                                    <?= format_price($product['sale_price']) ?>
                                </div>
                                <small class="text-muted text-decoration-line-through">
                                    <?= format_price($product['price']) ?>
                                </small>
                            <?php else: ?>
                                <?= format_price($product['price']) ?>
                            <?php endif; ?>
                        </td>

                        <td><?= (int)$product['stock'] ?></td>

                        <td>
                            <?php if ((int)$product['status'] === 1): ?>
                                <span class="badge bg-success">
                                    Hiển thị
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">
                                    Đang ẩn
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="text-nowrap">
                            <a
                                href="edit.php?id=<?= (int)$product['id'] ?>"
                                class="btn btn-warning btn-sm"
                            >
                                <i class="bi bi-pencil-square"></i>
                                Sửa
                            </a>

                            <form
                                action="delete.php"
                                method="post"
                                class="d-inline"
                                onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?')"
                            >
                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$product['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-danger btn-sm"
                                >
                                    <i class="bi bi-trash"></i>
                                    Xóa
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$products): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            Chưa có sản phẩm nào.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    <?= render_pagination($page, $pages) ?>
</div>

<?php require __DIR__ . '/../admin_footer.php'; ?>

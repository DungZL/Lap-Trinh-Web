<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin(); // Chỉ Admin được truy cập

$pageTitle = 'Quản lý danh mục';

// Tổng số danh mục và phân trang 10 dòng/trang (ITEMS_PER_PAGE_ADMIN).
$total = (int)db_query('SELECT COUNT(*) FROM categories')->fetchColumn();
[$offset, $pages, $page] = paginate($total, ITEMS_PER_PAGE_ADMIN);

// Danh mục của trang hiện tại, kèm số sản phẩm (để biết danh mục nào không xóa được).
$categories = db_query(
    'SELECT c.id, c.name, c.description, c.status, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name, c.description, c.status
     ORDER BY c.id ASC
     LIMIT ' . (int)ITEMS_PER_PAGE_ADMIN . ' OFFSET ' . (int)$offset
)->fetchAll();

require __DIR__ . '/../admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Quản lý danh mục</h2>
    <a href="create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Thêm danh mục
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Tên danh mục</th>
                    <th>Mô tả</th>
                    <th class="text-center">Số sản phẩm</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?= (int)$category['id'] ?></td>
                        <td><?= e($category['name']) ?></td>
                        <td><?= e($category['description'] ?? '') ?></td>
                        <td class="text-center"><?= (int)$category['product_count'] ?></td>
                        <td>
                            <?php if ((int)$category['status'] === 1): ?>
                                <span class="badge bg-success">Hiển thị</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Đang ẩn</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <a href="edit.php?id=<?= (int)$category['id'] ?>" class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil-square"></i> Sửa
                            </a>
                            <form action="delete.php" method="post" class="d-inline"
                                  data-confirm="Bạn có chắc muốn xóa danh mục này?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="bi bi-trash"></i> Xóa
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$categories): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">Chưa có danh mục nào.</td>
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

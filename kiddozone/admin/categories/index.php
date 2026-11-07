
<?php
require_once __DIR__ . '/../../includes/init.php';

// Chỉ Admin được truy cập
require_admin();

$pageTitle = 'Quản lý danh mục';

// Lấy danh sách danh mục từ MySQL


// Số danh mục mỗi trang
$limit = 10;

// Trang hiện tại
$page = max(1, (int)($_GET['page'] ?? 1));

// Tổng số danh mục
$total = (int) db_query(
    "SELECT COUNT(*) FROM categories"
)->fetchColumn();

// Tổng số trang
$totalPages = max(1, (int)ceil($total / $limit));

// Không cho vượt quá trang cuối
$page = min($page, $totalPages);

// Vị trí bắt đầu lấy dữ liệu
$offset = ($page - 1) * $limit;

// Lấy danh mục theo trang
$categories = db_query(
    "SELECT id, name, description, status
     FROM categories
     ORDER BY id ASC
     LIMIT $limit OFFSET $offset"
)->fetchAll();


// Sử dụng giao diện Admin
require __DIR__ . '/../admin_header.php';
?>

<?php if (!empty($_SESSION['category_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= e($_SESSION['category_success']) ?>

        <button type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Đóng"></button>
    </div>

    <?php unset($_SESSION['category_success']); ?>
<?php endif; ?>


<?php if (!empty($_SESSION['category_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= e($_SESSION['category_error']) ?>

        <button type="button"
                class="btn-close"
                data-bs-dismiss="alert">
        </button>
    </div>

    <?php unset($_SESSION['category_error']); ?>
<?php endif; ?>


<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold">Quản lý danh mục</h2>

    <a href="create.php" class="btn btn-primary">
    <i class="bi bi-plus-circle"></i> Thêm danh mục
</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>

                    <th>ID</th>
                    <th>Tên danh mục</th>
                    <th>Mô tả</th>
                    <th>Trạng thái</th>
                    <th>Thao tác </th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?= (int)$category['id'] ?></td>

                        <td><?= e($category['name']) ?></td>

                        <td><?= e($category['description'] ?? '') ?></td>

                        <td>
                            <?php if ($category['status'] == 1): ?>
                                <span class="badge bg-success">
                                    Hiển thị
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">
                                    Đang ẩn
                                </span>
                            <?php endif; ?>
                            
<td>
    <a href="edit.php?id=<?= (int)$category['id'] ?>"
       class="btn btn-warning btn-sm">
        <i class="bi bi-pencil-square"></i> Sửa
    </a>
    
<form action="delete.php"
      method="POST"
      class="d-inline"
      onsubmit="return confirm('Bạn có chắc muốn xóa danh mục này?');">

    <?= csrf_field() ?>

    <input type="hidden"
           name="id"
           value="<?= (int)$category['id'] ?>">

    <button type="submit" class="btn btn-danger btn-sm">
        <i class="bi bi-trash"></i> Xóa
    </button>
</form>

</td>

                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            Chưa có danh mục nào.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav aria-label="Phân trang danh mục">
    <ul class="pagination justify-content-center mt-4">

        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?page=<?= max(1, $page - 1) ?>">
                Trước
            </a>
        </li>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>">
                    <?= $i ?>
                </a>
            </li>
        <?php endfor; ?>

        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?page=<?= min($totalPages, $page + 1) ?>">
                Sau
            </a>
        </li>

    </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/../admin_footer.php'; ?>

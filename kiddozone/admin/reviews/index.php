<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$pageTitle = 'Quản lý đánh giá';

// Lọc đánh giá theo trạng thái.
$filter = $_GET['status'] ?? 'all';

if (!in_array($filter, ['all', 'visible', 'hidden'], true)) {
    $filter = 'all';
}

$where = '';
$params = [];

if ($filter === 'visible') {
    $where = ' WHERE r.status = ?';
    $params[] = 1;
} elseif ($filter === 'hidden') {
    $where = ' WHERE r.status = ?';
    $params[] = 0;
}

// Thống kê đánh giá.
$stats = db_query(
    'SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 1), 0) AS visible_count,
        COALESCE(SUM(status = 0), 0) AS hidden_count
     FROM reviews'
)->fetch();

// Tổng số đánh giá theo bộ lọc.
$total = (int)db_query(
    'SELECT COUNT(*) FROM reviews r' . $where,
    $params
)->fetchColumn();

// Phân trang.
[$offset, $pages, $page] = paginate(
    $total,
    ITEMS_PER_PAGE_ADMIN
);

// Danh sách đánh giá.
$reviews = db_query(
    'SELECT
        r.id,
        r.rating,
        r.comment,
        r.status,
        r.created_at,
        u.full_name,
        p.name AS product_name
     FROM reviews r
     LEFT JOIN users u ON u.id = r.user_id
     LEFT JOIN products p ON p.id = r.product_id'
    . $where .
    ' ORDER BY r.created_at DESC, r.id DESC
      LIMIT ' . (int)ITEMS_PER_PAGE_ADMIN .
    ' OFFSET ' . (int)$offset,
    $params
)->fetchAll();

require __DIR__ . '/../admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">
        <i class="bi bi-star-fill text-warning"></i>
        Quản lý đánh giá
    </h2>
</div>

<!-- Thống kê -->
<div class="row g-3 mb-4">

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <p class="text-muted mb-2">Tổng đánh giá</p>
                <h3 class="fw-bold">
                    <?= (int)$stats['total'] ?>
                </h3>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <p class="text-muted mb-2">Đang hiển thị</p>
                <h3 class="fw-bold text-success">
                    <?= (int)$stats['visible_count'] ?>
                </h3>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <p class="text-muted mb-2">Đang ẩn</p>
                <h3 class="fw-bold text-secondary">
                    <?= (int)$stats['hidden_count'] ?>
                </h3>
            </div>
        </div>
    </div>

</div>

<!-- Danh sách -->
<div class="card shadow-sm border-0">
    <div class="card-body">

        <div class="d-flex justify-content-between
                    align-items-center mb-3">
            <h5 class="fw-bold mb-0">Danh sách đánh giá</h5>

            <form method="get" class="d-flex gap-2">
                <select name="status" class="form-select">
                    <option value="all"
                        <?= $filter === 'all' ? 'selected' : '' ?>>
                        Tất cả
                    </option>

                    <option value="visible"
                        <?= $filter === 'visible' ? 'selected' : '' ?>>
                        Hiển thị
                    </option>

                    <option value="hidden"
                        <?= $filter === 'hidden' ? 'selected' : '' ?>>
                        Đang ẩn
                    </option>
                </select>

                <button type="submit" class="btn btn-primary">
                    Lọc
                </button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">

                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Khách hàng</th>
                        <th>Sản phẩm</th>
                        <th>Số sao</th>
                        <th>Nội dung</th>
                        <th>Ngày đánh giá</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($reviews as $review): ?>
                    <tr>
                        <td>
                            <?= (int)$review['id'] ?>
                        </td>

                        <td>
                            <?= e($review['full_name'] ?? 'Không xác định') ?>
                        </td>

                        <td>
                            <?= e($review['product_name'] ?? 'Sản phẩm đã xóa') ?>
                        </td>

                        <td class="text-warning text-nowrap">
                            <?= str_repeat('★', (int)$review['rating']) ?>
                            <span class="text-muted">
                                (<?= (int)$review['rating'] ?>/5)
                            </span>
                        </td>

                        <td style="min-width:180px; max-width:300px;
                                   white-space:pre-wrap; overflow-wrap:anywhere;"><?= e($review['comment'] ?? '') ?></td>

                        <td class="text-nowrap">
                            <?= e($review['created_at']) ?>
                        </td>

                        <td>
                            <?php if ((int)$review['status'] === 1): ?>
                                <span class="badge bg-success">
                                    Hiển thị
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">
                                    Đang ẩn
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="d-flex gap-2">

                                <!-- Ẩn / Hiện -->
                                <form action="toggle.php"
                                      method="post">
                                    <?= csrf_field() ?>

                                    <input type="hidden"
                                           name="id"
                                           value="<?= (int)$review['id'] ?>">

                                    <button type="submit"
                                            class="btn btn-warning btn-sm">
                                        <i class="bi bi-eye"></i>
                                        <?= (int)$review['status'] === 1
                                            ? 'Ẩn' : 'Hiện' ?>
                                    </button>
                                </form>

                                <!-- Xóa -->
                                <form action="delete.php"
                                      method="post"
                                      onsubmit="return confirm('Bạn có chắc muốn xóa đánh giá này?');">
                                    <?= csrf_field() ?>

                                    <input type="hidden"
                                           name="id"
                                           value="<?= (int)$review['id'] ?>">

                                    <button type="submit"
                                            class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash"></i>
                                        Xóa
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$reviews): ?>
                    <tr>
                        <td colspan="8"
                            class="text-center text-muted py-4">
                            Chưa có đánh giá nào.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Phân trang -->
<div class="mt-4">
    <?= render_pagination($page, $pages) ?>
</div>

<?php require __DIR__ . '/../admin_footer.php'; ?>

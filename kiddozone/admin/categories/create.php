

<?php
require_once __DIR__ . '/../../includes/init.php';

require_admin();

$pageTitle = 'Thêm danh mục';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = (int)($_POST['status'] ?? 1);

    if ($name === '') {
        $error = 'Vui lòng nhập tên danh mục.';
    } elseif (mb_strlen($name) > 255) {
        $error = 'Tên danh mục quá dài.';
    } elseif (!in_array($status, [0, 1], true)) {
        $error = 'Trạng thái không hợp lệ.';
    } else {
        db_query(
            "INSERT INTO categories (name, description, status)
             VALUES (?, ?, ?)",
            [$name, $description, $status]
        );

        
$_SESSION['category_success'] = 'Thêm danh mục thành công!';
header('Location: index.php');
exit;

    }
}

require __DIR__ . '/../admin_header.php';
?>


<h2 class="fw-bold mb-4">Thêm danh mục mới</h2>

<div class="card shadow-sm border-0">
    <div class="card-body">

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>
<?php endif; ?>

        <form method="POST">
<?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Tên danh mục</label>
                <input type="text"
                       name="name"
                       class="form-control"
                       placeholder="Nhập tên danh mục"
                       required>
            </div>

            <div class="mb-3">
                <label class="form-label">Mô tả</label>
                <textarea name="description"
                          class="form-control"
                          rows="4"
                          placeholder="Nhập mô tả danh mục"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="1">Hiển thị</option>
                    <option value="0">Ẩn</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" >
                <i class="bi bi-save"></i> Lưu danh mục
            </button>

            <a href="index.php" class="btn btn-secondary">
                Quay lại
            </a>

        </form>

    </div>
</div>

<?php require __DIR__ . '/../admin_footer.php'; ?>

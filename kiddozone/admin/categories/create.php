<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$pageTitle = 'Thêm danh mục';
$error = '';

if (is_post()) {
    csrf_verify();

    $name        = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $status      = filter_var($_POST['status'] ?? null, FILTER_VALIDATE_INT);

    // Giới hạn độ dài khớp với CSDL: name VARCHAR(100), description VARCHAR(255).
    if ($name === '') {
        $error = 'Vui lòng nhập tên danh mục.';
    } elseif (mb_strlen($name) > 100) {
        $error = 'Tên danh mục tối đa 100 ký tự.';
    } elseif (mb_strlen($description) > 255) {
        $error = 'Mô tả tối đa 255 ký tự.';
    } elseif (!in_array($status, [0, 1], true)) {
        $error = 'Trạng thái không hợp lệ.';
    } else {
        db_query(
            'INSERT INTO categories (name, description, status) VALUES (?, ?, ?)',
            [$name, $description !== '' ? $description : null, $status]
        );
        flash('success', 'Thêm danh mục thành công!');
        redirect('admin/categories/index.php');
    }
}

require __DIR__ . '/../admin_header.php';
?>

<h2 class="fw-bold mb-4">Thêm danh mục mới</h2>

<div class="card shadow-sm border-0">
    <div class="card-body">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="name">Tên danh mục</label>
                <input type="text" id="name" name="name" class="form-control" maxlength="100"
                       placeholder="Nhập tên danh mục" value="<?= old('name') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label" for="description">Mô tả</label>
                <textarea id="description" name="description" class="form-control" rows="4" maxlength="255"
                          placeholder="Nhập mô tả danh mục"><?= old('description') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label" for="status">Trạng thái</label>
                <select id="status" name="status" class="form-select">
                    <option value="1">Hiển thị</option>
                    <option value="0" <?= ($_POST['status'] ?? '') === '0' ? 'selected' : '' ?>>Ẩn</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Lưu danh mục
            </button>
            <a href="index.php" class="btn btn-secondary">Quay lại</a>
        </form>

    </div>
</div>

<?php require __DIR__ . '/../admin_footer.php'; ?>

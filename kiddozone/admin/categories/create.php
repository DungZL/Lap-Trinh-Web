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

$catForm = ['name' => (string)($_POST['name'] ?? ''), 'description' => (string)($_POST['description'] ?? ''), 'status' => (string)($_POST['status'] ?? '1')];
require __DIR__ . '/../admin_header.php';
?>
<div class="card max-w-2xl p-6 sm:p-8">
    <?php if ($error): ?>
        <div class="alert alert-danger mb-5" role="alert"><i class="ph-fill ph-warning-circle text-lg" aria-hidden="true"></i> <?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="label" for="name">Tên danh mục</label>
            <input type="text" id="name" name="name" class="input" maxlength="100" placeholder="Nhập tên danh mục" value="<?= e($catForm['name']) ?>" required>
        </div>
        <div>
            <label class="label" for="description">Mô tả</label>
            <textarea id="description" name="description" class="textarea" rows="4" maxlength="255" placeholder="Nhập mô tả danh mục"><?= e($catForm['description']) ?></textarea>
        </div>
        <div>
            <label class="label" for="status">Trạng thái</label>
            <select id="status" name="status" class="select">
                <option value="1" data-icon="ph-eye" <?= (string)$catForm['status'] === '1' ? 'selected' : '' ?>>Hiển thị</option>
                <option value="0" data-icon="ph-eye-slash" <?= (string)$catForm['status'] === '0' ? 'selected' : '' ?>>Ẩn</option>
            </select>
        </div>
        <div class="flex gap-2 pt-2">
            <button type="submit" class="btn btn-primary"><i class="ph-bold ph-floppy-disk" aria-hidden="true"></i> Lưu danh mục</button>
            <a href="<?= e(url('admin/categories/index.php')) ?>" class="btn btn-ghost">Quay lại</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../admin_footer.php'; ?>

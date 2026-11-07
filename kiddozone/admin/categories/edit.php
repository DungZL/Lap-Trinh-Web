
<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$pageTitle = 'Sửa danh mục';
$error = '';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID danh mục không hợp lệ.');
}

$category = db_query(
    "SELECT * FROM categories WHERE id = ?",
    [$id]
)->fetch();

if (!$category) {
    http_response_code(404);
    exit('Không tìm thấy danh mục.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = filter_var(
        $_POST['status'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($name === '') {
        $error = 'Vui lòng nhập tên danh mục.';
    } elseif (mb_strlen($name) > 255) {
        $error = 'Tên danh mục quá dài.';
    } elseif (!in_array($status, [0, 1], true)) {
        $error = 'Trạng thái không hợp lệ.';
    } else {
        db_query(
            "UPDATE categories
             SET name = ?, description = ?, status = ?
             WHERE id = ?",
            [$name, $description, $status, $id]
        );

      
$_SESSION['category_success'] = 'Thêm danh mục thành công!';
header('Location: index.php');
exit;

    }

    // Giữ dữ liệu vừa nhập nếu có lỗi
    $category['name'] = $name;
    $category['description'] = $description;
    $category['status'] = $status;
}

require __DIR__ . '/../admin_header.php';
?>

<h2 class="fw-bold mb-4">Chỉnh sửa danh mục</h2>

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
                <input type="text" name="name"
                       class="form-control"
                       value="<?= e($category['name']) ?>"
                       required>
            </div>

            <div class="mb-3">
                <label class="form-label">Mô tả</label>
                <textarea name="description"
                          class="form-control"
                          rows="4"><?= e($category['description'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="1"
                        <?= $category['status'] == 1 ? 'selected' : '' ?>>
                        Hiển thị
                    </option>
                    <option value="0"
                        <?= $category['status'] == 0 ? 'selected' : '' ?>>
                        Ẩn
                    </option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Cập nhật
            </button>

            <a href="index.php" class="btn btn-secondary">
                Quay lại
            </a>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../admin_footer.php'; ?>

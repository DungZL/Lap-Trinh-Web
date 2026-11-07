
<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

// Chỉ cho phép xóa bằng POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Phương thức không hợp lệ.');
}

csrf_verify();

$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    exit('ID danh mục không hợp lệ.');
}

// Kiểm tra danh mục có tồn tại không
$category = db_query(
    "SELECT id FROM categories WHERE id = ?",
    [$id]
)->fetch();

if (!$category) {
    exit('Danh mục không tồn tại.');
}

// Kiểm tra danh mục có sản phẩm không
$count = db_query(
    "SELECT COUNT(*) FROM products WHERE category_id = ?",
    [$id]
)->fetchColumn();


if ($count > 0) {
    $_SESSION['category_error'] =
        'Không thể xóa danh mục vì đang có sản phẩm thuộc danh mục này.';

    header('Location: index.php');
    exit;
}


// Xóa danh mục
db_query(
    "DELETE FROM categories WHERE id = ?",
    [$id]
);

$_SESSION['category_success'] = 'Xóa danh mục thành công!';
header('Location: index.php');
exit;

<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

// Chỉ cho phép xóa bằng POST (không dùng link GET)
if (!is_post()) {
    http_response_code(405);
    exit('Phương thức không hợp lệ.');
}

csrf_verify();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$category = ($id && $id > 0)
    ? db_query('SELECT id FROM categories WHERE id = ?', [$id])->fetch()
    : false;

if (!$category) {
    flash('danger', 'Danh mục không tồn tại.');
    redirect('admin/categories/index.php');
}

// Không cho xóa danh mục còn sản phẩm (TC11)
$count = (int)db_query('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id])->fetchColumn();
if ($count > 0) {
    flash('danger', 'Không thể xóa danh mục vì đang có ' . $count . ' sản phẩm thuộc danh mục này.');
    redirect('admin/categories/index.php');
}

db_query('DELETE FROM categories WHERE id = ?', [$id]);

flash('success', 'Xóa danh mục thành công!');
redirect('admin/categories/index.php');

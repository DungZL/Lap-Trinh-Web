<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

// Chỉ cho phép xóa bằng POST
if (!is_post()) {
    http_response_code(405);
    exit('Phương thức không hợp lệ.');
}

// Kiểm tra CSRF
csrf_verify();

// Lấy ID đánh giá
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    flash('danger', 'ID đánh giá không hợp lệ.');
    redirect('admin/reviews/index.php');
}

// Kiểm tra đánh giá tồn tại
$review = db_query(
    'SELECT id FROM reviews WHERE id = ?',
    [$id]
)->fetch();

if (!$review) {
    flash('danger', 'Đánh giá không tồn tại.');
    redirect('admin/reviews/index.php');
}

// Xóa đánh giá khỏi database
db_query(
    'DELETE FROM reviews WHERE id = ?',
    [$id]
);

flash('success', 'Đã xóa đánh giá thành công!');

redirect('admin/reviews/index.php');


<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

// Chỉ cho phép gửi bằng POST
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
    'SELECT id, status FROM reviews WHERE id = ?',
    [$id]
)->fetch();

if (!$review) {
    flash('danger', 'Đánh giá không tồn tại.');
    redirect('admin/reviews/index.php');
}

// Đảo trạng thái ẩn / hiện
$newStatus = (int)$review['status'] === 1 ? 0 : 1;

db_query(
    'UPDATE reviews SET status = ? WHERE id = ?',
    [$newStatus, $id]
);

flash(
    'success',
    $newStatus === 1
        ? 'Đã hiển thị đánh giá thành công!'
        : 'Đã ẩn đánh giá thành công!'
);

redirect('admin/reviews/index.php');

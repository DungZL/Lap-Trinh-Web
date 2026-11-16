<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

// Chỉ cho phép xóa bằng POST (không dùng link GET)
if (!is_post()) {
    http_response_code(405);
    exit('Phương thức không hợp lệ.');
}

csrf_verify();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    flash('danger', 'ID người dùng không hợp lệ.');
    redirect('admin/users/index.php');
}

if ($id === (int)current_user()['id']) {
    flash('danger', 'Không thể xóa tài khoản đang đăng nhập.');
    redirect('admin/users/index.php');
}

$userRecord = db_query('SELECT id FROM users WHERE id = ?', [$id])->fetch();
if (!$userRecord) {
    flash('danger', 'Người dùng không tồn tại.');
    redirect('admin/users/index.php');
}

// Không cho xóa người dùng đã có đơn hàng (TC28)
$orderCount = (int)db_query('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$id])->fetchColumn();
if ($orderCount > 0) {
    flash('danger', 'Không thể xóa người dùng đã có ' . $orderCount . ' đơn hàng. Chỉ được phép khóa tài khoản này.');
    redirect('admin/users/index.php');
}

db_query('DELETE FROM users WHERE id = ?', [$id]);

flash('success', 'Xóa người dùng thành công!');
redirect('admin/users/index.php');

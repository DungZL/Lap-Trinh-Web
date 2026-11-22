<?php
// Xóa mã giảm giá (#6). Chỉ nhận POST + CSRF. Đơn đã dùng mã vẫn giữ coupon_code và số tiền đã giảm.
require_once __DIR__ . '/../../includes/init.php';
require_admin();

if (!is_post()) {
    http_response_code(405);
    exit('Phương thức không hợp lệ.');
}
csrf_verify();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$coupon = $id === false ? false : db_query('SELECT code FROM coupons WHERE id = ?', [$id])->fetch();
if (!$coupon) {
    flash('danger', 'Mã giảm giá không tồn tại.');
    redirect('admin/coupons/index.php');
}

db_query('DELETE FROM coupons WHERE id = ?', [$id]);
flash('success', 'Đã xóa mã ' . $coupon['code'] . '.');
redirect('admin/coupons/index.php');

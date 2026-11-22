<?php
// API mã giảm giá (#6). POST action = apply (kèm code) | remove, csrf_token.
// Mã hợp lệ được lưu trong phiên ($_SESSION['coupon_code']); checkout.php kiểm tra lại lần cuối khi đặt hàng.
require_once __DIR__ . '/../includes/init.php';

ini_set('display_errors', '0');
ob_start();

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Phương thức không hợp lệ.'], 405);
}
csrf_verify();

if (is_admin()) {
    json_response(['ok' => false, 'message' => 'Tài khoản Admin không dùng chức năng này.'], 403);
}

$action = (string)($_POST['action'] ?? '');
if ($action === 'remove') {
    unset($_SESSION['coupon_code']);
    json_response(['ok' => true, 'message' => 'Đã bỏ mã giảm giá.', 'cart' => cart_json()]);
}

$code = strtoupper(trim((string)($_POST['code'] ?? '')));
$subtotal = cart_details()['subtotal'];
if ($subtotal <= 0) {
    json_response(['ok' => false, 'message' => 'Giỏ hàng đang trống.'], 422);
}

$result = coupon_evaluate(coupon_find($code), $subtotal);
if ($result['ok']) {
    $_SESSION['coupon_code'] = $code;
}
json_response($result + ['code' => $code, 'cart' => cart_json()], $result['ok'] ? 200 : 422);

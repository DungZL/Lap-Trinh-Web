<?php
// API giỏ hàng cho ngăn kéo giỏ hàng (#1).
//  GET  : trả giỏ hàng hiện tại.
//  POST : action = add | update | remove, product_id, quantity, csrf_token.
// Dùng chung logic với cart.php qua cart_apply() (includes/store.php).
require_once __DIR__ . '/../includes/init.php';

ini_set('display_errors', '0'); // cảnh báo PHP không được lẫn vào JSON
ob_start();

if (is_post()) {
    csrf_verify();
    $result = cart_apply((string)($_POST['action'] ?? ''), $_POST['product_id'] ?? null, $_POST['quantity'] ?? 1);
    json_response($result + ['cart' => cart_json()], $result['ok'] ? 200 : 422);
}

json_response(['ok' => true, 'cart' => cart_json()]);

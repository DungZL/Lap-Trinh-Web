<?php
// Thông báo đơn hàng mới cho Admin (#13).
//  GET  : số đơn Admin chưa xem (orders.admin_seen = 0) và 8 đơn mới nhất trong số đó.
//  POST : action = mark_all -> đánh dấu tất cả đã xem.
// Mở trang chi tiết đơn (admin/orders.php?id=...) cũng đánh dấu đơn đó đã xem.
require_once __DIR__ . '/../../includes/init.php';

ini_set('display_errors', '0');
ob_start();

if (!is_admin()) {
    json_response(['ok' => false, 'message' => 'Không có quyền.'], 403);
}

if (is_post()) {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'mark_all') {
        db_query('UPDATE orders SET admin_seen = 1 WHERE admin_seen = 0');
    }
    json_response(['ok' => true]);
}

/** "5 phút trước", "2 giờ trước", "3 ngày trước" */
function time_ago(string $datetime): string
{
    $diff = max(0, time() - strtotime($datetime));
    if ($diff < 60) {
        return 'vừa xong';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' phút trước';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' giờ trước';
    }
    return floor($diff / 86400) . ' ngày trước';
}

$count = (int)db_query('SELECT COUNT(*) FROM orders WHERE admin_seen = 0')->fetchColumn();
$rows = db_query(
    'SELECT o.id, o.code, o.total_amount, o.created_at, o.receiver_name
     FROM orders o WHERE o.admin_seen = 0
     ORDER BY o.id DESC LIMIT 8'
)->fetchAll();

json_response([
    'ok'    => true,
    'count' => $count,
    'items' => array_map(fn($r) => [
        'id'       => (int)$r['id'],
        'code'     => $r['code'],
        'total'    => format_price($r['total_amount']),
        'customer' => $r['receiver_name'],
        'ago'      => time_ago($r['created_at']),
    ], $rows),
]);

<?php
// Xuất danh sách đơn hàng ra file CSV mở được bằng Excel (#12). Nhận cùng bộ lọc q, status với admin/orders.php.
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) && is_string($_GET['status']) && isset(order_statuses()[$_GET['status']]) ? $_GET['status'] : '';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(o.code LIKE ? OR o.receiver_name LIKE ? OR o.receiver_phone LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
    $term = '%' . addcslashes($search, '%_\\') . '%';
    array_push($params, $term, $term, $term, $term, $term);
}
if ($status !== '') {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$rows = db_query(
    "SELECT o.code, o.created_at, u.full_name, u.email, o.receiver_name, o.receiver_phone, o.shipping_address,
            o.payment_method, o.status, o.coupon_code, o.discount_amount, o.total_amount, o.note,
            (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' x', oi.quantity) SEPARATOR '; ') FROM order_items oi WHERE oi.order_id = o.id) AS items
     FROM orders o INNER JOIN users u ON u.id = o.user_id
     $whereSql
     ORDER BY o.created_at DESC, o.id DESC",
    $params
)->fetchAll();

$statuses = order_statuses();
$filename = 'don-hang-kiddozone-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel đọc đúng tiếng Việt

/** Chặn công thức Excel (CSV injection): ô bắt đầu bằng = + - @ thì thêm dấu ' phía trước. */
$safe = fn($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $v : $v;

fputcsv($out, ['Mã đơn', 'Ngày đặt', 'Tài khoản', 'Email', 'Người nhận', 'Điện thoại', 'Địa chỉ', 'Thanh toán',
               'Trạng thái', 'Mã giảm giá', 'Giảm giá', 'Tổng tiền', 'Sản phẩm', 'Ghi chú'], ',', '"', '');
foreach ($rows as $r) {
    fputcsv($out, array_map($safe, [
        $r['code'],
        date('d/m/Y H:i', strtotime($r['created_at'])),
        $r['full_name'],
        $r['email'],
        $r['receiver_name'],
        $r['receiver_phone'],
        $r['shipping_address'],
        $r['payment_method'] === 'bank_transfer' ? 'Chuyển khoản' : 'COD',
        $statuses[$r['status']][0] ?? $r['status'],
        $r['coupon_code'] ?? '',
        (int)$r['discount_amount'],
        (int)$r['total_amount'],
        $r['items'] ?? '',
        $r['note'] ?? '',
    ]), ',', '"', '');
}
fclose($out);

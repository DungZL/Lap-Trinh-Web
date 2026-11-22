<?php
// API tìm kiếm tức thì (#2): GET q (tối thiểu 2 ký tự) -> tối đa 6 sản phẩm khớp tên.
require_once __DIR__ . '/../includes/init.php';

ini_set('display_errors', '0');
ob_start();

$qRaw = $_GET['q'] ?? '';
$q = is_string($qRaw) ? mb_substr(trim($qRaw), 0, 100) : '';
if (mb_strlen($q) < 2) {
    json_response(['ok' => true, 'items' => []]);
}

$like = '%' . addcslashes($q, '%_\\') . '%';
$rows = db_query(
    'SELECT ' . product_card_columns() . '
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.status = 1 AND c.status = 1 AND (p.name LIKE ? OR c.name LIKE ?)
     ORDER BY (p.name LIKE ?) DESC, p.is_featured DESC, p.id DESC
     LIMIT 6',
    [$like, $like, addcslashes($q, '%_\\') . '%']
)->fetchAll();

json_response(['ok' => true, 'items' => array_map('product_public_json', $rows)]);

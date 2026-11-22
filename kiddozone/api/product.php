<?php
// API xem nhanh sản phẩm (#9): GET id -> thông tin, tất cả ảnh và đoạn mô tả ngắn.
require_once __DIR__ . '/../includes/init.php';

ini_set('display_errors', '0');
ob_start();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$row = $id === false ? false : db_query(
    'SELECT ' . product_card_columns() . ', p.description
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.id = ? AND p.status = 1 AND c.status = 1',
    [$id]
)->fetch();

if (!$row) {
    json_response(['ok' => false, 'message' => 'Sản phẩm không tồn tại hoặc đã ngừng bán.'], 404);
}

$images = db_query(
    'SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC',
    [$id]
)->fetchAll(PDO::FETCH_COLUMN);

// Mô tả dạng chữ thường (bỏ thẻ HTML) cho cửa sổ xem nhanh.
$summary = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$row['description']), ENT_QUOTES, 'UTF-8')));

$product = product_public_json($row);
$product['images']  = $images ? array_map('product_image_url', $images) : [product_image_url(null)];
$product['summary'] = $summary !== '' ? mb_substr($summary, 0, 280) : 'Chưa có mô tả cho sản phẩm này.';

json_response(['ok' => true, 'product' => $product]);

<?php
// API lọc sản phẩm cho trợ lý chọn quà (#4).
// GET age (khóa nhóm tuổi, ví dụ "3-5"), category (id), max (ngân sách tối đa), limit (1-12).
require_once __DIR__ . '/../includes/init.php';

ini_set('display_errors', '0');
ob_start();

$where  = ['p.status = 1', 'c.status = 1', 'p.stock > 0'];
$params = [];

$groups = age_groups();
$age = is_string($_GET['age'] ?? null) ? $_GET['age'] : '';
if (isset($groups[$age])) {
    // Sản phẩm phù hợp nếu khoảng tuổi của nó giao với nhóm tuổi đã chọn.
    $where[]  = 'p.age_min <= ? AND p.age_max >= ?';
    $params[] = $groups[$age][2];
    $params[] = $groups[$age][1];
}

$category = filter_var($_GET['category'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($category !== false) {
    $where[]  = 'p.category_id = ?';
    $params[] = $category;
}

$max = filter_var($_GET['max'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($max !== false) {
    $where[]  = final_price_sql() . ' <= ?';
    $params[] = $max;
}

$limit = filter_var($_GET['limit'] ?? 8, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) ?: 8;
$whereSql = implode(' AND ', $where);

$total = (int)db_query(
    "SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE $whereSql",
    $params
)->fetchColumn();

// Ưu tiên sản phẩm nổi bật, được đánh giá cao, đang giảm giá.
$rows = db_query(
    'SELECT ' . product_card_columns() . "
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE $whereSql
     ORDER BY p.is_featured DESC, avg_rating IS NULL, avg_rating DESC, (" . final_price_sql() . ' < p.price) DESC, p.id DESC
     LIMIT ' . (int)$limit,
    $params
)->fetchAll();

json_response(['ok' => true, 'total' => $total, 'items' => array_map('product_public_json', $rows)]);

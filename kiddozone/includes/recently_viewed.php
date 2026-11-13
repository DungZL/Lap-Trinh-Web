<?php
// Sản phẩm đã xem gần đây (task F18).
// Cookie "recently_viewed" lưu tối đa 8 id sản phẩm dạng "5,12,3": mới nhất đứng đầu, giữ 30 ngày.
// Cookie do trình duyệt gửi lên nên có thể bị sửa tay: luôn đọc lại thành danh sách số nguyên.

require_once __DIR__ . '/product_card.php';

const RECENTLY_VIEWED_COOKIE = 'recently_viewed';
const RECENTLY_VIEWED_MAX    = 8;
const RECENTLY_VIEWED_DAYS   = 30;

/** Đọc cookie thành danh sách id hợp lệ (số nguyên dương, không trùng, tối đa 8). Giá trị lạ thì bỏ qua. */
function recently_viewed_ids(): array
{
    $raw = $_COOKIE[RECENTLY_VIEWED_COOKIE] ?? '';
    if (!is_string($raw)) { // ví dụ recently_viewed[]=x làm PHP tạo ra mảng
        return [];
    }
    $ids = [];
    foreach (explode(',', substr($raw, 0, 200)) as $part) {
        $part = trim($part);
        if ($part !== '' && strlen($part) <= 9 && ctype_digit($part) && (int)$part > 0) {
            $ids[(int)$part] = true; // dùng khóa mảng để loại trùng
        }
    }
    return array_slice(array_keys($ids), 0, RECENTLY_VIEWED_MAX);
}

/** Ghi nhận đã xem 1 sản phẩm: đưa lên đầu danh sách, giữ tối đa 8. Gọi trước khi in bất kỳ nội dung nào. */
function recently_viewed_add(int $productId): void
{
    if ($productId <= 0) {
        return;
    }
    $ids = recently_viewed_ids();
    array_unshift($ids, $productId);
    $ids = array_slice(array_values(array_unique($ids)), 0, RECENTLY_VIEWED_MAX);
    $value = implode(',', $ids);

    if (!headers_sent()) {
        setcookie(RECENTLY_VIEWED_COOKIE, $value, [
            'expires'  => time() + RECENTLY_VIEWED_DAYS * 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
    }
    $_COOKIE[RECENTLY_VIEWED_COOKIE] = $value; // để cùng yêu cầu này đọc lại được
}

/** Lấy thông tin sản phẩm theo danh sách id, giữ đúng thứ tự. Sản phẩm đã ẩn hoặc đã xóa thì bỏ qua. */
function recently_viewed_products(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
    if (!$ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?')); // chỉ sinh dấu ?, giá trị đi qua tham số
    $rows = db_query(
        "SELECT p.id, p.name, p.price, p.sale_price, p.stock, c.name AS category_name,
                (SELECT pi.image_path FROM product_images pi
                  WHERE pi.product_id = p.id
                  ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.id IN ($marks) AND p.status = 1 AND c.status = 1",
        $ids
    )->fetchAll();

    $byId = [];
    foreach ($rows as $row) {
        $byId[(int)$row['id']] = $row;
    }
    $ordered = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }
    return $ordered;
}

/** In khối "Sản phẩm đã xem gần đây". Không có sản phẩm nào thì không in gì. */
function render_recently_viewed(array $products, string $title = 'Sản phẩm đã xem gần đây'): void
{
    if (!$products) {
        return;
    }
    ?>
    <section class="mt-5 mb-5">
        <h2 class="kz-section-title"><?= e($title) ?></h2>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
            <?php foreach ($products as $p) { render_product_card($p); } ?>
        </div>
    </section>
    <?php
}
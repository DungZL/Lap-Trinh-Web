<?php
// Danh sách yêu thích (task F17).
//  - POST: thêm hoặc bỏ 1 sản phẩm (từ nút trái tim), xong quay lại trang trước.
//  - GET : trang xem danh sách yêu thích của khách hàng đang đăng nhập.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/product_card.php'; // đã gồm wishlist.php

/** Trả JSON rồi dừng (dùng khi nút trái tim gọi bằng fetch, xem assets/js/shop.js). */
function wishlist_json(int $status, array $data): void
{
    while (ob_get_level() > 0) {
        ob_end_clean(); // bỏ mọi chữ lạ đã lỡ in ra, để phản hồi là JSON thuần
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (is_post()) {
    $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    if ($isAjax) {
        ini_set('display_errors', '0'); // cảnh báo PHP không được lẫn vào JSON
        ob_start();
    }
    csrf_verify();
    $back   = safe_redirect_target($_POST['redirect'] ?? '', 'account/wishlist.php');

    // Khách vãng lai: chuyển sang đăng nhập, đăng nhập xong quay lại đúng trang đang xem.
    if (!is_logged_in()) {
        flash('info', 'Vui lòng đăng nhập để dùng danh sách yêu thích.');
        $loginPath = 'login.php?redirect=' . urlencode($back);
        if ($isAjax) {
            wishlist_json(401, ['ok' => false, 'login' => url($loginPath)]);
        }
        redirect($loginPath);
    }
    if (is_admin()) {
        if ($isAjax) {
            wishlist_json(403, ['ok' => false, 'message' => 'Tài khoản Admin không dùng chức năng này.']);
        }
        http_response_code(403);
        exit('403 - Tài khoản Admin không dùng chức năng này.');
    }

    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    $action    = $_POST['action'] ?? '';
    $userId    = (int)current_user()['id'];

    $ok = false;
    $active = null;
    $message = '';
    if ($productId !== false && $productId > 0) {
        if ($action === 'add') {
            if (wishlist_add($userId, $productId)) {
                $ok = true;
                $active = true;
                $message = 'Đã thêm vào danh sách yêu thích.';
            } else {
                $message = 'Sản phẩm không tồn tại hoặc đã ngừng bán.';
            }
        } elseif ($action === 'remove') {
            wishlist_remove($userId, $productId);
            $ok = true;
            $active = false;
            $message = 'Đã bỏ khỏi danh sách yêu thích.';
        }
    }

    if ($isAjax) {
        wishlist_json($ok ? 200 : 422, ['ok' => $ok, 'active' => $active, 'message' => $message]);
    }
    if ($message !== '') {
        flash($ok ? 'success' : 'warning', $message);
    }
    redirect($back);
}

require_customer(); // chưa đăng nhập thì chuyển sang login.php, Admin thì bị chặn 403

$products = db_query(
    'SELECT p.id, p.name, p.price, p.sale_price, p.stock, c.name AS category_name,
            (SELECT pi.image_path FROM product_images pi
              WHERE pi.product_id = p.id
              ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image
     FROM wishlists w
     JOIN products p   ON p.id = w.product_id
     JOIN categories c ON c.id = p.category_id
     WHERE w.user_id = ? AND p.status = 1 AND c.status = 1
     ORDER BY w.created_at DESC, w.id DESC',
    [(int)current_user()['id']]
)->fetchAll();

$pageTitle = 'Sản phẩm yêu thích';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="kz-section-title">Sản phẩm yêu thích</h1>

<?php if (!$products): ?>
    <div class="text-center py-5">
        <i class="bi bi-heart fs-1 text-muted"></i>
        <p class="mt-2 mb-3">Bạn chưa có sản phẩm yêu thích nào.</p>
        <a class="btn kz-btn-register" href="<?= e(url('products.php')) ?>">Xem sản phẩm</a>
    </div>
<?php else: ?>
    <p class="text-muted">Có <strong data-wishlist-count><?= count($products) ?></strong> sản phẩm. Bấm vào trái tim để bỏ khỏi danh sách.</p>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3" data-wishlist-page>
        <?php foreach ($products as $p) { render_product_card($p); } ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
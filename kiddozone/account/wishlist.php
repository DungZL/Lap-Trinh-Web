<?php
// Danh sách yêu thích (task F17).
//  - POST: thêm hoặc bỏ 1 sản phẩm (từ nút trái tim), xong quay lại trang trước.
//  - GET : trang xem danh sách yêu thích của khách hàng đang đăng nhập.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/product_card.php'; // đã gồm wishlist.php

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
            json_response(['ok' => false, 'login' => url($loginPath)], 401);
        }
        redirect($loginPath);
    }
    if (is_admin()) {
        if ($isAjax) {
            json_response(['ok' => false, 'message' => 'Tài khoản Admin không dùng chức năng này.'], 403);
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
        json_response(['ok' => $ok, 'active' => $active, 'message' => $message], $ok ? 200 : 422);
    }
    if ($message !== '') {
        flash($ok ? 'success' : 'warning', $message);
    }
    redirect($back);
}

require_customer(); // chưa đăng nhập thì chuyển sang login.php, Admin thì bị chặn 403

$products = db_query(
    'SELECT ' . product_card_columns() . '
     FROM wishlists w
     JOIN products p   ON p.id = w.product_id
     JOIN categories c ON c.id = p.category_id
     WHERE w.user_id = ? AND p.status = 1 AND c.status = 1
     ORDER BY w.created_at DESC, w.id DESC',
    [(int)current_user()['id']]
)->fetchAll();

$pageTitle = 'Sản phẩm yêu thích';
$accountTab = 'wishlist';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/account_nav.php';
?>

<?php if (!$products): ?>
    <div class="card flex flex-col items-center px-6 py-16 text-center">
        <?= empty_icon('ph-heart', 'coral') ?>
        <h2 class="mt-4 text-2xl font-semibold">Bạn chưa có sản phẩm yêu thích nào</h2>
        <p class="mt-1 text-muted">Bấm vào trái tim trên sản phẩm để lưu lại món đồ chơi bạn thích.</p>
        <a class="btn btn-primary mt-6" href="<?= e(url('products.php')) ?>">Khám phá đồ chơi</a>
    </div>
<?php else: ?>
    <p class="mb-5 text-ink-500 dark:text-ink-300">Có <strong data-wishlist-count><?= count($products) ?></strong> sản phẩm. Bấm vào trái tim để bỏ khỏi danh sách.</p>
    <h2 class="sr-only">Danh sách sản phẩm yêu thích</h2>
    <div class="grid grid-cols-2 gap-4 sm:gap-5 md:grid-cols-3 lg:grid-cols-4" data-wishlist-page>
        <?php foreach ($products as $p) { render_product_card($p); } ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

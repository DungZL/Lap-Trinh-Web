<?php
// Gửi đánh giá sản phẩm (task F16). Chỉ nhận POST từ form ở product.php.
// Chỉ khách có đơn "completed" chứa sản phẩm mới đánh giá được; gửi lại thì cập nhật đánh giá cũ.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/reviews.php';

if (!is_post()) {
    redirect('index.php');
}
csrf_verify();

$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
if ($productId === false || $productId < 1) {
    flash('warning', 'Sản phẩm không hợp lệ.');
    redirect('products.php');
}
$back = 'product.php?id=' . $productId . '#reviews';

// Khách vãng lai: chuyển sang đăng nhập, xong quay lại trang sản phẩm.
if (!is_logged_in()) {
    flash('info', 'Vui lòng đăng nhập để đánh giá sản phẩm.');
    redirect('login.php?redirect=' . urlencode(url($back)));
}
if (is_admin()) {
    http_response_code(403);
    exit('403 - Tài khoản Admin không dùng chức năng này.');
}
$userId = (int)current_user()['id'];

// Sản phẩm phải tồn tại và đang bán.
$st = db_query('SELECT 1 FROM products WHERE id = ? AND status = 1', [$productId]);
$exists = $st->fetchColumn() !== false;
$st->closeCursor();
if (!$exists) {
    flash('warning', 'Sản phẩm không tồn tại hoặc đã ngừng bán.');
    redirect('products.php');
}

// TC19: chưa mua, hoặc đơn chưa hoàn thành thì từ chối.
if (!review_can_review($userId, $productId)) {
    review_notice_set('danger', 'Bạn chỉ có thể đánh giá sản phẩm sau khi đơn hàng chứa sản phẩm này đã hoàn thành.');
    redirect($back);
}

// Kiểm tra dữ liệu gửi lên.
$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
$commentRaw = $_POST['comment'] ?? '';
$comment = is_string($commentRaw) ? trim(str_replace("\r\n", "\n", $commentRaw)) : '';

$errors = [];
if ($rating === false) {
    $errors[] = 'Vui lòng chọn số sao từ 1 đến 5.';
}
if (mb_strlen($comment) > 1000) {
    $errors[] = 'Bình luận tối đa 1000 ký tự.';
}
if ($errors) {
    review_notice_set('danger', implode(' ', $errors));
    redirect($back);
}

// TC20: đã có đánh giá thì cập nhật, không tạo thêm.
$result = review_save($userId, $productId, $rating, $comment !== '' ? $comment : null);
review_notice_set('success', $result === 'updated' ? 'Đã cập nhật đánh giá của bạn.' : 'Cảm ơn bạn đã đánh giá sản phẩm.');
redirect($back);
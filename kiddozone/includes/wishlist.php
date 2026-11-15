<?php
// Danh sách yêu thích (task F17): hàm dùng chung và nút trái tim.
// Bảng wishlists có ràng buộc duy nhất (user_id, product_id) nên không bao giờ có dòng trùng.

/** Các product_id mà người dùng hiện tại đã yêu thích, dạng [id => true]. Chỉ truy vấn 1 lần cho cả trang. */
function wishlist_ids(): array
{
    static $ids = null;
    if ($ids === null) {
        $ids = [];
        $user = current_user();
        if ($user && !is_admin()) {
            $rows = db_query('SELECT product_id FROM wishlists WHERE user_id = ?', [(int)$user['id']])
                ->fetchAll(PDO::FETCH_COLUMN);
            foreach ($rows as $pid) {
                $ids[(int)$pid] = true;
            }
        }
    }
    return $ids;
}

/** Thêm sản phẩm vào yêu thích. Trả về false nếu sản phẩm không tồn tại hoặc đang ẩn. Thêm lần 2 thì không làm gì. */
function wishlist_add(int $userId, int $productId): bool
{
    $check  = db_query('SELECT 1 FROM products WHERE id = ? AND status = 1', [$productId]);
    $exists = $check->fetchColumn() !== false;
    $check->closeCursor();
    if (!$exists) {
        return false;
    }
    db_query(
        'INSERT INTO wishlists (user_id, product_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = id',
        [$userId, $productId]
    );
    return true;
}

function wishlist_remove(int $userId, int $productId): void
{
    db_query('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?', [$userId, $productId]);
}

/**
 * In nút trái tim (là 1 form POST tới account/wishlist.php). Admin không thấy nút.
 * $withLabel = true: nút có chữ, dùng ở trang chi tiết; false: nút tròn nổi trên thẻ sản phẩm.
 */
function render_wishlist_button(int $productId, bool $withLabel = false): void
{
    if (is_admin()) {
        return;
    }
    $active = isset(wishlist_ids()[$productId]);
    $label  = $active ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích';
    // Sau khi bấm thì quay lại đúng trang và đúng thẻ sản phẩm đang xem.
    $back = strtok($_SERVER['REQUEST_URI'] ?? '/', '#') . '#product-' . $productId;
    ?>
    <form method="post" action="<?= e(url('account/wishlist.php')) ?>"
          class="kz-wish-form <?= $withLabel ? 'kz-wish-form-inline' : '' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $active ? 'remove' : 'add' ?>">
        <input type="hidden" name="product_id" value="<?= $productId ?>">
        <input type="hidden" name="redirect" value="<?= e($back) ?>">
        <button type="submit" class="kz-wish-btn <?= $withLabel ? 'kz-wish-btn-label' : '' ?> <?= $active ? 'active' : '' ?>"
                aria-pressed="<?= $active ? 'true' : 'false' ?>" aria-label="<?= e($label) ?>" title="<?= e($label) ?>">
            <i class="bi <?= $active ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
            <?php if ($withLabel): ?><span><?= e($label) ?></span><?php endif; ?>
        </button>
    </form>
    <?php
}
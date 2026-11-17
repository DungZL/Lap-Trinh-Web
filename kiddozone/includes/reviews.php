<?php
// Đánh giá sản phẩm (task F16).
// Bảng reviews có ràng buộc duy nhất (user_id, product_id): mỗi khách chỉ có 1 đánh giá cho mỗi sản phẩm.
// Điểm trung bình và số lượt chỉ tính các đánh giá đang hiển thị (status = 1).

/** Đoạn SQL lấy avg_rating và review_count, dùng trong câu SELECT có bảng products đặt bí danh "p". */
function review_rating_columns(): string
{
    return '(SELECT ROUND(AVG(rv.rating), 1) FROM reviews rv WHERE rv.product_id = p.id AND rv.status = 1) AS avg_rating,
            (SELECT COUNT(*) FROM reviews rv2 WHERE rv2.product_id = p.id AND rv2.status = 1) AS review_count';
}

/** Điểm trung bình và số lượt đánh giá của 1 sản phẩm: ['avg' => float, 'count' => int]. */
function review_summary(int $productId): array
{
    $row = db_query(
        'SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS review_count
         FROM reviews WHERE product_id = ? AND status = 1',
        [$productId]
    )->fetch();
    return [
        'avg'   => ($row && $row['avg_rating'] !== null) ? (float)$row['avg_rating'] : 0.0,
        'count' => (int)($row['review_count'] ?? 0),
    ];
}

/** Khách có đơn "completed" chứa sản phẩm này thì mới được đánh giá. */
function review_can_review(int $userId, int $productId): bool
{
    $st = db_query(
        "SELECT 1 FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = ? AND o.status = 'completed' AND oi.product_id = ?
         LIMIT 1",
        [$userId, $productId]
    );
    $ok = $st->fetchColumn() !== false;
    $st->closeCursor();
    return $ok;
}

/** Đánh giá hiện có của khách cho sản phẩm (kể cả đang bị ẩn), hoặc null. */
function review_get(int $userId, int $productId): ?array
{
    $row = db_query(
        'SELECT rating, comment, status FROM reviews WHERE user_id = ? AND product_id = ?',
        [$userId, $productId]
    )->fetch();
    return $row ?: null;
}

/**
 * Lưu đánh giá. Đã có thì cập nhật điểm và bình luận (không tạo dòng mới, không đổi status do Admin đặt).
 * Trả về 'created' hoặc 'updated'.
 */
function review_save(int $userId, int $productId, int $rating, ?string $comment): string
{
    $existed = review_get($userId, $productId) !== null;
    db_query(
        'INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)',
        [$userId, $productId, $rating, $comment]
    );
    return $existed ? 'updated' : 'created';
}

/** Các đánh giá đang hiển thị của 1 sản phẩm, mới nhất trước. */
function review_list(int $productId, int $limit = 10): array
{
    return db_query(
        'SELECT r.rating, r.comment, r.created_at, u.full_name
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ? AND r.status = 1
         ORDER BY r.created_at DESC, r.id DESC
         LIMIT ' . max(1, $limit),
        [$productId]
    )->fetchAll();
}

/** "Nguyễn Văn An" -> "Nguyễn V. An" (không lộ họ tên đầy đủ trên trang công khai). */
function review_mask_name(string $fullName): string
{
    $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    $n = count($parts);
    if ($n === 0) {
        return 'Khách hàng';
    }
    if ($n === 1) {
        return $parts[0];
    }
    if ($n === 2) {
        return $parts[0] . ' ' . mb_substr($parts[1], 0, 1) . '.';
    }
    $middle = [];
    for ($i = 1; $i < $n - 1; $i++) {
        $middle[] = mb_substr($parts[$i], 0, 1) . '.';
    }
    return $parts[0] . ' ' . implode(' ', $middle) . ' ' . $parts[$n - 1];
}

/** In 5 ngôi sao theo điểm (làm tròn tới nửa sao). */
function render_stars(float $value): void
{
    echo '<span class="kz-stars" aria-hidden="true">';
    for ($i = 1; $i <= 5; $i++) {
        $icon = $value >= $i ? 'bi-star-fill' : ($value >= $i - 0.5 ? 'bi-star-half' : 'bi-star');
        echo '<i class="bi ' . $icon . '"></i>';
    }
    echo '</span>';
}

/** Điểm hiển thị kiểu Việt Nam: 4,5 */
function review_format_avg(float $avg): string
{
    return number_format($avg, 1, ',', '.');
}

/* Thông báo hiện ngay trong khối đánh giá (không dùng flash chung vì trang được cuộn tới #reviews). */
function review_notice_set(string $type, string $message): void
{
    $_SESSION['review_notice'] = ['type' => $type, 'message' => $message];
}

function review_notice_pull(): ?array
{
    $n = $_SESSION['review_notice'] ?? null;
    unset($_SESSION['review_notice']);
    return $n;
}
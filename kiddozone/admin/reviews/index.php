<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$pageTitle = 'Quản lý đánh giá';

// Lọc đánh giá theo trạng thái.
$filter = $_GET['status'] ?? 'all';

if (!in_array($filter, ['all', 'visible', 'hidden'], true)) {
    $filter = 'all';
}

$where = '';
$params = [];

if ($filter === 'visible') {
    $where = ' WHERE r.status = ?';
    $params[] = 1;
} elseif ($filter === 'hidden') {
    $where = ' WHERE r.status = ?';
    $params[] = 0;
}

// Thống kê đánh giá.
$stats = db_query(
    'SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 1), 0) AS visible_count,
        COALESCE(SUM(status = 0), 0) AS hidden_count
     FROM reviews'
)->fetch();

// Tổng số đánh giá theo bộ lọc.
$total = (int)db_query(
    'SELECT COUNT(*) FROM reviews r' . $where,
    $params
)->fetchColumn();

// Phân trang.
[$offset, $pages, $page] = paginate(
    $total,
    ITEMS_PER_PAGE_ADMIN
);

// Danh sách đánh giá.
$reviews = db_query(
    'SELECT
        r.id,
        r.rating,
        r.comment,
        r.status,
        r.created_at,
        u.full_name,
        p.name AS product_name
     FROM reviews r
     LEFT JOIN users u ON u.id = r.user_id
     LEFT JOIN products p ON p.id = r.product_id'
    . $where .
    ' ORDER BY r.created_at DESC, r.id DESC
      LIMIT ' . (int)ITEMS_PER_PAGE_ADMIN .
    ' OFFSET ' . (int)$offset,
    $params
)->fetchAll();

$pageSubtitle = 'Ẩn hoặc xóa đánh giá không phù hợp';
require __DIR__ . '/../admin_header.php';
?>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <?php foreach ([['Tổng đánh giá', (int)$stats['total'], 'ph-chat-circle-dots', 'from-sun-400 to-coral-400'], ['Đang hiển thị', (int)$stats['visible_count'], 'ph-eye', 'from-mint-400 to-sky-400'], ['Đang ẩn', (int)$stats['hidden_count'], 'ph-eye-slash', 'from-ink-300 to-ink-400']] as [$label, $n, $icon, $grad]): ?>
        <div data-reveal class="card flex items-center gap-4 p-5">
            <span class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br text-2xl text-white <?= $grad ?>"><i class="ph-fill <?= $icon ?>" aria-hidden="true"></i></span>
            <div><p class="text-sm font-bold text-muted"><?= e($label) ?></p><p class="font-display text-3xl font-bold" data-count="<?= $n ?>"><?= $n ?></p></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card overflow-hidden">
    <div class="flex flex-wrap items-center gap-2 border-b border-ink-100 p-4 dark:border-ink-800">
        <?php foreach (['all' => 'Tất cả', 'visible' => 'Hiển thị', 'hidden' => 'Đang ẩn'] as $key => $label): ?>
            <a href="<?= e(url('admin/reviews/index.php' . ($key === 'all' ? '' : '?status=' . $key))) ?>"
               class="rounded-full px-4 py-2 text-sm font-extrabold transition <?= $filter === $key ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-900' : 'text-ink-500 hover:bg-ink-50 dark:hover:bg-ink-800' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <ul class="divide-y divide-ink-100 dark:divide-ink-800">
        <?php foreach ($reviews as $review): ?>
            <li class="flex flex-wrap items-start gap-4 p-5 <?= (int)$review['status'] === 1 ? '' : 'bg-ink-50/60 dark:bg-ink-800/30' ?>">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-gradient-to-br from-sun-300 to-coral-300 font-extrabold text-ink-900"><?= e(mb_strtoupper(mb_substr($review['full_name'] ?? '?', 0, 1))) ?></span>
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 text-sm">
                        <b><?= e($review['full_name'] ?? 'Không xác định') ?></b>
                        <span class="text-muted">→</span>
                        <span class="font-bold text-sky-600 dark:text-sky-300"><?= e($review['product_name'] ?? 'Sản phẩm đã xóa') ?></span>
                        <?php render_stars((float)$review['rating']); ?>
                        <?= (int)$review['status'] === 1 ? '<span class="badge badge-mint">Hiển thị</span>' : '<span class="badge badge-ink">Đang ẩn</span>' ?>
                    </p>
                    <p class="mt-1 text-sm break-words whitespace-pre-wrap text-ink-600 dark:text-ink-300"><?= e($review['comment'] ?? '') ?: '<span class="italic text-muted">(Không có bình luận)</span>' ?></p>
                    <p class="mt-1 text-xs text-muted">#<?= (int)$review['id'] ?> · <?= e(date('H:i d/m/Y', strtotime($review['created_at']))) ?></p>
                </div>
                <div class="flex gap-2">
                    <form action="<?= e(url('admin/reviews/toggle.php')) ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm"><i class="ph-bold <?= (int)$review['status'] === 1 ? 'ph-eye-slash' : 'ph-eye' ?>" aria-hidden="true"></i> <?= (int)$review['status'] === 1 ? 'Ẩn' : 'Hiện' ?></button>
                    </form>
                    <form action="<?= e(url('admin/reviews/delete.php')) ?>" method="post" data-confirm="Bạn có chắc muốn xóa đánh giá này?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
                        <button type="submit" class="btn btn-danger-ghost btn-sm" aria-label="Xóa đánh giá"><i class="ph-bold ph-trash" aria-hidden="true"></i></button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
        <?php if (!$reviews): ?>
            <li class="p-10 text-center text-muted">Chưa có đánh giá nào.</li>
        <?php endif; ?>
    </ul>
</div>
<?= render_pagination($page, $pages) ?>

<?php require __DIR__ . '/../admin_footer.php'; ?>

<?php
// Chi tiết sản phẩm (task F05): thư viện ảnh, giá/flash sale, tồn kho, độ tuổi, mô tả, đánh giá, sản phẩm liên quan.
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product_card.php';
require_once __DIR__ . '/includes/recently_viewed.php';

/* ---------- 1. Lấy sản phẩm ---------- */

$idRaw = $_GET['id'] ?? 0;
$id = is_string($idRaw) ? (int)$idRaw : 0;

$product = false;
if ($id > 0) {
    $product = db_query(
        'SELECT p.*, c.name AS category_name
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.id = ? AND p.status = 1 AND c.status = 1',
        [$id]
    )->fetch();
}

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy sản phẩm';
    require __DIR__ . '/includes/header.php';
    render_not_found('Không tìm thấy sản phẩm', 'Món đồ chơi này không tồn tại hoặc đã ngừng bán. Bé thử tìm món khác nhé!');
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Ảnh: ảnh chính đứng đầu. Chưa có ảnh nào thì dùng ảnh mẫu.
$images = db_query(
    'SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC',
    [$product['id']]
)->fetchAll(PDO::FETCH_COLUMN);
$imageUrls = $images ? array_map('product_image_url', $images) : [product_image_url(null)];
$fallbackImg = product_image_url(null);

// Sản phẩm đã xem trước đó (không gồm sản phẩm này), rồi mới ghi nhận lượt xem hiện tại vào cookie.
$recentIds = array_values(array_diff(recently_viewed_ids(), [(int)$product['id']]));
recently_viewed_add((int)$product['id']);

/* ---------- 2. Chuẩn bị hiển thị ---------- */

$price   = (float)$product['price'];
$final   = effective_price($product);
$onSale  = $final < $price;
$percent = $onSale ? (int)round((1 - $final / $price) * 100) : 0;
$flash   = flash_active($product);
$stock   = (int)$product['stock'];
$description = sanitize_description_html((string)($product['description'] ?? ''));

// Đánh giá: điểm trung bình, phân bố số sao, danh sách, và trạng thái của người đang xem.
$reviewSummary = review_summary((int)$product['id']);
$reviews       = review_list((int)$product['id'], 10);
$reviewNotice  = review_notice_pull(); // thông báo sau khi gửi đánh giá
$distRows = db_query('SELECT rating, COUNT(*) AS n FROM reviews WHERE product_id = ? AND status = 1 GROUP BY rating', [(int)$product['id']])->fetchAll();
$dist = array_fill(1, 5, 0);
foreach ($distRows as $r) {
    $dist[(int)$r['rating']] = (int)$r['n'];
}
$me       = current_user();
$myReview = null;
if (!$me) {
    $reviewState = 'guest';
} elseif (is_admin()) {
    $reviewState = 'admin';
} elseif (review_can_review((int)$me['id'], (int)$product['id'])) {
    $reviewState = 'can';
    $myReview = review_get((int)$me['id'], (int)$product['id']);
} else {
    $reviewState = 'not_bought';
}
$reviewLoginUrl = url('login.php?redirect=' . urlencode(url('product.php?id=' . (int)$product['id'] . '#reviews')));

// Sản phẩm cùng danh mục
$related = db_query(
    'SELECT ' . product_card_columns() . '
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.status = 1 AND c.status = 1 AND p.category_id = ? AND p.id <> ?
     ORDER BY p.is_featured DESC, RAND() LIMIT 4',
    [(int)$product['category_id'], (int)$product['id']]
)->fetchAll();

$pageTitle = $product['name'];
require __DIR__ . '/includes/header.php';
?>

<nav class="mb-6 flex flex-wrap items-center gap-1.5 text-xs font-bold text-muted" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>" class="hover:text-sky-600">Trang chủ</a><i class="ph-bold ph-caret-right" aria-hidden="true"></i>
    <a href="<?= e(url('products.php')) ?>" class="hover:text-sky-600">Sản phẩm</a><i class="ph-bold ph-caret-right" aria-hidden="true"></i>
    <a href="<?= e(url('products.php?category=' . (int)$product['category_id'])) ?>" class="hover:text-sky-600"><?= e($product['category_name']) ?></a><i class="ph-bold ph-caret-right" aria-hidden="true"></i>
    <span class="text-ink-600 dark:text-ink-200" aria-current="page"><?= e($product['name']) ?></span>
</nav>

<div class="grid gap-8 lg:grid-cols-2 lg:gap-12">

    <!-- Thư viện ảnh: di chuột để phóng to -->
    <div x-data="productGallery(<?= e(json_encode($imageUrls, JSON_UNESCAPED_SLASHES)) ?>)" data-fly-source>
        <div class="relative aspect-square cursor-zoom-in overflow-hidden rounded-5xl bg-gradient-to-br from-sun-100 via-cream to-sky-100 shadow-soft ring-1 ring-ink-100 dark:from-ink-800 dark:via-ink-900 dark:to-ink-800 dark:ring-ink-800"
             @mouseenter="zoom = true" @mouseleave="zoom = false" @mousemove="track($event)">
            <img id="kz-main-img" :src="images[current]" src="<?= e($imageUrls[0]) ?>" alt="<?= e($product['name']) ?>" data-fallback="<?= e($fallbackImg) ?>"
                 class="size-full object-cover transition-transform duration-300 ease-out [view-transition-name:kz-product-img]" :style="zoom ? 'transform: scale(1.8); transform-origin:' + origin : ''">
            <div class="absolute top-4 left-4 flex flex-col gap-2">
                <?php if ($flash): ?><span class="badge bg-sun-400 px-3 py-1.5 text-sm text-ink-900 shadow-soft"><i class="ph-fill ph-lightning" aria-hidden="true"></i> Flash sale</span><?php endif; ?>
                <?php if ($onSale): ?><span class="badge bg-coral-600 px-3 py-1.5 text-sm text-white shadow-soft">-<?= $percent ?>%</span><?php endif; ?>
            </div>
            <?php if (count($imageUrls) > 1): ?>
                <button type="button" class="btn btn-ghost btn-icon absolute top-1/2 left-3 -translate-y-1/2" @click.stop="go(current - 1)" aria-label="Ảnh trước"><i class="ph-bold ph-caret-left" aria-hidden="true"></i></button>
                <button type="button" class="btn btn-ghost btn-icon absolute top-1/2 right-3 -translate-y-1/2" @click.stop="go(current + 1)" aria-label="Ảnh sau"><i class="ph-bold ph-caret-right" aria-hidden="true"></i></button>
            <?php endif; ?>
        </div>
        <?php if (count($imageUrls) > 1): ?>
            <div class="no-scrollbar mt-4 flex gap-3 overflow-x-auto pb-1">
                <?php foreach ($imageUrls as $i => $src): ?>
                    <button type="button" @click="go(<?= $i ?>)" aria-label="Xem ảnh <?= $i + 1 ?>"
                            class="size-20 shrink-0 overflow-hidden rounded-2xl ring-2 transition hover:-translate-y-0.5" :class="current === <?= $i ?> ? 'ring-sky-400 shadow-pop' : 'ring-transparent opacity-60 hover:opacity-100'">
                        <img src="<?= e($src) ?>" alt="" loading="lazy" data-fallback="<?= e($fallbackImg) ?>" class="size-full object-cover">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Thông tin -->
    <div class="flex flex-col">
        <div class="flex flex-wrap items-center gap-2">
            <a class="badge badge-sky" href="<?= e(url('products.php?category=' . (int)$product['category_id'])) ?>"><i class="ph-bold ph-tag" aria-hidden="true"></i><?= e($product['category_name']) ?></a>
            <span class="badge badge-grape"><i class="ph-bold ph-baby" aria-hidden="true"></i><?= e(age_label((int)$product['age_min'], (int)$product['age_max'])) ?></span>
        </div>
        <h1 class="mt-3 font-display text-3xl leading-tight font-bold text-ink-900 sm:text-4xl dark:text-white"><?= e($product['name']) ?></h1>

        <?php if ($reviewSummary['count'] > 0): ?>
            <a class="mt-2 inline-flex items-center gap-2 text-sm" href="#reviews">
                <?php render_stars($reviewSummary['avg']); ?>
                <strong><?= e(review_format_avg($reviewSummary['avg'])) ?></strong>
                <span class="text-muted underline-offset-2 hover:underline">(<?= $reviewSummary['count'] ?> đánh giá)</span>
            </a>
        <?php endif; ?>

        <!-- Giá -->
        <div class="mt-5 rounded-4xl p-5 <?= $flash ? 'bg-ink-900 text-white dark:bg-black' : 'bg-white shadow-soft ring-1 ring-ink-100 dark:bg-ink-900 dark:ring-ink-800' ?>"
             <?php if ($flash): ?>x-data="countdown('<?= e(date('c', strtotime($product['flash_sale_end']))) ?>')"<?php endif; ?>>
            <?php if ($flash): ?>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm font-extrabold">
                    <span class="flex items-center gap-1.5 text-sun-300"><i class="ph-fill ph-lightning" aria-hidden="true"></i> FLASH SALE</span>
                    <span class="flex items-center gap-1 tabular-nums">Kết thúc sau
                        <span class="rounded-lg bg-white/15 px-1.5" x-text="d">00</span>:<span class="rounded-lg bg-white/15 px-1.5" x-text="h">00</span>:<span class="rounded-lg bg-white/15 px-1.5" x-text="m">00</span>:<span class="rounded-lg bg-white/15 px-1.5" x-text="s">00</span>
                    </span>
                </div>
            <?php endif; ?>
            <div class="flex flex-wrap items-baseline gap-3">
                <span class="font-display text-4xl font-bold <?= $flash ? 'text-sun-300' : ($onSale ? 'text-price' : 'text-ink-900 dark:text-white') ?>"><?= e(format_price($final)) ?></span>
                <?php if ($onSale): ?>
                    <span class="text-lg line-through <?= $flash ? 'text-white/75' : 'text-muted' ?>"><?= e(format_price($price)) ?></span>
                    <span class="badge <?= $flash ? 'bg-white text-coral-700' : 'badge-coral' ?>">Tiết kiệm <?= e(format_price($price - $final)) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tồn kho -->
        <div class="mt-5">
            <?php if ($stock <= 0): ?>
                <p class="flex items-center gap-2 font-bold text-price"><i class="ph-fill ph-x-circle text-lg" aria-hidden="true"></i> Hết hàng</p>
            <?php else: ?>
                <div class="flex items-center justify-between text-sm font-bold">
                    <span class="flex items-center gap-2 <?= $stock <= 5 ? 'text-sun-800 dark:text-sun-300' : 'text-ok' ?>">
                        <i class="ph-fill <?= $stock <= 5 ? 'ph-warning' : 'ph-check-circle' ?> text-lg" aria-hidden="true"></i>
                        <?= $stock <= 5 ? 'Sắp hết! Chỉ còn ' . $stock . ' sản phẩm' : 'Còn hàng' ?>
                    </span>
                    <span class="text-muted"><?= $stock ?> trong kho</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                    <div class="h-full rounded-full <?= $stock <= 5 ? 'bg-gradient-to-r from-coral-400 to-sun-400' : 'bg-gradient-to-r from-mint-400 to-sky-400' ?>" style="width: <?= max(8, min(100, $stock * 2)) ?>%"></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Thêm vào giỏ: fetch tới api/cart.php (data-cart-form), không có JS thì gửi tới cart.php. -->
        <?php if (!is_admin()): ?>
            <div class="mt-6 flex flex-col items-start gap-3">
            <form id="kz-buy-form" method="post" action="<?= e(url('cart.php')) ?>" data-cart-form class="flex w-full flex-wrap items-center gap-3" x-data="{ qty: 1, max: <?= max(1, $stock) ?> }">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <div class="flex items-center rounded-full bg-white p-1.5 shadow-soft ring-1 ring-ink-100 dark:bg-ink-800 dark:ring-ink-700">
                    <button type="button" class="grid size-10 place-items-center rounded-full hover:bg-ink-50 dark:hover:bg-ink-700" @click="qty = Math.max(1, qty - 1)" aria-label="Giảm số lượng" <?= $stock <= 0 ? 'disabled' : '' ?>><i class="ph-bold ph-minus" aria-hidden="true"></i></button>
                    <label for="quantity" class="sr-only">Số lượng</label>
                    <input id="quantity" name="quantity" type="number" min="1" :max="max" x-model.number="qty" value="1" required <?= $stock <= 0 ? 'disabled' : '' ?>
                           class="w-12 [appearance:textfield] bg-transparent text-center text-lg font-extrabold outline-none [&::-webkit-inner-spin-button]:appearance-none">
                    <button type="button" class="grid size-10 place-items-center rounded-full hover:bg-ink-50 dark:hover:bg-ink-700" @click="qty = Math.min(max, qty + 1)" aria-label="Tăng số lượng" <?= $stock <= 0 ? 'disabled' : '' ?>><i class="ph-bold ph-plus" aria-hidden="true"></i></button>
                </div>
                <button class="btn btn-sun btn-lg flex-1" type="submit" <?= $stock <= 0 ? 'disabled' : '' ?>>
                    <i class="ph-fill ph-shopping-cart-simple text-xl" aria-hidden="true"></i> Thêm vào giỏ hàng
                </button>
            </form>
                <div id="product-<?= (int)$product['id'] ?>"><?php render_wishlist_button((int)$product['id'], true); ?></div>
            </div>
        <?php endif; ?>

        <ul class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
            <li class="flex items-center gap-3 rounded-2xl bg-mint-50 p-3 font-bold text-ok dark:bg-mint-500/10 dark:text-mint-300"><i class="ph-fill ph-shield-check text-2xl" aria-hidden="true"></i> Chất liệu an toàn cho bé</li>
            <li class="flex items-center gap-3 rounded-2xl bg-sky-50 p-3 font-bold text-sky-600 dark:bg-sky-500/10 dark:text-sky-300"><i class="ph-fill ph-truck text-2xl" aria-hidden="true"></i> Giao nhanh 2 - 4 ngày</li>
            <li class="flex items-center gap-3 rounded-2xl bg-sun-50 p-3 font-bold text-sun-800 dark:bg-sun-500/10 dark:text-sun-300"><i class="ph-fill ph-arrow-counter-clockwise text-2xl" aria-hidden="true"></i> Đổi trả trong 7 ngày</li>
            <li class="flex items-center gap-3 rounded-2xl bg-grape-50 p-3 font-bold text-grape-600 dark:bg-grape-500/10 dark:text-grape-300"><i class="ph-fill ph-gift text-2xl" aria-hidden="true"></i> Gói quà miễn phí</li>
        </ul>
    </div>
</div>

<!-- Mô tả và đánh giá -->
<div class="mt-14 grid gap-8 lg:grid-cols-[1.4fr_1fr]">
    <section class="card p-6 sm:p-8" data-reveal>
        <h2 class="section-title mb-6">Mô tả sản phẩm</h2>
        <?php if ($description !== ''): ?>
            <div class="prose-kz"><?= $description /* đã lọc bằng sanitize_description_html() */ ?></div>
        <?php else: ?>
            <p class="text-muted">Chưa có mô tả cho sản phẩm này.</p>
        <?php endif; ?>
    </section>

    <section class="card p-6 sm:p-8" id="reviews" data-reveal>
        <h2 class="section-title mb-6">Đánh giá</h2>

        <?php if ($reviewNotice): ?>
            <div class="alert alert-<?= e($reviewNotice['type']) ?> mb-4"><?= e($reviewNotice['message']) ?></div>
        <?php endif; ?>

        <?php if ($reviewSummary['count'] > 0): ?>
            <div class="flex items-center gap-5">
                <div class="text-center">
                    <div class="font-display text-5xl font-bold text-ink-900 dark:text-white"><?= e(review_format_avg($reviewSummary['avg'])) ?></div>
                    <?php render_stars($reviewSummary['avg']); ?>
                    <div class="mt-1 text-xs text-muted"><?= $reviewSummary['count'] ?> đánh giá</div>
                </div>
                <div class="flex-1 space-y-1.5">
                    <?php for ($s = 5; $s >= 1; $s--): ?>
                        <div class="flex items-center gap-2 text-xs font-bold text-muted">
                            <span class="w-3"><?= $s ?></span><i class="ph-fill ph-star text-sun-400" aria-hidden="true"></i>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                                <div class="h-full rounded-full bg-sun-400" style="width: <?= $reviewSummary['count'] ? round($dist[$s] / $reviewSummary['count'] * 100) : 0 ?>%"></div>
                            </div>
                            <span class="w-4 text-right"><?= $dist[$s] ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php else: ?>
            <p class="text-muted">Chưa có đánh giá nào cho sản phẩm này.</p>
        <?php endif; ?>

        <div class="mt-6">
            <?php if ($reviewState === 'guest'): ?>
                <p class="rounded-2xl bg-ink-50 p-4 text-sm dark:bg-ink-800"><a class="link" href="<?= e($reviewLoginUrl) ?>">Đăng nhập</a> để đánh giá sản phẩm bạn đã mua.</p>
            <?php elseif ($reviewState === 'not_bought'): ?>
                <p class="rounded-2xl bg-ink-50 p-4 text-sm text-ink-500 dark:bg-ink-800 dark:text-ink-300"><i class="ph-bold ph-info" aria-hidden="true"></i> Chỉ khách đã mua sản phẩm này và có đơn hàng đã hoàn thành mới đánh giá được.</p>
            <?php elseif ($reviewState === 'can'): ?>
                <form method="post" action="<?= e(url('account/review.php')) ?>" class="rounded-3xl bg-sun-50 p-5 dark:bg-sun-500/10">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                    <h3 class="font-display text-lg font-semibold"><?= $myReview ? 'Cập nhật đánh giá của bạn' : 'Viết đánh giá của bạn' ?></h3>
                    <?php if ($myReview && (int)$myReview['status'] === 0): ?>
                        <p class="mt-1 text-xs text-muted">Đánh giá hiện tại của bạn đang được quản trị viên ẩn.</p>
                    <?php endif; ?>
                    <div class="star-input mt-2" role="radiogroup" aria-label="Chọn số sao">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= ($myReview && (int)$myReview['rating'] === $i) ? 'checked' : '' ?> required>
                            <label for="star<?= $i ?>" title="<?= $i ?> sao"><i class="ph-fill ph-star" aria-hidden="true"></i><span class="sr-only"><?= $i ?> sao</span></label>
                        <?php endfor; ?>
                    </div>
                    <label class="label mt-3" for="comment">Bình luận (không bắt buộc)</label>
                    <textarea class="textarea" id="comment" name="comment" rows="3" maxlength="1000" placeholder="Chia sẻ cảm nhận của bạn về sản phẩm"><?= e($myReview['comment'] ?? '') ?></textarea>
                    <button class="btn btn-primary mt-3" type="submit"><i class="ph-bold ph-paper-plane-tilt" aria-hidden="true"></i> <?= $myReview ? 'Cập nhật đánh giá' : 'Gửi đánh giá' ?></button>
                </form>
            <?php endif; ?>
        </div>

        <div class="mt-6 space-y-4">
            <?php foreach ($reviews as $r): ?>
                <article class="flex gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-sun-300 to-coral-300 font-extrabold text-ink-900"><?= e(mb_strtoupper(mb_substr($r['full_name'], 0, 1))) ?></span>
                    <div class="flex-1 rounded-3xl rounded-tl-md bg-ink-50 p-4 dark:bg-ink-800">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <strong><?= e(review_mask_name($r['full_name'])) ?></strong>
                            <?php render_stars((float)$r['rating']); ?>
                            <span class="badge badge-mint"><i class="ph-fill ph-seal-check" aria-hidden="true"></i> Đã mua hàng</span>
                            <span class="ml-auto text-xs text-muted"><?= e(date('d/m/Y', strtotime($r['created_at']))) ?></span>
                        </div>
                        <?php if ($r['comment'] !== null && $r['comment'] !== ''): ?>
                            <p class="mt-2 text-sm text-ink-600 dark:text-ink-300"><?= nl2br(e($r['comment'])) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php if ($related): ?>
    <section class="mt-16">
        <div class="mb-6 flex items-center gap-3">
            <span class="grid size-11 place-items-center rounded-2xl bg-sky-100 text-2xl text-sky-600 dark:bg-sky-500/15"><i class="ph-fill ph-sparkle" aria-hidden="true"></i></span>
            <h2 class="section-title">Có thể bé cũng thích</h2>
        </div>
        <div class="grid grid-cols-2 gap-4 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
            <?php foreach ($related as $p) { render_product_card($p); } ?>
        </div>
    </section>
<?php endif; ?>

<?php render_recently_viewed(recently_viewed_products($recentIds)); ?>

<?php if (!is_admin() && $stock > 0): ?>
<!-- Thanh mua dính dưới màn hình: hiện khi đã cuộn qua nút "Thêm vào giỏ hàng" -->
<div x-data="stickyBuy" x-show="show" x-cloak x-effect="document.body.style.paddingBottom = show ? '76px' : ''"
     x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full opacity-0"
     x-transition:leave="transition duration-200 ease-in" x-transition:leave-end="translate-y-full opacity-0"
     class="fixed inset-x-0 bottom-0 z-30 border-t border-ink-100 bg-white/90 px-4 py-3 shadow-[0_-12px_30px_-14px_rgb(28_37_64/0.25)] backdrop-blur-xl dark:border-ink-800 dark:bg-ink-900/90">
    <div class="mx-auto flex max-w-7xl items-center gap-3 sm:px-2 lg:px-4">
        <img src="<?= e($imageUrls[0]) ?>" alt="" data-fallback="<?= e($fallbackImg) ?>" class="size-12 shrink-0 rounded-xl bg-ink-50 object-cover dark:bg-ink-800">
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-extrabold text-ink-800 dark:text-white"><?= e($product['name']) ?></p>
            <p class="flex items-baseline gap-2">
                <span class="font-display text-lg font-bold <?= $onSale ? 'text-price' : 'text-ink-900 dark:text-white' ?>"><?= e(format_price($final)) ?></span>
                <?php if ($onSale): ?><span class="text-xs text-muted line-through max-sm:hidden"><?= e(format_price($price)) ?></span><?php endif; ?>
            </p>
        </div>
        <button type="submit" form="kz-buy-form" class="btn btn-sun shrink-0">
            <i class="ph-fill ph-shopping-cart-simple text-lg" aria-hidden="true"></i> <span class="max-sm:sr-only">Thêm vào giỏ</span>
        </button>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

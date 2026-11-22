<?php
// Thẻ sản phẩm dùng chung (trang chủ, danh sách, yêu thích, đã xem gần đây...).
// Cách dùng: require_once __DIR__ . '/includes/product_card.php';  rồi  render_product_card($p);
// $p lấy bằng product_card_columns() (includes/store.php).

require_once __DIR__ . '/wishlist.php';
require_once __DIR__ . '/reviews.php';

if (!function_exists('render_product_card')) {
    function render_product_card(array $p): void
    {
        $price    = (float)$p['price'];
        $final    = effective_price($p);
        $onSale   = $final < $price;
        $percent  = $onSale && $price > 0 ? (int)round((1 - $final / $price) * 100) : 0;
        $flash    = flash_active($p);
        $soldOut  = (int)$p['stock'] <= 0;
        $id       = (int)$p['id'];
        $detail   = url('product.php?id=' . $id);
        $fallback = url('assets/img/placeholder.svg');
        // Nền pastel xoay vòng theo id (viết đủ tên class để Tailwind quét được).
        $tints = [
            'from-sun-100 to-coral-100 dark:from-sun-500/10 dark:to-coral-500/10',
            'from-sky-100 to-grape-100 dark:from-sky-500/10 dark:to-grape-500/10',
            'from-mint-100 to-sky-100 dark:from-mint-500/10 dark:to-sky-500/10',
            'from-coral-100 to-grape-100 dark:from-coral-500/10 dark:to-grape-500/10',
            'from-grape-100 to-sun-100 dark:from-grape-500/10 dark:to-sun-500/10',
        ];
        $tint = $tints[$id % count($tints)];
        ?>
        <article id="product-<?= $id ?>" data-product-card data-tilt data-reveal
                 class="kz-card group relative flex flex-col rounded-4xl bg-white p-2.5 shadow-soft ring-1 ring-ink-100/80 transition duration-300 hover:-translate-y-1.5 hover:shadow-pop hover:ring-sky-200 dark:bg-ink-900 dark:ring-ink-800 dark:hover:ring-sky-500/40 <?= $soldOut ? 'opacity-75' : '' ?>">
            <div class="relative aspect-square overflow-hidden rounded-3xl bg-gradient-to-br <?= $tint ?>">
                <img src="<?= e(product_image_url($p['image'] ?? null)) ?>" alt="<?= e($p['name']) ?>" loading="lazy"
                     data-fallback="<?= e($fallback) ?>"
                     class="size-full object-cover transition duration-500 ease-out <?= !empty($p['image2']) ? 'group-hover:opacity-0' : 'group-hover:scale-110 group-hover:rotate-2' ?> <?= $soldOut ? 'grayscale' : '' ?>">
                <?php if (!empty($p['image2'])): ?>
                    <!-- Ảnh thứ 2: hiện khi rê chuột vào thẻ -->
                    <img src="<?= e(product_image_url($p['image2'])) ?>" alt="" loading="lazy" aria-hidden="true" data-fallback="<?= e($fallback) ?>"
                         class="absolute inset-0 size-full scale-105 object-cover opacity-0 transition duration-500 ease-out group-hover:scale-100 group-hover:opacity-100">
                <?php endif; ?>

                <div class="absolute top-2.5 left-2.5 flex flex-col items-start gap-1.5">
                    <?php if ($soldOut): ?>
                        <span class="badge bg-ink-800 text-white">Hết hàng</span>
                    <?php else: ?>
                        <?php if ($flash): ?>
                            <span class="badge bg-sun-400 text-ink-900 shadow-soft"><i class="ph-fill ph-lightning" aria-hidden="true"></i> Flash</span>
                        <?php endif; ?>
                        <?php if ($onSale): ?>
                            <span class="badge bg-coral-600 text-white shadow-soft">-<?= $percent ?>%</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="absolute top-2.5 right-2.5 z-10"><?php render_wishlist_button($id); ?></div>

                <?php if (!is_admin()): ?>
                    <button type="button" @click="$store.quick.show(<?= $id ?>)"
                            class="absolute inset-x-3 bottom-3 z-10 flex translate-y-2 items-center justify-center gap-2 rounded-full bg-white/90 py-2 text-xs font-extrabold text-ink-800 opacity-0 shadow-soft backdrop-blur transition duration-300 group-hover:translate-y-0 group-hover:opacity-100 hover:bg-white focus-visible:translate-y-0 focus-visible:opacity-100 max-sm:hidden dark:bg-ink-900/90 dark:text-white">
                        <i class="ph-bold ph-eye text-base" aria-hidden="true"></i> Xem nhanh
                    </button>
                <?php endif; ?>
            </div>
            <span class="kz-shine" aria-hidden="true"></span>

            <div class="flex flex-1 flex-col px-2 pt-3 pb-1">
                <h3 class="line-clamp-2 min-h-[2.6em] font-sans text-[0.95rem] leading-snug font-extrabold text-ink-800 dark:text-ink-50">
                    <a class="after:absolute after:inset-0 hover:text-sky-600 dark:hover:text-sky-300" href="<?= e($detail) ?>"><?= e($p['name']) ?></a>
                </h3>
                <p class="mt-1 flex items-center gap-1.5 text-xs font-bold text-muted">
                    <span class="truncate"><?= e($p['category_name']) ?></span>
                    <?php if (isset($p['age_min'])): ?>
                        <span class="text-ink-300 dark:text-ink-600" aria-hidden="true">•</span>
                        <span class="shrink-0 text-age"><?= e(age_label((int)$p['age_min'], (int)$p['age_max'])) ?></span>
                    <?php endif; ?>
                </p>
                <?php if ((int)($p['review_count'] ?? 0) > 0): ?>
                    <div class="mt-1 flex items-center gap-1 text-xs" aria-label="Điểm trung bình <?= e(review_format_avg((float)$p['avg_rating'])) ?> trên 5, <?= (int)$p['review_count'] ?> đánh giá">
                        <?php render_stars((float)$p['avg_rating']); ?>
                        <span class="font-bold text-muted"><?= e(review_format_avg((float)$p['avg_rating'])) ?> (<?= (int)$p['review_count'] ?>)</span>
                    </div>
                <?php endif; ?>
                <div class="mt-auto flex items-end justify-between gap-2 pt-3">
                    <div class="leading-tight">
                        <span class="block font-display text-lg font-bold <?= $onSale ? 'text-price' : 'text-ink-900 dark:text-white' ?>"><?= e(format_price($final)) ?></span>
                        <?php if ($onSale): ?>
                            <span class="text-xs font-semibold text-muted line-through"><?= e(format_price($price)) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!is_admin() && !$soldOut): ?>
                        <form method="post" action="<?= e(url('cart.php')) ?>" data-cart-form class="relative z-10">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $id ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="btn btn-sun btn-icon size-11" aria-label="Thêm <?= e($p['name']) ?> vào giỏ" title="Thêm vào giỏ">
                                <i class="ph-bold ph-plus text-lg transition duration-300 group-hover:rotate-90" aria-hidden="true"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php
    }
}

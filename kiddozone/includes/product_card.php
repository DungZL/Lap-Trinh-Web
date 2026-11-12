<?php
// Thẻ sản phẩm dùng chung (trang chủ, trang danh sách sản phẩm...).
// Cách dùng: require_once __DIR__ . '/includes/product_card.php';  rồi  render_product_card($p);
// $p cần có: id, name, price, sale_price, stock, category_name, image.

require_once __DIR__ . '/wishlist.php';

if (!function_exists('product_image_url')) {
    /** image_path là "assets/..." (ảnh mẫu) hoặc tên file trong uploads/ (ảnh Admin tải lên). */
    function product_image_url(?string $path): string
    {
        if (!$path) {
            return url('assets/img/placeholder.svg');
        }
        return str_starts_with($path, 'assets/') ? url($path) : url('uploads/' . $path);
    }
}

if (!function_exists('render_product_card')) {
    function render_product_card(array $p): void
    {
        $price    = (float)$p['price'];
        $final    = effective_price($p);
        $onSale   = $final < $price;
        $percent  = $onSale ? (int)round((1 - $final / $price) * 100) : 0;
        $soldOut  = (int)$p['stock'] <= 0;
        $detail   = url('product.php?id=' . (int)$p['id']);
        $fallback = url('assets/img/placeholder.svg');
        ?>
        <div class="col" id="product-<?= (int)$p['id'] ?>">
            <div class="card kz-product h-100 <?= $soldOut ? 'kz-sold-out' : '' ?>">
                <div class="kz-product-img ratio ratio-1x1">
                    <img src="<?= e(product_image_url($p['image'] ?? null)) ?>" alt="<?= e($p['name']) ?>"
                         loading="lazy" data-fallback="<?= e($fallback) ?>">
                </div>
                <?php render_wishlist_button((int)$p['id']); ?>
                <?php if ($soldOut): ?>
                    <span class="kz-badge kz-badge-out">Hết hàng</span>
                <?php elseif ($onSale): ?>
                    <span class="kz-badge kz-badge-sale">-<?= $percent ?>%</span>
                <?php endif; ?>
                <div class="card-body d-flex flex-column">
                    <small class="text-muted"><?= e($p['category_name']) ?></small>
                    <h3 class="h6 kz-product-name mb-2">
                        <a class="stretched-link text-reset text-decoration-none" href="<?= e($detail) ?>"><?= e($p['name']) ?></a>
                    </h3>
                    <div class="mt-auto">
                        <span class="kz-price"><?= e(format_price($final)) ?></span>
                        <?php if ($onSale): ?>
                            <small class="kz-price-old"><?= e(format_price($price)) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
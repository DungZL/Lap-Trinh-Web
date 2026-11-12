<?php
// Chi tiết sản phẩm (task F05): gallery ảnh, giá/giá khuyến mãi, tồn kho, mô tả HTML, nút thêm vào giỏ.
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product_card.php';

/**
 * Lọc HTML mô tả (từ TinyMCE) trước khi in ra: bỏ script/iframe/form..., bỏ mọi thuộc tính ngoài danh sách
 * cho phép, bỏ link javascript:. Giữ lại các thẻ định dạng thường dùng.
 */
function sanitize_description_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (!class_exists('DOMDocument')) {
        return nl2br(e(strip_tags($html))); // không có extension DOM thì hiện dạng chữ thường
    }

    $allowedTags  = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5',
                     'blockquote', 'a', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'span', 'div', 'hr'];
    $dropTags     = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea',
                     'select', 'link', 'meta', 'svg', 'math', 'base'];
    $allowedAttrs = ['href', 'src', 'alt', 'title', 'colspan', 'rowspan', 'width', 'height'];
    $allowedSchemes = ['http', 'https', 'mailto'];

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $dom->getElementsByTagName('div')->item(0);
    if (!$root) {
        return '';
    }

    $clean = function (DOMNode $node) use (&$clean, $allowedTags, $dropTags, $allowedAttrs, $allowedSchemes): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!($child instanceof DOMElement)) {
                continue;
            }
            $tag = strtolower($child->tagName);

            if (in_array($tag, $dropTags, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!in_array($tag, $allowedTags, true)) {
                // Thẻ lạ: bỏ thẻ nhưng giữ nội dung bên trong.
                $clean($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $allowedAttrs, true)) {
                    $child->removeAttribute($attr->name);
                    continue;
                }
                if ($name === 'href' || $name === 'src') {
                    $val = strtolower(preg_replace('/[\x00-\x20]+/', '', $attr->value));
                    if (preg_match('/^([a-z][a-z0-9+.\-]*):/', $val, $m) && !in_array($m[1], $allowedSchemes, true)) {
                        $child->removeAttribute($attr->name); // ví dụ javascript:, data:
                    }
                }
            }

            if ($tag === 'a') {
                $child->setAttribute('rel', 'noopener noreferrer');
            } elseif ($tag === 'img') {
                $child->setAttribute('class', 'img-fluid');
                $child->setAttribute('loading', 'lazy');
            } elseif ($tag === 'table') {
                $child->setAttribute('class', 'table table-bordered');
            }
            $clean($child);
        }
    };
    $clean($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return $out;
}

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
    ?>
    <div class="text-center py-5">
        <i class="bi bi-emoji-frown fs-1 text-muted"></i>
        <h1 class="h4 mt-2">Không tìm thấy sản phẩm</h1>
        <p class="text-muted">Sản phẩm không tồn tại hoặc đã ngừng bán.</p>
        <a class="btn kz-btn-register" href="<?= e(url('products.php')) ?>">Xem tất cả sản phẩm</a>
    </div>
    <?php
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

/* ---------- 2. Chuẩn bị hiển thị ---------- */

$price   = (float)$product['price'];
$final   = effective_price($product);
$onSale  = $final < $price;
$percent = $onSale ? (int)round((1 - $final / $price) * 100) : 0;
$stock   = (int)$product['stock'];
$description = sanitize_description_html((string)($product['description'] ?? ''));

$pageTitle = $product['name'];
require __DIR__ . '/includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('index.php')) ?>">Trang chủ</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('products.php')) ?>">Sản phẩm</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('products.php?category=' . (int)$product['category_id'])) ?>"><?= e($product['category_name']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li>
    </ol>
</nav>

<div class="row g-4 mb-5">

    <!-- Gallery ảnh -->
    <div class="col-md-6">
        <div class="kz-gallery-main ratio ratio-1x1">
            <img id="kz-main-img" src="<?= e($imageUrls[0]) ?>" alt="<?= e($product['name']) ?>"
                 data-fallback="<?= e($fallbackImg) ?>">
        </div>
        <?php if (count($imageUrls) > 1): ?>
            <div class="kz-thumbs">
                <?php foreach ($imageUrls as $i => $src): ?>
                    <button type="button" class="kz-thumb <?= $i === 0 ? 'active' : '' ?>"
                            data-src="<?= e($src) ?>" aria-label="Xem ảnh <?= $i + 1 ?>">
                        <img src="<?= e($src) ?>" alt="" loading="lazy"
                             data-fallback="<?= e($fallbackImg) ?>">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Thông tin -->
    <div class="col-md-6">
        <a class="text-muted small" href="<?= e(url('products.php?category=' . (int)$product['category_id'])) ?>"><?= e($product['category_name']) ?></a>
        <h1 class="kz-detail-title"><?= e($product['name']) ?></h1>

        <div class="mb-3">
            <span class="kz-detail-price"><?= e(format_price($final)) ?></span>
            <?php if ($onSale): ?>
                <span class="kz-price-old fs-5"><?= e(format_price($price)) ?></span>
                <span class="badge kz-detail-sale">-<?= $percent ?>%</span>
            <?php endif; ?>
        </div>

        <p class="mb-4">
            <?php if ($stock <= 0): ?>
                <span class="fw-bold text-danger"><i class="bi bi-x-circle"></i> Hết hàng</span>
            <?php elseif ($stock <= 5): ?>
                <span class="fw-bold text-warning-emphasis"><i class="bi bi-exclamation-circle"></i> Chỉ còn <?= $stock ?> sản phẩm</span>
            <?php else: ?>
                <span class="fw-bold text-success"><i class="bi bi-check-circle"></i> Còn hàng</span>
                <span class="text-muted">(<?= $stock ?> sản phẩm)</span>
            <?php endif; ?>
        </p>

        <!-- Thêm vào giỏ: gửi tới cart.php (action=add). Số lượng thật sự được backend giới hạn theo tồn kho. -->
        <form method="post" action="<?= e(url('cart.php')) ?>" class="kz-add-cart">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <label class="form-label mb-1" for="quantity">Số lượng</label>
                    <input class="form-control kz-qty" type="number" id="quantity" name="quantity"
                           value="1" min="1" max="<?= max(1, $stock) ?>" <?= $stock <= 0 ? 'disabled' : '' ?> required>
                </div>
                <button class="btn btn-lg kz-btn-register align-self-end" type="submit" <?= $stock <= 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-cart-plus"></i> Thêm vào giỏ
                </button>
            </div>
        </form>

        <div class="mt-3" id="product-<?= (int)$product['id'] ?>">
            <?php render_wishlist_button((int)$product['id'], true); ?>
        </div>
    </div>
</div>

<!-- Mô tả -->
<section class="mb-5">
    <h2 class="kz-section-title">Mô tả sản phẩm</h2>
    <?php if ($description !== ''): ?>
        <div class="kz-description"><?= $description /* đã lọc bằng sanitize_description_html() */ ?></div>
    <?php else: ?>
        <p class="text-muted">Chưa có mô tả cho sản phẩm này.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
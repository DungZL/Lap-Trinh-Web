<?php
// Trang chủ (task F03): banner, danh mục, flash sale, mua theo độ tuổi, sản phẩm nổi bật, mới nhất.
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product_card.php';
require_once __DIR__ . '/includes/recently_viewed.php';

const HOME_PRODUCT_LIMIT = 8;

// Danh mục đang hiển thị kèm số sản phẩm đang bán.
$categories = db_query(
    'SELECT c.id, c.name, c.description, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.status = 1
     WHERE c.status = 1
     GROUP BY c.id, c.name, c.description
     ORDER BY c.id'
)->fetchAll();

$productSelect =
    'SELECT ' . product_card_columns() . '
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.status = 1 AND c.status = 1';

$featured = db_query($productSelect . ' AND p.is_featured = 1 ORDER BY p.created_at DESC, p.id DESC LIMIT ' . HOME_PRODUCT_LIMIT)->fetchAll();
$newest   = db_query($productSelect . ' ORDER BY p.created_at DESC, p.id DESC LIMIT ' . HOME_PRODUCT_LIMIT)->fetchAll();
$flash    = flash_sale_products(8);
$flashEnd = $flash ? min(array_map(fn($p) => strtotime($p['flash_sale_end']), $flash)) : null;

// Số liệu nhỏ ở banner
$stats = db_query(
    "SELECT (SELECT COUNT(*) FROM products WHERE status = 1) AS products,
            (SELECT COUNT(*) FROM users WHERE role = 'customer') AS customers,
            (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE status = 1) AS rating,
            (SELECT COUNT(*) FROM reviews WHERE status = 1) AS reviews"
)->fetch();

// Mã giảm giá quảng bá trên banner (ưu tiên KIDDO10)
$heroCoupon = db_query(
    "SELECT * FROM coupons WHERE status = 1
       AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at > NOW())
       AND (usage_limit IS NULL OR used_count < usage_limit)
     ORDER BY code = 'KIDDO10' DESC, min_order ASC LIMIT 1"
)->fetch();
$flashMaxPercent = 0;
foreach ($flash as $fp) {
    $flashMaxPercent = max($flashMaxPercent, (int)round((1 - effective_price($fp) / max(1, (float)$fp['price'])) * 100));
}

// Đánh giá thật của khách (4 - 5 sao, có bình luận) cho khu "Ba mẹ nói gì"
$testimonials = db_query(
    "SELECT r.rating, r.comment, u.full_name, p.id AS product_id, p.name AS product_name,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     JOIN products p ON p.id = r.product_id AND p.status = 1
     WHERE r.status = 1 AND r.rating >= 4 AND r.comment IS NOT NULL AND r.comment <> ''
     ORDER BY r.created_at DESC LIMIT 8"
)->fetchAll();

// Chủ đề bé thích (dải chạy ngang): từ khóa tìm kiếm + icon + màu
$themes = [
    ['Lego', 'ph-cube', 'text-coral-700 dark:text-coral-300'], ['Robot', 'ph-robot', 'text-sky-700 dark:text-sky-300'],
    ['Búp bê', 'ph-flower', 'text-grape-600 dark:text-grape-300'], ['Gấu bông', 'ph-heart', 'text-coral-700 dark:text-coral-300'],
    ['Xe', 'ph-car-profile', 'text-mint-700 dark:text-mint-300'], ['Puzzle', 'ph-puzzle-piece', 'text-sun-800 dark:text-sun-300'],
    ['Khoa học', 'ph-flask', 'text-sky-700 dark:text-sky-300'], ['Bóng', 'ph-basketball', 'text-coral-700 dark:text-coral-300'],
    ['Tàu hỏa', 'ph-train', 'text-grape-600 dark:text-grape-300'], ['Máy bay', 'ph-airplane-tilt', 'text-sky-700 dark:text-sky-300'],
    ['Chữ cái', 'ph-text-aa', 'text-mint-700 dark:text-mint-300'], ['Scooter', 'ph-scooter', 'text-sun-800 dark:text-sun-300'],
];

$recent = recently_viewed_products(recently_viewed_ids());

// Mỗi danh mục 1 màu + icon (viết đủ tên class để Tailwind quét được).
$categoryStyles = [
    ['ph-lightbulb', 'bg-sun-100 text-sun-800 hover:bg-sun-200 dark:bg-sun-500/10 dark:text-sun-300', 'bg-sun-400'],
    ['ph-puzzle-piece', 'bg-sky-100 text-sky-700 hover:bg-sky-200 dark:bg-sky-500/10 dark:text-sky-300', 'bg-sky-400'],
    ['ph-heart', 'bg-coral-100 text-coral-700 hover:bg-coral-200 dark:bg-coral-500/10 dark:text-coral-300', 'bg-coral-400'],
    ['ph-car-profile', 'bg-mint-100 text-ok hover:bg-mint-200 dark:bg-mint-500/10 dark:text-mint-300', 'bg-mint-400'],
    ['ph-soccer-ball', 'bg-grape-100 text-grape-600 hover:bg-grape-200 dark:bg-grape-500/10 dark:text-grape-300', 'bg-grape-400'],
];
$ageStyles = [
    '0-2'  => 'from-sun-200 to-sun-300 text-ink-900',
    '3-5'  => 'from-coral-200 to-coral-300 text-ink-900',
    '6-8'  => 'from-sky-200 to-sky-300 text-ink-900',
    '9-12' => 'from-grape-200 to-grape-300 text-ink-900',
];

$pageTitle = 'Trang chủ';
require __DIR__ . '/includes/header.php';
?>

<!-- Banner trượt: 1 giới thiệu, 2 flash sale, 3 mã giảm giá. Tự chuyển, kéo/vuốt được, có nút tạm dừng. -->
<?php $slideCount = 1 + ($flash ? 1 : 0) + ($heroCoupon ? 1 : 0); $slideNo = 0; ?>
<h1 class="sr-only">KiddoZone - đồ chơi an toàn cho bé từ 0 đến 12 tuổi</h1>
<section data-hero x-data="heroSlider(<?= $slideCount ?>)" class="relative overflow-hidden rounded-5xl"
         aria-roledescription="carousel" aria-label="Ưu đãi nổi bật"
         @mouseenter="hover = true" @mouseleave="hover = false" @focusin="hover = true" @focusout="hover = false"
         @keydown.arrow-left="prev()" @keydown.arrow-right="next()">
    <div class="grid touch-pan-y" @pointerdown="swipeStart($event)" @pointerup="swipeEnd($event)" @pointercancel="startX = null">

    <!-- Slide 1: giới thiệu -->
    <div class="kz-slide relative isolate overflow-hidden px-6 pt-12 pb-24 transition-opacity duration-700 [grid-area:1/1] sm:px-12 lg:pt-16 is-active bg-gradient-to-br from-sun-300 via-sun-200 to-coral-200 dark:from-ink-800 dark:via-ink-900 dark:to-grape-500/30"
         role="group" aria-roledescription="slide" aria-label="1 / <?= $slideCount ?>"
         :class="{ 'is-active': active === 0, 'opacity-0 pointer-events-none': active !== 0 }" :inert="active !== 0">
    <div class="bg-dots absolute inset-0 -z-10 opacity-70"></div>
    <div class="absolute -top-24 -right-24 -z-10 size-96 rounded-full bg-white/40 blur-3xl dark:bg-sky-500/20"></div>

    <div class="grid items-center gap-10 lg:grid-cols-2">
        <div>
            <h2 data-hero-text class="font-display text-4xl leading-[1.05] font-bold text-ink-900 sm:text-5xl lg:text-6xl dark:text-white">
                Chơi vui mỗi ngày,
                <span class="relative inline-block text-sky-600 dark:text-sky-300">
                    bé học
                    <svg class="absolute -bottom-2 left-0 w-full text-price" viewBox="0 0 200 12" preserveAspectRatio="none" aria-hidden="true"><path d="M2 9 C 50 2, 150 2, 198 8" stroke="currentColor" stroke-width="5" fill="none" stroke-linecap="round"/></svg>
                </span>
                mỗi giờ!
            </h2>
            <p data-hero-text class="mt-5 max-w-lg text-lg text-ink-600 dark:text-ink-300">Đồ chơi an toàn, vui và hữu ích cho bé từ 0 đến 12 tuổi, chọn lọc kỹ theo từng độ tuổi. Giao nhanh, đổi trả dễ dàng.</p>
            <div data-hero-text class="mt-8 flex flex-wrap gap-3">
                <a class="btn btn-primary btn-lg" href="<?= e(url('products.php')) ?>"><i class="ph-bold ph-storefront text-lg" aria-hidden="true"></i> Mua sắm ngay</a>
                <a class="btn btn-ghost btn-lg" href="<?= e(url('gift-finder.php')) ?>"><i class="ph-fill ph-gift text-lg text-price" aria-hidden="true"></i> Tìm quà cho bé</a>
            </div>
            <dl data-hero-text class="mt-10 grid max-w-md grid-cols-3 gap-4">
                <div><dt class="text-xs font-bold text-ink-700 dark:text-ink-300">Món đồ chơi</dt><dd class="font-display text-3xl font-bold text-ink-900 dark:text-white"><span data-count="<?= (int)$stats['products'] ?>" data-suffix="+"><?= (int)$stats['products'] ?>+</span></dd></div>
                <div><dt class="text-xs font-bold text-ink-700 dark:text-ink-300">Gia đình tin dùng</dt><dd class="font-display text-3xl font-bold text-ink-900 dark:text-white"><span data-count="<?= (int)$stats['customers'] ?>" data-suffix="+"><?= (int)$stats['customers'] ?>+</span></dd></div>
                <div><dt class="text-xs font-bold text-ink-700 dark:text-ink-300">Điểm đánh giá</dt><dd class="font-display text-3xl font-bold text-ink-900 dark:text-white"><?= e(review_format_avg((float)($stats['rating'] ?? 5))) ?><i class="ph-fill ph-star ml-1 text-2xl text-sun-500" aria-hidden="true"></i></dd></div>
            </dl>
        </div>

        <!-- Cụm đồ chơi bay lơ lửng, lệch theo chuột -->
        <div class="relative mx-auto aspect-square w-full max-w-md" aria-hidden="true">
            <div data-hero-toy data-depth="8" class="absolute inset-[12%] rounded-full bg-gradient-to-br from-sky-300 to-grape-400 shadow-pop"></div>
            <div data-hero-toy data-depth="14" class="absolute inset-[24%] grid place-items-center rounded-full bg-white/90 shadow-soft dark:bg-ink-900/90">
                <span class="block animate-float [--r:-6deg]"><?= mascot_svg('size-36 sm:size-44') ?></span>
            </div>
            <span data-hero-toy data-depth="30" class="absolute top-[4%] left-[8%] grid size-20 place-items-center rounded-3xl bg-coral-400 text-5xl text-white shadow-chunky"><span class="animate-float [--r:10deg] [animation-delay:-1s]"><i class="ph-fill ph-car-profile" aria-hidden="true"></i></span></span>
            <span data-hero-toy data-depth="-24" class="absolute top-[2%] right-[10%] grid size-16 place-items-center rounded-full bg-mint-400 text-4xl text-white shadow-chunky"><span class="animate-float [animation-delay:-2s]"><i class="ph-fill ph-balloon" aria-hidden="true"></i></span></span>
            <span data-hero-toy data-depth="26" class="absolute right-[2%] bottom-[18%] grid size-20 place-items-center rounded-3xl bg-sun-400 text-5xl text-white shadow-chunky"><span class="animate-float [--r:-8deg] [animation-delay:-3s]"><i class="ph-fill ph-puzzle-piece" aria-hidden="true"></i></span></span>
            <span data-hero-toy data-depth="-30" class="absolute bottom-[4%] left-[14%] grid size-16 place-items-center rounded-2xl bg-grape-400 text-4xl text-white shadow-chunky"><span class="animate-float [animation-delay:-4s]"><i class="ph-fill ph-rocket-launch" aria-hidden="true"></i></span></span>
            <span data-hero-toy data-depth="18" class="absolute top-[42%] -left-[2%] grid size-14 place-items-center rounded-full bg-sky-400 text-3xl text-white shadow-chunky"><span class="animate-float [animation-delay:-1.5s]"><i class="ph-fill ph-soccer-ball" aria-hidden="true"></i></span></span>
            <span data-hero-toy data-depth="-14" class="absolute top-[30%] right-0 text-4xl text-sun-500"><span class="animate-float [animation-delay:-2.5s]"><i class="ph-fill ph-star" aria-hidden="true"></i></span></span>
        </div>
    </div>
    </div>

    <?php if ($flash): $slideNo++; $fanned = array_slice($flash, 0, 3); ?>
    <!-- Slide 2: flash sale -->
    <div class="kz-slide relative isolate overflow-hidden px-6 pt-12 pb-24 transition-opacity duration-700 [grid-area:1/1] sm:px-12 lg:pt-16 opacity-0 pointer-events-none bg-ink-900 text-white dark:bg-black" inert
         role="group" aria-roledescription="slide" aria-label="<?= $slideNo + 1 ?> / <?= $slideCount ?>"
         :class="{ 'is-active': active === <?= $slideNo ?>, 'opacity-0 pointer-events-none': active !== <?= $slideNo ?> }" :inert="active !== <?= $slideNo ?>">
        <div class="absolute -top-24 -left-24 -z-10 size-96 rounded-full bg-coral-500/35 blur-3xl"></div>
        <div class="absolute -right-16 -bottom-24 -z-10 size-96 rounded-full bg-sun-400/25 blur-3xl"></div>
        <div class="grid items-center gap-10 lg:grid-cols-2">
            <div x-data="countdown('<?= e(date('c', $flashEnd)) ?>')">
                <p data-slide-in class="badge bg-sun-400 px-3 py-1.5 text-sm text-ink-900"><i class="ph-fill ph-lightning" aria-hidden="true"></i> Flash sale hôm nay</p>
                <h2 data-slide-in style="--i:1" class="mt-4 font-display text-4xl leading-[1.05] font-bold sm:text-5xl lg:text-6xl">
                    Giá sốc <span class="text-sun-300">đến -<?= $flashMaxPercent ?>%</span>
                </h2>
                <p data-slide-in style="--i:2" class="mt-4 max-w-lg text-lg text-ink-100">Số lượng có hạn, hết giờ là về giá cũ. Nhanh tay chọn quà cho bé!</p>
                <div data-slide-in style="--i:3" class="mt-6 flex items-center gap-2 tabular-nums" aria-hidden="true">
                    <?php foreach (["String(Number(d) * 24 + Number(h)).padStart(2, '0')" => 'giờ', 'm' => 'phút', 's' => 'giây'] as $expr => $label): ?>
                        <span class="grid w-16 rounded-2xl bg-white/10 py-2 text-center ring-1 ring-white/15">
                            <span class="font-display text-2xl font-bold" x-text="<?= $expr ?>">00</span>
                            <span class="text-[11px] font-bold tracking-wider text-ink-200 uppercase"><?= $label ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
                <a data-slide-in style="--i:4" class="btn btn-sun btn-lg mt-8" href="<?= e(url('products.php?sale=1')) ?>"><i class="ph-fill ph-fire text-lg" aria-hidden="true"></i> Săn deal ngay</a>
            </div>
            <div class="relative mx-auto flex h-72 w-full max-w-md items-center justify-center sm:h-80" aria-hidden="true">
                <?php foreach ($fanned as $fi => $fp): ?>
                    <?php $rot = ['-rotate-12 -translate-x-[70%]', '-translate-y-3', 'rotate-12 translate-x-[70%]'][$fi] ?? ''; ?>
                    <span data-slide-in style="--i:<?= $fi + 2 ?>" class="absolute w-40 sm:w-48 <?= $fi === 1 ? 'z-10' : '' ?>">
                        <span class="block rounded-3xl bg-white p-2 shadow-2xl transition duration-500 <?= $rot ?>">
                            <img src="<?= e(product_image_url($fp['image'] ?? null)) ?>" alt="" class="aspect-square w-full rounded-2xl object-cover">
                            <span class="mt-2 flex items-center justify-between px-1 pb-1">
                                <span class="font-display text-base font-bold text-price"><?= e(format_price(effective_price($fp))) ?></span>
                                <span class="badge bg-coral-600 text-white">-<?= (int)round((1 - effective_price($fp) / max(1, (float)$fp['price'])) * 100) ?>%</span>
                            </span>
                        </span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($heroCoupon): $slideNo++; ?>
    <!-- Slide 3: mã giảm giá -->
    <div class="kz-slide relative isolate overflow-hidden px-6 pt-12 pb-24 transition-opacity duration-700 [grid-area:1/1] sm:px-12 lg:pt-16 opacity-0 pointer-events-none bg-gradient-to-br from-coral-200 via-sun-100 to-sun-200 dark:from-coral-500/25 dark:via-ink-900 dark:to-ink-800" inert
         role="group" aria-roledescription="slide" aria-label="<?= $slideNo + 1 ?> / <?= $slideCount ?>"
         :class="{ 'is-active': active === <?= $slideNo ?>, 'opacity-0 pointer-events-none': active !== <?= $slideNo ?> }" :inert="active !== <?= $slideNo ?>">
        <div class="bg-dots absolute inset-0 -z-10 opacity-60"></div>
        <div class="grid items-center gap-10 lg:grid-cols-2">
            <div>
                <p data-slide-in class="badge bg-white px-3 py-1.5 text-sm text-price shadow-soft dark:bg-ink-800"><i class="ph-fill ph-ticket" aria-hidden="true"></i> Ưu đãi cho bé</p>
                <h2 data-slide-in style="--i:1" class="mt-4 font-display text-4xl leading-[1.05] font-bold text-ink-900 sm:text-5xl lg:text-6xl dark:text-white">
                    Nhập mã, <span class="text-price">giảm ngay!</span>
                </h2>
                <p data-slide-in style="--i:2" class="mt-4 max-w-lg text-lg text-ink-700 dark:text-ink-200"><?= e(coupon_summary($heroCoupon)) ?>. Áp dụng ngay trong giỏ hàng.</p>
                <div data-slide-in style="--i:3" class="mt-6 inline-flex items-stretch overflow-hidden rounded-3xl bg-white border-2 border-dashed border-coral-400 shadow-soft dark:bg-ink-900"
                     x-data="{ copied: false }">
                    <span class="flex items-center gap-2 px-5 py-3 font-display text-2xl font-bold tracking-widest text-ink-900 dark:text-white"><i class="ph-fill ph-ticket text-price" aria-hidden="true"></i><?= e($heroCoupon['code']) ?></span>
                    <button type="button" class="flex items-center gap-1.5 bg-coral-600 px-4 text-sm font-extrabold text-white transition hover:bg-coral-700"
                            @click="navigator.clipboard && navigator.clipboard.writeText('<?= e($heroCoupon['code']) ?>').then(() => { copied = true; $store.toast.show('Đã chép mã <?= e($heroCoupon['code']) ?>', 'success'); setTimeout(() => copied = false, 2000) })">
                        <i class="ph-bold" :class="copied ? 'ph-check' : 'ph-copy'" aria-hidden="true"></i><span x-text="copied ? 'Đã chép' : 'Chép mã'">Chép mã</span>
                    </button>
                </div>
                <div data-slide-in style="--i:4" class="mt-8"><a class="btn btn-primary btn-lg" href="<?= e(url('products.php')) ?>"><i class="ph-bold ph-storefront text-lg" aria-hidden="true"></i> Chọn đồ chơi</a></div>
            </div>
            <div class="relative mx-auto grid aspect-square w-full max-w-sm place-items-center" aria-hidden="true">
                <span data-slide-in style="--i:1" class="absolute inset-[8%] rounded-full bg-white/80 shadow-soft dark:bg-ink-900/80"></span>
                <span data-slide-in style="--i:2" class="relative block animate-float [--r:4deg]"><?= mascot_svg('size-48 sm:size-56', 'happy', true) ?></span>
                <span data-slide-in style="--i:3" class="absolute top-[6%] right-[8%] grid size-16 rotate-12 place-items-center rounded-2xl bg-coral-500 text-3xl text-white shadow-chunky"><i class="ph-fill ph-percent" aria-hidden="true"></i></span>
                <span data-slide-in style="--i:4" class="absolute bottom-[10%] left-[4%] grid size-14 -rotate-12 place-items-center rounded-full bg-sun-400 text-3xl text-ink-900 shadow-chunky"><i class="ph-fill ph-gift" aria-hidden="true"></i></span>
                <i data-slide-in style="--i:5" class="ph-fill ph-sparkle absolute top-[20%] left-[10%] text-4xl text-sun-500" aria-hidden="true"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div>

    <?php if ($slideCount > 1): ?>
    <!-- Điều khiển banner -->
    <div class="absolute right-4 bottom-4 z-10 flex items-center gap-1 rounded-full bg-white/85 p-1 shadow-soft ring-1 ring-ink-100 backdrop-blur sm:right-6 sm:bottom-6 dark:bg-ink-900/85 dark:ring-ink-700">
        <button type="button" class="grid size-9 place-items-center rounded-full transition hover:bg-ink-100 dark:hover:bg-ink-800" @click="prev()" aria-label="Banner trước"><i class="ph-bold ph-caret-left" aria-hidden="true"></i></button>
        <?php for ($i = 0; $i < $slideCount; $i++): ?>
            <button type="button" class="group/dot grid h-6 min-w-6 place-items-center px-[7px]" @click="go(<?= $i ?>)"
                    aria-label="Xem banner <?= $i + 1 ?>" :aria-current="active === <?= $i ?> ? 'true' : null">
                <span class="relative block h-2.5 overflow-hidden rounded-full transition-all duration-300"
                      :class="active === <?= $i ?> ? 'w-10 bg-ink-200 dark:bg-ink-700' : 'w-2.5 bg-ink-400 group-hover/dot:bg-ink-600 dark:bg-ink-400 dark:group-hover/dot:bg-ink-200'">
                    <span class="absolute inset-y-0 left-0 rounded-full bg-ink-900 dark:bg-white" :style="'width:' + (active === <?= $i ?> ? (playing ? progress : 100) : 0) + '%'"></span>
                </span>
            </button>
        <?php endfor; ?>
        <button type="button" class="grid size-9 place-items-center rounded-full transition hover:bg-ink-100 dark:hover:bg-ink-800" @click="next()" aria-label="Banner sau"><i class="ph-bold ph-caret-right" aria-hidden="true"></i></button>
        <button type="button" class="grid size-9 place-items-center rounded-full transition hover:bg-ink-100 dark:hover:bg-ink-800" @click="playing = !playing"
                :aria-label="playing ? 'Tạm dừng banner tự chuyển' : 'Cho banner tự chuyển'" :aria-pressed="(!playing).toString()">
            <i class="ph-fill" :class="playing ? 'ph-pause' : 'ph-play'" aria-hidden="true"></i>
        </button>
    </div>
    <?php endif; ?>
</section>

<!-- Cam kết -->
<section class="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <?php foreach ([
        ['ph-shield-check', 'An toàn cho bé', 'Chất liệu đạt chuẩn', 'text-ok bg-mint-100 dark:bg-mint-500/10'],
        ['ph-truck', 'Giao siêu tốc', 'Miễn phí từ 500k', 'text-sky-600 bg-sky-100 dark:bg-sky-500/10'],
        ['ph-arrow-counter-clockwise', 'Đổi trả 7 ngày', 'Không cần lý do', 'text-price bg-coral-100 dark:bg-coral-500/10'],
        ['ph-qr-code', 'Thanh toán QR', 'Nhanh, chính xác', 'text-age bg-grape-100 dark:bg-grape-500/10'],
    ] as [$icon, $title, $sub, $tone]): ?>
        <div data-reveal class="card flex items-center gap-3 p-4">
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl text-2xl <?= $tone ?>"><i class="ph-fill <?= $icon ?>" aria-hidden="true"></i></span>
            <div><p class="font-extrabold leading-tight"><?= e($title) ?></p><p class="text-xs text-muted"><?= e($sub) ?></p></div>
        </div>
    <?php endforeach; ?>
</section>

<!-- Chủ đề bé thích: dải chạy ngang, rê chuột / focus thì dừng -->
<section class="mt-10" aria-labelledby="kz-themes-title">
    <h2 id="kz-themes-title" class="sr-only">Chủ đề bé yêu thích</h2>
    <div class="relative overflow-hidden [mask-image:linear-gradient(90deg,transparent,#000_6%,#000_94%,transparent)]">
        <ul class="flex w-max animate-marquee gap-3 py-3 [animation-duration:45s] hover:[animation-play-state:paused] focus-within:[animation-play-state:paused]">
            <?php for ($loop = 0; $loop < 2; $loop++): ?>
                <?php foreach ($themes as [$term, $icon, $tone]): ?>
                    <li <?= $loop ? 'aria-hidden="true"' : '' ?>>
                        <a href="<?= e(url('products.php?q=' . urlencode($term))) ?>" <?= $loop ? 'tabindex="-1"' : '' ?>
                           class="flex items-center gap-2 rounded-full bg-white px-5 py-2.5 font-display text-lg font-semibold whitespace-nowrap text-ink-800 shadow-soft ring-1 ring-ink-100 transition duration-300 hover:-translate-y-1 hover:-rotate-2 hover:shadow-pop dark:bg-ink-900 dark:text-white dark:ring-ink-800">
                            <i class="ph-fill <?= $icon ?> text-2xl <?= $tone ?>" aria-hidden="true"></i><?= e($term) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endfor; ?>
        </ul>
    </div>
</section>

<!-- Danh mục -->
<section class="mt-14">
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h2 class="section-title">Danh mục đồ chơi</h2>
        </div>
        <a class="link hidden text-sm sm:inline" href="<?= e(url('products.php')) ?>">Xem tất cả <i class="ph-bold ph-arrow-right" aria-hidden="true"></i></a>
    </div>
    <?php if (!$categories): ?>
        <p class="text-muted">Chưa có danh mục nào.</p>
    <?php else: ?>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <?php foreach ($categories as $i => $c): ?>
                <?php [$icon, $tone, $dot] = $categoryStyles[$i % count($categoryStyles)]; ?>
                <a data-reveal href="<?= e(url('products.php?category=' . (int)$c['id'])) ?>"
                   class="group relative flex flex-col items-center overflow-hidden rounded-4xl p-6 text-center transition duration-300 hover:-translate-y-1.5 <?= $tone ?>">
                    <span class="absolute -top-6 -right-6 size-20 rounded-full opacity-30 transition duration-500 group-hover:scale-[3] <?= $dot ?>"></span>
                    <span class="relative grid size-16 place-items-center rounded-3xl bg-white text-4xl shadow-soft transition duration-300 group-hover:animate-wiggle dark:bg-ink-900"><i class="ph-fill <?= $icon ?>" aria-hidden="true"></i></span>
                    <span class="relative mt-4 font-display text-lg leading-tight font-semibold text-ink-900 dark:text-white"><?= e($c['name']) ?></span>
                    <span class="relative mt-1 text-xs font-bold"><?= (int)$c['product_count'] ?> sản phẩm</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Flash sale (#5) -->
<?php if ($flash): ?>
<section class="relative mx-[calc(50%-50vw)] mt-16 overflow-hidden bg-ink-900 pt-20 pb-20 dark:bg-black" x-data="countdown('<?= e(date('c', $flashEnd)) ?>')">
    <?= wave_svg('absolute inset-x-0 top-0 z-[1] h-8 text-cream sm:h-12 dark:text-ink-950') ?>
    <?= wave_svg('absolute inset-x-0 bottom-0 z-[1] h-8 text-cream sm:h-12 dark:text-ink-950', true) ?>
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="absolute -top-20 -left-20 size-72 rounded-full bg-coral-500/30 blur-3xl"></div>
    <div class="absolute -right-10 -bottom-24 size-72 rounded-full bg-sun-400/20 blur-3xl"></div>
    <div class="relative mb-8 flex flex-wrap items-center justify-between gap-6">
        <div>
            <h2 class="flex items-center gap-3 font-display text-3xl font-bold text-white sm:text-4xl"><i class="ph-fill ph-lightning text-sun-400" aria-hidden="true"></i> Flash Sale hôm nay</h2>
        </div>
        <div class="flex items-center gap-2" aria-live="off">
            <span class="mr-1 text-sm font-bold text-ink-200">Kết thúc sau</span>
            <?php foreach (['d' => 'ngày', 'h' => 'giờ', 'm' => 'phút', 's' => 'giây'] as $k => $label): ?>
                <span class="grid w-16 rounded-2xl bg-white/10 py-2 text-center ring-1 ring-white/15 backdrop-blur">
                    <span class="font-display text-2xl font-bold text-white tabular-nums" x-text="<?= $k ?>">00</span>
                    <span class="text-[11px] font-bold tracking-wider text-ink-200 uppercase"><?= $label ?></span>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="no-scrollbar relative -mx-2 flex snap-x snap-mandatory gap-4 overflow-x-auto px-2 pb-2">
        <?php foreach ($flash as $p): ?>
            <div class="w-[46%] shrink-0 snap-start sm:w-[31%] lg:w-[23.5%]"><?php render_product_card($p); ?></div>
        <?php endforeach; ?>
    </div>
    </div>
</section>
<?php endif; ?>

<!-- Mua theo độ tuổi (#3) -->
<section class="mt-16">
    <div class="mb-6">
        <h2 class="section-title">Mua theo độ tuổi</h2>
    </div>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <?php foreach (age_groups() as $key => [$label, $from, $to, $ageIcon]): ?>
            <a data-reveal href="<?= e(url('products.php?age=' . $key)) ?>"
               class="group relative overflow-hidden rounded-4xl bg-gradient-to-br p-6 shadow-soft transition duration-300 hover:-translate-y-1.5 hover:shadow-pop <?= $ageStyles[$key] ?>">
                <i class="ph-fill <?= e($ageIcon) ?> absolute -right-3 -bottom-5 text-8xl text-white/60 transition duration-500 group-hover:scale-125 group-hover:-rotate-12" aria-hidden="true"></i>
                <span class="relative block text-sm font-bold">Bé</span>
                <span class="relative block font-display text-3xl font-bold"><?= e($label) ?></span>
                <span class="relative mt-6 inline-flex items-center gap-1 rounded-full bg-white/70 px-3 py-1 text-xs font-extrabold">Xem đồ chơi <i class="ph-bold ph-arrow-right transition group-hover:translate-x-1" aria-hidden="true"></i></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Sản phẩm nổi bật -->
<section class="mt-16">
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h2 class="section-title">Sản phẩm nổi bật</h2>
        </div>
        <a class="link text-sm" href="<?= e(url('products.php')) ?>">Xem tất cả <i class="ph-bold ph-arrow-right" aria-hidden="true"></i></a>
    </div>
    <?php if (!$featured): ?>
        <p class="text-muted">Chưa có sản phẩm nổi bật.</p>
    <?php else: ?>
        <div class="grid grid-cols-2 gap-4 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
            <?php foreach ($featured as $p) { render_product_card($p); } ?>
        </div>
    <?php endif; ?>
</section>

<!-- Quảng bá trợ lý chọn quà (#4) -->
<section data-reveal class="relative mt-16 overflow-hidden rounded-5xl bg-gradient-to-r from-grape-600 via-sky-700 to-sky-600 p-8 text-white sm:p-12">
    <div class="bg-dots absolute inset-0 opacity-30"></div>
    <div class="relative grid items-center gap-8 md:grid-cols-[1fr_auto]">
        <div>
            <h2 class="font-display text-3xl font-bold sm:text-4xl">Chưa biết tặng gì cho bé?</h2>
            <p class="mt-2 max-w-xl text-white">Trả lời 3 câu hỏi nhanh về độ tuổi, sở thích và ngân sách, KiddoZone sẽ gợi ý những món quà phù hợp nhất.</p>
        </div>
        <a href="<?= e(url('gift-finder.php')) ?>" class="btn btn-sun btn-lg justify-self-start"><i class="ph-fill ph-magic-wand text-lg" aria-hidden="true"></i> Bắt đầu chọn quà</a>
    </div>
    <i class="ph-fill ph-gift absolute top-6 right-[30%] hidden animate-float text-5xl text-sun-300 md:block" aria-hidden="true"></i>
</section>

<!-- Ba mẹ nói gì: đánh giá thật từ CSDL -->
<?php if ($testimonials): ?>
<section class="relative mx-[calc(50%-50vw)] mt-16 bg-sun-100 dark:bg-ink-900" aria-labelledby="kz-love-title">
    <?= wave_svg('absolute inset-x-0 top-0 h-8 text-cream sm:h-12 dark:text-ink-950') ?>
    <div class="mx-auto max-w-7xl px-4 pt-20 pb-20 sm:px-6 lg:px-8">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-4">
                <span class="hidden sm:block"><?= mascot_svg('size-16', 'happy', true) ?></span>
                <div>
                    <h2 id="kz-love-title" class="section-title">Ba mẹ nói gì về KiddoZone</h2>
                    <p class="mt-2 text-sm font-bold text-muted">Đánh giá từ khách đã mua hàng</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-full bg-white px-5 py-2.5 shadow-soft dark:bg-ink-800">
                <span class="font-display text-3xl font-bold text-ink-900 dark:text-white"><?= e(review_format_avg((float)($stats['rating'] ?? 5))) ?></span>
                <span><?php render_stars((float)($stats['rating'] ?? 5)); ?><span class="block text-xs font-bold text-muted"><?= (int)$stats['reviews'] ?> đánh giá</span></span>
            </div>
        </div>
        <div class="no-scrollbar -mx-4 flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pt-4 pb-6">
            <?php foreach ($testimonials as $ti => $t): ?>
                <figure data-reveal class="flex w-[85%] shrink-0 snap-start flex-col rounded-4xl bg-white p-6 shadow-soft ring-1 ring-ink-100 transition duration-300 hover:rotate-0 hover:shadow-pop sm:w-[46%] lg:w-[31.5%] dark:bg-ink-800 dark:ring-ink-700 <?= $ti % 2 ? 'rotate-1' : '-rotate-1' ?>">
                    <div class="flex items-center justify-between">
                        <i class="ph-fill ph-quotes text-4xl text-sun-400" aria-hidden="true"></i>
                        <span><?php render_stars((float)$t['rating']); ?><span class="sr-only"><?= (int)$t['rating'] ?> trên 5 sao</span></span>
                    </div>
                    <blockquote class="mt-3 line-clamp-5 flex-1 text-ink-700 dark:text-ink-200"><?= e($t['comment']) ?></blockquote>
                    <figcaption class="mt-5 flex items-center gap-3 border-t border-ink-100 pt-4 dark:border-ink-700">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-sun-300 to-coral-300 font-extrabold text-ink-900"><?= e(mb_strtoupper(mb_substr($t['full_name'], 0, 1))) ?></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-extrabold"><?= e(review_mask_name($t['full_name'])) ?></span>
                            <span class="flex items-center gap-1 text-xs font-bold text-ok"><i class="ph-fill ph-seal-check" aria-hidden="true"></i> Đã mua hàng</span>
                        </span>
                        <a href="<?= e(url('product.php?id=' . (int)$t['product_id'])) ?>" class="shrink-0 transition hover:-translate-y-0.5" title="<?= e($t['product_name']) ?>">
                            <img src="<?= e(product_image_url($t['image'] ?? null)) ?>" alt="<?= e($t['product_name']) ?>" loading="lazy" class="size-12 rounded-xl object-cover ring-2 ring-white dark:ring-ink-700">
                        </a>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
    <?= wave_svg('absolute inset-x-0 bottom-0 h-8 text-cream sm:h-12 dark:text-ink-950', true) ?>
</section>
<?php endif; ?>

<!-- Sản phẩm mới nhất -->
<section class="mt-16">
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h2 class="section-title">Sản phẩm mới nhất</h2>
        </div>
        <a class="link text-sm" href="<?= e(url('products.php')) ?>">Xem tất cả <i class="ph-bold ph-arrow-right" aria-hidden="true"></i></a>
    </div>
    <?php if (!$newest): ?>
        <p class="text-muted">Chưa có sản phẩm nào.</p>
    <?php else: ?>
        <div class="grid grid-cols-2 gap-4 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
            <?php foreach ($newest as $p) { render_product_card($p); } ?>
        </div>
    <?php endif; ?>
</section>

<?php render_recently_viewed($recent); ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

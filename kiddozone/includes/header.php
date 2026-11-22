<?php
// Header trang khách. Trang gọi: đặt $pageTitle rồi require __DIR__ . '/includes/header.php';
// Tùy chọn: $mainClass (class cho thẻ <main>).
$pageTitle = $pageTitle ?? 'KiddoZone';
$user = current_user();

// Trang hiện tại, dùng để tô sáng mục menu đang mở.
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$cartCount = cart_count();

// Menu danh mục lấy từ CSDL (chỉ danh mục đang hiển thị).
$navCategories = db_query(
    'SELECT c.id, c.name, COUNT(p.id) AS product_count
     FROM categories c LEFT JOIN products p ON p.category_id = c.id AND p.status = 1
     WHERE c.status = 1 GROUP BY c.id, c.name ORDER BY c.id LIMIT 8'
)->fetchAll();
// Sản phẩm hiện trong mega menu: bán chạy nhất (theo đơn chưa hủy); chưa có đơn nào thì lấy sản phẩm nổi bật.
$megaPick = db_query(
    "SELECT p.id, p.name, " . product_price_columns() . ",
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image,
            (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE oi.product_id = p.id AND o.status <> 'cancelled') AS sold
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.status = 1 AND c.status = 1 AND p.stock > 0
     ORDER BY sold DESC, p.is_featured DESC, p.created_at DESC LIMIT 1"
)->fetch();
$megaTones = [
    'bg-sun-100 text-sun-800 dark:bg-sun-500/15 dark:text-sun-300',
    'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    'bg-coral-100 text-coral-700 dark:bg-coral-500/15 dark:text-coral-300',
    'bg-mint-100 text-mint-700 dark:bg-mint-500/15 dark:text-mint-300',
    'bg-grape-100 text-grape-600 dark:bg-grape-500/15 dark:text-grape-300',
];
$navSale       = $currentPage === 'products.php' && ($_GET['sale'] ?? '') === '1';
$navCatalog    = $currentPage === 'products.php' && !$navSale;
$navIcons      = ['ph-lightbulb', 'ph-puzzle-piece', 'ph-heart', 'ph-car-profile', 'ph-soccer-ball', 'ph-palette', 'ph-music-notes', 'ph-planet'];

// Giữ lại từ khóa trong ô tìm kiếm khi đang ở trang sản phẩm.
$searchValue = ($currentPage === 'products.php' && is_string($_GET['q'] ?? null)) ? $_GET['q'] : '';

// Cấu hình cho assets/js/app.js
$kzConfig = [
    'base'     => base_url(),
    'csrf'     => csrf_token(),
    'loggedIn' => $user !== null,
    'isAdmin'  => is_admin(),
    'cartCount'=> $cartCount,
    'api'      => [
        'cart'    => url('api/cart.php'),
        'search'  => url('api/search.php'),
        'product' => url('api/product.php'),
        'products'=> url('api/products.php'),
        'coupon'  => url('api/coupon.php'),
    ],
    'urls'     => [
        'cart'     => url('cart.php'),
        'checkout' => url('checkout.php'),
        'login'    => url('login.php'),
        'products' => url('products.php'),
    ],
];

require __DIR__ . '/head.php';
?>
<body class="flex min-h-dvh flex-col">
<script>window.KZ = <?= json_encode($kzConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>

<a href="#main" data-page-chrome class="sr-only z-50 rounded-full bg-sky-600 px-4 py-2 font-bold text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Bỏ qua tới nội dung</a>

<!-- Dải khuyến mãi chạy ngang -->
<div data-page-chrome class="overflow-hidden bg-ink-900 text-xs font-bold text-white dark:bg-black" role="region" aria-label="Ưu đãi">
    <div class="flex w-max animate-marquee gap-10 py-2 whitespace-nowrap hover:[animation-play-state:paused]">
        <?php for ($loop = 0; $loop < 2; $loop++): ?>
            <span class="flex items-center gap-2" <?= $loop ? 'aria-hidden="true"' : '' ?>><i class="ph-fill ph-truck text-sun-400" aria-hidden="true"></i> Miễn phí giao hàng cho đơn từ 500.000đ</span>
            <span class="flex items-center gap-2" <?= $loop ? 'aria-hidden="true"' : '' ?>><i class="ph-fill ph-ticket text-coral-300" aria-hidden="true"></i> Nhập mã <b class="text-sun-300">KIDDO10</b> giảm ngay 10%</span>
            <span class="flex items-center gap-2" <?= $loop ? 'aria-hidden="true"' : '' ?>><i class="ph-fill ph-lightning text-sun-400" aria-hidden="true"></i> Flash sale mỗi ngày, số lượng có hạn</span>
            <span class="flex items-center gap-2" <?= $loop ? 'aria-hidden="true"' : '' ?>><i class="ph-fill ph-shield-check text-mint-300" aria-hidden="true"></i> Đồ chơi an toàn, đạt chuẩn cho bé 0 - 12 tuổi</span>
            <span class="flex items-center gap-2" <?= $loop ? 'aria-hidden="true"' : '' ?>><i class="ph-fill ph-phone text-sky-300" aria-hidden="true"></i> Hotline 1900 1234 (8h - 21h)</span>
        <?php endfor; ?>
    </div>
</div>

<header data-page-chrome class="sticky top-0 z-40 border-b border-ink-100/80 bg-cream/85 backdrop-blur-xl dark:border-ink-800 dark:bg-ink-950/85" x-data="siteHeader" @focusout="setTimeout(() => !$el.contains(document.activeElement) && closeMega(), 0)" @keydown.escape="closeMega(true)">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:px-6 lg:gap-6 lg:px-8">
        <!-- Logo -->
        <a href="<?= e(url('index.php')) ?>" class="group flex shrink-0 items-center gap-2" aria-label="KiddoZone - Trang chủ">
            <span class="grid size-11 place-items-center rounded-2xl bg-sun-400 shadow-chunky transition group-hover:-rotate-12 group-hover:scale-110"><?= mascot_svg('size-8') ?></span>
            <span class="hidden font-display text-2xl font-bold tracking-tight sm:block">
                <span class="text-sky-600">Kiddo</span><span class="text-price">Zone</span>
            </span>
        </a>

        <!-- Tìm kiếm tức thì (#2) -->
        <form class="relative hidden flex-1 md:block" action="<?= e(url('products.php')) ?>" method="get" role="search"
              x-data="liveSearch(<?= e(json_encode($searchValue, JSON_UNESCAPED_UNICODE)) ?>)" @click.outside="close()" @keydown.escape="close()">
            <label for="kz-search" class="sr-only">Tìm kiếm sản phẩm</label>
            <i class="ph-bold ph-magnifying-glass pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-lg text-muted" aria-hidden="true"></i>
            <input id="kz-search" type="search" name="q" maxlength="100" autocomplete="off"
                   class="input rounded-full! py-3! pr-12 pl-11" placeholder="Tìm đồ chơi, xe, búp bê, lego..."
                   x-model="q" @input.debounce.250ms="search()" @focus="q.length >= 2 && (open = true)"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter="choose($event)"
                   role="combobox" aria-autocomplete="list" aria-controls="kz-search-list" :aria-expanded="open" :aria-activedescendant="active >= 0 ? 'kz-opt-' + active : null">
            <span class="absolute top-1/2 right-4 -translate-y-1/2 text-muted" x-show="loading" x-cloak><i class="ph-bold ph-spinner-gap animate-spin text-lg" aria-hidden="true"></i></span>

            <div x-show="open" x-cloak x-transition.origin.top
                 class="absolute inset-x-0 top-full mt-2 overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-ink-100 dark:bg-ink-900 dark:ring-ink-700">
                <ul id="kz-search-list" role="listbox" class="max-h-[60vh] overflow-y-auto p-2">
                    <template x-for="(item, i) in results" :key="item.id">
                        <li role="option" :id="'kz-opt-' + i" :aria-selected="i === active">
                            <a :href="item.url" @mouseenter="active = i" tabindex="-1"
                               class="flex items-center gap-3 rounded-2xl p-2 transition"
                               :class="i === active ? 'bg-sky-50 dark:bg-ink-800' : ''">
                                <img :src="item.image" alt="" class="size-14 rounded-xl bg-ink-50 object-cover dark:bg-ink-800" loading="lazy">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-bold text-ink-800 dark:text-white" x-html="highlight(item.name)"></span>
                                    <span class="text-xs text-muted" x-text="item.category + ' · ' + item.age"></span>
                                </span>
                                <span class="text-right">
                                    <span class="block font-extrabold text-price" x-text="item.final_text"></span>
                                    <span class="block text-xs text-muted line-through" x-show="item.on_sale" x-text="item.price_text"></span>
                                </span>
                            </a>
                        </li>
                    </template>
                    <li x-show="!loading && results.length === 0" class="px-4 py-8 text-center text-sm text-muted">
                        <i class="ph-bold ph-magnifying-glass mb-2 block text-3xl" aria-hidden="true"></i> Không tìm thấy đồ chơi nào cho "<span x-text="q"></span>"
                    </li>
                </ul>
                <button type="submit" class="flex w-full items-center justify-center gap-2 border-t border-ink-100 px-4 py-3 text-sm font-bold text-sky-600 hover:bg-sky-50 dark:border-ink-800 dark:text-sky-300 dark:hover:bg-ink-800" x-show="results.length">
                    Xem tất cả kết quả <i class="ph-bold ph-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        </form>

        <!-- Hành động -->
        <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
            <a href="<?= e(url('gift-finder.php')) ?>" class="btn btn-ghost hidden lg:inline-flex" title="Trợ lý chọn quà">
                <i class="ph-fill ph-gift text-lg text-price" aria-hidden="true"></i> Chọn quà
            </a>

            <button type="button" class="btn btn-ghost btn-icon" @click="$store.theme.toggle()" :aria-label="$store.theme.dark ? 'Chuyển sang chế độ sáng' : 'Chuyển sang chế độ tối'" title="Đổi chế độ sáng / tối">
                <i class="ph-fill ph-moon-stars text-xl text-age dark:hidden" aria-hidden="true"></i>
                <i class="ph-fill ph-sun hidden text-xl text-sun-400 dark:inline" aria-hidden="true"></i>
            </button>

            <?php if ($user && !is_admin()): ?>
                <a href="<?= e(url('account/wishlist.php')) ?>" class="btn btn-ghost btn-icon hidden sm:inline-flex" aria-label="Sản phẩm yêu thích" title="Yêu thích">
                    <i class="ph-fill ph-heart text-xl text-price" aria-hidden="true"></i>
                </a>
            <?php endif; ?>

            <?php if ($user): ?>
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" class="btn btn-ghost gap-2 px-2 xl:pr-4" @click="open = !open" :aria-expanded="open" aria-haspopup="true" aria-label="Tài khoản">
                        <span class="grid size-7 place-items-center rounded-full bg-gradient-to-br from-sky-600 to-grape-600 text-xs font-extrabold text-white">
                            <?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?>
                        </span>
                        <span class="hidden max-w-28 truncate xl:block"><?= e($user['full_name']) ?></span>
                        <i class="ph-bold ph-caret-down hidden text-xs transition xl:block" aria-hidden="true" :class="open && 'rotate-180'"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-60 overflow-hidden rounded-3xl bg-white p-2 shadow-2xl ring-1 ring-ink-100 dark:bg-ink-900 dark:ring-ink-700">
                        <div class="px-3 py-2">
                            <p class="text-xs text-muted">Xin chào</p>
                            <p class="truncate font-bold"><?= e($user['full_name']) ?></p>
                        </div>
                        <?php
                        $menuItems = is_admin()
                            ? [['admin/index.php', 'ph-squares-four', 'Trang quản trị']]
                            : [
                                ['account/profile.php', 'ph-user-circle', 'Hồ sơ cá nhân'],
                                ['account/orders.php', 'ph-package', 'Đơn hàng của tôi'],
                                ['account/wishlist.php', 'ph-heart', 'Sản phẩm yêu thích'],
                            ];
                        foreach ($menuItems as [$href, $icon, $label]): ?>
                            <a href="<?= e(url($href)) ?>" class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold hover:bg-sky-50 dark:hover:bg-ink-800">
                                <i class="ph-bold <?= e($icon) ?> text-lg text-sky-600" aria-hidden="true"></i> <?= e($label) ?>
                            </a>
                        <?php endforeach; ?>
                        <form method="post" action="<?= e(url('logout.php')) ?>" class="mt-1 border-t border-ink-100 pt-1 dark:border-ink-800">
                            <?= csrf_field() ?>
                            <button type="submit" class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-price hover:bg-coral-50 dark:hover:bg-coral-500/10">
                                <i class="ph-bold ph-sign-out text-lg" aria-hidden="true"></i> Đăng xuất
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= e(url('login.php')) ?>" class="btn btn-ghost" aria-label="Đăng nhập">
                    <i class="ph-bold ph-user text-lg" aria-hidden="true"></i><span class="hidden sm:inline">Đăng nhập</span>
                </a>
            <?php endif; ?>

            <?php if (!is_admin()): ?>
                <a href="<?= e(url('cart.php')) ?>" class="btn btn-sun relative px-3 sm:px-5" data-cart-target
                   @click.prevent="$store.cart.show()" aria-label="Giỏ hàng">
                    <i class="ph-fill ph-shopping-cart-simple text-xl" aria-hidden="true"></i>
                    <span class="hidden sm:inline">Giỏ hàng</span>
                    <span class="absolute -top-2 -right-2 grid h-6 min-w-6 place-items-center rounded-full bg-coral-600 px-1.5 text-xs font-extrabold text-white ring-2 ring-cream dark:ring-ink-950"
                          x-text="$store.cart.count" :class="$store.cart.bump && 'animate-pop'" x-show="$store.cart.count > 0"
                          <?= $cartCount > 0 ? '' : 'x-cloak' ?>><?= $cartCount ?></span>
                </a>
            <?php endif; ?>

            <button type="button" class="btn btn-ghost btn-icon md:hidden" @click="menu = !menu" :aria-expanded="menu" aria-label="Mở menu">
                <i class="ph-bold text-xl" aria-hidden="true" :class="menu ? 'ph-x' : 'ph-list'"></i>
            </button>
        </div>
    </div>

    <!-- Menu danh mục -->
    <nav class="mx-auto max-w-7xl px-4 pb-3 sm:px-6 lg:px-8" aria-label="Danh mục sản phẩm">
        <div class="md:block" :class="menu ? 'block' : 'hidden md:block'">
            <form class="relative mb-3 md:hidden" action="<?= e(url('products.php')) ?>" method="get" role="search">
                <i class="ph-bold ph-magnifying-glass absolute top-1/2 left-4 -translate-y-1/2 text-muted" aria-hidden="true"></i>
                <input type="search" name="q" maxlength="100" class="input rounded-full! pl-11" placeholder="Tìm kiếm đồ chơi..." value="<?= e($searchValue) ?>" aria-label="Tìm kiếm sản phẩm">
            </form>
            <?php
            $chip = 'flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-bold whitespace-nowrap transition xl:gap-2 xl:px-3.5 xl:py-2 xl:text-sm';
            $chipIdle = 'text-ink-600 hover:bg-white hover:shadow-soft dark:text-ink-300 dark:hover:bg-ink-800';
            $chipOn = 'bg-ink-900 text-white shadow-soft dark:bg-white dark:text-ink-900';
            ?>
            <div class="flex flex-col gap-1 md:flex-row md:items-center md:gap-2">
                <ul class="flex flex-col gap-1 md:min-w-0 md:flex-1 md:flex-row md:items-center xl:gap-1.5">
                    <li><a class="<?= $chip ?> <?= $currentPage === 'index.php' ? $chipOn : $chipIdle ?>" href="<?= e(url('index.php')) ?>"><i class="ph-bold ph-house" aria-hidden="true"></i> Trang chủ</a></li>
                    <?php if ($navCategories): ?>
                        <li class="hidden md:block">
                            <button type="button" x-ref="megaBtn" class="<?= $chip ?> <?= $navCatalog ? $chipOn : $chipIdle ?>"<?= !$navCatalog ? " :class=\"mega && 'bg-white shadow-soft dark:bg-ink-800'\"" : '' ?>
                                    @click="toggleMega($event)" @mouseenter="hoverMega(true)" @mouseleave="hoverMega(false)"
                                    :aria-expanded="mega" aria-controls="kz-mega">
                                <i class="ph-bold ph-squares-four" aria-hidden="true"></i> Danh mục
                                <i class="ph-bold ph-caret-down text-xs transition duration-200" :class="mega && 'rotate-180'" aria-hidden="true"></i>
                            </button>
                        </li>
                    <?php endif; ?>
                    <li class="md:hidden"><a class="<?= $chip ?> <?= $navCatalog ? $chipOn : $chipIdle ?>" href="<?= e(url('products.php')) ?>"><i class="ph-bold ph-squares-four" aria-hidden="true"></i> Danh mục</a></li>
                </ul>
                <div class="flex flex-col gap-1 md:shrink-0 md:flex-row md:items-center">
                    <a class="<?= $chip ?> <?= $navSale ? 'bg-coral-600 text-white' : 'text-price hover:bg-coral-50 dark:hover:bg-coral-500/10' ?>" href="<?= e(url('products.php?sale=1')) ?>"><i class="ph-fill ph-fire" aria-hidden="true"></i> Khuyến mãi</a>
                    <a class="<?= $chip ?> lg:hidden <?= $currentPage === 'gift-finder.php' ? $chipOn : $chipIdle ?>" href="<?= e(url('gift-finder.php')) ?>"><i class="ph-fill ph-gift text-price" aria-hidden="true"></i> Chọn quà</a>
                </div>
            </div>
        </div>
    </nav>

    <?php if ($navCategories): ?>
    <!-- Mega menu danh mục (máy tính) -->
    <div id="kz-mega" class="absolute inset-x-0 top-full hidden md:block" x-show="mega" x-cloak
         x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="opacity-0 -translate-y-2"
         @mouseenter="hoverMega(true)" @mouseleave="hoverMega(false)">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-12 gap-6 rounded-b-4xl rounded-t-xl bg-white p-6 shadow-2xl ring-1 ring-ink-100 dark:bg-ink-900 dark:ring-ink-700">
                <div class="col-span-12 lg:col-span-6">
                    <p class="mb-3 text-xs font-extrabold tracking-wider text-muted uppercase">Danh mục đồ chơi</p>
                    <ul class="grid grid-cols-2 gap-1">
                        <?php foreach ($navCategories as $i => $c): ?>
                            <li>
                                <a href="<?= e(url('products.php?category=' . (int)$c['id'])) ?>" class="group/m flex items-center gap-3 rounded-2xl p-2.5 transition hover:bg-ink-50 dark:hover:bg-ink-800">
                                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl text-2xl transition duration-300 group-hover/m:-rotate-6 group-hover/m:scale-110 <?= $megaTones[$i % count($megaTones)] ?>"><i class="ph-fill <?= e($navIcons[$i % count($navIcons)]) ?>" aria-hidden="true"></i></span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-extrabold leading-tight text-ink-800 dark:text-white"><?= e($c['name']) ?></span>
                                        <span class="text-xs font-bold text-muted"><?= (int)$c['product_count'] ?> sản phẩm</span>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-span-6 lg:col-span-3">
                    <p class="mb-3 text-xs font-extrabold tracking-wider text-muted uppercase">Theo độ tuổi</p>
                    <ul class="space-y-1">
                        <?php foreach (age_groups() as $key => [$ageLabel, , , $ageIcon]): ?>
                            <li><a href="<?= e(url('products.php?age=' . $key)) ?>" class="group/m flex items-center gap-3 rounded-2xl px-2.5 py-2 font-bold transition hover:bg-ink-50 dark:hover:bg-ink-800">
                                <i class="ph-fill <?= e($ageIcon) ?> text-xl text-age" aria-hidden="true"></i> Bé <?= e($ageLabel) ?>
                                <i class="ph-bold ph-arrow-right ml-auto text-sm opacity-0 transition group-hover/m:translate-x-1 group-hover/m:opacity-100" aria-hidden="true"></i>
                            </a></li>
                        <?php endforeach; ?>
                        <li><a href="<?= e(url('products.php?sale=1')) ?>" class="flex items-center gap-3 rounded-2xl px-2.5 py-2 font-bold text-price transition hover:bg-coral-50 dark:hover:bg-coral-500/10"><i class="ph-fill ph-fire text-xl" aria-hidden="true"></i> Đang khuyến mãi</a></li>
                        <li><a href="<?= e(url('gift-finder.php')) ?>" class="flex items-center gap-3 rounded-2xl px-2.5 py-2 font-bold transition hover:bg-ink-50 dark:hover:bg-ink-800"><i class="ph-fill ph-gift text-xl text-sky-600 dark:text-sky-300" aria-hidden="true"></i> Trợ lý chọn quà</a></li>
                    </ul>
                </div>
                <?php if ($megaPick): ?>
                    <?php $megaFinal = effective_price($megaPick); $megaSale = $megaFinal < (float)$megaPick['price']; ?>
                    <div class="col-span-6 lg:col-span-3">
                        <a href="<?= e(url('product.php?id=' . (int)$megaPick['id'])) ?>" class="group/m relative block h-full overflow-hidden rounded-3xl bg-gradient-to-br from-sun-200 to-coral-200 p-4 dark:from-sun-500/15 dark:to-coral-500/15">
                            <?php if ((int)$megaPick['sold'] > 0): ?>
                                <span class="badge bg-ink-900 text-white"><i class="ph-fill ph-fire text-sun-300" aria-hidden="true"></i> Bán chạy nhất</span>
                            <?php else: ?>
                                <span class="badge bg-ink-900 text-white"><i class="ph-fill ph-sparkle text-sun-300" aria-hidden="true"></i> Nổi bật</span>
                            <?php endif; ?>
                            <img src="<?= e(product_image_url($megaPick['image'] ?? null)) ?>" alt="" loading="lazy" class="mx-auto mt-3 aspect-square w-3/4 rounded-2xl object-cover shadow-soft transition duration-500 group-hover/m:scale-105 group-hover/m:-rotate-3">
                            <span class="mt-3 block line-clamp-1 font-extrabold text-ink-900 dark:text-white"><?= e($megaPick['name']) ?></span>
                            <span class="flex items-baseline gap-2">
                                <span class="font-display text-lg font-bold text-price"><?= e(format_price($megaFinal)) ?></span>
                                <?php if ($megaSale): ?><span class="text-xs text-muted line-through"><?= e(format_price($megaPick['price'])) ?></span><?php endif; ?>
                            </span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</header>

<!-- Thông báo (flash) hiện dạng toast -->
<div class="pointer-events-none fixed inset-x-0 top-20 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6" aria-live="polite" data-toast-root>
    <?php foreach (flash_pull() as $f): ?>
        <?php $tone = ['success' => 'alert-success', 'danger' => 'alert-danger', 'warning' => 'alert-warning', 'info' => 'alert-info'][$f['type']] ?? 'alert-info'; ?>
        <?php $sticky = in_array($f['type'], ['danger', 'warning'], true); ?>
        <div class="alert <?= $tone ?> pointer-events-auto w-full max-w-sm animate-pop shadow-soft backdrop-blur" role="<?= $sticky ? 'alert' : 'status' ?>"
             x-data="{ show: true, t: null }" x-show="show" x-transition.opacity.duration.400ms
             <?php if (!$sticky): ?>x-init="t = setTimeout(() => show = false, 6000)" @mouseenter="clearTimeout(t)" @focusin="clearTimeout(t)"<?php endif; ?>>
            <i class="ph-fill <?= $f['type'] === 'success' ? 'ph-check-circle' : ($f['type'] === 'danger' ? 'ph-warning-circle' : 'ph-info') ?> mt-0.5 text-lg" aria-hidden="true"></i>
            <span class="flex-1"><?= e($f['message']) ?></span>
            <button type="button" class="opacity-60 hover:opacity-100" @click="show = false" aria-label="Đóng"><i class="ph-bold ph-x" aria-hidden="true"></i></button>
        </div>
    <?php endforeach; ?>
</div>

<main id="main" data-page-chrome class="<?= e($mainClass ?? 'mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8') ?>">

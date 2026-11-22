<?php
// Danh sách sản phẩm (task F04 + #3): lọc theo danh mục, độ tuổi, khoảng giá, khuyến mãi; tìm theo tên; sắp xếp; phân trang.
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product_card.php';

/* ---------- 1. Đọc và kiểm tra tham số trên URL ---------- */

$qRaw = $_GET['q'] ?? '';
$q = is_string($qRaw) ? mb_substr(trim($qRaw), 0, 100) : '';

$catRaw = $_GET['category'] ?? 0;
$categoryId = is_string($catRaw) ? (int)$catRaw : 0;

$saleOnly = ($_GET['sale'] ?? '') === '1';

$ageGroups = age_groups();
$ageRaw = $_GET['age'] ?? '';
$age = (is_string($ageRaw) && isset($ageGroups[$ageRaw])) ? $ageRaw : '';

// Khoảng giá trên thanh trượt (bước 10.000đ, tối đa = giá cao nhất đang bán làm tròn lên).
$finalPrice = final_price_sql();
$priceCeil = (int)db_query("SELECT COALESCE(MAX($finalPrice), 0) FROM products p WHERE p.status = 1")->fetchColumn();
$priceCeil = max(100000, (int)ceil($priceCeil / 50000) * 50000);
$minPrice = filter_var($_GET['min'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$maxPrice = filter_var($_GET['max'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$minPrice = $minPrice === false ? 0 : min($minPrice, $priceCeil);
$maxPrice = $maxPrice === false ? $priceCeil : min(max($maxPrice, $minPrice), $priceCeil);
$hasPrice = $minPrice > 0 || $maxPrice < $priceCeil;

// Danh sách trắng kiểu sắp xếp: chỉ các khóa này mới được đưa vào ORDER BY.
$sortOptions = [
    'newest'     => ['Mới nhất',          'p.created_at DESC, p.id DESC',                                     'ph-sparkle'],
    'popular'    => ['Đánh giá cao',      'avg_rating IS NULL, avg_rating DESC, review_count DESC, p.id DESC', 'ph-star'],
    'price_asc'  => ['Giá thấp đến cao',  $finalPrice . ' ASC, p.id DESC',                                     'ph-trend-up'],
    'price_desc' => ['Giá cao đến thấp',  $finalPrice . ' DESC, p.id DESC',                                    'ph-trend-down'],
    'name_asc'   => ['Tên A đến Z',       'p.name ASC, p.id DESC',                                             'ph-text-aa'],
];
$sortRaw = $_GET['sort'] ?? 'newest';
$sort = (is_string($sortRaw) && isset($sortOptions[$sortRaw])) ? $sortRaw : 'newest';

// Danh mục đang bật, kèm số sản phẩm.
$categories = db_query(
    'SELECT c.id, c.name, COUNT(p.id) AS n
     FROM categories c LEFT JOIN products p ON p.category_id = c.id AND p.status = 1
     WHERE c.status = 1 GROUP BY c.id, c.name ORDER BY c.id'
)->fetchAll();
$categoryNames = array_column($categories, 'name', 'id');
if (!isset($categoryNames[$categoryId])) {
    $categoryId = 0; // danh mục không tồn tại hoặc đang ẩn thì bỏ qua bộ lọc
}

/* ---------- 2. Truy vấn (mọi giá trị người dùng đều đi qua tham số ?) ---------- */

$where  = ['p.status = 1', 'c.status = 1'];
$params = [];

if ($q !== '') {
    $where[]  = 'p.name LIKE ?';
    $params[] = '%' . addcslashes($q, '%_\\') . '%'; // chặn ký tự đại diện của LIKE
}
if ($categoryId > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($saleOnly) {
    $where[] = "$finalPrice < p.price";
}
if ($age !== '') {
    // Khoảng tuổi của sản phẩm giao với nhóm tuổi đã chọn.
    $where[]  = 'p.age_min <= ? AND p.age_max >= ?';
    $params[] = $ageGroups[$age][2];
    $params[] = $ageGroups[$age][1];
}
if ($hasPrice) {
    $where[]  = "$finalPrice BETWEEN ? AND ?";
    $params[] = $minPrice;
    $params[] = $maxPrice;
}
$whereSql = implode(' AND ', $where);

$total = (int)db_query(
    "SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE $whereSql",
    $params
)->fetchColumn();

[$offset, $pages, $page] = paginate($total, ITEMS_PER_PAGE_SHOP);

$products = db_query(
    'SELECT ' . product_card_columns() . "
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE $whereSql
     ORDER BY {$sortOptions[$sort][1]}
     LIMIT " . (int)ITEMS_PER_PAGE_SHOP . ' OFFSET ' . (int)$offset,
    $params
)->fetchAll();

/* ---------- 3. Chuẩn bị hiển thị ---------- */

$filters = ['q' => $q, 'category' => $categoryId, 'sale' => $saleOnly, 'sort' => $sort, 'age' => $age,
            'min' => $hasPrice ? $minPrice : null, 'max' => $hasPrice ? $maxPrice : null];

/** Link tới products.php, giữ các bộ lọc hiện có và ghi đè một vài giá trị (luôn về trang 1). */
$linkTo = function (array $override = []) use ($filters): string {
    $f = array_merge($filters, $override);
    $qs = [];
    if ($f['q'] !== '')          { $qs['q'] = $f['q']; }
    if ((int)$f['category'] > 0) { $qs['category'] = (int)$f['category']; }
    if (!empty($f['sale']))      { $qs['sale'] = '1'; }
    if ($f['age'] !== '')        { $qs['age'] = $f['age']; }
    if ($f['min'] !== null)      { $qs['min'] = (int)$f['min']; }
    if ($f['max'] !== null)      { $qs['max'] = (int)$f['max']; }
    if ($f['sort'] !== 'newest') { $qs['sort'] = $f['sort']; }
    return url('products.php' . ($qs ? '?' . http_build_query($qs) : ''));
};

// Các "thẻ" bộ lọc đang bật, bấm X để bỏ.
$activeChips = [];
if ($q !== '')        { $activeChips[] = ['“' . $q . '”', $linkTo(['q' => ''])]; }
if ($categoryId > 0)  { $activeChips[] = [$categoryNames[$categoryId], $linkTo(['category' => 0])]; }
if ($age !== '')      { $activeChips[] = [$ageGroups[$age][0], $linkTo(['age' => ''])]; }
if ($saleOnly)        { $activeChips[] = ['Đang khuyến mãi', $linkTo(['sale' => false])]; }
if ($hasPrice)        { $activeChips[] = [format_price($minPrice) . ' - ' . format_price($maxPrice), $linkTo(['min' => null, 'max' => null])]; }

$allProductCount = array_sum(array_map(fn($c) => (int)$c['n'], $categories));
$catIcons = ['ph-lightbulb', 'ph-puzzle-piece', 'ph-heart', 'ph-car-profile', 'ph-soccer-ball', 'ph-palette', 'ph-music-notes', 'ph-planet'];
$catTones = [
    'bg-sun-100 text-sun-800 dark:bg-sun-500/15 dark:text-sun-300',
    'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    'bg-coral-100 text-coral-700 dark:bg-coral-500/15 dark:text-coral-300',
    'bg-mint-100 text-ok dark:bg-mint-500/15 dark:text-mint-300',
    'bg-grape-100 text-grape-600 dark:bg-grape-500/15 dark:text-grape-300',
];
$selectedCatLabel = 'Tất cả danh mục';
$selectedCatCount = $allProductCount;
$selectedCatIcon  = 'ph-squares-four';
$selectedCatTone  = 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300';
foreach ($categories as $i => $c) {
    if ((int)$c['id'] === $categoryId) {
        $selectedCatLabel = $c['name'];
        $selectedCatCount = (int)$c['n'];
        $selectedCatIcon  = $catIcons[$i % count($catIcons)];
        $selectedCatTone  = $catTones[$i % count($catTones)];
        break;
    }
}

$heading = $categoryId > 0 ? $categoryNames[$categoryId] : ($saleOnly ? 'Đồ chơi đang khuyến mãi' : ($age !== '' ? 'Đồ chơi cho bé ' . $ageGroups[$age][0] : 'Tất cả đồ chơi'));
$pageTitle = $heading;
require __DIR__ . '/includes/header.php';
?>

<!-- Tiêu đề trang -->
<section class="relative mb-8 overflow-hidden rounded-4xl bg-gradient-to-r from-sky-100 via-grape-100 to-coral-100 px-6 py-8 sm:px-10 dark:from-sky-500/10 dark:via-grape-500/10 dark:to-coral-500/10">
    <nav class="mb-2 flex items-center gap-1.5 text-xs font-bold text-muted" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>" class="hover:text-sky-600">Trang chủ</a><i class="ph-bold ph-caret-right" aria-hidden="true"></i><span class="text-ink-600 dark:text-ink-200">Sản phẩm</span>
    </nav>
    <h1 class="font-display text-3xl font-bold text-ink-900 sm:text-4xl dark:text-white"><?= e($heading) ?></h1>
    <p class="mt-1 text-ink-500 dark:text-ink-300">Tìm thấy <strong class="text-ink-800 dark:text-white"><?= $total ?></strong> sản phẩm phù hợp</p>
    <span class="absolute top-1/2 right-8 hidden -translate-y-1/2 animate-float text-7xl text-sky-600/70 sm:block dark:text-sky-300/60" aria-hidden="true"><i class="ph-fill <?= e($saleOnly ? 'ph-fire' : ($age !== '' ? $ageGroups[$age][3] : 'ph-storefront')) ?>" aria-hidden="true"></i></span>
</section>

<div class="grid gap-8 lg:grid-cols-[17rem_1fr]" x-data="{ filters: false }">

    <!-- Bộ lọc -->
    <aside class="hidden lg:block" :class="filters && 'fixed inset-0 z-[70] block! bg-ink-950/50 backdrop-blur-sm lg:static lg:bg-transparent lg:backdrop-blur-none'" @click.self="filters = false">
        <form method="get" action="<?= e(url('products.php')) ?>" data-loading="#kz-results" id="filterForm"
              class="card relative h-full max-w-xs max-lg:overflow-y-auto rounded-none p-4 sm:p-5 lg:sticky lg:top-32 lg:z-20 lg:h-auto lg:max-w-none lg:overflow-visible lg:rounded-4xl"
              x-data="{ min: <?= $minPrice ?>, max: <?= $maxPrice ?>, ceil: <?= $priceCeil ?> }">
            <div class="mb-4 flex items-center justify-between lg:hidden">
                <h2 class="text-xl font-semibold">Bộ lọc</h2>
                <button type="button" class="btn btn-ghost btn-icon" @click="filters = false" aria-label="Đóng bộ lọc"><i class="ph-bold ph-x" aria-hidden="true"></i></button>
            </div>
            <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
            <input type="hidden" name="sort" value="<?= e($sort) ?>">

            <div class="relative" x-data="{ open: false, catId: <?= $categoryId ?> }" @click.outside="open = false" @keydown.escape.stop="open = false">
                <span class="mb-2 flex items-center gap-2 font-display text-lg font-semibold"><i class="ph-fill ph-squares-four text-sky-600" aria-hidden="true"></i> Danh mục</span>
                <input type="hidden" name="category" value="<?= $categoryId ?>" :value="catId">
                <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="kz-cat-popup" aria-haspopup="true"
                        class="flex w-full cursor-pointer items-center gap-2.5 rounded-2xl border-2 px-3 py-2 text-left text-sm font-bold transition"
                        :class="open || catId > 0 ? 'border-sky-300 bg-sky-50/70 text-ink-900 dark:border-sky-500/50 dark:bg-sky-500/10 dark:text-white' : 'border-ink-100 bg-white text-ink-700 hover:border-sky-200 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-100'">
                    <span class="grid size-8 shrink-0 place-items-center rounded-xl text-lg <?= $selectedCatTone ?>"><i class="ph-fill <?= e($selectedCatIcon) ?>" aria-hidden="true"></i></span>
                    <span class="min-w-0 flex-1 truncate"><?= e($selectedCatLabel) ?></span>
                    <span class="badge badge-ink shrink-0"><?= $selectedCatCount ?></span>
                    <i class="ph-bold ph-caret-down shrink-0 text-xs text-muted transition duration-200 lg:hidden" :class="open && 'rotate-180'" aria-hidden="true"></i>
                    <i class="ph-bold ph-caret-right hidden shrink-0 text-xs text-muted transition duration-200 lg:inline" :class="open && 'translate-x-0.5 text-sky-600'" aria-hidden="true"></i>
                </button>

                <div id="kz-cat-popup" x-show="open" x-cloak
                     x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 scale-95"
                     x-transition:leave="transition duration-100 ease-in" x-transition:leave-end="opacity-0 scale-95"
                     class="mt-2 rounded-3xl bg-white p-2 shadow-2xl ring-1 ring-ink-100 lg:absolute lg:top-0 lg:left-full lg:z-30 lg:mt-0 lg:ml-3 lg:w-80 dark:bg-ink-900 dark:ring-ink-700">
                    <p class="px-3 pt-1 pb-2 text-xs font-extrabold tracking-wider text-muted uppercase">Chọn danh mục</p>
                    <div class="space-y-1">
                        <button type="button" @click="catId = 0; open = false; $nextTick(() => $el.form.requestSubmit())"
                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-2xl px-2.5 py-2 text-left text-sm font-bold transition <?= $categoryId === 0 ? 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' : 'text-ink-700 hover:bg-ink-50 dark:text-ink-200 dark:hover:bg-ink-800' ?>">
                            <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-ink-100 text-lg text-ink-600 dark:bg-ink-800 dark:text-ink-300"><i class="ph-fill ph-squares-four" aria-hidden="true"></i></span>
                            <span class="min-w-0 flex-1 truncate">Tất cả danh mục</span>
                            <span class="badge badge-ink"><?= $allProductCount ?></span>
                            <?php if ($categoryId === 0): ?><i class="ph-bold ph-check text-sky-600 dark:text-sky-300" aria-hidden="true"></i><?php endif; ?>
                        </button>
                        <?php foreach ($categories as $i => $c): ?>
                            <?php $cid = (int)$c['id']; $isActiveCat = $categoryId === $cid; ?>
                            <button type="button" @click="catId = <?= $cid ?>; open = false; $nextTick(() => $el.form.requestSubmit())"
                                    class="flex w-full cursor-pointer items-center gap-2.5 rounded-2xl px-2.5 py-2 text-left text-sm font-bold transition <?= $isActiveCat ? 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' : 'text-ink-700 hover:bg-ink-50 dark:text-ink-200 dark:hover:bg-ink-800' ?>">
                                <span class="grid size-8 shrink-0 place-items-center rounded-xl text-lg <?= $catTones[$i % count($catTones)] ?>"><i class="ph-fill <?= e($catIcons[$i % count($catIcons)]) ?>" aria-hidden="true"></i></span>
                                <span class="min-w-0 flex-1 truncate"><?= e($c['name']) ?></span>
                                <span class="badge badge-ink"><?= (int)$c['n'] ?></span>
                                <?php if ($isActiveCat): ?><i class="ph-bold ph-check text-sky-600 dark:text-sky-300" aria-hidden="true"></i><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <noscript>
                    <select name="category" class="select mt-2" aria-label="Danh mục">
                        <option value="0">Tất cả danh mục (<?= $allProductCount ?>)</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= (int)$c['n'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </noscript>
            </div>

            <fieldset class="mt-4">
                <legend class="mb-2 flex items-center gap-2 font-display text-lg font-semibold"><i class="ph-fill ph-baby text-age" aria-hidden="true"></i> Độ tuổi</legend>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach ($ageGroups as $key => [$label, , , $ageIcon]): ?>
                        <label class="flex cursor-pointer flex-col items-center rounded-2xl p-2 text-center text-xs font-extrabold ring-2 transition has-checked:bg-grape-50 has-checked:text-grape-600 has-checked:ring-grape-300 dark:has-checked:bg-grape-500/15 dark:has-checked:text-grape-300 <?= $age === $key ? '' : 'ring-ink-100 hover:ring-grape-200 dark:ring-ink-700' ?>">
                            <input type="radio" name="age" value="<?= e($key) ?>" class="sr-only" data-autosubmit <?= $age === $key ? 'checked' : '' ?>>
                            <i class="ph-fill <?= e($ageIcon) ?> text-2xl" aria-hidden="true"></i><?= e($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if ($age !== ''): ?>
                    <a href="<?= e($linkTo(['age' => ''])) ?>" class="mt-2 inline-block text-xs font-bold text-muted hover:text-coral-500">Bỏ chọn độ tuổi</a>
                <?php endif; ?>
            </fieldset>

            <fieldset class="mt-4">
                <legend class="mb-2.5 flex items-center gap-2 font-display text-lg font-semibold"><i class="ph-fill ph-coins text-sun-500" aria-hidden="true"></i> Khoảng giá</legend>
                <div class="range-dual" :style="'--from:' + (min / ceil * 100) + '%; --to:' + (max / ceil * 100) + '%'">
                    <input type="range" :min="0" :max="ceil" step="10000" x-model.number="min" @input="if (min > max) max = min" aria-label="Giá thấp nhất">
                    <input type="range" :min="0" :max="ceil" step="10000" x-model.number="max" @input="if (max < min) min = max" aria-label="Giá cao nhất">
                </div>
                <div class="mt-2.5 flex items-center justify-between gap-2 text-xs font-extrabold">
                    <span class="rounded-full bg-ink-50 px-3 py-1.5 dark:bg-ink-800" x-text="min.toLocaleString('vi-VN') + ' ₫'"><?= e(format_price($minPrice)) ?></span>
                    <span class="text-muted">—</span>
                    <span class="rounded-full bg-ink-50 px-3 py-1.5 dark:bg-ink-800" x-text="max.toLocaleString('vi-VN') + ' ₫'"><?= e(format_price($maxPrice)) ?></span>
                </div>
                <input type="hidden" name="min" :value="min" :disabled="min <= 0 && max >= ceil">
                <input type="hidden" name="max" :value="max" :disabled="min <= 0 && max >= ceil">
            </fieldset>

            <label class="mt-4 flex cursor-pointer items-center justify-between rounded-2xl bg-coral-50 p-2.5 font-bold text-coral-700 dark:bg-coral-500/10 dark:text-coral-300">
                <span class="flex items-center gap-2"><i class="ph-fill ph-fire" aria-hidden="true"></i> Chỉ hàng khuyến mãi</span>
                <span class="relative inline-flex">
                    <input type="checkbox" name="sale" value="1" class="peer sr-only" data-autosubmit <?= $saleOnly ? 'checked' : '' ?>>
                    <span class="h-6 w-11 rounded-full bg-ink-200 transition peer-checked:bg-coral-400 dark:bg-ink-700"></span>
                    <span class="absolute top-1 left-1 size-4 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                </span>
            </label>

            <button type="submit" class="btn btn-primary mt-4 w-full"><i class="ph-bold ph-funnel" aria-hidden="true"></i> Áp dụng bộ lọc</button>
            <?php if ($activeChips): ?>
                <a href="<?= e(url('products.php')) ?>" class="btn btn-ghost mt-2 w-full">Xóa tất cả</a>
            <?php endif; ?>
        </form>
    </aside>

    <!-- Danh sách -->
    <div>
        <div class="mb-5 flex flex-wrap items-center gap-3">
            <button type="button" class="btn btn-ghost lg:hidden" @click="filters = true"><i class="ph-bold ph-sliders-horizontal" aria-hidden="true"></i> Bộ lọc</button>
            <div class="flex flex-1 flex-wrap items-center gap-2">
                <?php foreach ($activeChips as [$label, $href]): ?>
                    <a href="<?= e($href) ?>" class="group inline-flex items-center gap-1.5 rounded-full bg-white py-1.5 pr-2 pl-3.5 text-xs font-extrabold shadow-soft ring-1 ring-ink-100 transition hover:ring-coral-300 dark:bg-ink-800 dark:ring-ink-700">
                        <?= e($label) ?><span class="grid size-5 place-items-center rounded-full bg-ink-100 transition group-hover:bg-coral-400 group-hover:text-white dark:bg-ink-700"><i class="ph-bold ph-x text-[11px]" aria-hidden="true"></i></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <form method="get" action="<?= e(url('products.php')) ?>" data-loading="#kz-results" class="flex items-center gap-2">
                <?php foreach (['q' => $q !== '' ? $q : null, 'category' => $categoryId ?: null, 'sale' => $saleOnly ? '1' : null, 'age' => $age ?: null,
                                'min' => $hasPrice ? $minPrice : null, 'max' => $hasPrice ? $maxPrice : null] as $k => $v): ?>
                    <?php if ($v !== null): ?><input type="hidden" name="<?= $k ?>" value="<?= e((string)$v) ?>"><?php endif; ?>
                <?php endforeach; ?>
                <label for="sort" class="text-sm font-bold text-muted max-sm:sr-only">Sắp xếp</label>
                <select id="sort" class="select w-auto rounded-full! py-2! text-sm font-bold" name="sort" data-autosubmit>
                    <?php foreach ($sortOptions as $key => $opt): ?>
                        <option value="<?= e($key) ?>" data-icon="<?= e($opt[2]) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($opt[0]) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-primary btn-sm" type="submit">Lọc</button></noscript>
            </form>
        </div>

        <?php if (!$products): ?>
            <div class="card flex flex-col items-center px-6 py-16 text-center">
                <span class="grid size-28 place-items-center rounded-full bg-sky-100 dark:bg-sky-500/15"><?= mascot_svg('size-20', 'wow') ?></span>
                <h2 class="mt-4 text-2xl font-semibold">Không tìm thấy đồ chơi phù hợp</h2>
                <p class="mt-1 text-muted">Thử bỏ bớt bộ lọc hoặc tìm với từ khóa khác nhé.</p>
                <a class="btn btn-primary mt-6" href="<?= e(url('products.php')) ?>">Xem tất cả sản phẩm</a>
            </div>
        <?php else: ?>
            <h2 class="sr-only">Danh sách sản phẩm</h2>
            <div id="kz-results" class="grid grid-cols-2 gap-4 sm:gap-5 md:grid-cols-3">
                <?php foreach ($products as $p) { render_product_card($p); } ?>
            </div>
            <?= render_pagination($page, $pages) /* link phân trang giữ nguyên các bộ lọc */ ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

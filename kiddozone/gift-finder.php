<?php
// Trợ lý chọn quà (#4): 3 câu hỏi (độ tuổi, sở thích, ngân sách) rồi gợi ý sản phẩm qua api/products.php.
// Logic ở component giftFinder trong assets/js/app.js.
require_once __DIR__ . '/includes/init.php';

$catIcons = ['ph-lightbulb', 'ph-puzzle-piece', 'ph-heart', 'ph-car-profile', 'ph-soccer-ball', 'ph-palette', 'ph-music-notes', 'ph-planet'];
$catTints = ['bg-sun-100 text-sun-800', 'bg-sky-100 text-sky-700', 'bg-coral-100 text-coral-700', 'bg-mint-100 text-ok', 'bg-grape-100 text-grape-600'];

$categories = db_query('SELECT id, name, description FROM categories WHERE status = 1 ORDER BY id')->fetchAll();

$config = [
    'ages' => array_map(
        fn($key, $g) => ['key' => $key, 'label' => $g[0], 'icon' => $g[3]],
        array_keys(age_groups()), age_groups()
    ),
    'categories' => array_merge(
        [['key' => '', 'label' => 'Bé thích mọi thứ', 'desc' => 'Gợi ý từ tất cả danh mục', 'icon' => 'ph-sparkle', 'tint' => 'bg-ink-100 text-ink-600']],
        array_map(fn($c, $i) => [
            'key' => (int)$c['id'], 'label' => $c['name'], 'desc' => (string)($c['description'] ?? ''),
            'icon' => $catIcons[$i % count($catIcons)], 'tint' => $catTints[$i % count($catTints)],
        ], $categories, array_keys($categories))
    ),
    'budgets' => [
        ['key' => 150000, 'label' => 'Dưới 150.000đ', 'icon' => 'ph-coin'],
        ['key' => 300000, 'label' => 'Dưới 300.000đ', 'icon' => 'ph-coins'],
        ['key' => 500000, 'label' => 'Dưới 500.000đ', 'icon' => 'ph-wallet'],
        ['key' => '', 'label' => 'Không giới hạn', 'icon' => 'ph-diamond'],
    ],
];

$pageTitle = 'Trợ lý chọn quà';
require __DIR__ . '/includes/header.php';
?>

<div class="mx-auto max-w-4xl" x-data="giftFinder(<?= e(json_encode($config, JSON_UNESCAPED_UNICODE)) ?>)">
    <!-- Tiêu đề -->
    <div class="text-center" data-hero>
        <h1 data-hero-text class="font-display text-4xl font-bold text-ink-900 sm:text-5xl dark:text-white">Tìm món quà hoàn hảo cho bé</h1>
        <p data-hero-text class="mx-auto mt-3 max-w-xl text-ink-500 dark:text-ink-300">Chỉ 3 câu hỏi nhanh, KiddoZone sẽ gợi ý những món đồ chơi phù hợp nhất với bé.</p>
    </div>

    <!-- Tiến độ -->
    <div class="mx-auto mt-10 max-w-md">
        <div class="mb-2 flex justify-between text-xs font-extrabold text-muted">
            <span :class="step >= 0 && 'text-sky-600 dark:text-sky-300'">Độ tuổi</span>
            <span :class="step >= 1 && 'text-sky-600 dark:text-sky-300'">Sở thích</span>
            <span :class="step >= 2 && 'text-sky-600 dark:text-sky-300'">Ngân sách</span>
            <span :class="step >= 3 && 'text-sky-600 dark:text-sky-300'">Kết quả</span>
        </div>
        <div class="h-3 overflow-hidden rounded-full bg-white shadow-inner ring-1 ring-ink-100 dark:bg-ink-800 dark:ring-ink-700">
            <div class="h-full rounded-full bg-gradient-to-r from-sun-400 via-coral-400 to-grape-400 transition-all duration-500 ease-out" :style="'width:' + Math.max(6, progress) + '%'"></div>
        </div>
    </div>

    <div class="card relative mt-8 min-h-[24rem] overflow-hidden p-6 sm:p-10">
        <!-- Bước 1: độ tuổi -->
        <section x-show="step === 0" :data-step-active="step === 0">
            <h2 class="text-center font-display text-2xl font-semibold">Bé nhà bạn bao nhiêu tuổi?</h2>
            <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <template x-for="a in ages" :key="a.key">
                    <button type="button" data-step-item @click="pick('age', a.key)"
                            class="group flex flex-col items-center gap-2 rounded-4xl p-6 ring-2 transition hover:-translate-y-1 hover:shadow-pop"
                            :class="age === a.key ? 'bg-sky-50 ring-sky-400 dark:bg-sky-500/10' : 'ring-ink-100 hover:ring-sky-300 dark:ring-ink-700'">
                        <i class="ph-fill text-6xl text-sky-600 transition group-hover:scale-125 group-hover:-rotate-6 dark:text-sky-300" :class="a.icon" aria-hidden="true"></i>
                        <span class="font-display text-lg font-semibold" x-text="a.label"></span>
                    </button>
                </template>
            </div>
        </section>

        <!-- Bước 2: sở thích -->
        <section x-show="step === 1" x-cloak :data-step-active="step === 1">
            <h2 class="text-center font-display text-2xl font-semibold">Bé thích loại đồ chơi nào?</h2>
            <div class="mt-8 grid gap-3 sm:grid-cols-2">
                <template x-for="c in categories" :key="c.key">
                    <button type="button" data-step-item @click="pick('category', c.key)"
                            class="group flex items-center gap-4 rounded-3xl p-4 text-left ring-2 transition hover:-translate-y-0.5 hover:shadow-soft"
                            :class="String(category) === String(c.key) ? 'bg-sky-50 ring-sky-400 dark:bg-sky-500/10' : 'ring-ink-100 hover:ring-sky-300 dark:ring-ink-700'">
                        <span class="grid size-14 shrink-0 place-items-center rounded-2xl text-3xl transition group-hover:animate-wiggle" :class="c.tint"><i class="ph-fill" aria-hidden="true" :class="c.icon"></i></span>
                        <span><b class="block" x-text="c.label"></b><span class="text-sm text-muted" x-text="c.desc"></span></span>
                    </button>
                </template>
            </div>
        </section>

        <!-- Bước 3: ngân sách -->
        <section x-show="step === 2" x-cloak :data-step-active="step === 2">
            <h2 class="text-center font-display text-2xl font-semibold">Ngân sách của bạn khoảng bao nhiêu?</h2>
            <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <template x-for="b in budgets" :key="b.key">
                    <button type="button" data-step-item @click="pick('budget', b.key)"
                            class="group flex flex-col items-center gap-2 rounded-4xl p-6 ring-2 transition hover:-translate-y-1 hover:shadow-pop"
                            :class="String(budget) === String(b.key) ? 'bg-sky-50 ring-sky-400 dark:bg-sky-500/10' : 'ring-ink-100 hover:ring-sky-300 dark:ring-ink-700'">
                        <i class="ph-fill text-5xl text-sun-800 transition group-hover:scale-125 dark:text-sun-300" :class="b.icon" aria-hidden="true"></i>
                        <span class="text-center font-bold" x-text="b.label"></span>
                    </button>
                </template>
            </div>
        </section>

        <!-- Kết quả -->
        <section x-show="step === 3" x-cloak :data-step-active="step === 3">
            <div class="flex flex-wrap items-center justify-center gap-2 text-sm">
                <span class="badge badge-sky"><i class="ph-fill ph-baby" aria-hidden="true"></i><span x-text="label(ages, age)"></span></span>
                <span class="badge badge-grape"><i class="ph-fill ph-heart" aria-hidden="true"></i><span x-text="label(categories, category)"></span></span>
                <span class="badge badge-sun"><i class="ph-fill ph-coins" aria-hidden="true"></i><span x-text="label(budgets, budget)"></span></span>
            </div>

            <template x-if="loading">
                <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4"><div class="skeleton aspect-[3/4]"></div><div class="skeleton aspect-[3/4]"></div><div class="skeleton aspect-[3/4]"></div><div class="skeleton aspect-[3/4]"></div></div>
            </template>

            <template x-if="!loading && items.length">
                <div>
                    <h2 class="mt-6 text-center font-display text-2xl font-semibold">Tìm thấy <span class="text-price" x-text="total"></span> món quà phù hợp!</h2>
                    <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <template x-for="p in items" :key="p.id">
                            <div data-gift-result data-product-card class="group relative flex flex-col rounded-3xl bg-white p-2 shadow-soft ring-1 ring-ink-100 transition hover:-translate-y-1 hover:shadow-pop dark:bg-ink-800 dark:ring-ink-700">
                                <a :href="p.url" class="relative block aspect-square overflow-hidden rounded-2xl bg-gradient-to-br from-sun-100 to-sky-100 dark:from-ink-700 dark:to-ink-700">
                                    <img :src="p.image" :alt="p.name" class="size-full object-cover transition duration-500 group-hover:scale-110">
                                    <span class="badge absolute top-2 left-2 bg-coral-600 text-white" x-show="p.on_sale" x-text="'-' + p.percent + '%'"></span>
                                </a>
                                <div class="flex flex-1 flex-col p-2">
                                    <span class="text-xs font-bold text-age" x-text="p.age"></span>
                                    <a :href="p.url" class="line-clamp-2 text-sm font-extrabold hover:text-sky-600" x-text="p.name"></a>
                                    <div class="mt-auto flex items-center justify-between pt-2">
                                        <span class="font-display font-bold text-price" x-text="p.final_text"></span>
                                        <button type="button" class="btn btn-sun btn-icon size-9" @click="addToCart(p, $event)" :aria-label="'Thêm ' + p.name + ' vào giỏ'"><i class="ph-bold ph-plus" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="mt-8 text-center"><a :href="moreUrl()" class="link">Xem tất cả <span x-text="total"></span> gợi ý <i class="ph-bold ph-arrow-right" aria-hidden="true"></i></a></div>
                </div>
            </template>

            <template x-if="!loading && !items.length">
                <div class="py-10 text-center">
                    <div class="flex justify-center"><?= empty_icon('ph-magnifying-glass', 'grape') ?></div>
                    <h2 class="mt-4 font-display text-2xl font-semibold">Chưa có món nào khớp hoàn toàn</h2>
                    <p class="mt-1 text-muted">Thử nới ngân sách hoặc chọn "Bé thích mọi thứ" nhé.</p>
                </div>
            </template>
        </section>

        <!-- Điều hướng -->
        <div class="mt-10 flex items-center justify-between">
            <button type="button" class="btn btn-ghost" @click="back()" x-show="step > 0" x-cloak><i class="ph-bold ph-arrow-left" aria-hidden="true"></i> Quay lại</button>
            <span x-show="step === 0"></span>
            <button type="button" class="btn btn-primary" @click="restart()" x-show="step === 3" x-cloak><i class="ph-bold ph-arrow-counter-clockwise" aria-hidden="true"></i> Chọn lại</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

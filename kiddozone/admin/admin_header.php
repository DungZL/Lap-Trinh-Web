<?php
// Layout chung trang quản trị. Trang gọi: đặt $pageTitle rồi require __DIR__ . '/../admin_header.php';
// Tùy chọn: $pageSubtitle (dòng mô tả dưới tiêu đề), $pageActions (HTML nút bên phải tiêu đề).
$pageTitle = $pageTitle ?? 'Quản trị';
$titleSuffix = 'KiddoZone Admin';

// Mục menu: đường dẫn => [icon Phosphor, nhãn]. Mục đang mở: so khớp đầu đường dẫn hiện tại.
$adminMenu = [
    'admin/index.php'      => ['ph-squares-four', 'Tổng quan'],
    'admin/orders.php'     => ['ph-receipt', 'Đơn hàng'],
    'admin/products/'      => ['ph-package', 'Sản phẩm'],
    'admin/categories/'    => ['ph-folders', 'Danh mục'],
    'admin/coupons/'       => ['ph-ticket', 'Mã giảm giá'],
    'admin/users/'         => ['ph-users-three', 'Người dùng'],
    'admin/reviews/'       => ['ph-star', 'Đánh giá'],
];
$currentPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
$adminUser = current_user();
$pendingCount = (int)db_query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

$adminConfig = [
    'csrf' => csrf_token(),
    'api'  => ['notifications' => url('admin/api/notifications.php')],
    'urls' => ['order' => url('admin/orders.php?id=')],
];

require __DIR__ . '/../includes/head.php';
?>
<body class="min-h-dvh bg-ink-50 dark:bg-ink-950" x-data="{ nav: false }">
<script>window.KZA = <?= json_encode($adminConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>

<!-- Thanh bên -->
<aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-ink-900 text-ink-200 transition-transform duration-300 lg:translate-x-0 dark:bg-black"
       :class="nav && 'translate-x-0'" aria-label="Menu quản trị">
    <a href="<?= e(url('admin/index.php')) ?>" class="flex items-center gap-3 px-6 py-6">
        <span class="grid size-11 place-items-center rounded-2xl bg-sun-400 shadow-chunky"><?= mascot_svg('size-8') ?></span>
        <span>
            <span class="block font-display text-xl leading-none font-bold"><span class="text-sky-400">Kiddo</span><span class="text-coral-300">Zone</span></span>
            <span class="text-xs font-bold tracking-widest text-ink-300 uppercase">Quản trị</span>
        </span>
    </a>
    <nav class="flex-1 space-y-1 overflow-y-auto px-4">
        <?php foreach ($adminMenu as $path => [$icon, $label]): ?>
            <?php $href = url($path); $active = str_starts_with($currentPath, $href) || ($path === 'admin/orders.php' && str_starts_with($currentPath, url('admin/orders/'))); ?>
            <a href="<?= e($href) ?>"
               class="group flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold transition <?= $active ? 'bg-white/10 text-white shadow-[inset_3px_0_0_var(--color-sun-400)]' : 'hover:bg-white/5 hover:text-white' ?>">
                <i class="<?= $active ? 'ph-fill text-sun-400' : 'ph-bold' ?> <?= e($icon) ?> text-xl transition group-hover:scale-110"></i>
                <span class="flex-1"><?= e($label) ?></span>
                <?php if ($path === 'admin/orders.php' && $pendingCount > 0): ?>
                    <span class="badge bg-coral-600 text-white"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="space-y-1 border-t border-white/10 p-4">
        <a href="<?= e(url('index.php')) ?>" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold hover:bg-white/5 hover:text-white" target="_blank" rel="noopener">
            <i class="ph-bold ph-storefront text-xl" aria-hidden="true"></i> Xem website <i class="ph-bold ph-arrow-square-out ml-auto text-xs" aria-hidden="true"></i>
        </a>
        <form method="post" action="<?= e(url('logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold text-coral-300 hover:bg-coral-500/10">
                <i class="ph-bold ph-sign-out text-xl" aria-hidden="true"></i> Đăng xuất
            </button>
        </form>
    </div>
</aside>
<div class="fixed inset-0 z-40 bg-ink-950/50 backdrop-blur-sm lg:hidden" x-show="nav" x-cloak x-transition.opacity @click="nav = false"></div>

<div class="lg:pl-72">
    <!-- Thanh trên -->
    <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-ink-100 bg-white/80 px-4 py-3 backdrop-blur-xl sm:px-8 dark:border-ink-800 dark:bg-ink-900/80">
        <button type="button" class="btn btn-ghost btn-icon lg:hidden" @click="nav = true" aria-label="Mở menu"><i class="ph-bold ph-list text-xl" aria-hidden="true"></i></button>
        <p class="hidden text-sm font-bold text-muted sm:block"><?= e(date('d/m/Y')) ?> · Chúc một ngày bán hàng thật vui!</p>

        <div class="ml-auto flex items-center gap-2">
            <button type="button" class="btn btn-ghost btn-icon" @click="$store.theme.toggle()" aria-label="Đổi chế độ sáng / tối">
                <i class="ph-fill ph-moon-stars text-xl text-age dark:hidden" aria-hidden="true"></i>
                <i class="ph-fill ph-sun hidden text-xl text-sun-400 dark:inline" aria-hidden="true"></i>
            </button>

            <!-- Chuông thông báo đơn mới (#13) -->
            <div class="relative" x-data @click.outside="$store.notify.open = false">
                <button type="button" class="btn btn-ghost btn-icon relative" @click="$store.notify.toggle()" aria-label="Thông báo đơn hàng mới" :aria-expanded="$store.notify.open">
                    <i class="ph-fill ph-bell text-xl text-sun-500" aria-hidden="true" :class="$store.notify.ring && 'animate-wiggle'"></i>
                    <span class="absolute -top-1 -right-1 grid h-5 min-w-5 place-items-center rounded-full bg-coral-600 px-1 text-[11px] font-extrabold text-white ring-2 ring-white dark:ring-ink-900"
                          x-show="$store.notify.count > 0" x-text="$store.notify.count" x-cloak></span>
                </button>
                <div x-show="$store.notify.open" x-cloak x-transition.origin.top.right
                     class="absolute right-0 mt-2 w-80 overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-ink-100 dark:bg-ink-900 dark:ring-ink-700">
                    <div class="flex items-center justify-between border-b border-ink-100 px-4 py-3 dark:border-ink-800">
                        <b>Đơn hàng mới</b>
                        <button type="button" class="text-xs font-bold text-sky-600 hover:underline dark:text-sky-300" @click="$store.notify.markAll()" x-show="$store.notify.count > 0">Đánh dấu đã xem</button>
                    </div>
                    <ul class="max-h-80 overflow-y-auto p-2">
                        <template x-for="o in $store.notify.items" :key="o.id">
                            <li>
                                <a :href="KZA.urls.order + o.id" class="flex items-center gap-3 rounded-2xl p-3 hover:bg-sky-50 dark:hover:bg-ink-800">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-sun-100 text-sun-800 dark:bg-sun-500/15"><i class="ph-fill ph-shopping-bag" aria-hidden="true"></i></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold"><span x-text="o.code"></span> · <span x-text="o.total"></span></span>
                                        <span class="block truncate text-xs text-muted" x-text="o.customer + ' · ' + o.ago"></span>
                                    </span>
                                    <span class="size-2 rounded-full bg-coral-400"></span>
                                </a>
                            </li>
                        </template>
                        <li x-show="!$store.notify.items.length" class="px-4 py-8 text-center text-sm text-muted"><i class="ph-bold ph-bell-simple-slash mb-1 block text-3xl" aria-hidden="true"></i> Không có đơn mới</li>
                    </ul>
                    <a href="<?= e(url('admin/orders.php')) ?>" class="block border-t border-ink-100 py-3 text-center text-sm font-bold text-sky-600 hover:bg-sky-50 dark:border-ink-800 dark:text-sky-300 dark:hover:bg-ink-800">Xem tất cả đơn hàng</a>
                </div>
            </div>

            <span class="hidden items-center gap-2 rounded-full bg-ink-50 py-1 pr-4 pl-1 text-sm font-bold sm:flex dark:bg-ink-800">
                <span class="grid size-8 place-items-center rounded-full bg-gradient-to-br from-sky-600 to-grape-600 text-xs font-extrabold text-white"><?= e(mb_strtoupper(mb_substr($adminUser['full_name'], 0, 1))) ?></span>
                <?= e($adminUser['full_name']) ?>
            </span>
        </div>
    </header>

    <!-- Thông báo -->
    <div class="pointer-events-none fixed inset-x-0 top-20 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-8" aria-live="polite" data-toast-root>
        <?php foreach (flash_pull() as $f): ?>
            <?php $tone = ['success' => 'alert-success', 'danger' => 'alert-danger', 'warning' => 'alert-warning', 'info' => 'alert-info'][$f['type']] ?? 'alert-info'; ?>
            <?php $sticky = in_array($f['type'], ['danger', 'warning'], true); ?>
            <div class="alert <?= $tone ?> pointer-events-auto w-full max-w-sm animate-pop shadow-soft" role="<?= $sticky ? 'alert' : 'status' ?>"
                 x-data="{ show: true, t: null }" x-show="show" x-transition.opacity.duration.400ms
                 <?php if (!$sticky): ?>x-init="t = setTimeout(() => show = false, 6000)" @mouseenter="clearTimeout(t)" @focusin="clearTimeout(t)"<?php endif; ?>>
                <i class="ph-fill <?= $f['type'] === 'success' ? 'ph-check-circle' : ($f['type'] === 'danger' ? 'ph-warning-circle' : 'ph-info') ?> mt-0.5 text-lg" aria-hidden="true"></i>
                <span class="flex-1"><?= e($f['message']) ?></span>
                <button type="button" class="opacity-60 hover:opacity-100" @click="show = false" aria-label="Đóng"><i class="ph-bold ph-x" aria-hidden="true"></i></button>
            </div>
        <?php endforeach; ?>
    </div>

    <main class="px-4 py-8 sm:px-8">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl font-bold text-ink-900 dark:text-white"><?= e($pageTitle) ?></h1>
                <?php if (!empty($pageSubtitle)): ?><p class="mt-1 text-muted"><?= e($pageSubtitle) ?></p><?php endif; ?>
            </div>
            <?php if (!empty($pageActions)): ?><div class="flex flex-wrap gap-2"><?= $pageActions ?></div><?php endif; ?>
        </div>

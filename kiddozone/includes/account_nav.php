<?php
// Thanh điều hướng các trang tài khoản khách hàng. Đặt $accountTab = 'profile' | 'orders' | 'wishlist' trước khi require.
$accountTab = $accountTab ?? '';
$me = current_user();
$tabs = [
    'profile'  => ['account/profile.php', 'ph-user-circle', 'Hồ sơ'],
    'orders'   => ['account/orders.php', 'ph-package', 'Đơn hàng'],
    'wishlist' => ['account/wishlist.php', 'ph-heart', 'Yêu thích'],
];
?>
<div class="relative mb-8 overflow-hidden rounded-4xl bg-gradient-to-r from-sky-700 via-grape-600 to-coral-700 p-6 text-white sm:p-8">
    <div class="bg-dots absolute inset-0 opacity-30"></div>
    <div class="relative flex flex-wrap items-center gap-5">
        <span class="grid size-16 place-items-center rounded-3xl bg-white/25 font-display text-3xl font-bold backdrop-blur"><?= e(mb_strtoupper(mb_substr($me['full_name'], 0, 1))) ?></span>
        <div class="flex-1">
            <h1 class="font-display text-3xl font-bold">Xin chào, <?= e($me['full_name']) ?></h1>
        </div>
        <nav class="flex flex-wrap gap-2" aria-label="Tài khoản">
            <?php foreach ($tabs as $key => [$href, $icon, $label]): ?>
                <a href="<?= e(url($href)) ?>" class="flex items-center gap-2 rounded-full px-4 py-2 text-sm font-extrabold transition <?= $accountTab === $key ? 'bg-white text-ink-900 shadow-soft' : 'bg-white/15 hover:bg-white/30' ?>"
                   <?= $accountTab === $key ? 'aria-current="page"' : '' ?>><i class="ph-fill <?= $icon ?>" aria-hidden="true"></i> <?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

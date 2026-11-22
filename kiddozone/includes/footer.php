</main>

<footer data-page-chrome class="relative mt-16 overflow-hidden bg-ink-900 text-ink-200 dark:bg-black">
    <!-- Sóng trang trí -->
    <svg class="absolute -top-px left-0 w-full text-cream dark:text-ink-950" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true">
        <path fill="currentColor" d="M0,0 L1440,0 L1440,20 C1200,60 960,0 720,24 C480,48 240,8 0,36 Z"/>
    </svg>
    <div class="mx-auto grid max-w-7xl gap-10 px-4 pt-20 pb-10 sm:px-6 md:grid-cols-12 lg:px-8">
        <div class="md:col-span-5">
            <a href="<?= e(url('index.php')) ?>" class="inline-flex items-center gap-2">
                <span class="grid size-11 place-items-center rounded-2xl bg-sun-400"><?= mascot_svg('size-8') ?></span>
                <span class="font-display text-2xl font-bold"><span class="text-sky-400">Kiddo</span><span class="text-coral-300">Zone</span></span>
            </a>
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-ink-300">Đồ chơi an toàn, vui và hữu ích cho bé từ 0 đến 12 tuổi. Mỗi món đồ chơi đều được chọn lọc theo độ tuổi để bé vừa chơi vừa học.</p>
            <div class="mt-5 flex gap-2">
                <?php foreach ([['ph-facebook-logo', 'Facebook'], ['ph-instagram-logo', 'Instagram'], ['ph-tiktok-logo', 'TikTok'], ['ph-youtube-logo', 'YouTube']] as [$icon, $name]): ?>
                    <span class="grid size-10 place-items-center rounded-full bg-white/5 text-xl transition hover:-translate-y-1 hover:bg-sun-400 hover:text-ink-900" title="<?= e($name) ?>"><i class="ph-fill <?= e($icon) ?>" aria-hidden="true"></i></span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="md:col-span-3">
            <h3 class="mb-4 font-display text-lg font-semibold text-white">Mua sắm</h3>
            <ul class="space-y-2.5 text-sm">
                <li><a class="hover:text-sun-300" href="<?= e(url('products.php')) ?>">Tất cả sản phẩm</a></li>
                <li><a class="hover:text-sun-300" href="<?= e(url('products.php?sale=1')) ?>">Đang khuyến mãi</a></li>
                <li><a class="hover:text-sun-300" href="<?= e(url('gift-finder.php')) ?>">Trợ lý chọn quà</a></li>
                <li><a class="hover:text-sun-300" href="<?= e(url('cart.php')) ?>">Giỏ hàng</a></li>
            </ul>
        </div>
        <div class="md:col-span-4">
            <h3 class="mb-4 font-display text-lg font-semibold text-white">Liên hệ</h3>
            <ul class="space-y-2.5 text-sm">
                <li class="flex items-center gap-2"><i class="ph-fill ph-map-pin text-coral-300" aria-hidden="true"></i> TP. Hồ Chí Minh</li>
                <li class="flex items-center gap-2"><i class="ph-fill ph-phone text-mint-300" aria-hidden="true"></i> 0900 000 000</li>
                <li class="flex items-center gap-2"><i class="ph-fill ph-envelope-simple text-sky-300" aria-hidden="true"></i> hello@kiddozone.vn</li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10 py-5 text-center text-xs text-ink-300">
        &copy; <?= date('Y') ?> KiddoZone - Đồ chơi trẻ em. Làm với <i class="ph-fill ph-heart text-coral-400" aria-hidden="true"></i> cho các bé.
    </div>
</footer>

<?php if (!is_admin()): ?>
<!-- Ngăn kéo giỏ hàng (#1) -->
<div id="kz-cart-drawer" data-dialog x-data x-show="$store.cart.isOpen" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true" aria-labelledby="kz-drawer-title" @keydown.escape.window="$store.cart.isOpen && $store.cart.hide()">
    <div class="absolute inset-0 bg-ink-950/50 backdrop-blur-sm" x-show="$store.cart.isOpen" x-transition.opacity @click="$store.cart.hide()"></div>
    <aside class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-cream shadow-2xl dark:bg-ink-900"
           x-show="$store.cart.isOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-full"
           x-transition:leave="transition duration-200 ease-in" x-transition:leave-end="translate-x-full">
        <div class="flex items-center justify-between border-b border-ink-100 px-6 py-4 dark:border-ink-800">
            <h2 id="kz-drawer-title" class="flex items-center gap-2 text-xl font-semibold">
                <i class="ph-fill ph-shopping-cart-simple text-sun-500" aria-hidden="true"></i> Giỏ hàng
                <span class="badge badge-sun" x-text="$store.cart.count + ' món'"></span>
            </h2>
            <button type="button" class="btn btn-ghost btn-icon" @click="$store.cart.hide()" aria-label="Đóng giỏ hàng" data-autofocus><i class="ph-bold ph-x text-lg" aria-hidden="true"></i></button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
            <template x-if="$store.cart.loading && !$store.cart.items.length">
                <div class="space-y-3"><div class="skeleton h-20"></div><div class="skeleton h-20"></div><div class="skeleton h-20"></div></div>
            </template>
            <template x-if="!$store.cart.loading && !$store.cart.items.length">
                <div class="flex h-full flex-col items-center justify-center text-center">
                    <span class="mb-4 grid size-28 place-items-center rounded-full bg-sun-100 dark:bg-sun-500/15"><?= mascot_svg('size-20', 'sad') ?></span>
                    <p class="font-display text-xl font-semibold">Giỏ hàng đang trống</p>
                    <p class="mt-1 text-sm text-muted">Chọn vài món đồ chơi thật vui cho bé nhé!</p>
                    <a :href="KZ.urls.products" class="btn btn-primary mt-5">Khám phá đồ chơi <i class="ph-bold ph-arrow-right" aria-hidden="true"></i></a>
                </div>
            </template>
            <ul class="space-y-3">
                <template x-for="item in $store.cart.items" :key="item.id">
                    <li class="flex gap-3 rounded-3xl bg-white p-3 shadow-soft ring-1 ring-ink-100 transition dark:bg-ink-800 dark:ring-ink-700"
                        :class="$store.cart.busy === item.id && 'opacity-60'" x-transition>
                        <a :href="item.url" class="shrink-0"><img :src="item.image" :alt="item.name" class="size-20 rounded-2xl bg-ink-50 object-cover dark:bg-ink-900"></a>
                        <div class="min-w-0 flex-1">
                            <a :href="item.url" class="line-clamp-2 text-sm font-bold hover:text-sky-600" x-text="item.name"></a>
                            <p class="mt-0.5 text-xs text-coral-500" x-show="item.problem" x-text="item.problem"></p>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div class="flex items-center rounded-full bg-ink-50 p-1 dark:bg-ink-900" x-show="item.available">
                                    <button type="button" class="grid size-7 place-items-center rounded-full hover:bg-white dark:hover:bg-ink-700" @click="$store.cart.setQty(item, item.quantity - 1)" aria-label="Giảm số lượng"><i class="ph-bold ph-minus text-xs" aria-hidden="true"></i></button>
                                    <span class="w-8 text-center text-sm font-extrabold" x-text="item.quantity"></span>
                                    <button type="button" class="grid size-7 place-items-center rounded-full hover:bg-white disabled:opacity-30 dark:hover:bg-ink-700" @click="$store.cart.setQty(item, item.quantity + 1)" :disabled="item.quantity >= item.max" aria-label="Tăng số lượng"><i class="ph-bold ph-plus text-xs" aria-hidden="true"></i></button>
                                </div>
                                <span class="font-extrabold text-ink-900 dark:text-white" x-text="item.line_text"></span>
                            </div>
                        </div>
                        <button type="button" class="self-start text-ink-300 hover:text-coral-500" @click="$store.cart.remove(item)" aria-label="Xóa khỏi giỏ"><i class="ph-bold ph-trash text-lg" aria-hidden="true"></i></button>
                    </li>
                </template>
            </ul>
        </div>

        <div class="border-t border-ink-100 bg-white px-6 py-5 dark:border-ink-800 dark:bg-ink-950" x-show="$store.cart.items.length">
            <!-- Thanh tiến độ miễn phí giao hàng -->
            <div class="mb-5" x-data="{ goal: 500000, get pct() { return Math.min(100, $store.cart.subtotal / this.goal * 100) } }">
                <p class="mb-3 text-xs font-bold text-muted" aria-live="polite">
                    <template x-if="$store.cart.subtotal >= goal"><span class="text-ok"><i class="ph-fill ph-confetti" aria-hidden="true"></i> Bạn được miễn phí giao hàng!</span></template>
                    <template x-if="$store.cart.subtotal < goal"><span>Mua thêm <b class="text-price" x-text="(goal - $store.cart.subtotal).toLocaleString('vi-VN') + ' ₫'"></b> để được miễn phí giao hàng</span></template>
                </p>
                <!-- Xe tải chạy theo tiến độ -->
                <div class="relative h-2 rounded-full bg-ink-100 dark:bg-ink-800">
                    <div class="h-full rounded-full bg-gradient-to-r from-sun-400 to-mint-400 transition-all duration-500" :style="'width:' + pct + '%'"></div>
                    <span class="absolute top-1/2 grid size-7 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-white text-sm shadow-soft ring-2 transition-all duration-500 dark:bg-ink-900"
                          :class="pct >= 100 ? 'ring-mint-400 text-ok' : 'ring-sun-400 text-sun-800 dark:text-sun-300'" :style="'left:' + Math.min(94, Math.max(6, pct)) + '%'" aria-hidden="true">
                        <i class="ph-fill ph-truck" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
            <div class="flex items-center justify-between text-sm"><span class="text-muted">Tạm tính</span><span class="font-bold" x-text="$store.cart.subtotalText"></span></div>
            <div class="mt-1 flex items-center justify-between text-sm text-ok" x-show="$store.cart.coupon && $store.cart.coupon.ok">
                <span>Mã <b x-text="$store.cart.coupon && $store.cart.coupon.code"></b></span><span class="font-bold" x-text="'-' + $store.cart.discountText"></span>
            </div>
            <div class="mt-2 flex items-center justify-between"><span class="font-bold">Tổng cộng</span><span class="font-display text-2xl font-bold text-price" x-text="$store.cart.totalText"></span></div>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <a :href="KZ.urls.cart" class="btn btn-ghost">Xem giỏ hàng</a>
                <a :href="KZ.urls.checkout" class="btn btn-primary">Thanh toán <i class="ph-bold ph-arrow-right" aria-hidden="true"></i></a>
            </div>
        </div>
    </aside>
</div>

<!-- Xem nhanh sản phẩm (#9) -->
<div id="kz-quick-view" data-dialog x-data x-show="$store.quick.isOpen" x-cloak class="fixed inset-0 z-[70] grid place-items-center p-4" role="dialog" aria-modal="true" aria-label="Xem nhanh sản phẩm" @keydown.escape.window="$store.quick.isOpen && $store.quick.hide()">
    <div class="absolute inset-0 bg-ink-950/50 backdrop-blur-sm" x-show="$store.quick.isOpen" x-transition.opacity @click="$store.quick.hide()"></div>
    <div class="relative max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-4xl bg-white shadow-2xl dark:bg-ink-900"
         x-show="$store.quick.isOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="opacity-0 scale-90 translate-y-4"
         x-transition:leave="transition duration-150" x-transition:leave-end="opacity-0 scale-95">
        <button type="button" class="btn btn-ghost btn-icon absolute top-4 right-4 z-10" @click="$store.quick.hide()" aria-label="Đóng" data-autofocus><i class="ph-bold ph-x text-lg" aria-hidden="true"></i></button>
        <template x-if="$store.quick.loading">
            <div class="grid gap-6 p-6 sm:grid-cols-2"><div class="skeleton aspect-square"></div><div class="space-y-3"><div class="skeleton h-6 w-1/3"></div><div class="skeleton h-10"></div><div class="skeleton h-8 w-1/2"></div><div class="skeleton h-24"></div></div></div>
        </template>
        <template x-if="!$store.quick.loading && $store.quick.product">
            <div class="grid gap-6 p-6 sm:grid-cols-2" x-data="{ img: 0, qty: 1 }">
                <div>
                    <div class="relative aspect-square overflow-hidden rounded-3xl bg-gradient-to-br from-sun-100 to-sky-100 dark:from-ink-800 dark:to-ink-800">
                        <img :src="$store.quick.product.images[img]" :alt="$store.quick.product.name" class="size-full object-cover">
                        <span class="badge absolute top-3 left-3 bg-coral-400 text-white" x-show="$store.quick.product.on_sale" x-text="'-' + $store.quick.product.percent + '%'"></span>
                    </div>
                    <div class="mt-3 flex gap-2 overflow-x-auto" x-show="$store.quick.product.images.length > 1">
                        <template x-for="(src, i) in $store.quick.product.images" :key="i">
                            <button type="button" @click="img = i" class="size-16 shrink-0 overflow-hidden rounded-2xl ring-2 transition" :class="img === i ? 'ring-sky-400' : 'ring-transparent opacity-60'"><img :src="src" alt="" class="size-full object-cover"></button>
                        </template>
                    </div>
                </div>
                <div class="flex flex-col">
                    <p class="text-sm font-bold text-sky-500" x-text="$store.quick.product.category"></p>
                    <h2 class="mt-1 text-2xl leading-tight font-semibold" x-text="$store.quick.product.name"></h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        <span class="badge badge-grape"><i class="ph-bold ph-baby" aria-hidden="true"></i><span x-text="$store.quick.product.age"></span></span>
                        <span class="badge badge-sun" x-show="$store.quick.product.reviews > 0"><i class="ph-fill ph-star" aria-hidden="true"></i><span x-text="$store.quick.product.rating + ' (' + $store.quick.product.reviews + ')'"></span></span>
                        <span class="badge badge-coral" x-show="$store.quick.product.flash"><i class="ph-fill ph-lightning" aria-hidden="true"></i> Flash sale</span>
                    </div>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="font-display text-3xl font-bold text-coral-500" x-text="$store.quick.product.final_text"></span>
                        <span class="text-ink-300 line-through" x-show="$store.quick.product.on_sale" x-text="$store.quick.product.price_text"></span>
                    </div>
                    <p class="mt-3 line-clamp-4 text-sm text-ink-500 dark:text-ink-300" x-text="$store.quick.product.summary"></p>
                    <p class="mt-3 text-sm font-bold" :class="$store.quick.product.stock > 0 ? 'text-mint-500' : 'text-coral-500'">
                        <i class="ph-fill" aria-hidden="true" :class="$store.quick.product.stock > 0 ? 'ph-check-circle' : 'ph-x-circle'"></i>
                        <span x-text="$store.quick.product.stock > 0 ? ($store.quick.product.stock <= 5 ? 'Chỉ còn ' + $store.quick.product.stock + ' sản phẩm' : 'Còn hàng') : 'Hết hàng'"></span>
                    </p>
                    <div class="mt-auto flex flex-wrap items-center gap-3 pt-6" x-show="$store.quick.product.stock > 0">
                        <div class="flex items-center rounded-full bg-ink-50 p-1 dark:bg-ink-800">
                            <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-white dark:hover:bg-ink-700" @click="qty = Math.max(1, qty - 1)" aria-label="Giảm"><i class="ph-bold ph-minus" aria-hidden="true"></i></button>
                            <span class="w-10 text-center font-extrabold" x-text="qty"></span>
                            <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-white dark:hover:bg-ink-700" @click="qty = Math.min($store.quick.product.stock, qty + 1)" aria-label="Tăng"><i class="ph-bold ph-plus" aria-hidden="true"></i></button>
                        </div>
                        <button type="button" class="btn btn-sun btn-lg flex-1" @click="$store.cart.add($store.quick.product.id, qty, $event.currentTarget); $store.quick.hide()">
                            <i class="ph-fill ph-shopping-cart-simple text-lg" aria-hidden="true"></i> Thêm vào giỏ
                        </button>
                    </div>
                    <a :href="$store.quick.product.url" class="link mt-4 text-sm">Xem chi tiết sản phẩm <i class="ph-bold ph-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </template>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js" defer></script>
<script src="<?= e(url('assets/js/select.js')) ?>?v=<?= (int)(@filemtime(dirname(__DIR__) . '/assets/js/select.js') ?: 1) ?>" defer></script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= (int)(@filemtime(dirname(__DIR__) . '/assets/js/app.js') ?: 1) ?>" defer></script>
<?php foreach ($extraScripts ?? [] as $src): ?>
    <script src="<?= e($src) ?>" defer></script>
<?php endforeach; ?>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js" defer></script>
</body>
</html>

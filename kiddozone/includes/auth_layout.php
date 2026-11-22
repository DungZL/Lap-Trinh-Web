<?php
// Khung chung cho trang Đăng nhập / Đăng ký: cột trái minh họa có hiệu ứng, cột phải là form.
// Gọi auth_layout_start($eyebrow, $title, $subtitle, $bannerText) trước form, auth_layout_end() sau form.

function auth_layout_start(string $eyebrow, string $title, string $subtitle, string $bannerText): void
{
    ?>
    <div class="mx-auto grid max-w-5xl overflow-hidden rounded-5xl bg-white shadow-soft ring-1 ring-ink-100 lg:grid-cols-2 dark:bg-ink-900 dark:ring-ink-800">
        <!-- Cột minh họa -->
        <div data-hero class="relative isolate hidden overflow-hidden bg-gradient-to-br from-sky-700 via-grape-600 to-coral-700 p-10 text-white lg:flex lg:flex-col">
            <div class="bg-dots absolute inset-0 -z-10 opacity-30"></div>
            <a href="<?= e(url('index.php')) ?>" data-hero-text class="flex items-center gap-2 font-display text-2xl font-bold">
                <span class="grid size-11 place-items-center rounded-2xl bg-white"><?= mascot_svg('size-8') ?></span> KiddoZone
            </a>
            <div class="my-auto">
                <h2 data-hero-text class="font-display text-5xl leading-tight font-bold">Niềm vui nhỏ,<br><span class="text-sun-300">ước mơ lớn!</span></h2>
                <p data-hero-text class="mt-4 max-w-xs text-white"><?= e($bannerText) ?></p>
            </div>
            <div class="relative h-28" aria-hidden="true">
                <?php foreach ([['ph-car-profile', 'left-0', '0s'], ['ph-puzzle-piece', 'left-[22%]', '-1s'], ['ph-rocket-launch', 'left-[44%]', '-2s'], ['ph-gift', 'left-[66%]', '-3s'], ['ph-balloon', 'left-[84%]', '-1.5s']] as [$toyIcon, $pos, $delay]): ?>
                    <span data-hero-toy data-depth="20" class="absolute bottom-0 <?= $pos ?> grid size-16 place-items-center rounded-3xl bg-white/20 text-4xl backdrop-blur">
                        <i class="ph-fill <?= e($toyIcon) ?> animate-float" aria-hidden="true" style="animation-delay: <?= $delay ?>"></i>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cột form -->
        <div class="p-8 sm:p-12">
            <h1 class="font-display text-4xl font-bold text-ink-900 dark:text-white"><?= e($title) ?></h1>
            <p class="mt-2 text-ink-500 dark:text-ink-300"><?= e($subtitle) ?></p>
    <?php
}

function auth_layout_end(): void
{
    ?>
        </div>
    </div>
    <?php
}

/** Ô nhập có icon bên trái. $extra: thuộc tính thêm (đã an toàn). */
function auth_input(string $id, string $label, string $type, string $icon, string $placeholder, string $value = '', string $extra = '', bool $toggle = false): void
{
    $errorId = ['email' => 'emailError', 'password' => 'passwordError', 'full_name' => 'nameError', 'confirm_password' => 'confirmError'][$id] ?? $id . 'Error';
    ?>
    <div>
        <label class="label" for="<?= e($id) ?>"><?= e($label) ?></label>
        <div class="relative">
            <i class="ph-bold <?= e($icon) ?> pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-lg text-muted" aria-hidden="true"></i>
            <input type="<?= e($type) ?>" id="<?= e($id) ?>" name="<?= e($id) ?>" class="input py-3 pl-11 <?= $toggle ? 'pr-12' : '' ?>" placeholder="<?= e($placeholder) ?>" value="<?= $value ?>" aria-describedby="<?= e($errorId) ?>" <?= $extra ?> required>
            <?php if ($toggle): ?>
                <button type="button" id="togglePassword" class="absolute top-1/2 right-3 grid size-8 -translate-y-1/2 place-items-center rounded-full text-muted hover:bg-ink-50 hover:text-sky-500 dark:hover:bg-ink-800" aria-label="Hiện hoặc ẩn mật khẩu">
                    <i class="ph-bold ph-eye-slash text-lg" aria-hidden="true" id="eyeIcon"></i>
                </button>
            <?php endif; ?>
        </div>
        <small class="mt-1 block min-h-4 text-xs font-bold text-price" id="<?= e($errorId) ?>" aria-live="polite"></small>
    </div>
    <?php
}

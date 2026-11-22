<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth_layout.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'index.php');
}

$error = null;
$redirectTo = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
$redirectTo = is_string($redirectTo) ? $redirectTo : '';

if (is_post()) {
    csrf_verify();
    $error = attempt_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($error === null) {
        if (is_admin()) {
            redirect('admin/index.php');
        }
        redirect(safe_redirect_target($redirectTo));
    }
}

$pageTitle = 'Đăng nhập';
$extraScripts = [url('assets/js/auth.js')];
require __DIR__ . '/includes/header.php';

auth_layout_start('Chào mừng bạn trở lại', 'Đăng nhập', 'Đăng nhập để tiếp tục mua sắm tại KiddoZone nhé!',
    'Khám phá thế giới đồ chơi đầy màu sắc và lựa chọn những món quà tuyệt vời dành cho bé yêu.');
?>
    <?php if ($error): ?>
        <div class="alert alert-danger mt-6" role="alert"><i class="ph-fill ph-warning-circle text-lg" aria-hidden="true"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" id="loginForm" novalidate class="mt-8 space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
        <?php auth_input('email', 'Địa chỉ email', 'email', 'ph-envelope-simple', 'Nhập email của bạn', old('email'), 'autocomplete="email"'); ?>
        <?php auth_input('password', 'Mật khẩu', 'password', 'ph-lock-key', 'Nhập mật khẩu', '', 'autocomplete="current-password"', true); ?>
        <p class="flex items-center gap-2 text-xs font-bold text-ok"><i class="ph-fill ph-shield-check" aria-hidden="true"></i> Đăng nhập an toàn, mật khẩu được mã hóa</p>
        <button type="submit" class="btn btn-primary btn-lg w-full">Đăng nhập <i class="ph-bold ph-arrow-right" aria-hidden="true"></i></button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-500 dark:text-ink-300">Chưa có tài khoản? <a class="link" href="<?= e(url('register.php')) ?>">Đăng ký ngay</a></p>
<?php
auth_layout_end();
require __DIR__ . '/includes/footer.php';

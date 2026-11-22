<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth_layout.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'index.php');
}

$errors = [];

if (is_post()) {
    csrf_verify();

    // register_customer() (includes/auth.php) kiểm tra dữ liệu, trùng email và tạo tài khoản khách hàng.
    $errors = register_customer([
        'full_name'        => $_POST['full_name'] ?? '',
        'email'            => $_POST['email'] ?? '',
        'password'         => $_POST['password'] ?? '',
        'password_confirm' => $_POST['confirm_password'] ?? '',
    ]);

    if (!$errors) {
        flash('success', 'Đăng ký thành công! Vui lòng đăng nhập để tiếp tục.');
        redirect('login.php');
    }
}

$pageTitle = 'Đăng ký';
$extraScripts = [url('assets/js/auth.js')];
require __DIR__ . '/includes/header.php';

auth_layout_start('Bắt đầu hành trình mới', 'Tạo tài khoản', 'Tạo tài khoản để cùng bé yêu khám phá KiddoZone!',
    'Tạo tài khoản để lưu sản phẩm yêu thích, theo dõi đơn hàng và nhận nhiều ưu đãi.');
?>
    <?php if ($errors): ?>
        <div class="alert alert-danger mt-6" role="alert">
            <i class="ph-fill ph-warning-circle text-lg" aria-hidden="true"></i>
            <ul class="list-disc pl-4"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" id="registerForm" novalidate class="mt-8 space-y-3" x-data="{ pw: '' }">
        <?= csrf_field() ?>
        <?php auth_input('full_name', 'Họ và tên', 'text', 'ph-user', 'Nhập họ và tên', old('full_name'), 'autocomplete="name"'); ?>
        <?php auth_input('email', 'Địa chỉ email', 'email', 'ph-envelope-simple', 'Nhập email của bạn', old('email'), 'autocomplete="email"'); ?>
        <?php auth_input('password', 'Mật khẩu', 'password', 'ph-lock-key', 'Tối thiểu 8 ký tự, có chữ và số', '', 'autocomplete="new-password" x-model="pw"', true); ?>
        <!-- Thanh độ mạnh mật khẩu -->
        <div class="-mt-2 flex gap-1.5" aria-hidden="true" x-show="pw.length" x-cloak>
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <span class="h-1.5 flex-1 rounded-full bg-ink-100 transition dark:bg-ink-800"
                      :class="(pw.length >= 8) + /[A-Za-z]/.test(pw) + /[0-9]/.test(pw) + /[^A-Za-z0-9]/.test(pw) >= <?= $i ?> && ['', 'bg-coral-400!', 'bg-sun-400!', 'bg-sky-400!', 'bg-mint-400!'][(pw.length >= 8) + /[A-Za-z]/.test(pw) + /[0-9]/.test(pw) + /[^A-Za-z0-9]/.test(pw)]"></span>
            <?php endfor; ?>
        </div>
        <?php auth_input('confirm_password', 'Xác nhận mật khẩu', 'password', 'ph-lock-key', 'Nhập lại mật khẩu', '', 'autocomplete="new-password"'); ?>
        <button type="submit" class="btn btn-primary btn-lg w-full">Đăng ký <i class="ph-bold ph-arrow-right" aria-hidden="true"></i></button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-500 dark:text-ink-300">Đã có tài khoản? <a class="link" href="<?= e(url('login.php')) ?>">Đăng nhập ngay</a></p>
<?php
auth_layout_end();
require __DIR__ . '/includes/footer.php';

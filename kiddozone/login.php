<?php
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'index.php');
}

$error = null;
$redirectTo = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

if (is_post()) {
    csrf_verify();
    $error = attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($error === null) {
        if (is_admin()) {
            redirect('admin/index.php');
        }
        redirect(safe_redirect_target($redirectTo));
    }
}

$pageTitle = 'Đăng nhập';
require __DIR__ . '/includes/header.php';
?>
<!-- Giao diện tạm: task "Giao diện đăng ký, đăng nhập và validate JS" sẽ hoàn thiện. Giữ nguyên tên field và csrf_field(). -->
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <h1 class="h3 mb-3">Đăng nhập</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= old('email') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu</label>
                <input class="form-control" type="password" id="password" name="password" required>
            </div>
            <button class="btn btn-primary w-100">Đăng nhập</button>
        </form>
        <p class="mt-3 text-center">Chưa có tài khoản? <a href="<?= e(url('register.php')) ?>">Đăng ký</a></p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

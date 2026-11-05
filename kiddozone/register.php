<?php
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
if (is_post()) {
    csrf_verify();
    $errors = register_customer($_POST, $newId);
    if (!$errors) {
        flash('success', 'Đăng ký thành công. Hãy đăng nhập.');
        redirect('login.php');
    }
}

$pageTitle = 'Đăng ký';
require __DIR__ . '/includes/header.php';
?>
<!-- Giao diện tạm: task "Giao diện đăng ký, đăng nhập và validate JS" sẽ hoàn thiện. Giữ nguyên tên field và csrf_field(). -->
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <h1 class="h3 mb-3">Đăng ký</h1>
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="full_name">Họ tên</label>
                <input class="form-control" id="full_name" name="full_name" value="<?= old('full_name') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= old('email') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone">Số điện thoại (không bắt buộc)</label>
                <input class="form-control" id="phone" name="phone" value="<?= old('phone') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu</label>
                <input class="form-control" type="password" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirm">Nhập lại mật khẩu</label>
                <input class="form-control" type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <button class="btn btn-primary w-100">Đăng ký</button>
        </form>
        <p class="mt-3 text-center">Đã có tài khoản? <a href="<?= e(url('login.php')) ?>">Đăng nhập</a></p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

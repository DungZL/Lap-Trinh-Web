<?php
// Cập nhật hồ sơ và đổi mật khẩu (F09 - LTWCK-29).
require_once __DIR__ . '/../includes/init.php';
require_customer();

$userId = (int)current_user()['id'];
$profile = db_query(
    'SELECT id, full_name, email, password, phone, address, created_at FROM users WHERE id = ?',
    [$userId]
)->fetch();

if (!$profile) {
    logout_user();
    redirect('login.php');
}

$profileErrors = [];
$passwordErrors = [];

$fullName = (string)$profile['full_name'];
$phone    = (string)($profile['phone'] ?? '');
$address  = (string)($profile['address'] ?? '');

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $phone    = trim((string)($_POST['phone'] ?? ''));
        $address  = trim((string)($_POST['address'] ?? ''));

        if ($fullName === '' || mb_strlen($fullName) > 100) {
            $profileErrors[] = 'Họ tên không được để trống (tối đa 100 ký tự).';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\s.-]{8,15}$/', $phone)) {
            $profileErrors[] = 'Số điện thoại không hợp lệ (8-15 ký tự).';
        }
        if (mb_strlen($address) > 255) {
            $profileErrors[] = 'Địa chỉ tối đa 255 ký tự.';
        }

        if (!$profileErrors) {
            db_query(
                'UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?',
                [$fullName, $phone !== '' ? $phone : null, $address !== '' ? $address : null, $userId]
            );
            $_SESSION['user']['full_name'] = $fullName;
            flash('success', 'Cập nhật thông tin hồ sơ thành công.');
            redirect('account/profile.php');
        }
    } elseif ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword     = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || !password_verify($currentPassword, $profile['password'])) {
            $passwordErrors[] = 'Mật khẩu hiện tại không đúng.';
        }
        if (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $passwordErrors[] = 'Mật khẩu mới tối thiểu 8 ký tự, gồm cả chữ và số.';
        }
        if ($newPassword !== $confirmPassword) {
            $passwordErrors[] = 'Mật khẩu xác nhận không khớp.';
        }

        if (!$passwordErrors) {
            db_query(
                'UPDATE users SET password = ? WHERE id = ?',
                [password_hash($newPassword, PASSWORD_DEFAULT), $userId]
            );
            flash('success', 'Đổi mật khẩu thành công.');
            redirect('account/profile.php');
        }
    } else {
        flash('danger', 'Thao tác không hợp lệ.');
        redirect('account/profile.php');
    }
}

$pageTitle = 'Hồ sơ cá nhân';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="kz-section-title mb-0">Hồ sơ cá nhân</h1>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-primary" href="<?= e(url('account/profile.php')) ?>">
            <i class="bi bi-person-gear me-1"></i> Hồ sơ
        </a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('account/orders.php')) ?>">
            <i class="bi bi-receipt me-1"></i> Đơn hàng của tôi
        </a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('account/wishlist.php')) ?>">
            <i class="bi bi-heart me-1"></i> Yêu thích
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Thông tin cá nhân</h2>

                <?php if ($profileErrors): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($profileErrors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('account/profile.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" class="form-control bg-light"
                               value="<?= e($profile['email']) ?>" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="full_name">Họ và tên</label>
                        <input type="text" id="full_name" name="full_name" class="form-control"
                               maxlength="100" value="<?= e($fullName) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="phone">Số điện thoại</label>
                        <input type="text" id="phone" name="phone" class="form-control"
                               maxlength="15" value="<?= e($phone) ?>" placeholder="VD: 0912345678">
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="address">Địa chỉ giao hàng mặc định</label>
                        <textarea id="address" name="address" class="form-control" rows="2"
                                  maxlength="255" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành"><?= e($address) ?></textarea>
                    </div>

                    <button type="submit" class="btn kz-btn-register">
                        <i class="bi bi-save me-1"></i> Lưu thay đổi
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Đổi mật khẩu</h2>

                <?php if ($passwordErrors): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($passwordErrors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('account/profile.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label" for="current_password">Mật khẩu hiện tại</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="new_password">Mật khẩu mới</label>
                        <input type="password" id="new_password" name="new_password" class="form-control"
                               minlength="8" required>
                        <div class="form-text">Tối thiểu 8 ký tự, gồm cả chữ và số.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="confirm_password">Xác nhận mật khẩu mới</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                               minlength="8" required>
                    </div>

                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-key me-1"></i> Cập nhật mật khẩu
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

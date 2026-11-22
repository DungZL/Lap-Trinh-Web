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
$accountTab = 'profile';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/account_nav.php';
?>

<div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
    <section class="card p-6 sm:p-8" data-reveal>
        <h2 class="mb-5 flex items-center gap-3 font-display text-xl font-semibold"><span class="grid size-9 place-items-center rounded-xl bg-sky-100 text-sky-600 dark:bg-sky-500/15"><i class="ph-fill ph-identification-card" aria-hidden="true"></i></span> Thông tin cá nhân</h2>

        <?php if ($profileErrors): ?>
            <div class="alert alert-danger mb-4" role="alert"><i class="ph-fill ph-warning-circle text-lg" aria-hidden="true"></i><ul class="list-disc pl-4"><?php foreach ($profileErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('account/profile.php')) ?>" class="grid gap-4 sm:grid-cols-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="sm:col-span-2">
                <label class="label" for="email">Email</label>
                <input type="email" id="email" class="input" value="<?= e($profile['email']) ?>" readonly disabled>
            </div>
            <div>
                <label class="label" for="full_name">Họ và tên</label>
                <input type="text" id="full_name" name="full_name" class="input" maxlength="100" value="<?= e($fullName) ?>" required>
            </div>
            <div>
                <label class="label" for="phone">Số điện thoại</label>
                <input type="tel" id="phone" name="phone" class="input" maxlength="15" value="<?= e($phone) ?>" placeholder="VD: 0912345678">
            </div>
            <div class="sm:col-span-2">
                <label class="label" for="address">Địa chỉ giao hàng mặc định</label>
                <textarea id="address" name="address" class="textarea" rows="2" maxlength="255" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành"><?= e($address) ?></textarea>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="btn btn-primary"><i class="ph-bold ph-floppy-disk" aria-hidden="true"></i> Lưu thay đổi</button>
            </div>
        </form>
        <p class="mt-6 text-xs text-muted">Thành viên từ <?= e(date('d/m/Y', strtotime($profile['created_at']))) ?></p>
    </section>

    <section class="card p-6 sm:p-8" data-reveal x-data="{ show: false }">
        <h2 class="mb-5 flex items-center gap-3 font-display text-xl font-semibold"><span class="grid size-9 place-items-center rounded-xl bg-coral-100 text-price dark:bg-coral-500/15"><i class="ph-fill ph-lock-key" aria-hidden="true"></i></span> Đổi mật khẩu</h2>

        <?php if ($passwordErrors): ?>
            <div class="alert alert-danger mb-4" role="alert"><i class="ph-fill ph-warning-circle text-lg" aria-hidden="true"></i><ul class="list-disc pl-4"><?php foreach ($passwordErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('account/profile.php')) ?>" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <div>
                <label class="label" for="current_password">Mật khẩu hiện tại</label>
                <input :type="show ? 'text' : 'password'" type="password" id="current_password" name="current_password" class="input" required autocomplete="current-password">
            </div>
            <div>
                <label class="label" for="new_password">Mật khẩu mới</label>
                <input :type="show ? 'text' : 'password'" type="password" id="new_password" name="new_password" class="input" minlength="8" required autocomplete="new-password">
                <p class="help">Tối thiểu 8 ký tự, gồm cả chữ và số.</p>
            </div>
            <div>
                <label class="label" for="confirm_password">Xác nhận mật khẩu mới</label>
                <input :type="show ? 'text' : 'password'" type="password" id="confirm_password" name="confirm_password" class="input" minlength="8" required autocomplete="new-password">
            </div>
            <label class="flex items-center gap-2 text-sm font-bold text-ink-500"><input type="checkbox" class="checkbox" x-model="show"> Hiện mật khẩu</label>
            <button type="submit" class="btn btn-ghost"><i class="ph-bold ph-key" aria-hidden="true"></i> Cập nhật mật khẩu</button>
        </form>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

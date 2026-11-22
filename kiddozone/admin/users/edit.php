<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$userRecord = $id ? db_query('SELECT * FROM users WHERE id = ?', [$id])->fetch() : false;

if (!$userRecord) {
    flash('danger', 'Không tìm thấy người dùng.');
    redirect('admin/users/index.php');
}

$orderCount = (int)db_query('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$id])->fetchColumn();
$isSelf     = ((int)$id === (int)current_user()['id']);
$error      = '';

if (is_post()) {
    csrf_verify();

    $fullName    = trim((string)($_POST['full_name'] ?? ''));
    $email       = trim((string)($_POST['email'] ?? ''));
    $phone       = trim((string)($_POST['phone'] ?? ''));
    $address     = trim((string)($_POST['address'] ?? ''));
    $role        = (string)($_POST['role'] ?? '');
    $status      = filter_var($_POST['status'] ?? null, FILTER_VALIDATE_INT);
    $newPassword = (string)($_POST['new_password'] ?? '');

    if ($fullName === '' || mb_strlen($fullName) > 100) {
        $error = 'Họ tên không được để trống (tối đa 100 ký tự).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $error = 'Email không hợp lệ.';
    } elseif ($phone !== '' && !preg_match('/^[0-9+\s.-]{8,15}$/', $phone)) {
        $error = 'Số điện thoại không hợp lệ (8-15 ký tự).';
    } elseif (mb_strlen($address) > 255) {
        $error = 'Địa chỉ tối đa 255 ký tự.';
    } elseif (!in_array($role, ['admin', 'customer'], true)) {
        $error = 'Vai trò không hợp lệ.';
    } elseif (!in_array($status, [0, 1], true)) {
        $error = 'Trạng thái không hợp lệ.';
    } elseif ($isSelf && ($status !== 1 || $role !== 'admin')) {
        $error = 'Không thể tự khóa hoặc tự hạ quyền quản trị của chính mình.';
    } elseif ($newPassword !== '' && (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword))) {
        $error = 'Mật khẩu mới (nếu nhập) phải tối thiểu 8 ký tự, gồm cả chữ và số.';
    } elseif (db_query('SELECT 1 FROM users WHERE email = ? AND id != ?', [$email, $id])->fetch()) {
        $error = 'Email đã được sử dụng bởi tài khoản khác.';
    } else {
        if ($newPassword !== '') {
            db_query(
                'UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, role = ?, status = ?, password = ? WHERE id = ?',
                [
                    $fullName,
                    $email,
                    $phone !== '' ? $phone : null,
                    $address !== '' ? $address : null,
                    $role,
                    $status,
                    password_hash($newPassword, PASSWORD_DEFAULT),
                    $id,
                ]
            );
        } else {
            db_query(
                'UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, role = ?, status = ? WHERE id = ?',
                [
                    $fullName,
                    $email,
                    $phone !== '' ? $phone : null,
                    $address !== '' ? $address : null,
                    $role,
                    $status,
                    $id,
                ]
            );
        }

        if ($isSelf) {
            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['email']     = $email;
        }

        flash('success', 'Cập nhật người dùng thành công!');
        redirect('admin/users/index.php');
    }

    $userRecord['full_name'] = $fullName;
    $userRecord['email']     = $email;
    $userRecord['phone']     = $phone;
    $userRecord['address']   = $address;
    $userRecord['role']      = $role;
    $userRecord['status']    = $status;
}

$pageTitle = 'Sửa người dùng #' . (int)$userRecord['id'];
$pageSubtitle = 'Đã đặt ' . $orderCount . ' đơn hàng';
require __DIR__ . '/../admin_header.php';
?>

<div class="card max-w-3xl p-6 sm:p-8">
    <?php if ($error): ?>
        <div class="alert alert-danger mb-5" role="alert"><i class="ph-fill ph-warning-circle text-lg" aria-hidden="true"></i> <?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="grid gap-4 sm:grid-cols-2">
        <?= csrf_field() ?>
        <div><label class="label" for="full_name">Họ và tên</label><input type="text" id="full_name" name="full_name" class="input" maxlength="100" value="<?= e($userRecord['full_name']) ?>" required></div>
        <div><label class="label" for="email">Email</label><input type="email" id="email" name="email" class="input" maxlength="150" value="<?= e($userRecord['email']) ?>" required></div>
        <div><label class="label" for="phone">Số điện thoại</label><input type="text" id="phone" name="phone" class="input" maxlength="15" value="<?= e($userRecord['phone'] ?? '') ?>"></div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label" for="role">Vai trò</label>
                <select id="role" name="role" class="select">
                    <option value="customer" data-icon="ph-user" <?= $userRecord['role'] === 'customer' ? 'selected' : '' ?>>Khách hàng</option>
                    <option value="admin" data-icon="ph-crown" <?= $userRecord['role'] === 'admin' ? 'selected' : '' ?>>Quản trị viên</option>
                </select>
            </div>
            <div>
                <label class="label" for="status">Trạng thái</label>
                <select id="status" name="status" class="select">
                    <option value="1" data-icon="ph-check-circle" <?= (int)$userRecord['status'] === 1 ? 'selected' : '' ?>>Hoạt động</option>
                    <option value="0" data-icon="ph-lock" <?= (int)$userRecord['status'] === 0 ? 'selected' : '' ?>>Đã khóa</option>
                </select>
            </div>
        </div>
        <div class="sm:col-span-2"><label class="label" for="address">Địa chỉ</label><input type="text" id="address" name="address" class="input" maxlength="255" value="<?= e($userRecord['address'] ?? '') ?>"></div>
        <div class="sm:col-span-2">
            <label class="label" for="new_password">Đặt lại mật khẩu <span class="font-normal text-muted">(để trống nếu không đổi)</span></label>
            <input type="password" id="new_password" name="new_password" class="input" autocomplete="new-password" placeholder="Tối thiểu 8 ký tự, gồm cả chữ và số">
        </div>
        <div class="flex gap-2 pt-2 sm:col-span-2">
            <button type="submit" class="btn btn-primary"><i class="ph-bold ph-floppy-disk" aria-hidden="true"></i> Cập nhật</button>
            <a href="<?= e(url('admin/users/index.php')) ?>" class="btn btn-ghost">Quay lại</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../admin_footer.php'; ?>

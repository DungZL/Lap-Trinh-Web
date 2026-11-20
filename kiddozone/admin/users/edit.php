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

$pageTitle = 'Chỉnh sửa người dùng';
require __DIR__ . '/../admin_header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h2 class="fw-bold mb-0">Chỉnh sửa người dùng #<?= (int)$userRecord['id'] ?></h2>
    <span class="text-muted">Số đơn hàng đã đặt: <strong><?= $orderCount ?></strong></span>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="full_name">Họ và tên</label>
                    <input type="text" id="full_name" name="full_name" class="form-control"
                           maxlength="100" value="<?= e($userRecord['full_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           maxlength="150" value="<?= e($userRecord['email']) ?>" required>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="phone">Số điện thoại</label>
                    <input type="text" id="phone" name="phone" class="form-control"
                           maxlength="15" value="<?= e($userRecord['phone'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="role">Vai trò</label>
                    <select id="role" name="role" class="form-select">
                        <option value="customer" <?= $userRecord['role'] === 'customer' ? 'selected' : '' ?>>Khách hàng</option>
                        <option value="admin" <?= $userRecord['role'] === 'admin' ? 'selected' : '' ?>>Quản trị viên</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Trạng thái</label>
                    <select id="status" name="status" class="form-select">
                        <option value="1" <?= (int)$userRecord['status'] === 1 ? 'selected' : '' ?>>Hoạt động</option>
                        <option value="0" <?= (int)$userRecord['status'] === 0 ? 'selected' : '' ?>>Đã khóa</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="address">Địa chỉ</label>
                <input type="text" id="address" name="address" class="form-control"
                       maxlength="255" value="<?= e($userRecord['address'] ?? '') ?>">
            </div>

            <div class="mb-4">
                <label class="form-label" for="new_password">Đặt lại mật khẩu mới (để trống nếu không đổi)</label>
                <input type="password" id="new_password" name="new_password" class="form-control"
                       autocomplete="new-password" placeholder="Tối thiểu 8 ký tự, gồm cả chữ và số">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Cập nhật
            </button>
            <a href="index.php" class="btn btn-secondary">Quay lại</a>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../admin_footer.php'; ?>

<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$searchRaw = $_GET['q'] ?? '';
$search = is_string($searchRaw) ? trim(mb_substr($searchRaw, 0, 100)) : '';

$roleRaw = $_GET['role'] ?? '';
$roleFilter = (is_string($roleRaw) && in_array($roleRaw, ['admin', 'customer'], true)) ? $roleRaw : '';

$statusRaw = $_GET['status'] ?? '';
$statusFilter = (is_string($statusRaw) && in_array($statusRaw, ['0', '1'], true)) ? $statusRaw : '';

// Xử lý khóa / mở khóa nhanh tài khoản (TC14)
if (is_post()) {
    csrf_verify();
    $action   = $_POST['action'] ?? '';
    $targetId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    $backParams = [];
    if ($search !== '') {
        $backParams['q'] = $search;
    }
    if ($roleFilter !== '') {
        $backParams['role'] = $roleFilter;
    }
    if ($statusFilter !== '') {
        $backParams['status'] = $statusFilter;
    }
    $postedPage = filter_var($_POST['page'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($postedPage !== false) {
        $backParams['page'] = $postedPage;
    }
    $backUrl = 'admin/users/index.php' . ($backParams ? '?' . http_build_query($backParams) : '');

    if ($action !== 'toggle_status' || $targetId === false) {
        flash('danger', 'Yêu cầu không hợp lệ.');
        redirect($backUrl);
    }

    if ($targetId === (int)current_user()['id']) {
        flash('warning', 'Không thể tự khóa tài khoản đang đăng nhập.');
        redirect($backUrl);
    }

    $targetUser = db_query('SELECT id, full_name, status FROM users WHERE id = ?', [$targetId])->fetch();
    if (!$targetUser) {
        flash('danger', 'Không tìm thấy người dùng.');
        redirect($backUrl);
    }

    $newStatus = (int)$targetUser['status'] === 1 ? 0 : 1;
    db_query('UPDATE users SET status = ? WHERE id = ?', [$newStatus, $targetId]);
    flash('success', $newStatus === 0
        ? 'Đã khóa tài khoản "' . $targetUser['full_name'] . '".'
        : 'Đã mở khóa tài khoản "' . $targetUser['full_name'] . '".');
    redirect($backUrl);
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $term = '%' . addcslashes($search, '%_\\') . '%';
    array_push($params, $term, $term, $term);
}
if ($roleFilter !== '') {
    $where[] = 'u.role = ?';
    $params[] = $roleFilter;
}
if ($statusFilter !== '') {
    $where[] = 'u.status = ?';
    $params[] = (int)$statusFilter;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = (int)db_query("SELECT COUNT(*) FROM users u $whereSql", $params)->fetchColumn();
[$offset, $pages, $page] = paginate($total, ITEMS_PER_PAGE_ADMIN);

$users = db_query(
    "SELECT u.id, u.full_name, u.email, u.phone, u.role, u.status, u.created_at, COUNT(o.id) AS order_count
     FROM users u
     LEFT JOIN orders o ON o.user_id = u.id
     $whereSql
     GROUP BY u.id, u.full_name, u.email, u.phone, u.role, u.status, u.created_at
     ORDER BY u.id ASC
     LIMIT " . (int)ITEMS_PER_PAGE_ADMIN . ' OFFSET ' . (int)$offset,
    $params
)->fetchAll();

$currentAdminId = (int)current_user()['id'];
$pageTitle = 'Quản lý người dùng';
require __DIR__ . '/../admin_header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h2 class="fw-bold mb-0">Quản lý người dùng</h2>
    <span class="text-muted">Tổng: <strong><?= $total ?></strong> tài khoản</span>
</div>

<form method="get" action="<?= e(url('admin/users/index.php')) ?>" class="row g-2 mb-4">
    <div class="col-md-5">
        <input type="search" name="q" class="form-control" maxlength="100"
               value="<?= e($search) ?>" placeholder="Tìm theo họ tên, email hoặc số điện thoại">
    </div>
    <div class="col-6 col-md-2">
        <select name="role" class="form-select" aria-label="Lọc vai trò">
            <option value="">Tất cả vai trò</option>
            <option value="customer" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>Khách hàng</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Quản trị viên</option>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select name="status" class="form-select" aria-label="Lọc trạng thái">
            <option value="">Tất cả trạng thái</option>
            <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Hoạt động</option>
            <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Đã khóa</option>
        </select>
    </div>
    <div class="col-md-auto">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-search"></i> Tìm kiếm
        </button>
        <?php if ($search !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
            <a href="<?= e(url('admin/users/index.php')) ?>" class="btn btn-outline-secondary">Xóa lọc</a>
        <?php endif; ?>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Họ tên</th>
                    <th>Email</th>
                    <th>Điện thoại</th>
                    <th>Vai trò</th>
                    <th class="text-center">Số đơn</th>
                    <th>Trạng thái</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $uid      = (int)$u['id'];
                    $isActive = (int)$u['status'] === 1;
                    $isSelf   = $uid === $currentAdminId;
                    ?>
                    <tr>
                        <td><?= $uid ?></td>
                        <td class="fw-semibold">
                            <?= e($u['full_name']) ?>
                            <?php if ($isSelf): ?>
                                <span class="badge bg-info text-dark ms-1">Bạn</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['phone'] ?? '—') ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge bg-primary">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border">Khách hàng</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= (int)$u['order_count'] ?></td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="badge bg-success">Hoạt động</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Đã khóa</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(date('d/m/Y', strtotime($u['created_at']))) ?></td>
                        <td class="text-nowrap">
                            <a href="edit.php?id=<?= $uid ?>" class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil-square"></i> Sửa
                            </a>

                            <?php if (!$isSelf): ?>
                                <form action="<?= e(url('admin/users/index.php' . ($search !== '' || $roleFilter !== '' || $statusFilter !== '' ? '?' . http_build_query(array_filter(['q' => $search, 'role' => $roleFilter, 'status' => $statusFilter], fn($v) => $v !== '')) : ''))) ?>"
                                      method="post" class="d-inline"
                                      data-confirm="<?= e($isActive ? 'Bạn có chắc muốn khóa tài khoản này?' : 'Mở khóa tài khoản này?') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $uid ?>">
                                    <input type="hidden" name="page" value="<?= $page ?>">
                                    <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
                                        <i class="bi <?= $isActive ? 'bi-lock' : 'bi-unlock' ?>"></i>
                                        <?= $isActive ? 'Khóa' : 'Mở khóa' ?>
                                    </button>
                                </form>

                                <form action="delete.php" method="post" class="d-inline"
                                      data-confirm="Bạn có chắc muốn xóa người dùng này?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $uid ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash"></i> Xóa
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$users): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Không tìm thấy người dùng phù hợp.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    <?= render_pagination($page, $pages) ?>
</div>

<?php require __DIR__ . '/../admin_footer.php'; ?>

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
$pageSubtitle = 'Tổng cộng ' . $total . ' tài khoản';
require __DIR__ . '/../admin_header.php';
$keepQs = array_filter(['q' => $search, 'role' => $roleFilter, 'status' => $statusFilter], fn($v) => $v !== '');
?>

<div class="card">
    <form method="get" action="<?= e(url('admin/users/index.php')) ?>" class="flex flex-wrap gap-2 border-b border-ink-100 p-4 dark:border-ink-800">
        <div class="relative min-w-60 flex-1">
            <i class="ph-bold ph-magnifying-glass absolute top-1/2 left-4 -translate-y-1/2 text-muted" aria-hidden="true"></i>
            <input type="search" name="q" class="input pl-11" maxlength="100" value="<?= e($search) ?>" placeholder="Tìm theo họ tên, email hoặc số điện thoại" aria-label="Tìm người dùng">
        </div>
        <select name="role" class="select w-auto" aria-label="Lọc vai trò" data-autosubmit>
            <option value="" data-icon="ph-users-three">Tất cả vai trò</option>
            <option value="customer" data-icon="ph-user" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>Khách hàng</option>
            <option value="admin" data-icon="ph-crown" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Quản trị viên</option>
        </select>
        <select name="status" class="select w-auto" aria-label="Lọc trạng thái" data-autosubmit>
            <option value="" data-icon="ph-funnel">Tất cả trạng thái</option>
            <option value="1" data-icon="ph-check-circle" <?= $statusFilter === '1' ? 'selected' : '' ?>>Hoạt động</option>
            <option value="0" data-icon="ph-lock" <?= $statusFilter === '0' ? 'selected' : '' ?>>Đã khóa</option>
        </select>
        <button type="submit" class="btn btn-primary"><i class="ph-bold ph-magnifying-glass" aria-hidden="true"></i> Tìm</button>
        <?php if ($keepQs): ?><a href="<?= e(url('admin/users/index.php')) ?>" class="btn btn-ghost">Xóa lọc</a><?php endif; ?>
    </form>

    <div class="overflow-x-auto p-2">
        <table class="table-kz">
            <thead><tr><th>Người dùng</th><th>Điện thoại</th><th>Vai trò</th><th class="text-center">Số đơn</th><th>Trạng thái</th><th>Ngày tạo</th><th class="text-right">Thao tác</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php $uid = (int)$u['id']; $isActive = (int)$u['status'] === 1; $isSelf = $uid === $currentAdminId; ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-gradient-to-br <?= $u['role'] === 'admin' ? 'from-sky-600 to-grape-600' : 'from-sun-300 to-coral-300' ?> font-extrabold text-ink-900"><?= e(mb_strtoupper(mb_substr($u['full_name'], 0, 1))) ?></span>
                                <div><b><?= e($u['full_name']) ?></b><?php if ($isSelf): ?> <span class="badge badge-sky">Bạn</span><?php endif; ?><br><span class="text-xs text-muted"><?= e($u['email']) ?></span></div>
                            </div>
                        </td>
                        <td class="text-ink-500"><?= e($u['phone'] ?? '—') ?></td>
                        <td><?= $u['role'] === 'admin' ? '<span class="badge badge-grape"><i class="ph-fill ph-crown" aria-hidden="true"></i> Admin</span>' : '<span class="badge badge-ink">Khách hàng</span>' ?></td>
                        <td class="text-center font-bold"><?= (int)$u['order_count'] ?></td>
                        <td><?= $isActive ? '<span class="badge badge-mint">Hoạt động</span>' : '<span class="badge badge-coral"><i class="ph-fill ph-lock" aria-hidden="true"></i> Đã khóa</span>' ?></td>
                        <td class="text-ink-500"><?= e(date('d/m/Y', strtotime($u['created_at']))) ?></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="<?= e(url('admin/users/edit.php?id=' . $uid)) ?>" class="btn btn-ghost btn-sm" aria-label="Sửa <?= e($u['full_name']) ?>"><i class="ph-bold ph-pencil-simple" aria-hidden="true"></i></a>
                            <?php if (!$isSelf): ?>
                                <form action="<?= e(url('admin/users/index.php' . ($keepQs ? '?' . http_build_query($keepQs) : ''))) ?>" method="post" class="inline"
                                      data-confirm="<?= e($isActive ? 'Bạn có chắc muốn khóa tài khoản này?' : 'Mở khóa tài khoản này?') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $uid ?>">
                                    <input type="hidden" name="page" value="<?= $page ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" title="<?= $isActive ? 'Khóa' : 'Mở khóa' ?>"><i class="ph-bold <?= $isActive ? 'ph-lock' : 'ph-lock-open text-ok' ?>" aria-hidden="true"></i> <?= $isActive ? 'Khóa' : 'Mở khóa' ?></button>
                                </form>
                                <form action="<?= e(url('admin/users/delete.php')) ?>" method="post" class="inline" data-confirm="Bạn có chắc muốn xóa người dùng này?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $uid ?>">
                                    <button type="submit" class="btn btn-danger-ghost btn-sm" aria-label="Xóa <?= e($u['full_name']) ?>"><i class="ph-bold ph-trash" aria-hidden="true"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?>
                    <tr><td colspan="7" class="py-10 text-center text-muted">Không tìm thấy người dùng phù hợp.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= render_pagination($page, $pages) ?>

<?php require __DIR__ . '/../admin_footer.php'; ?>

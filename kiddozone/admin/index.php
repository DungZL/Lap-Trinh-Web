
<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

$pageTitle = 'Dashboard Admin';

// Thống kê dữ liệu
$totalCategories = (int) db_query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalProducts = (int) db_query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders = (int) db_query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalUsers = (int) db_query("SELECT COUNT(*) FROM users")->fetchColumn();

require __DIR__ . '/admin_header.php';
?>

<h1 class="mb-3">Dashboard Admin</h1>
<p>Xin chào, <?= e(current_user()['full_name'] ?? 'Quản trị viên') ?>!</p>

<div class="row g-4 mt-3">
    <?php
    $stats = [
        ['Danh mục', $totalCategories, 'primary'],
        ['Sản phẩm', $totalProducts, 'success'],
        ['Đơn hàng', $totalOrders, 'warning'],
        ['Người dùng', $totalUsers, 'danger']
    ];
    ?>

    <?php foreach ($stats as $stat): ?>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5><?= e($stat[0]) ?></h5>
                    <h2 class="text-<?= e($stat[2]) ?>">
                        <?= (int)$stat[1] ?>
                    </h2>
                    <p class="text-muted mb-0">Tổng <?= e(strtolower($stat[0])) ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/admin_footer.php'; ?>

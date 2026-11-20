
<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

$pageTitle = 'Dashboard';

// 1. Tổng doanh thu từ đơn hoàn thành.
$totalRevenue = (float) db_query(
    "SELECT COALESCE(SUM(total_amount), 0)
     FROM orders
     WHERE status = 'completed'"
)->fetchColumn();

// 2. Tổng số đơn hàng.
$totalOrders = (int) db_query(
    "SELECT COUNT(*) FROM orders"
)->fetchColumn();

// 3. Đơn hàng chờ xác nhận.
$pendingOrders = (int) db_query(
    "SELECT COUNT(*) FROM orders
     WHERE status = 'pending'"
)->fetchColumn();

// 4. Tổng sản phẩm.
$totalProducts = (int) db_query(
    "SELECT COUNT(*) FROM products"
)->fetchColumn();

// 5. Tổng khách hàng.
$totalCustomers = (int) db_query(
    "SELECT COUNT(*) FROM users
     WHERE role = 'customer'"
)->fetchColumn();

// 6. Doanh thu theo tháng trong năm hiện tại.
$year = (int) date('Y');

$monthlyRows = db_query(
    "SELECT MONTH(created_at) AS month,
            SUM(total_amount) AS revenue
     FROM orders
     WHERE status = 'completed'
       AND YEAR(created_at) = ?
     GROUP BY MONTH(created_at)",
    [$year]
)->fetchAll();

$monthlyRevenue = array_fill(0, 12, 0);

foreach ($monthlyRows as $row) {
    $monthIndex = (int)$row['month'] - 1;
    $monthlyRevenue[$monthIndex] = (float)$row['revenue'];
}

require __DIR__ . '/admin_header.php';
?>

<div class="mb-4">
    <h2 class="fw-bold">Tổng quan hệ thống</h2>
    <p class="text-muted">
        Thống kê hoạt động kinh doanh KiddoZone
    </p>
</div>

<!-- Các thẻ thống kê -->
<div class="row g-3 mb-4">

    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2">
                    <i class="bi bi-cash-stack"></i>
                    Tổng doanh thu
                </p>
                <h4 class="fw-bold text-success">
                    <?= number_format($totalRevenue, 0, ',', '.') ?> đ
                </h4>
                <small class="text-muted">
                    Đơn hàng hoàn thành
                </small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2">
                    <i class="bi bi-cart-check"></i>
                    Tổng đơn hàng
                </p>
                <h4 class="fw-bold">
                    <?= $totalOrders ?>
                </h4>
                <small class="text-muted">
                    Chờ xác nhận: <?= $pendingOrders ?>
                </small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2">
                    <i class="bi bi-box-seam"></i>
                    Sản phẩm
                </p>
                <h4 class="fw-bold">
                    <?= $totalProducts ?>
                </h4>
                <small class="text-muted">
                    Tổng sản phẩm trong hệ thống
                </small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2">
                    <i class="bi bi-people"></i>
                    Khách hàng
                </p>
                <h4 class="fw-bold">
                    <?= $totalCustomers ?>
                </h4>
                <small class="text-muted">
                    Tài khoản khách hàng
                </small>
            </div>
        </div>
    </div>

</div>

<!-- Biểu đồ doanh thu -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="fw-bold mb-4">
            <i class="bi bi-bar-chart"></i>
            Biểu đồ doanh thu năm <?= $year ?>
        </h5>

        <div style="height: 350px;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
</div>

<div class="mb-4">
    <a href="<?= e(url('admin/orders.php')) ?>"
       class="btn btn-primary">
        <i class="bi bi-cart"></i>
        Quản lý đơn hàng
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const revenueData = <?= json_encode(
    $monthlyRevenue,
    JSON_NUMERIC_CHECK
) ?>;

const ctx = document.getElementById('revenueChart');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: [
            'T1', 'T2', 'T3', 'T4',
            'T5', 'T6', 'T7', 'T8',
            'T9', 'T10', 'T11', 'T12'
        ],
        datasets: [{
            label: 'Doanh thu (VNĐ)',
            data: revenueData,
            backgroundColor: '#e8ad43',
            borderColor: '#d18d21',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value =>
                        Number(value).toLocaleString('vi-VN') + ' đ'
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: context =>
                        Number(context.parsed.y)
                            .toLocaleString('vi-VN') + ' đ'
                }
            }
        }
    }
});
</script>

<?php require __DIR__ . '/admin_footer.php'; ?>

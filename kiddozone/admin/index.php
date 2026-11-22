<?php
// Dashboard (F14): số liệu tổng, biểu đồ doanh thu (tháng / 7 ngày), trạng thái đơn, bán chạy,
// cảnh báo sắp hết hàng (#11), đơn hàng gần đây.
require_once __DIR__ . '/../includes/init.php';
require_admin();

$pageTitle = 'Tổng quan';
$pageSubtitle = 'Thống kê hoạt động kinh doanh KiddoZone';

// 1. Số liệu tổng
$totalRevenue   = (float)db_query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed'")->fetchColumn();
$totalOrders    = (int)db_query('SELECT COUNT(*) FROM orders')->fetchColumn();
$pendingOrders  = (int)db_query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$totalProducts  = (int)db_query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalCustomers = (int)db_query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();

// 2. Doanh thu theo tháng trong năm hiện tại
$year = (int)date('Y');
$monthlyRows = db_query(
    "SELECT MONTH(created_at) AS month, SUM(total_amount) AS revenue
     FROM orders WHERE status = 'completed' AND YEAR(created_at) = ?
     GROUP BY MONTH(created_at)",
    [$year]
)->fetchAll();
$monthlyRevenue = array_fill(0, 12, 0);
foreach ($monthlyRows as $row) {
    $monthlyRevenue[(int)$row['month'] - 1] = (float)$row['revenue'];
}

// 3. Doanh thu 7 ngày gần đây (từ hôm nay lùi 6 ngày)
$dailyRows = db_query(
    "SELECT DATE(created_at) AS day, COALESCE(SUM(total_amount), 0) AS revenue
     FROM orders WHERE status = 'completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)"
)->fetchAll();
$dailyMap = [];
foreach ($dailyRows as $row) {
    $dailyMap[$row['day']] = (float)$row['revenue'];
}
$dailyLabels = [];
$dailyRevenue = [];
for ($i = 6; $i >= 0; $i--) {
    $ts = strtotime("-{$i} days");
    $dailyLabels[] = date('d/m', $ts);
    $dailyRevenue[] = $dailyMap[date('Y-m-d', $ts)] ?? 0;
}

// 4. Đơn theo trạng thái
$statusCounts = array_fill_keys(array_keys(order_statuses()), 0);
foreach (db_query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status')->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int)$row['n'];
}

// 5. Sản phẩm bán chạy (đơn không bị hủy)
$topProducts = db_query(
    "SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.quantity * oi.price) AS revenue
     FROM order_items oi JOIN orders o ON o.id = oi.order_id
     WHERE o.status <> 'cancelled'
     GROUP BY oi.product_name ORDER BY qty DESC, revenue DESC LIMIT 5"
)->fetchAll();
$topMax = $topProducts ? max(array_map(fn($r) => (int)$r['qty'], $topProducts)) : 1;

// 6. Sắp hết hàng (#11)
$lowStock = db_query(
    'SELECT p.id, p.name, p.stock,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image
     FROM products p WHERE p.status = 1 AND p.stock <= ?
     ORDER BY p.stock ASC, p.name ASC LIMIT 8',
    [LOW_STOCK_THRESHOLD]
)->fetchAll();

// 7. Đơn hàng gần đây
$recentOrders = db_query(
    'SELECT o.id, o.code, o.receiver_name, o.total_amount, o.status, o.created_at, o.admin_seen
     FROM orders o ORDER BY o.created_at DESC, o.id DESC LIMIT 6'
)->fetchAll();

$pageActions = '<a href="' . e(url('admin/orders/export.php')) . '" class="btn btn-ghost"><i class="ph-bold ph-file-csv text-lg text-ok" aria-hidden="true"></i> Xuất Excel</a>'
             . '<a href="' . e(url('admin/products/create.php')) . '" class="btn btn-primary"><i class="ph-bold ph-plus" aria-hidden="true"></i> Thêm sản phẩm</a>';

require __DIR__ . '/admin_header.php';

$stats = [
    ['Doanh thu', $totalRevenue, ' ₫', 'Từ đơn đã giao', 'ph-currency-circle-dollar', 'from-mint-400 to-sky-400'],
    ['Đơn hàng', $totalOrders, '', $pendingOrders . ' đơn chờ xác nhận', 'ph-receipt', 'from-sun-400 to-coral-400'],
    ['Sản phẩm', $totalProducts, '', count($lowStock) . ' sản phẩm sắp hết', 'ph-package', 'from-sky-600 to-grape-600'],
    ['Khách hàng', $totalCustomers, '', 'Tài khoản khách hàng', 'ph-users-three', 'from-grape-400 to-coral-400'],
];
?>

<!-- Thẻ thống kê -->
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($stats as [$label, $value, $suffix, $sub, $icon, $grad]): ?>
        <div data-reveal class="card relative overflow-hidden p-5">
            <span class="absolute -top-6 -right-6 size-24 rounded-full bg-gradient-to-br opacity-15 <?= $grad ?>"></span>
            <div class="flex items-start justify-between">
                <p class="text-sm font-bold text-muted"><?= e($label) ?></p>
                <span class="grid size-11 place-items-center rounded-2xl bg-gradient-to-br text-2xl text-white shadow-soft <?= $grad ?>"><i class="ph-fill <?= $icon ?>" aria-hidden="true"></i></span>
            </div>
            <p class="mt-2 font-display text-3xl font-bold text-ink-900 dark:text-white"><span data-count="<?= (float)$value ?>" data-suffix="<?= e($suffix) ?>"><?= e(number_format((float)$value, 0, ',', '.') . $suffix) ?></span></p>
            <p class="mt-1 text-xs font-bold text-muted"><?= e($sub) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <!-- Biểu đồ doanh thu -->
    <section data-reveal class="card p-6 xl:col-span-2" x-data="{ mode: 'monthly' }">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <h2 class="flex items-center gap-2 font-display text-xl font-semibold"><i class="ph-fill ph-chart-bar text-sky-600" aria-hidden="true"></i> <span id="revenueChartTitle">Doanh thu năm <?= $year ?></span></h2>
            <div class="flex rounded-full bg-ink-50 p-1 text-xs font-extrabold dark:bg-ink-800" role="group" aria-label="Chế độ biểu đồ doanh thu">
                <button type="button" data-chart-mode="monthly" class="rounded-full px-4 py-2 transition" :class="mode === 'monthly' ? 'bg-white shadow-soft dark:bg-ink-700' : 'text-muted'" @click="mode = 'monthly'">Theo tháng</button>
                <button type="button" data-chart-mode="daily" class="rounded-full px-4 py-2 transition" :class="mode === 'daily' ? 'bg-white shadow-soft dark:bg-ink-700' : 'text-muted'" @click="mode = 'daily'">7 ngày gần đây</button>
            </div>
        </div>
        <div class="h-80"><canvas id="revenueChart" aria-label="Biểu đồ doanh thu"></canvas></div>
    </section>

    <!-- Đơn theo trạng thái -->
    <section data-reveal class="card p-6">
        <h2 class="mb-5 flex items-center gap-2 font-display text-xl font-semibold"><i class="ph-fill ph-chart-donut text-age" aria-hidden="true"></i> Trạng thái đơn</h2>
        <div class="relative mx-auto h-48 w-48"><canvas id="statusChart" aria-label="Biểu đồ trạng thái đơn hàng"></canvas>
            <div class="pointer-events-none absolute inset-0 grid place-items-center text-center"><div><p class="font-display text-3xl font-bold"><?= $totalOrders ?></p><p class="text-xs font-bold text-muted">đơn hàng</p></div></div>
        </div>
        <ul class="mt-5 space-y-2 text-sm">
            <?php $dots = ['pending' => 'bg-sun-400', 'confirmed' => 'bg-sky-400', 'shipping' => 'bg-grape-400', 'completed' => 'bg-mint-400', 'cancelled' => 'bg-ink-300']; ?>
            <?php foreach (order_statuses() as $key => [$label]): ?>
                <li class="flex items-center gap-2"><span class="size-3 rounded-full <?= $dots[$key] ?>"></span><span class="flex-1 font-semibold"><?= e($label) ?></span><b><?= $statusCounts[$key] ?></b></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <!-- Sắp hết hàng (#11) -->
    <section data-reveal class="card p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="flex items-center gap-2 font-display text-xl font-semibold"><i class="ph-fill ph-warning text-price" aria-hidden="true"></i> Sắp hết hàng</h2>
            <span class="badge badge-coral">≤ <?= LOW_STOCK_THRESHOLD ?></span>
        </div>
        <?php if (!$lowStock): ?>
            <p class="rounded-2xl bg-mint-50 p-4 text-sm font-bold text-ok dark:bg-mint-500/10 dark:text-mint-300"><i class="ph-fill ph-check-circle" aria-hidden="true"></i> Kho hàng ổn định, chưa có sản phẩm nào sắp hết.</p>
        <?php else: ?>
            <ul class="space-y-2">
                <?php foreach ($lowStock as $p): ?>
                    <li class="flex items-center gap-3 rounded-2xl p-2 hover:bg-ink-50 dark:hover:bg-ink-800">
                        <img src="<?= e(product_image_url($p['image'])) ?>" alt="" class="size-10 rounded-xl object-cover" data-fallback="<?= e(url('assets/img/placeholder.svg')) ?>">
                        <span class="min-w-0 flex-1 truncate text-sm font-bold"><?= e($p['name']) ?></span>
                        <span class="badge <?= (int)$p['stock'] === 0 ? 'bg-coral-600 text-white' : 'badge-sun' ?>"><?= (int)$p['stock'] === 0 ? 'Hết hàng' : 'Còn ' . (int)$p['stock'] ?></span>
                        <a href="<?= e(url('admin/products/edit.php?id=' . (int)$p['id'])) ?>" class="btn btn-ghost btn-sm" aria-label="Nhập thêm hàng cho <?= e($p['name']) ?>"><i class="ph-bold ph-plus" aria-hidden="true"></i></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Bán chạy -->
    <section data-reveal class="card p-6">
        <h2 class="mb-4 flex items-center gap-2 font-display text-xl font-semibold"><i class="ph-fill ph-trophy text-sun-500" aria-hidden="true"></i> Bán chạy nhất</h2>
        <?php if (!$topProducts): ?>
            <p class="text-sm text-muted">Chưa có dữ liệu.</p>
        <?php else: ?>
            <ol class="space-y-4">
                <?php foreach ($topProducts as $i => $t): ?>
                    <li>
                        <div class="mb-1 flex items-center gap-2 text-sm">
                            <span class="grid size-6 place-items-center rounded-full text-xs font-extrabold <?= $i === 0 ? 'bg-sun-400 text-ink-900' : 'bg-ink-100 dark:bg-ink-800' ?>"><?= $i + 1 ?></span>
                            <span class="flex-1 truncate font-bold"><?= e($t['product_name']) ?></span>
                            <span class="text-xs font-bold text-muted"><?= (int)$t['qty'] ?> đã bán</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full bg-gradient-to-r from-sun-400 to-coral-400" style="width: <?= round((int)$t['qty'] / $topMax * 100) ?>%"></div></div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <!-- Đơn gần đây -->
    <section data-reveal class="card p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="flex items-center gap-2 font-display text-xl font-semibold"><i class="ph-fill ph-clock text-sky-600" aria-hidden="true"></i> Đơn gần đây</h2>
            <a href="<?= e(url('admin/orders.php')) ?>" class="link text-sm">Tất cả</a>
        </div>
        <ul class="space-y-1">
            <?php foreach ($recentOrders as $o): ?>
                <li>
                    <a href="<?= e(url('admin/orders.php?id=' . (int)$o['id'])) ?>" class="flex items-center gap-3 rounded-2xl p-2 hover:bg-ink-50 dark:hover:bg-ink-800">
                        <span class="relative grid size-10 place-items-center rounded-xl bg-ink-50 text-lg dark:bg-ink-800"><i class="ph-fill ph-receipt text-muted" aria-hidden="true"></i>
                            <?php if (!(int)$o['admin_seen']): ?><span class="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-coral-400 ring-2 ring-white dark:ring-ink-900"></span><?php endif; ?>
                        </span>
                        <span class="min-w-0 flex-1"><b class="block text-sm"><?= e($o['code']) ?></b><span class="block truncate text-xs text-muted"><?= e($o['receiver_name']) ?> · <?= e(date('d/m H:i', strtotime($o['created_at']))) ?></span></span>
                        <span class="text-right"><b class="block text-sm"><?= e(format_price($o['total_amount'])) ?></b><?= order_status_badge($o['status']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const datasets = {
        monthly: { title: <?= json_encode('Doanh thu năm ' . $year) ?>, labels: ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'], data: <?= json_encode($monthlyRevenue, JSON_NUMERIC_CHECK) ?> },
        daily:   { title: 'Doanh thu 7 ngày gần đây', labels: <?= json_encode($dailyLabels) ?>, data: <?= json_encode($dailyRevenue, JSON_NUMERIC_CHECK) ?> }
    };
    const money = v => Number(v).toLocaleString('vi-VN') + ' ₫';
    const isDark = () => document.documentElement.classList.contains('dark');
    Chart.defaults.font.family = 'Nunito, sans-serif';
    Chart.defaults.font.weight = 'bold';

    const ctx = document.getElementById('revenueChart').getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 320);
    grad.addColorStop(0, '#4f93fb');
    grad.addColorStop(1, 'rgba(155,123,255,.35)');

    const revenueChart = new Chart(ctx, {
        type: 'bar',
        data: { labels: datasets.monthly.labels, datasets: [{ label: 'Doanh thu', data: datasets.monthly.data, backgroundColor: grad, hoverBackgroundColor: '#ffc93c', borderRadius: 12, borderSkipped: false, maxBarThickness: 42 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 900, easing: 'easeOutBack' },
            plugins: { legend: { display: false }, tooltip: { padding: 12, cornerRadius: 12, callbacks: { label: c => money(c.parsed.y) } } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, border: { display: false }, ticks: { callback: v => Number(v) >= 1e6 ? (v / 1e6).toLocaleString('vi-VN') + ' tr' : Number(v).toLocaleString('vi-VN') } }
            }
        }
    });

    document.querySelectorAll('[data-chart-mode]').forEach(btn => btn.addEventListener('click', () => {
        const cfg = datasets[btn.dataset.chartMode];
        document.getElementById('revenueChartTitle').textContent = cfg.title;
        revenueChart.data.labels = cfg.labels;
        revenueChart.data.datasets[0].data = cfg.data;
        revenueChart.update();
    }));

    const statusChart = new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: { labels: <?= json_encode(array_map(fn($s) => $s[0], array_values(order_statuses())), JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{ data: <?= json_encode(array_values($statusCounts)) ?>, backgroundColor: ['#ffc93c', '#4f93fb', '#9b7bff', '#2ecc9a', '#a6b1c7'], borderWidth: 0, hoverOffset: 8 }] },
        options: { cutout: '72%', plugins: { legend: { display: false } }, animation: { animateRotate: true, duration: 1200 } }
    });

    function applyTheme() {
        const c = isDark() ? '#a6b1c7' : '#75839f';
        const g = isDark() ? 'rgba(255,255,255,.06)' : 'rgba(28,37,64,.06)';
        [revenueChart, statusChart].forEach(ch => {
            if (ch.options.scales && ch.options.scales.x) {
                ch.options.scales.x.ticks.color = c;
                ch.options.scales.y.ticks.color = c;
                ch.options.scales.y.grid.color = g;
            }
            ch.update('none');
        });
    }
    applyTheme();
    document.addEventListener('kz:theme', applyTheme);
})();
</script>

<?php require __DIR__ . '/admin_footer.php'; ?>

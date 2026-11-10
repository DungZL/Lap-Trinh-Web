<?php
require_once __DIR__ . '/../includes/init.php';
require_admin(); // BẮT BUỘC ở dòng đầu mọi trang trong admin/

$pageTitle = 'Dashboard';
require __DIR__ . '/admin_header.php';
?>
<!-- TODO (F14): dashboard - tổng doanh thu, đơn chờ xác nhận, số sản phẩm, số khách hàng, biểu đồ Chart.js. -->
<h2 class="fw-bold mb-3">Trang quản trị</h2>
<p class="text-muted">Xin chào <?= e(current_user()['full_name']) ?>. Dashboard sẽ được bổ sung ở task F14.</p>
<?php require __DIR__ . '/admin_footer.php'; ?>

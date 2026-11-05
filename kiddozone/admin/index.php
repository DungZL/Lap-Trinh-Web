<?php
require_once __DIR__ . '/../includes/init.php';
require_admin(); // BẮT BUỘC ở dòng đầu mọi trang trong admin/

$pageTitle = 'Quản trị';
require __DIR__ . '/../includes/header.php';
?>
<!-- TODO (F14): dashboard. Layout admin riêng sẽ làm ở task "Layout trang quản trị". -->
<h1 class="h3">Trang quản trị</h1>
<p class="text-muted">Xin chào <?= e(current_user()['full_name']) ?>. Dashboard sẽ được bổ sung ở task F14.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>

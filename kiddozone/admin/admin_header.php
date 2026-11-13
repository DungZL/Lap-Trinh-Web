<?php
// Layout chung trang quản trị. Trang gọi: đặt $pageTitle rồi require __DIR__ . '/../admin_header.php';
$pageTitle = $pageTitle ?? 'Quản trị';

// Mục menu đang mở: so khớp đường dẫn hiện tại với từng mục.
$adminMenu = [
    'admin/index.php'      => ['bi-speedometer2', 'Dashboard'],
    'admin/categories/'    => ['bi-folder', 'Danh mục'],
    'admin/products/'      => ['bi-box', 'Sản phẩm'],
    'admin/orders/'        => ['bi-cart', 'Đơn hàng'],
    'admin/users/'         => ['bi-people', 'Người dùng'],
    'admin/reviews/'       => ['bi-star', 'Đánh giá'],
];
$currentPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | KiddoZone Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/admin.css')) ?>" rel="stylesheet">
</head>
<body>

<div class="admin-sidebar">
    <h4 class="text-white fw-bold mb-4">
        <i class="bi bi-box-seam"></i> KiddoZone
    </h4>

    <?php foreach ($adminMenu as $path => [$icon, $label]): ?>
        <?php $href = url($path); ?>
        <a href="<?= e($href) ?>" class="<?= str_starts_with($currentPath, $href) ? 'active' : '' ?>">
            <i class="bi <?= e($icon) ?> me-2"></i> <?= e($label) ?>
        </a>
    <?php endforeach; ?>

    <hr class="text-white">

    <a href="<?= e(url('index.php')) ?>">
        <i class="bi bi-house me-2"></i> Xem website
    </a>

    <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
        <?= csrf_field() ?>
        <button type="submit" class="admin-logout">
            <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
        </button>
    </form>
</div>

<main class="admin-content">
    <?php foreach (flash_pull() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show">
            <?= e($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endforeach; ?>

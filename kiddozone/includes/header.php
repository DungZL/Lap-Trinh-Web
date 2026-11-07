<?php
// Header chung cho trang khách và admin.
// Cách dùng: $pageTitle = 'Tên trang'; require __DIR__ . '/header.php';
$pageTitle = $pageTitle ?? 'KiddoZone';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | KiddoZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>">KiddoZone</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(url('cart.php')) ?>">
                        <i class="bi bi-cart3" aria-hidden="true"></i> Giỏ hàng
                        <span class="badge text-bg-primary"><?= cart_item_count() ?></span>
                    </a>
                </li>
                <?php if ($user): ?>
                    <li class="nav-item"><span class="nav-link"><?= e($user['full_name']) ?></span></li>
                    <?php if (is_admin()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/index.php')) ?>">Quản trị</a></li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-secondary btn-sm">Đăng xuất</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('login.php')) ?>">Đăng nhập</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('register.php')) ?>">Đăng ký</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="container py-4">
<?php foreach (flash_pull() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

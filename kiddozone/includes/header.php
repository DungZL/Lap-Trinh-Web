<?php
// Header trang khách (task LTWCK-12): menu thu gọn trên mobile, ô tìm kiếm, giỏ hàng, menu theo đăng nhập.
// Cách dùng: $pageTitle = 'Tên trang'; require __DIR__ . '/header.php';
$pageTitle = $pageTitle ?? 'KiddoZone';
$user = current_user();

// Trang hiện tại, dùng để tô sáng mục menu đang mở.
$currentPage = basename($_SERVER['SCRIPT_NAME']);

// Số sản phẩm trong giỏ. Hàm cart_count() sẽ do task giỏ hàng (F19) định nghĩa; chưa có thì hiện 0.
$cartCount = function_exists('cart_count') ? (int) cart_count() : 0;

$menu = [
    'index.php'    => 'Trang chủ',
    'products.php' => 'Sản phẩm',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | KiddoZone</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/header-footer.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('assets/css/shop.css')) ?>" rel="stylesheet">
</head>
<body>
<header class="kz-header sticky-top">
    <nav class="navbar navbar-expand-lg">
        <div class="container">

            <a class="navbar-brand kz-brand" href="<?= e(url('index.php')) ?>">KiddoZone</a>

            <!-- Giỏ hàng + nút hamburger luôn hiện trên thanh trên cùng, kể cả mobile -->
            <div class="d-flex align-items-center gap-2 order-lg-last">
                <?php if (!is_admin()): ?>
                    <a class="kz-cart position-relative" href="<?= e(url('cart.php')) ?>"
                       aria-label="Giỏ hàng, <?= $cartCount ?> sản phẩm">
                        <i class="bi bi-cart3"></i>
                        <span class="kz-cart-badge badge rounded-pill" <?= $cartCount > 0 ? '' : 'hidden' ?>><?= $cartCount ?></span>
                    </a>
                <?php endif; ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                        data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Mở menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <div class="collapse navbar-collapse" id="nav">

                <ul class="navbar-nav me-lg-3 mb-2 mb-lg-0">
                    <?php foreach ($menu as $file => $label): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === $file ? 'active' : '' ?>"
                               <?= $currentPage === $file ? 'aria-current="page"' : '' ?>
                               href="<?= e(url($file)) ?>"><?= e($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Ô tìm kiếm: gửi từ khóa q tới trang sản phẩm -->
                <form class="kz-search flex-grow-1 me-lg-3 mb-3 mb-lg-0" role="search"
                      action="<?= e(url('products.php')) ?>" method="get">
                    <div class="input-group">
                        <input class="form-control" type="search" name="q" maxlength="100"
                               placeholder="Tìm đồ chơi cho bé" aria-label="Tìm kiếm sản phẩm"
                               value="<?= e($_GET['q'] ?? '') ?>">
                        <button class="btn kz-btn-search" type="submit" aria-label="Tìm">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>

                <!-- Menu theo trạng thái đăng nhập -->
                <ul class="navbar-nav ms-lg-auto align-items-lg-center">
                    <?php if ($user): ?>
                        <?php if (is_admin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= e(url('admin/index.php')) ?>">Quản trị</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?= e($user['full_name']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-lg-end">
                                <?php if (!is_admin()): ?>
                                    <!-- TODO: đối chiếu tên file thật trong thư mục account/ -->
                                    <li><a class="dropdown-item" href="<?= e(url('account/index.php')) ?>">Tài khoản của tôi</a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('account/orders.php')) ?>">Đơn hàng của tôi</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li>
                                    <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
                                        <?= csrf_field() ?>
                                        <button class="dropdown-item" type="submit">Đăng xuất</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e(url('login.php')) ?>">Đăng nhập</a></li>
                        <li class="nav-item ms-lg-1">
                            <a class="btn kz-btn-register" href="<?= e(url('register.php')) ?>">Đăng ký</a>
                        </li>
                    <?php endif; ?>
                </ul>

            </div>
        </div>
    </nav>
</header>
<main class="container py-4">
<?php foreach (flash_pull() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

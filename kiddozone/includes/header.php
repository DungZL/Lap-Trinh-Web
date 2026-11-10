<?php
// Header trang khách. Trang gọi: đặt $pageTitle rồi require __DIR__ . '/includes/header.php';
$pageTitle = $pageTitle ?? 'KiddoZone';
$user = current_user();

// Trang hiện tại, dùng để tô sáng mục menu đang mở.
$currentPage = basename($_SERVER['SCRIPT_NAME']);

// Số sản phẩm trong giỏ. Hàm cart_count() sẽ do task giỏ hàng (F06/F19) định nghĩa; chưa có thì hiện 0.
$cartCount = function_exists('cart_count') ? (int)cart_count() : 0;

// Menu danh mục lấy từ CSDL (chỉ danh mục đang hiển thị).
$navCategories = db_query('SELECT id, name FROM categories WHERE status = 1 ORDER BY id LIMIT 6')->fetchAll();
$navCategoryId = ($currentPage === 'products.php' && is_string($_GET['category'] ?? null)) ? (int)$_GET['category'] : 0;
$navSale       = $currentPage === 'products.php' && ($_GET['sale'] ?? '') === '1';

// Giữ lại từ khóa trong ô tìm kiếm khi đang ở trang sản phẩm.
$searchValue = ($currentPage === 'products.php' && is_string($_GET['q'] ?? null)) ? $_GET['q'] : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | KiddoZone</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/header-footer.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('assets/css/shop.css')) ?>" rel="stylesheet">
    <?php foreach ($extraCss ?? [] as $href): /* CSS riêng của trang, ví dụ auth.css */ ?>
        <link href="<?= e($href) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body class="bg-light">

<!-- Thanh chào mừng trên cùng -->
<div class="header-top">
    <div class="container d-flex justify-content-between">
        <span><i class="bi bi-telephone-fill me-1"></i> Hotline: 1900 1234</span>
        <span class="d-none d-sm-inline">Miễn phí giao hàng cho đơn từ 500k</span>
    </div>
</div>

<!-- Phần Header chính -->
<div class="header-main">
    <div class="container">
        <div class="row align-items-center">
            <!-- Logo -->
            <div class="col-md-3">
                <a href="<?= e(url('index.php')) ?>" class="brand-logo">
                    <i class="bi bi-star-fill"></i> Kiddo<span>Zone</span>
                </a>
            </div>

            <!-- Thanh tìm kiếm: gửi tới trang danh sách sản phẩm (tham số q) -->
            <div class="col-md-5 my-3 my-md-0">
                <form class="search-box" action="<?= e(url('products.php')) ?>" method="get" role="search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" class="form-control" maxlength="100"
                           placeholder="Tìm kiếm đồ chơi, xe cộ, búp bê..." aria-label="Tìm kiếm sản phẩm"
                           value="<?= e($searchValue) ?>">
                </form>
            </div>

            <!-- Tài khoản & Giỏ hàng -->
            <div class="col-md-4 d-flex justify-content-md-end align-items-center gap-4">
                <?php if ($user): ?>
                    <div class="dropdown">
                        <a href="#" class="action-icon dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                            <div>
                                <div class="action-sub">Xin chào</div>
                                <div><?= e($user['full_name']) ?></div>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (is_admin()): ?>
                                <li><a class="dropdown-item" href="<?= e(url('admin/index.php')) ?>">Trang quản trị</a></li>
                            <?php endif; ?>
                            <li>
                                <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item text-danger">Đăng xuất</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= e(url('login.php')) ?>" class="action-icon">
                        <i class="bi bi-person-circle"></i>
                        <div>
                            <div class="action-sub">Tài khoản</div>
                            <div>Đăng nhập</div>
                        </div>
                    </a>
                <?php endif; ?>

                <a href="<?= e(url('cart.php')) ?>" class="action-icon cart-icon-wrapper">
                    <i class="bi bi-cart3"></i>
                    <span class="cart-badge"><?= $cartCount ?></span>
                    <div class="ms-2">
                        <div class="action-sub">Giỏ hàng</div>
                        <div><?= $cartCount ?> sản phẩm</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Menu danh mục -->
<nav class="nav-category shadow-sm" aria-label="Danh mục sản phẩm">
    <div class="container">
        <ul class="nav justify-content-center">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= e(url('index.php')) ?>">
                    <i class="bi bi-house-door me-1"></i> Trang chủ
                </a>
            </li>
            <?php foreach ($navCategories as $c): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $navCategoryId === (int)$c['id'] ? 'active' : '' ?>"
                       href="<?= e(url('products.php?category=' . (int)$c['id'])) ?>"><?= e($c['name']) ?></a>
                </li>
            <?php endforeach; ?>
            <li class="nav-item">
                <a class="nav-link nav-sale <?= $navSale ? 'active' : '' ?>" href="<?= e(url('products.php?sale=1')) ?>">
                    <i class="bi bi-fire"></i> Khuyến mãi
                </a>
            </li>
        </ul>
    </div>
</nav>

<!-- Vùng chứa nội dung chính -->
<main class="container py-4">
    <!-- Hiển thị thông báo (flash message) -->
    <?php foreach (flash_pull() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show">
            <?= e($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endforeach; ?>

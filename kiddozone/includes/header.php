<?php

// Header tối ưu giao diện web bán hàng
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
    <!-- Thư viện Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Tùy chỉnh CSS cho Header -->
    <style>
        .header-top {
            background: linear-gradient(90deg, #3a64da, #6f9ce6);
            color: white;
            font-size: 13px;
            padding: 5px 0;
        }
        .header-main {
            background-color: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 15px 0;
        }
        .brand-logo {
            font-size: 28px;
            font-weight: 900;
            color: #1e3a8a;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .brand-logo span { color: #f59e0b; }
        
        /* Thanh tìm kiếm */
        .search-box {
            position: relative;
            width: 100%;
        }
        .search-box input {
            border-radius: 50px;
            padding: 10px 20px 10px 45px;
            border: 2px solid #3b82f6;
            background-color: #f8fafc;
        }
        .search-box input:focus {
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            background-color: #fff;
        }
        .search-box i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #3b82f6;
            font-size: 18px;
        }
        
        /* Icon giỏ hàng và tài khoản */
        .action-icon {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #334155;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: 0.2s;
        }
        .action-icon:hover { color: #3b82f6; }
        .action-icon i { font-size: 22px; }
        
        .cart-icon-wrapper {
            position: relative;
        }
        .cart-badge {
            position: absolute;
            top: -6px;
            right: -10px;
            background: #ef4444;
            color: white;
            font-size: 11px;
            font-weight: bold;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Menu danh mục */
        .nav-category {
            background-color: #f3de65;
            border-bottom: 1px solid #e5e9ee;
        }
        .nav-category .nav-link {
            color: #f5f1f1;
            font-weight: 600;
            padding: 12px 20px;
            font-size: 15px;
        }
        .nav-category .nav-link:hover { color: #3b82f6; }
    </style>
</head>
<body class="bg-light">

<!-- Thanh chào mừng trên cùng -->
<div class="header-top">
    <div class="container d-flex justify-content-between">
        <span><i class="bi bi-telephone-fill me-1"></i> Hotline: 1900 1234</span>
        <span>Miễn phí giao hàng cho đơn từ 500k</span>
    </div>
</div>

<!-- Phần Header chính -->
<div class="header-main">
    <div class="container">
        <div class="row align-items-center">
            <!-- Logo -->
            <div class="col-md-3">
                <a href="<?= e(url('index.php')) ?>" class="brand-logo">
                    <i class="bi bi-star-fill" style="color: #f59e0b;"></i> Kiddo<span>Zone</span>
                </a>
            </div>
            
            <!-- Thanh tìm kiếm -->
            <div class="col-md-5 my-3 my-md-0">
                <form class="search-box" action="<?= e(url('index.php')) ?>" method="GET">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Tìm kiếm đồ chơi, xe cộ, búp bê...">
                </form>
            </div>
            
            <!-- Tài khoản & Giỏ hàng -->
            <div class="col-md-4 d-flex justify-content-md-end align-items-center gap-4">
                <?php if ($user): ?>
                    <div class="dropdown">
                        <a href="#" class="action-icon dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <div>
                                <div style="font-size: 11px; color: #64748b;">Xin chào</div>
                                <div><?= e($user['full_name']) ?></div>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (is_admin()): ?>
                                <li><a class="dropdown-item" href="<?= e(url('admin/index.php')) ?>">Trang Quản Trị</a></li>
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
                            <div style="font-size: 11px; color: #64748b;">Tài khoản</div>
                            <div>Đăng nhập</div>
                        </div>
                    </a>
                <?php endif; ?>
                
                <a href="#" class="action-icon cart-icon-wrapper">
                    <i class="bi bi-cart3"></i>
                    <span class="cart-badge">0</span>
                    <div class="ms-2">
                        <div style="font-size: 11px; color: #64748b;">Giỏ hàng</div>
                        <div>0 sản phẩm</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Menu danh mục -->
<div class="nav-category shadow-sm">
    <div class="container">
        <ul class="nav justify-content-center">
            <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php')) ?>"><i class="bi bi-house-door me-1"></i> Trang chủ</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Đồ chơi giáo dục</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Lắp ráp & Xếp hình</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Búp bê & Thú bông</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Xe & Mô hình</a></li>
            <li class="nav-item"><a class="nav-link text-danger fw-bold" href="#"><i class="bi bi-fire"></i> Khuyến mãi</a></li>
        </ul>
    </div>
</div>

<!-- Vùng chứa nội dung chính -->
<main class="container py-4">
    <!-- Hiển thị thông báo (flash message) -->
    <?php foreach (flash_pull() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show">
            <?= e($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>
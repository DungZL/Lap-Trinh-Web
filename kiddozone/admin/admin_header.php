
<?php
$pageTitle = 'Admin KiddoZone';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | KiddoZone Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/kiddozone/assets/css/header-footer.css">
<link rel="stylesheet" href="/kiddozone/assets/css/shop.css">
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
   
<style>
   body {
    background: #fff7e6;
    color: #24324b;
font-family: 'Nunito', system-ui, sans-serif;}

    /* Sidebar Admin */
    .admin-sidebar {
        width: 250px;
        min-height: 100vh;
       background: #fff0bd;
        position: fixed;
        top: 0;
        left: 0;
        padding: 24px 15px;
        border-right: 1px solid #e5e7eb;
        box-shadow: 2px 0 12px rgba(0, 0, 0, 0.03);
    }

    /* Logo KiddoZone */
    .admin-sidebar a.admin-logo {
        color: #2476f2 !important;
        font-weight: 800;
        padding: 10px 12px;
        margin-bottom: 25px;
    }

    /* Menu */
    .admin-sidebar a {
        display: block;
        color: #24324b;
        text-decoration: none;
        padding: 13px 15px;
        border-radius: 12px;
        margin-bottom: 7px;
        font-weight: 500;
        transition: 0.2s;
    }

    .admin-sidebar a i {
        color: #2476f2;
    }

    .admin-sidebar a:hover {
        background: #fff0bd;
        color: #175bc2;
    }

    .admin-sidebar a:hover i {
        color: #175bc2;
    }

    /* Nội dung Admin */
    .admin-content {
        margin-left: 250px;
        padding: 30px;
    }

    /* Card thống kê */
    .admin-content .card {
        border: 1px solid #e9edf5;
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    }

    /* Nút Thêm danh mục và Lưu danh mục */
.admin-content .btn-primary {
    background: #1F6FEB;
    border: 1px solid #1F6FEB;
    color: #ffffff;
    border-radius: 12px;
    font-weight: 700;
    padding: 10px 20px;
}

/* Khi di chuột vào nút */
.admin-content .btn-primary:hover {
    background: #1859BE;
    border-color: #1859BE;
    color: #ffffff;
}

/* Khi nhấn vào nút */
.admin-content .btn-primary:active {
    background: #144A9E;
    border-color: #144A9E;
    color: #ffffff;
}

    /* Responsive */
    @media (max-width: 768px) {
        .admin-sidebar {
            position: static;
            width: 100%;
            min-height: auto;
        }

        .admin-content {
            margin-left: 0;
            padding: 15px;
        }
    }
    .admin-sidebar .kz-brand {
    display: block;
    color: #2476f2;
    font-size: 26px;
    font-weight: 800;
    text-decoration: none;
    letter-spacing: -0.5px;
    padding: 10px 12px;
    margin-bottom: 25px;
}

.admin-sidebar .kz-brand:hover {
    color: #2476f2;
    background: transparent;
}
/* Giao diện thẻ thống kê Admin */
.admin-content .card {
    background: #ffffff;
    border: 1px solid #ffe4a3;
    border-radius: 20px;
    box-shadow: 0 5px 15px rgba(180, 130, 30, 0.08);
}

/* Bo tròn các mục menu */
.admin-sidebar a {
    border-radius: 14px;
}

/* Hiệu ứng khi di chuột vào menu */
.admin-sidebar a:not(.admin-logo):hover {
    background: #ffffff;
    color: #2476f2;
}

/* Logo trang chủ KiddoZone */
.admin-sidebar .admin-logo {
    display: block;
    width: fit-content;
    font-family: 'Fredoka', sans-serif;
    font-weight: 600;
    font-size: 1.6rem;
    color: #1F6FEB !important;

    background: transparent !important;
    border: none;
    border-radius: 0;
    box-shadow: none;

    padding: 10px 12px;
    margin-bottom: 25px;
    text-decoration: none;
}

.admin-sidebar .admin-logo:hover {
    color: #1F6FEB !important;
    background: transparent !important;
}
</style>

</head>
<body>

<div class="admin-sidebar">
<a class="admin-logo" href="/kiddozone/index.php">
    KiddoZone
</a>
    <a href="/kiddozone/admin/index.php">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
    </a>

    <a href="/kiddozone/admin/categories/">
        <i class="bi bi-folder me-2"></i> Danh mục
    </a>

    <a href="/kiddozone/admin/products/">
        <i class="bi bi-box me-2"></i> Sản phẩm
    </a>

    <a href="/kiddozone/admin/orders/">
        <i class="bi bi-cart me-2"></i> Đơn hàng
    </a>

    <a href="/kiddozone/admin/users/">
        <i class="bi bi-people me-2"></i> Người dùng
    </a>

    <a href="/kiddozone/admin/reviews/">
        <i class="bi bi-star me-2"></i> Đánh giá
    </a>

    <hr class="text-white">

    <a href="/kiddozone/index.php">
        <i class="bi bi-house me-2"></i> Xem website
    </a>
</div>

<main class="admin-content">

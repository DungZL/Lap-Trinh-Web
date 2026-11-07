
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

    <style>
        body {
            background: #f4f6f9;
        }

        .admin-sidebar {
            width: 250px;
            min-height: 100vh;
            background: #17263d;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px;
        }

        .admin-sidebar a {
            display: block;
            color: #dce5f0;
            text-decoration: none;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 5px;
        }

        .admin-sidebar a:hover {
            background: #304a6e;
            color: white;
        }

        .admin-content {
            margin-left: 250px;
            padding: 30px;
        }

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
    </style>
</head>
<body>

<div class="admin-sidebar">
    <h4 class="text-white fw-bold mb-4">
        <i class="bi bi-box-seam"></i> KiddoZone
    </h4>

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

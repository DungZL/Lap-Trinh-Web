<?php
// Phần <head> dùng chung cho trang khách và trang quản trị.
// Biến có thể đặt trước khi require: $pageTitle, $titleSuffix, $extraHead (chuỗi HTML).
$titleSuffix = $titleSuffix ?? 'KiddoZone';
$cssVersion  = @filemtime(dirname(__DIR__) . '/assets/css/app.css') ?: 1;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#ffc93c">
    <title><?= e($pageTitle ?? 'KiddoZone') ?> | <?= e($titleSuffix) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg"' . substr(mascot_svg(''), 4)) ?>">

    <!-- Chọn chế độ sáng/tối trước khi vẽ trang để không bị nháy màu. -->
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('kz-theme');
                var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (dark) { document.documentElement.classList.add('dark'); }
            } catch (e) { /* trình duyệt chặn localStorage: dùng chế độ sáng */ }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Bộ icon Phosphor: chỉ dùng 2 kiểu ph-bold (đậm) và ph-fill (tô đặc) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=<?= (int)$cssVersion ?>">
    <?= $extraHead ?? '' ?>
</head>

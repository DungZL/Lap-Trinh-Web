<?php
require_once __DIR__ . '/includes/init.php';

// Đăng xuất chỉ nhận POST kèm token CSRF (không dùng link GET).
if (!is_post()) {
    redirect('index.php');
}
csrf_verify();
logout_user();
session_start(); // phiên mới để hiện thông báo
flash('success', 'Bạn đã đăng xuất.');
redirect('login.php');

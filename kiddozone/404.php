<?php
// Trang 404 thân thiện. .htaccess chuyển mọi đường dẫn không tồn tại về đây.
require_once __DIR__ . '/includes/init.php';

http_response_code(404);
$pageTitle = 'Không tìm thấy trang';
require __DIR__ . '/includes/header.php';
render_not_found('Ối! Trang này đi chơi mất rồi', 'Đường dẫn có thể đã thay đổi hoặc không còn tồn tại. Bé thử tìm món đồ chơi khác nhé!');
require __DIR__ . '/includes/footer.php';


<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

// Chỉ chấp nhận yêu cầu POST
if (!is_post()) {
    http_response_code(405);
    exit('Phương thức không được hỗ trợ.');
}

// Kiểm tra CSRF
csrf_verify();

// Kiểm tra ID sản phẩm
$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    flash('danger', 'ID sản phẩm không hợp lệ.');
    redirect('admin/products/index.php');
}

try {
    // Kiểm tra sản phẩm tồn tại
    $product = db_query(
        'SELECT id, name FROM products WHERE id = ?',
        [$id]
    )->fetch();

    if (!$product) {
        flash('danger', 'Không tìm thấy sản phẩm.');
        redirect('admin/products/index.php');
    }

    // Không xóa sản phẩm đã có trong đơn hàng
    $orderCount = (int) db_query(
        'SELECT COUNT(*) FROM order_items
         WHERE product_id = ?',
        [$id]
    )->fetchColumn();

    if ($orderCount > 0) {
        flash(
            'warning',
            'Sản phẩm đã có đơn hàng. Hãy chuyển sang trạng thái Ẩn thay vì xóa.'
        );
        redirect('admin/products/index.php');
    }

    // Lấy danh sách ảnh trước khi xóa
    $images = db_query(
        'SELECT image_path FROM product_images
         WHERE product_id = ?',
        [$id]
    )->fetchAll();

    db()->beginTransaction();

    // Các bảng liên quan sử dụng ON DELETE CASCADE
    db_query(
        'DELETE FROM products WHERE id = ?',
        [$id]
    );

    db()->commit();

    // Chỉ xóa file upload do ứng dụng quản lý
    foreach ($images as $image) {
        $filename = $image['image_path'];

        // Bỏ qua ảnh mẫu hoặc đường dẫn không an toàn
        if (!is_string($filename) ||
            basename($filename) !== $filename ||
            $filename === '.' ||
            $filename === '..') {
            continue;
        }

        $path = rtrim(UPLOAD_DIR, '/\\') .
                DIRECTORY_SEPARATOR . $filename;

        // Không xóa file nếu sản phẩm khác còn sử dụng
        $references = (int) db_query(
            'SELECT COUNT(*) FROM product_images
             WHERE image_path = ?',
            [$filename]
        )->fetchColumn();

        if ($references === 0 && is_file($path)) {
            unlink($path);
        }
    }

    flash('success', 'Đã xóa sản phẩm thành công.');

} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    error_log('Delete product error: ' . $e->getMessage());

    flash('danger', 'Không thể xóa sản phẩm.');
}

redirect('admin/products/index.php');

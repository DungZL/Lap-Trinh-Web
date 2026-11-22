<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$pageTitle = 'Thêm sản phẩm';
$error = '';

$categories = db_query(
    'SELECT id, name FROM categories
     WHERE status = 1 ORDER BY name'
)->fetchAll();

if (is_post()) {
    csrf_verify();

    $name = trim((string)($_POST['name'] ?? ''));
    $categoryId = filter_var(
        $_POST['category_id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $price = filter_var(
        $_POST['price'] ?? null,
        FILTER_VALIDATE_INT
    );
    $saleInput = trim((string)($_POST['sale_price'] ?? ''));
    $salePrice = $saleInput === '' ? null :
        filter_var($saleInput, FILTER_VALIDATE_INT);
    $stock = filter_var(
        $_POST['stock'] ?? null,
        FILTER_VALIDATE_INT
    );
    $description = sanitize_description_html((string)($_POST['description'] ?? ''));
    $status = filter_var(
        $_POST['status'] ?? null,
        FILTER_VALIDATE_INT
    );
    $featured = isset($_POST['is_featured']) ? 1 : 0;
    $extra = product_extra_input($_POST, $price === false ? 0 : (int)$price); // độ tuổi, flash sale

    $validCategoryIds = array_map(
        'intval',
        array_column($categories, 'id')
    );

    if ($name === '' || mb_strlen($name) > 200) {
        $error = 'Tên sản phẩm phải từ 1 đến 200 ký tự.';
    } elseif (!in_array($categoryId, $validCategoryIds, true)) {
        $error = 'Vui lòng chọn danh mục hợp lệ.';
    } elseif ($price === false || $price < 0) {
        $error = 'Giá sản phẩm không hợp lệ.';
    } elseif ($salePrice === false ||
        ($salePrice !== null &&
         ($salePrice < 0 || $salePrice > $price))) {
        $error = 'Giá khuyến mãi không hợp lệ.';
    } elseif ($stock === false || $stock < 0) {
        $error = 'Số lượng tồn kho không hợp lệ.';
    } elseif (!in_array($status, [0, 1], true)) {
        $error = 'Trạng thái không hợp lệ.';
    } elseif ($extra['error'] !== null) {
        $error = $extra['error'];
    } else {
        $savedFiles = [];

        try {
            $files = $_FILES['images'] ?? null;
            $uploads = [];

            if ($files && is_array($files['name'])) {
                foreach ($files['name'] as $i => $filename) {
                    if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    $uploads[] = [
                        'name' => $filename,
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                }
            }

            if (count($uploads) > 10) {
                throw new RuntimeException(
                    'Chỉ được tải tối đa 10 ảnh.'
                );
            }

            // Lưu ảnh trước, nếu lỗi sẽ xóa ảnh đã lưu.
            foreach ($uploads as $upload) {
                $savedFiles[] = save_uploaded_image($upload);
            }

            db()->beginTransaction();

            db_query(
                'INSERT INTO products
                 (category_id, name, price, sale_price,
                  stock, description, is_featured, status,
                  age_min, age_max, flash_sale_price, flash_sale_start, flash_sale_end)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $categoryId,
                    $name,
                    $price,
                    $salePrice,
                    $stock,
                    $description,
                    $featured,
                    $status,
                    $extra['data']['age_min'],
                    $extra['data']['age_max'],
                    $extra['data']['flash_sale_price'],
                    $extra['data']['flash_sale_start'],
                    $extra['data']['flash_sale_end'],
                ]
            );

            $productId = (int)db()->lastInsertId();

            foreach ($savedFiles as $index => $filename) {
                db_query(
                    'INSERT INTO product_images
                     (product_id, image_path, is_main)
                     VALUES (?, ?, ?)',
                    [$productId, $filename, $index === 0 ? 1 : 0]
                );
            }

            db()->commit();

            flash('success', 'Thêm sản phẩm thành công!');
            redirect('admin/products/index.php');

        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            foreach ($savedFiles as $filename) {
                $path = UPLOAD_DIR . $filename;
                if (is_file($path)) {
                    unlink($path);
                }
            }

            $error = 'Không thể thêm sản phẩm: ' . $ex->getMessage();
        }
    }
}

// Giá trị hiện trên form (giữ lại dữ liệu vừa nhập khi có lỗi).
$form = [
    'name'             => (string)($_POST['name'] ?? ''),
    'category_id'      => (string)($_POST['category_id'] ?? ''),
    'price'            => (string)($_POST['price'] ?? ''),
    'sale_price'       => (string)($_POST['sale_price'] ?? ''),
    'stock'            => (int)($_POST['stock'] ?? 0),
    'description'      => (string)($_POST['description'] ?? ''),
    'age_min'          => (int)($_POST['age_min'] ?? 0),
    'age_max'          => (int)($_POST['age_max'] ?? 12),
    'flash_sale_price' => ($_POST['flash_sale_price'] ?? '') === '' ? null : (string)$_POST['flash_sale_price'],
    'flash_sale_start' => parse_datetime_local($_POST['flash_sale_start'] ?? null),
    'flash_sale_end'   => parse_datetime_local($_POST['flash_sale_end'] ?? null),
    'status'           => (string)($_POST['status'] ?? '1'),
    'is_featured'      => isset($_POST['is_featured']),
];
$isEdit = false;
$pageSubtitle = 'Điền thông tin, tải ảnh và lưu để đăng bán';

require __DIR__ . '/../admin_header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../admin_footer.php';

<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$pageTitle = 'Chỉnh sửa sản phẩm';

// Lấy ID sản phẩm từ URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    flash('danger', 'ID sản phẩm không hợp lệ.');
    redirect('admin/products/index.php');
}

// Lấy thông tin sản phẩm
$product = db_query(
    'SELECT * FROM products WHERE id = ?',
    [$id]
)->fetch();

if (!$product) {
    flash('danger', 'Không tìm thấy sản phẩm.');
    redirect('admin/products/index.php');
}

// Lấy danh mục
$categories = db_query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll();

// Lấy các ảnh đã lưu
$images = db_query(
    'SELECT * FROM product_images
     WHERE product_id = ?
     ORDER BY is_main DESC, id ASC',
    [$id]
)->fetchAll();

$error = '';

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
    $salePrice = $saleInput === ''
        ? null
        : filter_var($saleInput, FILTER_VALIDATE_INT);

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
        $error = 'Tên sản phẩm không hợp lệ.';
    } elseif (!in_array($categoryId, $validCategoryIds, true)) {
        $error = 'Danh mục không hợp lệ.';
    } elseif ($price === false || $price < 0) {
        $error = 'Giá sản phẩm không hợp lệ.';
    } elseif (
        $salePrice === false ||
        ($salePrice !== null &&
         ($salePrice < 0 || $salePrice > $price))
    ) {
        $error = 'Giá khuyến mãi không hợp lệ.';
    } elseif ($stock === false || $stock < 0) {
        $error = 'Tồn kho không hợp lệ.';
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

            // Lấy danh sách ID ảnh người dùng muốn xóa
            $deleteImageIds = array_values(array_unique(
                array_filter(
                    array_map(
                        'intval',
                        (array)($_POST['delete_images'] ?? [])
                    ),
                    fn($imageId) => $imageId > 0
                )
            ));

            // Chỉ cho phép xóa ảnh thuộc sản phẩm đang sửa
            $imagesToDelete = array_values(array_filter(
                $images,
                fn($image) => in_array(
                    (int)$image['id'],
                    $deleteImageIds,
                    true
                )
            ));

            // Những ảnh được giữ lại
            $remainingImages = array_values(array_filter(
                $images,
                fn($image) => !in_array(
                    (int)$image['id'],
                    $deleteImageIds,
                    true
                )
            ));

            if (count($remainingImages) + count($uploads) > 10) {
                throw new RuntimeException(
                    'Mỗi sản phẩm chỉ được có tối đa 10 ảnh.'
                );
            }

            foreach ($uploads as $upload) {
                $savedFiles[] = save_uploaded_image($upload);
            }

            db()->beginTransaction();

            // Xóa những ảnh đã được đánh dấu
            foreach ($imagesToDelete as $image) {
                db_query(
                    'DELETE FROM product_images
                     WHERE id = ? AND product_id = ?',
                    [(int)$image['id'], $id]
                );
            }

            db_query(
                'UPDATE products
                 SET category_id = ?,
                     name = ?,
                     price = ?,
                     sale_price = ?,
                     stock = ?,
                     description = ?,
                     is_featured = ?,
                     status = ?,
                     age_min = ?,
                     age_max = ?,
                     flash_sale_price = ?,
                     flash_sale_start = ?,
                     flash_sale_end = ?
                 WHERE id = ?',
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
                    $id
                ]
            );

            // Kiểm tra ảnh đại diện: ưu tiên ảnh được chọn từ radio main_image_id
            $mainImageId = filter_var($_POST['main_image_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $remainingIds = array_map(fn($img) => (int)$img['id'], $remainingImages);
            $hasMain = false;

            if ($mainImageId !== false && in_array($mainImageId, $remainingIds, true)) {
                db_query('UPDATE product_images SET is_main = 0 WHERE product_id = ?', [$id]);
                db_query('UPDATE product_images SET is_main = 1 WHERE id = ? AND product_id = ?', [$mainImageId, $id]);
                $hasMain = true;
            } else {
                foreach ($remainingImages as $image) {
                    if ((int)$image['is_main'] === 1) {
                        $hasMain = true;
                        break;
                    }
                }

                // Nếu ảnh đại diện bị xóa hoặc chưa có, chọn ảnh cũ còn lại đầu tiên
                if (!$hasMain && count($remainingImages) > 0) {
                    $newMainId = (int)$remainingImages[0]['id'];

                    db_query(
                        'UPDATE product_images
                         SET is_main = 1
                         WHERE id = ? AND product_id = ?',
                        [$newMainId, $id]
                    );

                    $hasMain = true;
                }
            }

            foreach ($savedFiles as $index => $filename) {
                db_query(
                    'INSERT INTO product_images
                     (product_id, image_path, is_main)
                     VALUES (?, ?, ?)',
                    [
                        $id,
                        $filename,
                        (!$hasMain && $index === 0) ? 1 : 0
                    ]
                );
            }

            db()->commit();

            foreach ($imagesToDelete as $image) {
                $filename = (string)$image['image_path'];

                // Chỉ xử lý tên file đơn, không xóa ảnh mẫu
                if (
                    $filename === '' ||
                    $filename === '.' ||
                    $filename === '..' ||
                    basename($filename) !== $filename
                ) {
                    continue;
                }

                // Không xóa nếu sản phẩm khác vẫn sử dụng ảnh này
                $count = (int) db_query(
                    'SELECT COUNT(*) FROM product_images WHERE image_path = ?',
                    [$filename]
                )->fetchColumn();

                if ($count === 0) {
                    $filePath = rtrim(UPLOAD_DIR, '/\\')
                              . DIRECTORY_SEPARATOR . $filename;

                    if (is_file($filePath)) {
                        if (!unlink($filePath)) {
                            error_log('Không thể xóa ảnh: ' . $filePath);
                        }
                    }
                }
            }

            flash('success', 'Cập nhật sản phẩm thành công!');
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

            $error = 'Không thể cập nhật: ' . $ex->getMessage();
        }
    }

    // Giữ lại dữ liệu người dùng vừa nhập khi có lỗi
    $product['name']        = $name;
    $product['category_id'] = $categoryId !== false ? $categoryId : $product['category_id'];
    $product['price']       = preg_replace('/\D/', '', (string)($_POST['price'] ?? ''));
    $product['sale_price']  = $saleInput === '' ? null : preg_replace('/\D/', '', $saleInput);
    $product['stock']       = (int)($_POST['stock'] ?? $product['stock']);
    $product['description'] = (string)($_POST['description'] ?? '');
    $product['is_featured'] = $featured;
    $product['status']      = in_array($status, [0, 1], true) ? $status : $product['status'];
    $product = array_merge($product, $extra['data']);
    $product['flash_sale_price'] = ($_POST['flash_sale_price'] ?? '') === '' ? null : (string)$_POST['flash_sale_price'];
}

$form = [
    'name'             => (string)$product['name'],
    'category_id'      => (string)$product['category_id'],
    'price'            => (string)$product['price'],
    'sale_price'       => (string)($product['sale_price'] ?? ''),
    'stock'            => (int)$product['stock'],
    'description'      => (string)($product['description'] ?? ''),
    'age_min'          => (int)$product['age_min'],
    'age_max'          => (int)$product['age_max'],
    'flash_sale_price' => $product['flash_sale_price'],
    'flash_sale_start' => $product['flash_sale_start'],
    'flash_sale_end'   => $product['flash_sale_end'],
    'status'           => (string)$product['status'],
    'is_featured'      => (int)$product['is_featured'] === 1,
];
$isEdit = true;
$pageTitle = 'Sửa: ' . $product['name'];
$pageActions = '<a href="' . e(url('product.php?id=' . $id)) . '" target="_blank" rel="noopener" class="btn btn-ghost"><i class="ph-bold ph-eye" aria-hidden="true"></i> Xem trên web</a>';

require __DIR__ . '/../admin_header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../admin_footer.php';

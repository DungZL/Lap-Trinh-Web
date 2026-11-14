
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

    $description = trim((string)($_POST['description'] ?? ''));

    $status = filter_var(
        $_POST['status'] ?? null,
        FILTER_VALIDATE_INT
    );

    $featured = isset($_POST['is_featured']) ? 1 : 0;

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

if (count($remainingImages) + count($uploads) > 10) {                throw new RuntimeException(
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
                     status = ?
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
                    $id
                ]
            );

         
// Kiểm tra ảnh đại diện còn tồn tại hay không
$hasMain = false;

foreach ($remainingImages as $image) {
    if ((int)$image['is_main'] === 1) {
        $hasMain = true;
        break;
    }
}

// Nếu ảnh đại diện bị xóa, chọn ảnh cũ còn lại đầu tiên
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
}

require __DIR__ . '/../admin_header.php';
?>

<h2 class="fw-bold mb-4">Chỉnh sửa sản phẩm</h2>
<?php if ($error): ?>
    <div class="alert alert-danger" id="productError">
        <?= e($error) ?>
    </div>
<?php endif; ?>
<div class="card shadow-sm border-0">
    <div class="card-body">

        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Tên sản phẩm</label>
                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="<?= e($product['name']) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Danh mục</label>
                <select name="category_id" class="form-select">
                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= (int)$category['id'] ?>"
                            <?= (int)$product['category_id'] ===
                                (int)$category['id'] ? 'selected' : '' ?>
                        >
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Giá bán</label>
                    <input
                        type="text"
                        id="priceDisplay"
                        class="form-control"
                        value="<?= number_format((float)$product['price'], 0, ',', '.') ?>"
                    >
                    <input
                        type="hidden"
                        id="price"
                        name="price"
                        value="<?= e($product['price']) ?>"
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">Giá khuyến mãi</label>
                    <input
                        type="text"
                        id="salePriceDisplay"
                        class="form-control"
                        value="<?= $product['sale_price'] === null ? '' : number_format((float)$product['sale_price'], 0, ',', '.') ?>"
                    >
                    <input
                        type="hidden"
                        id="salePrice"
                        name="sale_price"
                        value="<?= e($product['sale_price'] ?? '') ?>"
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tồn kho</label>
                    <input
                        type="number"
                        name="stock"
                        min="0"
                        class="form-control"
                        value="<?= (int)$product['stock'] ?>"
                    >
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Mô tả sản phẩm</label>
                <textarea
                    id="description"
                    name="description"
                    class="form-control"
                    rows="8"
                ><?= e($product['description'] ?? '') ?></textarea>
            </div>

<div class="mb-4">
    <h5 class="mb-3">Hình ảnh hiện tại</h5>

    <div class="product-preview-grid"
         style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;">

        <?php foreach ($images as $image): ?>
            <?php
                $path = $image['image_path'];
                $imageUrl = str_starts_with($path, 'assets/')
                    ? url($path)
                    : url('uploads/' . $path);
            ?>

            <div class="product-preview-item position-relative"
                 style="aspect-ratio:1/1;overflow:hidden;">

                <img
                    src="<?= e($imageUrl) ?>"
                    alt="Ảnh sản phẩm"
                    style="width:100%;height:100%;object-fit:cover;"
                >

                <button
                    type="button"
                    class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1"
                    onclick="removeOldImage(<?= (int)$image['id'] ?>, this)"
                    title="Xóa ảnh"
                >
                    &times;
                </button>

                <?php if ((int)$image['is_main'] === 1): ?>
                    <span class="badge bg-primary position-absolute bottom-0 start-0 m-2">
                        Ảnh đại diện
                    </span>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>

    </div>
</div>

<div class="mb-4">
    <h5>Thêm hình ảnh mới</h5>

    <div id="uploadZone" class="product-upload-zone">
        <p class="mb-1">Kéo thả ảnh vào đây</p>
        <small>Hoặc nhấn vào khung để chọn ảnh</small>
    </div>

    <input
        type="file"
        id="productImages"
        name="images[]"
        accept="image/jpeg,image/png,image/webp"
        multiple
        hidden
    >

    <div id="imagePreview" class="product-preview-grid mt-3"></div>

    <small id="imageCount" class="text-muted">
        Đã chọn 0/10 ảnh
    </small>
</div>


            <div class="mb-3">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="1"
                        <?= (int)$product['status'] === 1 ? 'selected' : '' ?>>
                        Hiển thị
                    </option>
                    <option value="0"
                        <?= (int)$product['status'] === 0 ? 'selected' : '' ?>>
                        Ẩn
                    </option>
                </select>
            </div>

            <div class="form-check mb-3">
                <input
                    type="checkbox"
                    name="is_featured"
                    class="form-check-input"
                    id="featured"
                    value="1"
                    <?= (int)$product['is_featured'] === 1 ? 'checked' : '' ?>
                >
                <label class="form-check-label" for="featured">
                    Sản phẩm nổi bật
                </label>
            </div>

      <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i>
                Cập nhật sản phẩm
            </button>

            <a href="index.php" class="btn btn-secondary">
                Quay lại
            </a>
        </form>
    </div>
</div>

<script src="<?= e(url('assets/vendor/tinymce/tinymce.min.js')) ?>"></script>
<script>
tinymce.init({
    selector: '#description',
    height: 350,
    menubar: false,
    license_key: 'gpl',
    plugins: 'lists link table code wordcount',
    toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link table | code'
});

document.addEventListener('DOMContentLoaded', function () {
    function formatMoney(displayId, hiddenId) {
        const display = document.getElementById(displayId);
        const hidden = document.getElementById(hiddenId);

        display.addEventListener('input', function () {
            const digits = display.value.replace(/\D/g, '');
            hidden.value = digits;
            display.value = digits
                ? digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
                : '';
        });
    }

    formatMoney('priceDisplay', 'price');
    formatMoney('salePriceDisplay', 'salePrice');
});
</script>

<script>
function removeOldImage(imageId, button) {
    if (!confirm('Bạn có chắc muốn xóa ảnh này?')) {
        return;
    }

    const form = button.closest('form');

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'delete_images[]';
    input.value = imageId;

    form.appendChild(input);

    button.closest('.product-preview-item').remove();
}
</script>

<script src="<?= e(url('assets/js/product-upload.js')) ?>?v=2"></script>
<?php require __DIR__ . '/../admin_footer.php'; ?>


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
                  stock, description, is_featured, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $categoryId,
                    $name,
                    $price,
                    $salePrice,
                    $stock,
                    $description,
                    $featured,
                    $status
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

require __DIR__ . '/../admin_header.php';
?>

<h2 class="fw-bold mb-4">Thêm sản phẩm mới</h2>

<?php if ($error): ?>
    <div id="productError" class="alert alert-danger">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
<div class="card-body">
<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label class="form-label">Tên sản phẩm</label>
        <input type="text" name="name" class="form-control"
               maxlength="200" value="<?= old('name') ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Danh mục</label>
        <select name="category_id" class="form-select" required>
            <option value="">-- Chọn danh mục --</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>"
                    <?= (string)($_POST['category_id'] ?? '') ===
                        (string)$category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

   
<div class="row g-3 mb-3">

    <!-- Giá bán -->
    <div class="col-md-4">
        <label for="priceDisplay" class="form-label">
            Giá bán (VNĐ)
        </label>

        <input
            type="text"
            id="priceDisplay"
            class="form-control money-input"
            inputmode="numeric"
            placeholder="VD: 250.000"
            value="<?= e(($_POST['price'] ?? '') === '' ? '' : number_format((int)preg_replace('/\D/', '', (string)$_POST['price']), 0, ',', '.')) ?>"
            required
        >

        <input
            type="hidden"
            id="price"
            name="price"
            value="<?= e(preg_replace('/\D/', '', (string)($_POST['price'] ?? ''))) ?>"
        >
    </div>

    <!-- Giá khuyến mãi -->
    <div class="col-md-4">
        <label for="salePriceDisplay" class="form-label">
            Giá khuyến mãi
        </label>

        <input
            type="text"
            id="salePriceDisplay"
            class="form-control money-input"
            inputmode="numeric"
            placeholder="VD: 190.000"
            value="<?= e(($_POST['sale_price'] ?? '') === '' ? '' : number_format((int)preg_replace('/\D/', '', (string)$_POST['sale_price']), 0, ',', '.')) ?>"
        >

        <input
            type="hidden"
            id="salePrice"
            name="sale_price"
            value="<?= e(preg_replace('/\D/', '', (string)($_POST['sale_price'] ?? ''))) ?>"
        >
    </div>

    <!-- Tồn kho -->
    <div class="col-md-4">
        <label for="stock" class="form-label">
            Tồn kho
        </label>

        <input
            type="number"
            id="stock"
            name="stock"
            min="0"
            class="form-control"
            value="<?= e($_POST['stock'] ?? '0') ?>"
            required
        >
    </div>

</div>


    <div class="mb-3">
        <label class="form-label">Mô tả sản phẩm</label>
        <textarea id="description" name="description"
                  class="form-control" rows="8"><?= old('description') ?></textarea>
    </div>

    
<div class="mb-4">
    <label class="form-label fw-bold">Hình ảnh sản phẩm</label>

    <div id="uploadZone" class="product-upload-zone">
        <div class="fs-1">📷</div>
        <strong>Kéo thả ảnh vào đây</strong>
        <p class="text-muted mb-0">
            Hoặc nhấn để chọn ảnh từ máy
        </p>
        <small>JPG, PNG, WEBP - Tối đa 10 ảnh, 2MB/ảnh</small>
    </div>

  
<input
    type="file"
    id="productImages"
    name="images[]"
    accept="image/jpeg,image/png,image/webp"
    multiple
    hidden
>


<div id="imagePreview"
     class="product-preview-grid"
     style="display:grid !important;
            grid-template-columns:repeat(3,minmax(0,1fr)) !important;
            gap:12px !important;
            width:100%;">
</div>
    <small id="imageCount" class="text-muted">
        Đã chọn 0/10 ảnh
    </small>
</div>


    <div class="mb-3">
        <label class="form-label">Trạng thái</label>
        <select name="status" class="form-select">
            <option value="1">Hiển thị</option>
            <option value="0"
                <?= ($_POST['status'] ?? '') === '0' ? 'selected' : '' ?>>
                Ẩn
            </option>
        </select>
    </div>

    <div class="form-check mb-3">
        <input type="checkbox" name="is_featured" value="1"
               class="form-check-input" id="featured"
               <?= isset($_POST['is_featured']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="featured">
            Sản phẩm nổi bật
        </label>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-save"></i> Lưu sản phẩm
    </button>

    <a href="index.php" class="btn btn-secondary">
        Quay lại
    </a>
</form>
</div>
</div>

<!-- TinyMCE Editor -->
<script src="<?= e(url('assets/vendor/tinymce/tinymce.min.js')) ?>"></script>

<script>
tinymce.init({
    selector: '#description',
    height: 400,
    menubar: false,
    plugins: 'lists link table code wordcount',
    toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link table | code',
    branding: false,
    promotion: false,
    license_key: 'gpl'
});
</script>
<script src="<?= e(url('assets/js/product-upload.js')) ?>?v=5"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function setupMoneyInput(displayId, hiddenId) {
        const display = document.getElementById(displayId);
        const hidden = document.getElementById(hiddenId);

        if (!display || !hidden) return;

        display.addEventListener('input', function () {
            const digits = display.value.replace(/\D/g, '');

            hidden.value = digits;

            display.value = digits
                ? digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
                : '';
        });
    }

    setupMoneyInput('priceDisplay', 'price');
    setupMoneyInput('salePriceDisplay', 'salePrice');
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector(
        'form[enctype="multipart/form-data"]'
    );

    if (!form) return;

    form.addEventListener('submit', function (event) {
        // Đồng bộ TinyMCE về textarea
        if (window.tinymce) {
            tinymce.triggerSave();
        }

        const price = document.getElementById('price');
        const salePrice = document.getElementById('salePrice');

        if (!price || !salePrice) return;

        const priceValue = Number(price.value);
        const saleValue = salePrice.value === ''
            ? null
            : Number(salePrice.value);

        let error = '';

        if (price.value === '' ||
            !Number.isSafeInteger(priceValue) ||
            priceValue < 0) {
            error = 'Giá bán không hợp lệ.';
        } else if (
            saleValue !== null &&
            (!Number.isSafeInteger(saleValue) ||
             saleValue < 0 ||
             saleValue > priceValue)
        ) {
            error = 'Giá khuyến mãi không được lớn hơn giá bán.';
        }

        if (error) {
            event.preventDefault();
            alert(error);
        }
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const errorBox = document.getElementById('productError');

    if (!errorBox) return;

    const fields = document.querySelectorAll(
        '#priceDisplay, #salePriceDisplay'
    );

    fields.forEach(field => {
        field.addEventListener('input', function () {
            errorBox.style.display = 'none';
        });
    });
});
</script>

<?php require __DIR__ . '/../admin_footer.php'; ?>

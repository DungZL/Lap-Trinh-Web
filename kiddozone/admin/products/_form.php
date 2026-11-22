<?php
// Form thêm / sửa sản phẩm dùng chung cho create.php và edit.php.
// Biến cần có: $form (giá trị các ô), $categories, $error, $isEdit, $images (ảnh hiện có, chỉ khi sửa).
if (!isset($form)) {
    exit;
}
$images = $images ?? [];
$money = fn($v) => ($d = preg_replace('/\D/', '', (string)$v)) === '' ? '' : number_format((int)$d, 0, ',', '.');
$digits = fn($v) => preg_replace('/\D/', '', (string)$v);
$dtLocal = fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string)$v)) : '';
?>
<?php if ($error): ?>
    <div id="productError" class="alert alert-danger mb-6" role="alert"><i class="ph-fill ph-warning-circle text-lg" aria-hidden="true"></i> <?= e($error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3" id="productForm">
    <?= csrf_field() ?>

    <div class="space-y-6 xl:col-span-2">
        <section class="card p-6">
            <h2 class="mb-5 font-display text-xl font-semibold">Thông tin cơ bản</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="name">Tên sản phẩm</label>
                    <input type="text" id="name" name="name" class="input" maxlength="200" value="<?= e($form['name']) ?>" required>
                </div>
                <div>
                    <label class="label" for="category_id">Danh mục</label>
                    <select id="category_id" name="category_id" class="select" required>
                        <option value="">-- Chọn danh mục --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int)$category['id'] ?>" <?= (string)$form['category_id'] === (string)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label" for="stock">Tồn kho</label>
                    <input type="number" id="stock" name="stock" min="0" class="input" value="<?= (int)$form['stock'] ?>" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="description">Mô tả sản phẩm</label>
                    <textarea id="description" name="description" class="textarea" rows="8"><?= e($form['description']) ?></textarea>
                </div>
            </div>
        </section>

        <section class="card p-6">
            <h2 class="mb-5 font-display text-xl font-semibold">Hình ảnh</h2>
            <?php if ($isEdit && $images): ?>
                <p class="mb-3 text-sm font-bold text-muted">Ảnh hiện tại: chọn ảnh đại diện hoặc bấm × để xóa.</p>
                <div class="mb-6 grid grid-cols-3 gap-3 sm:grid-cols-5">
                    <?php foreach ($images as $image): ?>
                        <div class="product-preview-item relative aspect-square overflow-hidden rounded-2xl ring-1 ring-ink-100 dark:ring-ink-700" data-existing-image>
                            <img src="<?= e(product_image_url($image['image_path'])) ?>" alt="Ảnh sản phẩm" class="size-full object-cover">
                            <button type="button" class="absolute top-1.5 right-1.5 grid size-7 place-items-center rounded-full bg-coral-600 text-white shadow hover:bg-coral-500"
                                    onclick="removeOldImage(<?= (int)$image['id'] ?>, this)" title="Xóa ảnh" aria-label="Xóa ảnh"><i class="ph-bold ph-x" aria-hidden="true"></i></button>
                            <label class="absolute inset-x-1.5 bottom-1.5 flex cursor-pointer items-center justify-center gap-1 rounded-full bg-ink-900/75 py-1 text-[11px] font-bold text-white backdrop-blur has-checked:bg-sky-500">
                                <input type="radio" name="main_image_id" value="<?= (int)$image['id'] ?>" class="size-3 accent-white" <?= (int)$image['is_main'] === 1 ? 'checked' : '' ?>> Đại diện
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div id="uploadZone" class="flex cursor-pointer flex-col items-center justify-center rounded-3xl border-2 border-dashed border-sky-200 bg-sky-50/60 px-6 py-10 text-center transition hover:border-sky-400 hover:bg-sky-50 dark:border-sky-500/30 dark:bg-sky-500/5 [&.dragover]:scale-[1.01] [&.dragover]:border-sky-500 [&.dragover]:bg-sky-100">
                <span class="grid size-16 place-items-center rounded-3xl bg-white text-3xl text-sky-600 shadow-soft dark:bg-ink-800"><i class="ph-fill ph-images" aria-hidden="true"></i></span>
                <p class="mt-3 font-extrabold"><?= $isEdit ? 'Thêm ảnh mới: kéo thả vào đây' : 'Kéo thả ảnh vào đây' ?></p>
                <p class="text-sm text-muted">hoặc bấm để chọn ảnh từ máy · JPG, PNG, WEBP, AVIF · tối đa 10 ảnh (ảnh &gt; 2MB sẽ tự động nén)</p>
            </div>
            <input type="file" id="productImages" name="images[]" accept="image/jpeg,image/png,image/webp,image/avif" multiple hidden>
            <div id="imagePreview" class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-5"></div>
            <small id="imageCount" class="mt-2 block text-xs font-bold text-muted">Đã chọn 0/10 ảnh</small>
        </section>
    </div>

    <div class="space-y-6">
        <section class="card p-6">
            <h2 class="mb-5 font-display text-xl font-semibold">Giá bán</h2>
            <div class="space-y-4">
                <div>
                    <label for="priceDisplay" class="label">Giá bán (VNĐ)</label>
                    <input type="text" id="priceDisplay" class="input" inputmode="numeric" placeholder="VD: 250.000" value="<?= e($money($form['price'])) ?>" required>
                    <input type="hidden" id="price" name="price" value="<?= e($digits($form['price'])) ?>">
                </div>
                <div>
                    <label for="salePriceDisplay" class="label">Giá khuyến mãi <span class="font-normal text-muted">(không bắt buộc)</span></label>
                    <input type="text" id="salePriceDisplay" class="input" inputmode="numeric" placeholder="VD: 190.000" value="<?= e($money($form['sale_price'])) ?>">
                    <input type="hidden" id="salePrice" name="sale_price" value="<?= e($digits($form['sale_price'])) ?>">
                </div>
            </div>
        </section>

        <!-- Flash sale (#5) -->
        <section class="card p-6" x-data="{ on: <?= $form['flash_sale_price'] !== null && $form['flash_sale_price'] !== '' ? 'true' : 'false' ?> }">
            <label class="flex cursor-pointer items-center justify-between">
                <span class="flex items-center gap-2 font-display text-xl font-semibold"><i class="ph-fill ph-lightning text-sun-500" aria-hidden="true"></i> Flash sale</span>
                <span class="relative inline-flex">
                    <input type="checkbox" class="peer sr-only" x-model="on">
                    <span class="h-6 w-11 rounded-full bg-ink-200 transition peer-checked:bg-sun-400 dark:bg-ink-700"></span>
                    <span class="absolute top-1 left-1 size-4 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                </span>
            </label>
            <div class="mt-4 space-y-4" x-show="on" x-transition>
                <div>
                    <label for="flashDisplay" class="label">Giá flash sale</label>
                    <input type="text" id="flashDisplay" class="input" inputmode="numeric" placeholder="VD: 99.000" value="<?= e($money($form['flash_sale_price'])) ?>">
                    <input type="hidden" id="flashPrice" name="flash_sale_price" :disabled="!on" value="<?= e($digits($form['flash_sale_price'])) ?>">
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div><label class="label" for="flash_sale_start">Bắt đầu</label><input type="datetime-local" id="flash_sale_start" name="flash_sale_start" class="input" :disabled="!on" value="<?= e($dtLocal($form['flash_sale_start'])) ?>"></div>
                    <div><label class="label" for="flash_sale_end">Kết thúc</label><input type="datetime-local" id="flash_sale_end" name="flash_sale_end" class="input" :disabled="!on" value="<?= e($dtLocal($form['flash_sale_end'])) ?>"></div>
                </div>
                <p class="help">Trong khoảng thời gian này, giá flash sale được ưu tiên và sản phẩm hiện ở mục Flash Sale trang chủ.</p>
            </div>
        </section>

        <!-- Độ tuổi (#3) -->
        <section class="card p-6" x-data="{ min: <?= (int)$form['age_min'] ?>, max: <?= (int)$form['age_max'] ?> }">
            <h2 class="mb-4 flex items-center gap-2 font-display text-xl font-semibold"><i class="ph-fill ph-baby text-age" aria-hidden="true"></i> Độ tuổi phù hợp</h2>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="age_min">Từ (tuổi)</label><input type="number" id="age_min" name="age_min" min="0" max="12" class="input" x-model.number="min" @input="if (min > max) max = min"></div>
                <div><label class="label" for="age_max">Đến (tuổi)</label><input type="number" id="age_max" name="age_max" min="0" max="12" class="input" x-model.number="max" @input="if (max < min) min = max"></div>
            </div>
            <p class="mt-3 rounded-2xl bg-grape-50 p-3 text-center text-sm font-extrabold text-grape-600 dark:bg-grape-500/10 dark:text-grape-300" x-text="(min <= 0 && max >= 12) ? 'Mọi lứa tuổi' : (max >= 12 ? 'Từ ' + min + ' tuổi' : min + ' - ' + max + ' tuổi')"></p>
        </section>

        <section class="card space-y-4 p-6">
            <div>
                <label class="label" for="status">Trạng thái</label>
                <select id="status" name="status" class="select">
                    <option value="1" data-icon="ph-eye" <?= (string)$form['status'] === '1' ? 'selected' : '' ?>>Hiển thị</option>
                    <option value="0" data-icon="ph-eye-slash" <?= (string)$form['status'] === '0' ? 'selected' : '' ?>>Ẩn</option>
                </select>
            </div>
            <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-sun-50 p-3 font-bold dark:bg-sun-500/10">
                <input type="checkbox" name="is_featured" value="1" class="checkbox" <?= $form['is_featured'] ? 'checked' : '' ?>>
                <span><i class="ph-fill ph-star text-sun-500" aria-hidden="true"></i> Sản phẩm nổi bật (hiện ở trang chủ)</span>
            </label>
            <button type="submit" class="btn btn-primary btn-lg w-full"><i class="ph-bold ph-floppy-disk" aria-hidden="true"></i> <?= $isEdit ? 'Cập nhật sản phẩm' : 'Lưu sản phẩm' ?></button>
            <a href="<?= e(url('admin/products/index.php')) ?>" class="btn btn-ghost w-full">Quay lại</a>
        </section>
    </div>
</form>

<script src="<?= e(url('assets/vendor/tinymce/tinymce.min.js')) ?>"></script>
<script>
tinymce.init({
    selector: '#description',
    height: 380,
    menubar: false,
    branding: false,
    promotion: false,
    license_key: 'gpl',
    skin: document.documentElement.classList.contains('dark') ? 'oxide-dark' : 'oxide',
    content_css: document.documentElement.classList.contains('dark') ? 'dark' : 'default',
    plugins: 'lists link table code wordcount',
    toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link table | code'
});

document.addEventListener('DOMContentLoaded', function () {
    // Ô tiền: hiển thị có dấu chấm, ô ẩn chỉ giữ chữ số để gửi lên máy chủ.
    function formatMoney(displayId, hiddenId) {
        const display = document.getElementById(displayId);
        const hidden = document.getElementById(hiddenId);
        if (!display || !hidden) return;
        display.addEventListener('input', function () {
            const digits = display.value.replace(/\D/g, '');
            hidden.value = digits;
            display.value = digits ? digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
        });
    }
    formatMoney('priceDisplay', 'price');
    formatMoney('salePriceDisplay', 'salePrice');
    formatMoney('flashDisplay', 'flashPrice');

    const form = document.getElementById('productForm');
    form.addEventListener('submit', function (event) {
        if (window.tinymce) { tinymce.triggerSave(); }
        const price = Number(document.getElementById('price').value);
        const saleRaw = document.getElementById('salePrice').value;
        let error = '';
        if (document.getElementById('price').value === '' || !Number.isSafeInteger(price) || price < 0) {
            error = 'Giá bán không hợp lệ.';
        } else if (saleRaw !== '' && Number(saleRaw) > price) {
            error = 'Giá khuyến mãi không được lớn hơn giá bán.';
        }
        if (error) { event.preventDefault(); alert(error); }
    });

    const errorBox = document.getElementById('productError');
    if (errorBox) {
        document.querySelectorAll('#priceDisplay, #salePriceDisplay').forEach(function (field) {
            field.addEventListener('input', function () { errorBox.style.display = 'none'; });
        });
    }
});

// Đánh dấu xóa ảnh cũ (gửi delete_images[] khi lưu).
function removeOldImage(imageId, button) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'delete_images[]';
    input.value = imageId;
    document.getElementById('productForm').appendChild(input);
    button.closest('.product-preview-item').remove();
    document.dispatchEvent(new CustomEvent('kz:existing-images-changed'));
}
</script>
<script src="<?= e(url('assets/js/product-upload.js')) ?>?v=<?= (int)(@filemtime(dirname(__DIR__, 2) . '/assets/js/product-upload.js') ?: 8) ?>"></script>

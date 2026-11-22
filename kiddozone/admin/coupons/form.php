<?php
// Thêm / sửa mã giảm giá (#6). Có ?id= thì là sửa.
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$coupon = null;
if ($id !== false) {
    $coupon = db_query('SELECT * FROM coupons WHERE id = ?', [$id])->fetch();
    if (!$coupon) {
        flash('danger', 'Không tìm thấy mã giảm giá.');
        redirect('admin/coupons/index.php');
    }
}
$isEdit = $coupon !== null;

$f = $coupon ?? [
    'code' => '', 'description' => '', 'type' => 'percent', 'value' => '', 'min_order' => 0, 'max_discount' => null,
    'usage_limit' => null, 'starts_at' => null, 'ends_at' => null, 'status' => 1,
];
$error = '';

if (is_post()) {
    csrf_verify();
    $digits = fn($k) => preg_replace('/\D/', '', (string)($_POST[$k] ?? ''));

    $f = [
        'code'         => strtoupper(trim((string)($_POST['code'] ?? ''))),
        'description'  => trim((string)($_POST['description'] ?? '')),
        'type'         => (string)($_POST['type'] ?? ''),
        'value'        => $digits('value'),
        'min_order'    => $digits('min_order') === '' ? 0 : (int)$digits('min_order'),
        'max_discount' => $digits('max_discount') === '' ? null : (int)$digits('max_discount'),
        'usage_limit'  => $digits('usage_limit') === '' ? null : (int)$digits('usage_limit'),
        'starts_at'    => parse_datetime_local($_POST['starts_at'] ?? null),
        'ends_at'      => parse_datetime_local($_POST['ends_at'] ?? null),
        'status'       => isset($_POST['status']) ? 1 : 0,
    ];
    $value = $f['value'] === '' ? 0 : (int)$f['value'];

    if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $f['code'])) {
        $error = 'Mã chỉ gồm chữ in hoa, số, gạch ngang hoặc gạch dưới (3 - 30 ký tự).';
    } elseif (mb_strlen($f['description']) > 255) {
        $error = 'Mô tả tối đa 255 ký tự.';
    } elseif (!in_array($f['type'], ['percent', 'fixed'], true)) {
        $error = 'Loại giảm giá không hợp lệ.';
    } elseif ($value <= 0 || ($f['type'] === 'percent' && $value > 100)) {
        $error = $f['type'] === 'percent' ? 'Phần trăm giảm phải từ 1 đến 100.' : 'Số tiền giảm phải lớn hơn 0.';
    } elseif ($f['starts_at'] && $f['ends_at'] && strtotime($f['ends_at']) <= strtotime($f['starts_at'])) {
        $error = 'Thời gian kết thúc phải sau thời gian bắt đầu.';
    } elseif (db_query('SELECT 1 FROM coupons WHERE code = ? AND id <> ?', [$f['code'], $isEdit ? (int)$id : 0])->fetch()) {
        $error = 'Mã này đã tồn tại.';
    } else {
        $params = [$f['code'], $f['description'] !== '' ? $f['description'] : null, $f['type'], $value, $f['min_order'],
                   $f['type'] === 'percent' ? $f['max_discount'] : null, $f['usage_limit'], $f['starts_at'], $f['ends_at'], $f['status']];
        if ($isEdit) {
            db_query(
                'UPDATE coupons SET code = ?, description = ?, type = ?, value = ?, min_order = ?, max_discount = ?,
                        usage_limit = ?, starts_at = ?, ends_at = ?, status = ? WHERE id = ?',
                array_merge($params, [(int)$id])
            );
            flash('success', 'Đã cập nhật mã ' . $f['code'] . '.');
        } else {
            db_query(
                'INSERT INTO coupons (code, description, type, value, min_order, max_discount, usage_limit, starts_at, ends_at, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $params
            );
            flash('success', 'Đã tạo mã ' . $f['code'] . '.');
        }
        redirect('admin/coupons/index.php');
    }
}

$money = fn($v) => $v === null || $v === '' ? '' : number_format((int)$v, 0, ',', '.');
$dtLocal = fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string)$v)) : '';

$pageTitle = $isEdit ? 'Sửa mã ' . $coupon['code'] : 'Tạo mã giảm giá';
require __DIR__ . '/../admin_header.php';
?>

<form method="post" class="grid gap-6 xl:grid-cols-[1fr_22rem]" x-data="{ type: '<?= e($f['type']) ?>', code: <?= e(json_encode((string)$f['code'])) ?>, value: '<?= e((string)$f['value']) ?>' }">
    <?= csrf_field() ?>
    <div class="card space-y-4 p-6 sm:p-8">
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><i class="ph-fill ph-warning-circle text-lg" aria-hidden="true"></i> <?= e($error) ?></div><?php endif; ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="code">Mã giảm giá</label>
                <input type="text" id="code" name="code" class="input font-extrabold tracking-widest uppercase" maxlength="30" x-model="code" @input="code = code.toUpperCase().replace(/[^A-Z0-9_-]/g, '')" placeholder="VD: KIDDO10" required>
            </div>
            <div>
                <label class="label">Loại giảm</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-2xl py-2.5 text-sm font-bold ring-2 transition" :class="type === 'percent' ? 'bg-sky-50 ring-sky-400 dark:bg-sky-500/10' : 'ring-ink-100 dark:ring-ink-700'">
                        <input type="radio" name="type" value="percent" class="sr-only" x-model="type"> <i class="ph-bold ph-percent" aria-hidden="true"></i> Phần trăm
                    </label>
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-2xl py-2.5 text-sm font-bold ring-2 transition" :class="type === 'fixed' ? 'bg-sky-50 ring-sky-400 dark:bg-sky-500/10' : 'ring-ink-100 dark:ring-ink-700'">
                        <input type="radio" name="type" value="fixed" class="sr-only" x-model="type"> <i class="ph-bold ph-money" aria-hidden="true"></i> Số tiền
                    </label>
                </div>
            </div>
            <div>
                <label class="label" for="value" x-text="type === 'percent' ? 'Phần trăm giảm (1 - 100)' : 'Số tiền giảm (VNĐ)'">Giá trị giảm</label>
                <input type="text" inputmode="numeric" id="value" name="value" class="input" x-model="value" value="<?= e((string)$f['value']) ?>" required>
            </div>
            <div x-show="type === 'percent'">
                <label class="label" for="max_discount">Giảm tối đa (VNĐ, không bắt buộc)</label>
                <input type="text" inputmode="numeric" id="max_discount" name="max_discount" class="input" value="<?= e($money($f['max_discount'])) ?>" placeholder="VD: 50.000">
            </div>
            <div>
                <label class="label" for="min_order">Đơn tối thiểu (VNĐ)</label>
                <input type="text" inputmode="numeric" id="min_order" name="min_order" class="input" value="<?= e($money($f['min_order'])) ?>" placeholder="0 = không yêu cầu">
            </div>
            <div>
                <label class="label" for="usage_limit">Số lượt dùng tối đa</label>
                <input type="number" min="1" id="usage_limit" name="usage_limit" class="input" value="<?= e((string)($f['usage_limit'] ?? '')) ?>" placeholder="Để trống = không giới hạn">
            </div>
            <div><label class="label" for="starts_at">Bắt đầu (không bắt buộc)</label><input type="datetime-local" id="starts_at" name="starts_at" class="input" value="<?= e($dtLocal($f['starts_at'])) ?>"></div>
            <div><label class="label" for="ends_at">Kết thúc (không bắt buộc)</label><input type="datetime-local" id="ends_at" name="ends_at" class="input" value="<?= e($dtLocal($f['ends_at'])) ?>"></div>
            <div class="sm:col-span-2">
                <label class="label" for="description">Mô tả hiển thị cho khách</label>
                <input type="text" id="description" name="description" class="input" maxlength="255" value="<?= e((string)$f['description']) ?>" placeholder="VD: Giảm 10% tối đa 50.000đ cho đơn từ 200.000đ">
            </div>
        </div>
        <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-mint-50 p-3 font-bold dark:bg-mint-500/10">
            <input type="checkbox" name="status" value="1" class="checkbox" <?= (int)$f['status'] === 1 ? 'checked' : '' ?>> Kích hoạt mã ngay
        </label>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="ph-bold ph-floppy-disk" aria-hidden="true"></i> Lưu mã</button>
            <a href="<?= e(url('admin/coupons/index.php')) ?>" class="btn btn-ghost">Quay lại</a>
        </div>
    </div>

    <!-- Xem trước vé -->
    <aside class="xl:sticky xl:top-24 xl:self-start">
        <p class="mb-2 text-sm font-bold text-muted">Xem trước</p>
        <div class="relative overflow-hidden rounded-4xl bg-gradient-to-br from-sun-300 via-coral-300 to-grape-300 p-6 text-ink-900 shadow-pop">
            <div class="bg-dots absolute inset-0 opacity-40"></div>
            <div class="relative">
                <p class="text-xs font-extrabold tracking-widest uppercase opacity-70">KiddoZone tặng bạn</p>
                <p class="mt-2 font-display text-4xl font-bold" x-text="type === 'percent' ? 'Giảm ' + (value || 0) + '%' : 'Giảm ' + Number(String(value).replace(/\D/g, '') || 0).toLocaleString('vi-VN') + 'đ'"></p>
                <div class="mt-5 flex items-center justify-between rounded-2xl border-2 border-dashed border-ink-900/30 bg-white/60 px-4 py-3 backdrop-blur">
                    <span class="font-display text-2xl font-bold tracking-widest" x-text="code || 'MÃ CỦA BẠN'"></span>
                    <i class="ph-fill ph-scissors text-xl" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </aside>
</form>

<?php require __DIR__ . '/../admin_footer.php'; ?>

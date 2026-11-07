# KiddoZone - hướng dẫn chạy và quy ước chung

## Cài đặt (Laragon)
1. Đặt thư mục dự án trong `C:\laragon\www\` (hoặc trỏ Laragon tới repo), bật Apache + MySQL.
2. Mở phpMyAdmin, **Import** file `database/kiddozone.sql` (tự tạo database `kiddozone`).
3. Nếu MySQL có mật khẩu, sửa `includes/config.php` (`DB_USER`, `DB_PASS`).
4. Mở `http://localhost/<tên-thư-mục>/kiddozone/`.

Tài khoản mẫu: `admin@kiddozone.test` / `Admin@123`; khách: `khach1@kiddozone.test` / `Khach@123` (khach2, khach3 tương tự).

## Quy ước khi viết trang mới
- Dòng đầu mọi file PHP: `require_once __DIR__ . '/includes/init.php';` (trong `admin/`: `'/../includes/init.php'`).
- Trang admin: dòng kế tiếp phải là `require_admin();`. Trang cần đăng nhập: `require_login();` hoặc `require_customer();`.
- Truy vấn: **luôn** prepared statement - `db_query('SELECT ... WHERE id = ?', [$id])`. Không nối chuỗi vào SQL.
- In dữ liệu ra HTML: **luôn** `<?= e($value) ?>`. Link/redirect: `url('products.php')`, `redirect('cart.php')`.
- Mọi form POST: `<?= csrf_field() ?>` trong form và `csrf_verify();` ở đầu phần xử lý. Xóa dữ liệu dùng form POST, không dùng link GET.
- Thông báo: `flash('success'|'danger'|'warning', '...')`, header tự hiển thị.
- Phân trang: `[$offset, $pages, $page] = paginate($total, ITEMS_PER_PAGE_SHOP);` rồi `LIMIT <int> OFFSET <int>`, in thanh phân trang bằng `render_pagination($page, $pages)` (giữ nguyên bộ lọc).
- Giá: `format_price($x)`; giá đang bán: `effective_price($product)`.
- Giỏ hàng khách vãng lai lưu trong `$_SESSION['cart']` theo dạng `product_id => quantity`; thao tác thêm, cập nhật và xóa thực hiện bằng POST có CSRF tại `cart.php`.
- Upload ảnh: `save_uploaded_image($file)` (kiểm MIME, tối đa 2MB, tên ngẫu nhiên).
- Giỏ hàng khi đăng nhập: định nghĩa hàm `merge_session_cart(int $userId)` (task F19), `login_user()` sẽ tự gọi.

## Cấu trúc
```
kiddozone/
├── index.php, cart.php, login.php, register.php, logout.php
├── admin/            trang quản trị (index.php là giữ chỗ)
├── includes/         init, config, db, functions, auth, header, footer
├── assets/           css/, js/, img/
├── uploads/          ảnh sản phẩm (không chạy được PHP)
└── database/         kiddozone.sql
```

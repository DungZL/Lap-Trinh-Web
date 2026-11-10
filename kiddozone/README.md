# KiddoZone - hướng dẫn chạy và quy ước chung

## Cài đặt (XAMPP)

**Yêu cầu:** XAMPP bản có **PHP 8.0 trở lên** (code dùng `str_starts_with`, `str_contains`...). Tải tại https://www.apachefriends.org. MySQL của XAMPP là MariaDB, dùng được bình thường.

### 1. Đặt code vào `C:\xampp\htdocs\`
Chọn **một** trong hai cách:

- **Clone thẳng vào htdocs** (đơn giản nhất):
  ```bash
  cd C:\xampp\htdocs
  git clone https://github.com/DungZL/Lap-Trinh-Web.git
  ```
- **Giữ repo ở chỗ khác** (ví dụ Desktop) và tạo junction để Apache đọc được. Mở PowerShell rồi chạy (thay đường dẫn repo của bạn):
  ```powershell
  cmd /c mklink /J "C:\xampp\htdocs\Lap-Trinh-Web" "D:\duong-dan\toi\Lap-Trinh-Web"
  ```

Sau bước này phải có thư mục `C:\xampp\htdocs\Lap-Trinh-Web\kiddozone\`.

### 2. Bật Apache và MySQL
Mở **XAMPP Control Panel** → bấm **Start** ở dòng **Apache** và **MySQL** (hai dòng chuyển xanh).

### 3. Tạo database
> Lưu ý: file SQL **xóa và tạo lại** các bảng trong database `kiddozone`, dữ liệu thử cũ sẽ mất.

- **Cách A - phpMyAdmin:** mở http://localhost/phpmyadmin → tab **Import** → chọn `kiddozone/database/kiddozone.sql` → **Import** (file tự tạo database `kiddozone`).
- **Cách B - dòng lệnh** (khi phpMyAdmin lỗi hoặc tiếng Việt bị lỗi font):
  ```powershell
  C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source C:/xampp/htdocs/Lap-Trinh-Web/kiddozone/database/kiddozone.sql"
  ```

XAMPP mặc định tài khoản MySQL là `root`, không mật khẩu. Nếu bạn đã đặt mật khẩu, sửa `DB_USER`, `DB_PASS` trong `includes/config.php`.

### 4. Mở website
- Trang khách: http://localhost/Lap-Trinh-Web/kiddozone/
- Trang quản trị: http://localhost/Lap-Trinh-Web/kiddozone/admin/

Đường dẫn trong code tự nhận theo thư mục (`url()`), không cần sửa gì nếu đặt tên thư mục khác.

Tài khoản mẫu: `admin@kiddozone.test` / `Admin@123`; khách: `khach1@kiddozone.test` / `Khach@123` (khach2, khach3 tương tự).

### Lỗi hay gặp
| Hiện tượng | Cách xử lý |
|---|---|
| Apache không Start được | Cổng 80/443 bị chiếm (Laragon, IIS, Skype, VMware...). Tắt chương trình đó, hoặc trong XAMPP bấm **Config → httpd.conf** đổi `Listen 80` thành `Listen 8080` rồi mở `http://localhost:8080/...` |
| MySQL không Start được | Cổng 3306 bị chiếm (thường do Laragon/MySQL khác còn chạy). Tắt dịch vụ đó rồi Start lại |
| `Not Found` khi mở link | Sai đường dẫn hoặc chưa làm bước 1: kiểm tra có `C:\xampp\htdocs\Lap-Trinh-Web\kiddozone\index.php` |
| `Unknown database 'kiddozone'` | Chưa làm bước 3 |
| `Access denied for user 'root'` | Sửa `DB_PASS` trong `includes/config.php` |
| `Class "finfo" not found` khi upload ảnh | Mở **Config → PHP (php.ini)**, bỏ dấu `;` trước `extension=fileinfo`, Stop/Start lại Apache |
| Tiếng Việt trong dữ liệu bị lỗi font | Import lại bằng cách B (có `--default-character-set=utf8mb4`) |

## Quy ước khi viết trang mới
- Dòng đầu mọi file PHP: `require_once __DIR__ . '/includes/init.php';` (trong `admin/`: `'/../includes/init.php'`).
- Trang admin: dòng kế tiếp phải là `require_admin();`. Trang cần đăng nhập: `require_login();` hoặc `require_customer();`.
- Truy vấn: **luôn** prepared statement - `db_query('SELECT ... WHERE id = ?', [$id])`. Không nối chuỗi vào SQL.
- In dữ liệu ra HTML: **luôn** `<?= e($value) ?>`. Link/redirect: `url('products.php')`, `redirect('cart.php')`.
- Mọi form POST: `<?= csrf_field() ?>` trong form và `csrf_verify();` ở đầu phần xử lý. Xóa dữ liệu dùng form POST, không dùng link GET.
- Thông báo: `flash('success'|'danger'|'warning', '...')`, header tự hiển thị.
- Phân trang: `[$offset, $pages, $page] = paginate($total, ITEMS_PER_PAGE_SHOP);` rồi `LIMIT <int> OFFSET <int>`, in thanh phân trang bằng `render_pagination($page, $pages)` (giữ nguyên bộ lọc).
- Giá: `format_price($x)`; giá đang bán: `effective_price($product)`.
- Upload ảnh: `save_uploaded_image($file)` (kiểm MIME, tối đa 2MB, tên ngẫu nhiên).
- Giỏ hàng khi đăng nhập: định nghĩa hàm `merge_session_cart(int $userId)` (task F19), `login_user()` sẽ tự gọi.

## Cấu trúc
```
kiddozone/
├── index.php, products.php, product.php            trang chủ, danh sách, chi tiết sản phẩm
├── login.php, register.php, logout.php
├── account/          trang của khách hàng: hồ sơ, đơn hàng, yêu thích
├── admin/            trang quản trị: admin_header/footer.php (layout), categories/, products/...
├── includes/         init, config, db, functions, auth, header, footer, product_card
├── assets/           css/, js/, img/
├── uploads/          ảnh sản phẩm (không chạy được PHP)
└── database/         kiddozone.sql
```

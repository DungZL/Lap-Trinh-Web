# KiddoZone - Hướng dẫn cài đặt, deploy, bảo mật và quy ước chung

## 1. Cài đặt trên máy (XAMPP hoặc Laragon)

**Yêu cầu:** **PHP 8.0 trở lên** (code dùng `str_starts_with`, `str_contains`...), MySQL 8 hoặc MariaDB 10.4+.
- **XAMPP:** tải tại https://www.apachefriends.org.
- **Laragon:** tải tại https://laragon.org (thư mục web mặc định là `C:\laragon\www\`).

### Bước 1: Đặt code vào thư mục web server
Chọn **một** trong hai cách:

- **Clone thẳng vào `htdocs` (XAMPP) hoặc `www` (Laragon):**
  ```bash
  cd C:\xampp\htdocs
  git clone https://github.com/DungZL/Lap-Trinh-Web.git
  ```
- **Giữ repo ở chỗ khác (ví dụ Desktop) và tạo junction:** Mở PowerShell rồi chạy (thay đường dẫn thực tế):
  ```powershell
  cmd /c mklink /J "C:\xampp\htdocs\Lap-Trinh-Web" "C:\Users\<TenBan>\OneDrive\Desktop\Lap-Trinh-Web"
  ```

### Bước 2: Bật Apache và MySQL
- **XAMPP:** Mở **XAMPP Control Panel** → bấm **Start** ở dòng **Apache** và **MySQL**.
- **Laragon:** Mở Laragon → bấm **Start All**.

### Bước 3: Tạo database
> Lưu ý: file SQL **xóa và tạo lại** các bảng trong database `kiddozone`, dữ liệu thử cũ sẽ mất.

- **Cách A - phpMyAdmin:** mở `http://localhost/phpmyadmin` → tab **Import** → chọn `kiddozone/database/kiddozone.sql` → **Import** (file tự tạo database `kiddozone`).
- **Cách B - dòng lệnh** (tránh lỗi font tiếng Việt):
  ```powershell
  C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source C:/xampp/htdocs/Lap-Trinh-Web/kiddozone/database/kiddozone.sql"
  ```

XAMPP/Laragon mặc định tài khoản MySQL là `root`, không mật khẩu. Nếu có mật khẩu hoặc cổng khác, tạo file `includes/config.local.php` từ `includes/config.local.example.php` (file `config.local.php` đã được bỏ qua trong `.gitignore`).

### Bước 4: Mở website
- **Trang khách:** `http://localhost/Lap-Trinh-Web/kiddozone/`
- **Trang quản trị:** `http://localhost/Lap-Trinh-Web/kiddozone/admin/`

Đường dẫn trong code tự nhận theo thư mục (`url()`), không cần sửa gì nếu đặt tên thư mục khác.

**Tài khoản mẫu:**
- **Admin:** `admin@kiddozone.test` / `Admin@123`
- **Khách hàng:** `khach1@kiddozone.test` / `Khach@123` (`khach2`, `khach3` tương tự)

### Lỗi hay gặp
| Hiện tượng | Cách xử lý |
|---|---|
| Apache không Start được | Cổng 80/443 bị chiếm (IIS, Skype, VMware...). Tắt chương trình đó, hoặc trong **httpd.conf** đổi `Listen 80` thành `Listen 8080` rồi mở `http://localhost:8080/...` |
| MySQL không Start được | Cổng 3306 bị chiếm bởi MySQL khác. Tắt dịch vụ đó rồi Start lại |
| `Not Found` khi mở link | Kiểm tra đã đặt đúng đường dẫn chứa `kiddozone/index.php` chưa |
| `Unknown database 'kiddozone'` | Chưa thực hiện Bước 3 |
| `Access denied for user 'root'` | Sao chép `includes/config.local.example.php` thành `includes/config.local.php` và điền `DB_PASS` |
| `Class "finfo" not found` khi upload ảnh | Mở **php.ini**, bỏ dấu `;` trước `extension=fileinfo`, khởi động lại Apache |
| Tiếng Việt bị lỗi font | Import lại bằng Cách B (có `--default-character-set=utf8mb4`) |

---

## 2. Hướng dẫn Deploy (InfinityFree / Render)

### Cách A: InfinityFree (Shared Hosting PHP + MySQL)
1. Tạo database MySQL mới trong **Control Panel → MySQL Databases** trên InfinityFree và ghi lại `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
2. Mở **phpMyAdmin** của hosting → Import file `kiddozone/database/kiddozone.sql` (nếu hosting không cho phép lệnh `CREATE DATABASE`, xóa 2 dòng đầu `CREATE DATABASE ...` và `USE kiddozone;` trước khi import trực tiếp vào DB đã tạo).
3. Upload toàn bộ nội dung trong thư mục `kiddozone/` lên thư mục `htdocs/` của hosting (FTP / File Manager).
4. Sao chép `includes/config.local.example.php` thành `includes/config.local.php` trên hosting và điền đúng thông số MySQL vừa tạo; nếu đặt code thẳng vào `htdocs/` của domain chính, đặt `define('BASE_URL_OVERRIDE', '');` (hoặc để `null` tự dò).
5. Đảm bảo thư mục `uploads/` có quyền ghi (`0755`) và giữ nguyên file `uploads/.htaccess`.

### Cách B: Render / Container PHP + MySQL
1. Tạo Web Service dùng PHP 8 (Document Root trỏ tới `kiddozone/`) và kết nối MySQL/MariaDB dịch vụ ngoài (Aiven, Railway, PlanetScale...).
2. Khai báo biến môi trường (Environment Variables) trên Render — `includes/config.php` sẽ tự động đọc:
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `BASE_URL_OVERRIDE` (đặt `""` nếu chạy ở root domain `/`)
3. Import `kiddozone/database/kiddozone.sql` vào database từ xa và kiểm tra các trang chính.

---

## 3. Rà soát bảo mật đã áp dụng
- **SQL Injection:** 100% truy vấn dùng PDO Prepared Statement (`db_query($sql, $params)`), tắt giả lập prepare (`ATTR_EMULATE_PREPARES => false`), ép kiểu `(int)` cho `LIMIT`/`OFFSET`, thoát ký tự đại diện `LIKE` bằng `addcslashes(..., '%_\\')`, danh sách trắng `ORDER BY`.
- **XSS:** Thoát dữ liệu in ra HTML bằng `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`). Mô tả HTML từ TinyMCE được lọc qua `sanitize_description_html()` (`DOMDocument` chỉ giữ thẻ/thuộc tính an toàn, loại bỏ `<script>`, sự kiện `on*`, `javascript:`, `data:`) cả khi lưu trong Admin lẫn khi hiển thị ở `product.php`.
- **CSRF:** Mọi form POST (kể cả đăng xuất, xóa, khóa/mở khóa, hủy đơn, wishlist) đều gắn `csrf_field()` và kiểm tra `csrf_verify()` dùng `hash_equals()`.
- **Xác thực & Phiên (Session):** Băm mật khẩu `password_hash()` / `password_verify()`, `session_regenerate_id(true)` sau đăng nhập, cookie session `httponly` + `SameSite=Lax` + `secure` khi dùng HTTPS, đối chiếu trạng thái `users.status = 1` ở mỗi request để vô hiệu hóa ngay tài khoản bị khóa (`TC14`).
- **Phân quyền & Open Redirect:** Trang quản trị gọi `require_admin()`, trang khách đăng nhập gọi `require_customer()` hoặc `require_login()`, `safe_redirect_target()` chỉ nhận đường dẫn nội bộ hợp lệ (chặn `//`, `\`, `\r`, `\n`).
- **Upload & Header:** `save_uploaded_image()` kiểm tra lỗi upload, giới hạn 2MB, kiểm tra MIME thực bằng `finfo`, đổi tên file ngẫu nhiên; `uploads/.htaccess` chặn thực thi file PHP; `includes/init.php` gửi `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`.

---

## 4. Quy ước khi viết trang mới
- Dòng đầu mọi file PHP: `require_once __DIR__ . '/includes/init.php';` (trong thư mục con: `'/../includes/init.php'` hoặc `'/../../includes/init.php'`).
- Trang admin: dòng kế tiếp phải là `require_admin();`. Trang cần đăng nhập của khách: `require_customer();` hoặc `require_login();`.
- Truy vấn: **luôn** dùng prepared statement — `db_query('SELECT ... WHERE id = ?', [$id])`.
- In dữ liệu ra HTML: **luôn** dùng `<?= e($value) ?>`. Link/redirect dùng `url('...')`, `redirect('...')`.
- Mọi form POST: `<?= csrf_field() ?>` trong form và `csrf_verify();` ở đầu phần xử lý. Không dùng GET để xóa/thay đổi dữ liệu.
- Thông báo: `flash('success'|'danger'|'warning'|'info', '...')`.
- Phân trang: `[$offset, $pages, $page] = paginate($total, ITEMS_PER_PAGE_SHOP|ITEMS_PER_PAGE_ADMIN);` và `render_pagination($page, $pages)`.
- Giá: `format_price($x)`; giá đang bán: `effective_price($product)`.
- Giỏ hàng: dùng bộ hàm `cart_items()`, `cart_count()`, `cart_set_item()`, `cart_remove_item()`, `cart_clear()`, `merge_session_cart()` trong `includes/functions.php`.

---

## 5. Cấu trúc thư mục
```
kiddozone/
├── index.php, products.php, product.php    Trang chủ, danh sách, chi tiết sản phẩm
├── cart.php, checkout.php                  Giỏ hàng và đặt hàng (transaction)
├── login.php, register.php, logout.php     Đăng nhập, đăng ký, đăng xuất
├── account/          Trang khách hàng: index, profile, orders, wishlist
├── admin/            Trang quản trị: admin_header/footer, index, orders.php,
│                     categories/, products/, orders/, users/
├── includes/         init, config, config.local.example, db, functions, auth,
│                     header, footer, product_card, recently_viewed, wishlist
├── assets/           css/, js/, img/, vendor/tinymce/
├── uploads/          Ảnh sản phẩm do Admin tải lên (.htaccess chặn chạy PHP)
└── database/         kiddozone.sql (10 bảng + dữ liệu mẫu)
```

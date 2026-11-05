<?php
// Đăng nhập, đăng xuất, phân quyền.

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

/** Chỉ cho phép đường dẫn nội bộ khi chuyển hướng sau đăng nhập (chống open redirect). */
function safe_redirect_target(?string $target, string $fallback = 'index.php'): string
{
    if ($target && $target[0] === '/' && !str_starts_with($target, '//') && !str_contains($target, '\\')) {
        return $target;
    }
    return $fallback;
}

/** Bắt buộc đăng nhập. Chưa đăng nhập thì chuyển sang login.php và quay lại sau. */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

/** Gọi ở dòng đầu mọi trang trong admin/. Khách vãng lai về đăng nhập, khách hàng bị chặn 403. */
function require_admin(): void
{
    if (!is_logged_in()) {
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    if (!is_admin()) {
        http_response_code(403);
        exit('403 - Bạn không có quyền truy cập khu vực quản trị.');
    }
}

/** Bắt buộc là khách hàng (Admin bị chặn khỏi đặt hàng, đánh giá, yêu thích). */
function require_customer(): void
{
    require_login();
    if (is_admin()) {
        http_response_code(403);
        exit('403 - Tài khoản Admin không dùng chức năng này.');
    }
}

/** Thử đăng nhập. Trả về null nếu thành công, hoặc chuỗi lỗi chung. */
function attempt_login(string $email, string $password): ?string
{
    $generic = 'Email hoặc mật khẩu không đúng.';
    $user = db_query('SELECT * FROM users WHERE email = ?', [trim($email)])->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return $generic;
    }
    if ((int)$user['status'] === 0) {
        return 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.';
    }
    login_user($user);
    return null;
}

function login_user(array $user): void
{
    session_regenerate_id(true); // chống session fixation
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
    ];
    // F19: gộp giỏ hàng session vào giỏ của tài khoản (được định nghĩa ở task F19).
    if ($user['role'] === 'customer' && function_exists('merge_session_cart')) {
        merge_session_cart((int)$user['id']);
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Đăng ký khách hàng mới. Trả về mảng lỗi (rỗng nếu thành công, khi đó đã tạo user và trả id qua $newId).
 */
function register_customer(array $in, ?int &$newId = null): array
{
    $errors = [];
    $name  = trim($in['full_name'] ?? '');
    $email = trim($in['email'] ?? '');
    $pass  = $in['password'] ?? '';
    $pass2 = $in['password_confirm'] ?? '';
    $phone = trim($in['phone'] ?? '');

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Họ tên không được để trống (tối đa 100 ký tự).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors[] = 'Email không hợp lệ.';
    }
    if (strlen($pass) < 6) {
        $errors[] = 'Mật khẩu tối thiểu 6 ký tự.';
    }
    if ($pass !== $pass2) {
        $errors[] = 'Mật khẩu xác nhận không khớp.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\s.-]{8,15}$/', $phone)) {
        $errors[] = 'Số điện thoại không hợp lệ.';
    }
    if (!$errors && db_query('SELECT 1 FROM users WHERE email = ?', [$email])->fetch()) {
        $errors[] = 'Email đã được sử dụng.';
    }
    if ($errors) {
        return $errors;
    }
    db_query(
        'INSERT INTO users (full_name, email, password, phone, role, status) VALUES (?, ?, ?, ?, "customer", 1)',
        [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $phone !== '' ? $phone : null]
    );
    $newId = (int)db()->lastInsertId();
    return [];
}

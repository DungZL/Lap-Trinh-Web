<?php
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'index.php');
}

$error = null;
$redirectTo = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

if (is_post()) {
    csrf_verify();
    $error = attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($error === null) {
        if (is_admin()) {
            redirect('admin/index.php');
        }
        redirect(safe_redirect_target($redirectTo));
    }
}

$pageTitle = 'Đăng nhập | KiddoZone';
require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="assets/css/auth.css">
<!-- Thêm thư viện FontAwesome để lấy icon -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<main class="auth-page">
    <div class="auth-container">
        
        <!-- CỘT TRÁI: BANNER MÀU XANH CHỨA CHỮ VÀ ẢNH -->
        <div class="auth-image-section">
            <div class="banner-content">
                <div class="brand-logo">
                    <span class="brand-icon">K</span>
                    <span>Kiddo<span>Zone</span></span>
                </div>
                
                <span class="banner-label">⭐ THẾ GIỚI ĐỒ CHƠI</span>
                
                <h2 class="banner-title">
                    Niềm vui nhỏ,<br>
                    <span>Ước mơ lớn!</span>
                </h2>
                
                <p class="banner-text">
                    Khám phá thế giới đồ chơi đầy màu sắc và lựa chọn những món quà tuyệt vời dành cho bé yêu.
                </p>
                
                <div class="toy-decoration">
                    <span>🧸</span><span>🚗</span><span>🧩</span><span>🎁</span>
                </div>
            </div>
            
            <!-- Dải băng vàng dưới cùng -->
            <div class="banner-bottom">KIDDOZONE - PLAY & GROW</div>
        </div>

        <!-- CỘT PHẢI: FORM ĐĂNG NHẬP -->
        <div class="auth-form-section">
            <div class="auth-heading">
                <span class="welcome-label">CHÀO MỪNG BẠN TRỞ LẠI</span>
                <h1>Đăng nhập</h1>
                <p>Đăng nhập để tiếp tục mua sắm tại KiddoZone nhé!</p>
            </div>

            <?php if ($error): ?>
                <div class="auth-alert" role="alert">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" id="loginForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">

                <div class="form-group">
                    <label for="email">Địa chỉ Email</label>
                    <div class="input-icon-wrapper">
                        <!-- Icon phong bì thư -->
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" id="email" name="email" class="auth-input" placeholder="Nhập email của bạn" value="<?= old('email') ?>" required>
                    </div>
                    <small class="field-error" id="emailError"></small>
                </div>

                <div class="form-group">
                    <label for="password">Mật khẩu</label>
                    <div class="input-icon-wrapper">
                        <!-- Icon ổ khóa -->
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" class="auth-input" placeholder="Nhập mật khẩu" required>
                        
                        <!-- Nút hiện/ẩn mật khẩu chứa icon con mắt -->
                        <button type="button" class="toggle-password" id="togglePassword">
                            <i class="fa-regular fa-eye-slash" id="eyeIcon"></i>
                        </button>
                    </div>
                    <small class="field-error" id="passwordError"></small>
                </div>

                <div class="auth-options">
                    <span class="secure-note"><i class="fa-solid fa-shield-halved"></i> Đăng nhập an toàn</span>
                </div>

                <button type="submit" class="btn-login">
                    ĐĂNG NHẬP <span>→</span>
                </button>
            </form>

            <div class="auth-register">
                Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
            </div>
            <div class="auth-support">
                Bằng việc đăng nhập, bạn có thể khám phá và quản lý đơn hàng tại KiddoZone.
            </div>
        </div>
    </div>
</main>

<script src="assets/js/auth.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
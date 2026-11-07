<?php
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'index.php');
}

$error = null;

if (is_post()) {
    csrf_verify();
    
   
    $error = attempt_register(
        $_POST['full_name'] ?? '',
        $_POST['email'] ?? '',
        $_POST['password'] ?? '',
        $_POST['confirm_password'] ?? ''
    );

    if ($error === null) {
        // Đăng ký thành công thì chuyển về trang đăng nhập
        redirect('login.php?registered=1');
    }
}

$pageTitle = 'Đăng ký | KiddoZone';
require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="assets/css/auth.css">
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
                    Tạo tài khoản để nhận ngay nhiều ưu đãi và theo dõi đơn hàng dễ dàng.
                </p>
                
                <div class="toy-decoration">
                    <span>🧸</span><span>🚗</span><span>🧩</span><span>🎁</span>
                </div>
            </div>
            
            <div class="banner-bottom">KIDDOZONE - PLAY & GROW</div>
        </div>

        <!-- CỘT PHẢI: FORM ĐĂNG KÝ -->
        <div class="auth-form-section">
            <div class="auth-heading">
                <span class="welcome-label">BẮT ĐẦU HÀNH TRÌNH MỚI</span>
                <h1>Đăng ký</h1>
                <p>Tạo tài khoản để cùng bé yêu khám phá KiddoZone!</p>
            </div>

            <?php if ($error): ?>
                <div class="auth-alert" role="alert">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" id="registerForm" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="full_name">Họ và tên</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" id="full_name" name="full_name" class="auth-input" placeholder="Nhập họ và tên" value="<?= old('full_name') ?>" required>
                    </div>
                    <small class="field-error" id="nameError"></small>
                </div>

                <div class="form-group">
                    <label for="email">Địa chỉ Email</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" id="email" name="email" class="auth-input" placeholder="Nhập email của bạn" value="<?= old('email') ?>" required>
                    </div>
                    <small class="field-error" id="emailError"></small>
                </div>

                <div class="form-group">
                    <label for="password">Mật khẩu</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" class="auth-input" placeholder="Tạo mật khẩu" required>
                        <button type="button" class="toggle-password" id="togglePassword">
                            <i class="fa-regular fa-eye-slash" id="eyeIcon"></i>
                        </button>
                    </div>
                    <small class="field-error" id="passwordError"></small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Xác nhận mật khẩu</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="auth-input" placeholder="Nhập lại mật khẩu" required>
                    </div>
                    <small class="field-error" id="confirmError"></small>
                </div>

                <button type="submit" class="btn-login" style="margin-top: 10px;">
                    ĐĂNG KÝ <span>→</span>
                </button>
            </form>

            <div class="auth-register" style="margin-top: 20px;">
                Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a>
            </div>
        </div>
    </div>
</main>

<script src="assets/js/auth.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
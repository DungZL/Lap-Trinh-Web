// ==========================================
// XỬ LÝ CHO FORM ĐĂNG NHẬP
// ==========================================
const loginForm = document.getElementById("loginForm");

if (loginForm) {
    const email = document.getElementById("email");
    const password = document.getElementById("password");
    const emailError = document.getElementById("emailError");
    const passwordError = document.getElementById("passwordError");
    const togglePassword = document.getElementById("togglePassword");
    const eyeIcon = document.getElementById("eyeIcon");

    // Hiện / ẩn mật khẩu
    if (togglePassword) {
        togglePassword.addEventListener("click", function () {
            if (password.type === "password") {
                password.type = "text";
                if (eyeIcon) {
                    eyeIcon.classList.remove("fa-eye-slash");
                    eyeIcon.classList.add("fa-eye");
                } else {
                    togglePassword.textContent = "Ẩn";
                }
            } else {
                password.type = "password";
                if (eyeIcon) {
                    eyeIcon.classList.remove("fa-eye");
                    eyeIcon.classList.add("fa-eye-slash");
                } else {
                    togglePassword.textContent = "Hiện";
                }
            }
        });
    }

    // Xóa lỗi khi người dùng nhập lại
    email.addEventListener("input", function () { emailError.textContent = ""; });
    password.addEventListener("input", function () { passwordError.textContent = ""; });

    // Kiểm tra trước khi gửi form Đăng nhập
    loginForm.addEventListener("submit", function (event) {
        let isValid = true;
        emailError.textContent = "";
        passwordError.textContent = "";

        const emailValue = email.value.trim();
        const passwordValue = password.value;
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (emailValue === "") {
            emailError.textContent = "Vui lòng nhập email.";
            isValid = false;
        } else if (!emailPattern.test(emailValue)) {
            emailError.textContent = "Email không đúng định dạng.";
            isValid = false;
        }

        if (passwordValue.trim() === "") {
            passwordError.textContent = "Vui lòng nhập mật khẩu.";
            isValid = false;
        }

        if (!isValid) {
            event.preventDefault();
        }
    });
}

// ==========================================
// XỬ LÝ VALIDATE CHO FORM ĐĂNG KÝ
// ==========================================
const registerForm = document.getElementById("registerForm");

if (registerForm) {
    const fullName = document.getElementById("full_name");
    const email = document.getElementById("email");
    const password = document.getElementById("password");
    const confirmPassword = document.getElementById("confirm_password");
    
    const nameError = document.getElementById("nameError");
    const emailError = document.getElementById("emailError");
    const passwordError = document.getElementById("passwordError");
    const confirmError = document.getElementById("confirmError");
    
    const togglePassword = document.getElementById("togglePassword");
    const eyeIcon = document.getElementById("eyeIcon");

    // Ẩn/hiện mật khẩu
    if (togglePassword && eyeIcon) {
        togglePassword.addEventListener("click", function () {
            if (password.type === "password") {
                password.type = "text";
                eyeIcon.classList.remove("fa-eye-slash");
                eyeIcon.classList.add("fa-eye");
            } else {
                password.type = "password";
                eyeIcon.classList.remove("fa-eye");
                eyeIcon.classList.add("fa-eye-slash");
            }
        });
    }

    // Xóa lỗi khi nhập lại
    fullName.addEventListener("input", function () { nameError.textContent = ""; });
    email.addEventListener("input", function () { emailError.textContent = ""; });
    password.addEventListener("input", function () { passwordError.textContent = ""; });
    confirmPassword.addEventListener("input", function () { confirmError.textContent = ""; });

    registerForm.addEventListener("submit", function (event) {
        let isValid = true;
        nameError.textContent = "";
        emailError.textContent = "";
        passwordError.textContent = "";
        confirmError.textContent = "";

        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (fullName.value.trim() === "") {
            nameError.textContent = "Vui lòng nhập họ tên.";
            isValid = false;
        }

        if (email.value.trim() === "") {
            emailError.textContent = "Vui lòng nhập email.";
            isValid = false;
        } else if (!emailPattern.test(email.value.trim())) {
            emailError.textContent = "Email không đúng định dạng.";
            isValid = false;
        }

        // KIỂM TRA MẬT KHẨU (Ít nhất 8 ký tự, có chữ và số)
        const passwordValue = password.value;
        const hasLetter = /[a-zA-Z]/.test(passwordValue);
        const hasNumber = /[0-9]/.test(passwordValue);

        if (passwordValue.trim() === "") {
            passwordError.textContent = "Vui lòng nhập mật khẩu.";
            isValid = false;
        } else if (passwordValue.length < 8) {
            passwordError.textContent = "Mật khẩu phải có ít nhất 8 ký tự.";
            isValid = false;
        } else if (!hasLetter || !hasNumber) {
            passwordError.textContent = "Mật khẩu phải bao gồm cả chữ và số.";
            isValid = false;
        }

        // Kiểm tra xác nhận mật khẩu
        if (confirmPassword.value !== password.value) {
            confirmError.textContent = "Mật khẩu xác nhận không khớp.";
            isValid = false;
        }

        if (!isValid) {
            event.preventDefault();
        }
    });
}
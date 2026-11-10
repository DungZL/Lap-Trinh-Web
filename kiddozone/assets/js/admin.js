/* JavaScript dùng chung cho trang quản trị (nạp ở cuối admin/admin_footer.php). */
(function () {
    'use strict';

    /* 1. Form có thuộc tính data-confirm: hỏi lại trước khi gửi (ví dụ nút Xóa). */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.getAttribute && form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    /* 2. Ô chọn ảnh có data-preview="#id": hiện ảnh xem trước vào phần tử #id. */
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        var box = document.querySelector(input.getAttribute('data-preview'));
        if (!box) {
            return;
        }
        input.addEventListener('change', function () {
            box.innerHTML = '';
            Array.prototype.forEach.call(input.files, function (file) {
                if (!file.type.startsWith('image/')) {
                    return;
                }
                var img = document.createElement('img');
                img.alt = file.name;
                img.src = URL.createObjectURL(file);
                img.onload = function () { URL.revokeObjectURL(img.src); };
                box.appendChild(img);
            });
        });
    });

    /* 3. Ô mô tả có data-editor: bật TinyMCE (trang phải nạp script TinyMCE trước file này). */
    if (window.tinymce && document.querySelector('textarea[data-editor]')) {
        window.tinymce.init({
            selector: 'textarea[data-editor]',
            height: 380,
            menubar: false,
            branding: false,
            promotion: false,
            plugins: 'lists link image table code',
            toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link image table | code',
            block_formats: 'Đoạn văn=p; Tiêu đề 2=h2; Tiêu đề 3=h3; Tiêu đề 4=h4'
        });
    }
})();

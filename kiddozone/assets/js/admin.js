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

document.addEventListener('DOMContentLoaded', function () {
    const zone = document.getElementById('uploadZone');
    const input = document.getElementById('productImages');
    const preview = document.getElementById('imagePreview');
    const counter = document.getElementById('imageCount');

    if (!zone || !input || !preview || !counter) return;

    let selectedFiles = [];
    const maxFiles = 10;
    const maxSize = 2 * 1024 * 1024;
    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    zone.addEventListener('click', () => input.click());

    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.classList.add('dragover');
    });

    zone.addEventListener('dragleave', function () {
        zone.classList.remove('dragover');
    });

    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('dragover');
        addFiles(e.dataTransfer.files);
    });

    input.addEventListener('change', function () {
        addFiles(input.files);
    });

    function addFiles(files) {
        for (const file of Array.from(files)) {
            if (selectedFiles.length >= maxFiles) {
                alert('Chỉ được chọn tối đa 10 ảnh.');
                break;
            }

            if (!allowedTypes.includes(file.type)) {
                alert(file.name + ': Định dạng không hợp lệ.');
                continue;
            }

            if (file.size > maxSize) {
                alert(file.name + ': Ảnh vượt quá 2MB.');
                continue;
            }

            const duplicate = selectedFiles.some(f =>
                f.name === file.name &&
                f.size === file.size &&
                f.lastModified === file.lastModified
            );

            if (!duplicate) {
                selectedFiles.push(file);
            }
        }

        updateInput();
        renderPreview();
    }

    function updateInput() {
        const transfer = new DataTransfer();

        selectedFiles.forEach(file => {
            transfer.items.add(file);
        });

        input.files = transfer.files;
    }

    function renderPreview() {
        preview.innerHTML = '';

        selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'product-preview-item';

            const img = document.createElement('img');
            const imageUrl = URL.createObjectURL(file);
            img.src = imageUrl;
            img.alt = file.name;
            img.onload = () => URL.revokeObjectURL(imageUrl);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'product-image-remove';
            removeBtn.textContent = '×';
            removeBtn.title = 'Xóa ảnh này';
            removeBtn.setAttribute('aria-label', 'Xóa ' + file.name);

            removeBtn.addEventListener('click', function () {
                selectedFiles.splice(index, 1);
                updateInput();
                renderPreview();
            });

            item.appendChild(img);
            item.appendChild(removeBtn);

            if (index === 0) {
                const label = document.createElement('span');
                label.className = 'product-main-label';
                label.textContent = 'Ảnh đại diện';
                item.appendChild(label);
            }

            preview.appendChild(item);
        });

        counter.textContent =
            `Đã chọn ${selectedFiles.length}/${maxFiles} ảnh`;
    }
});

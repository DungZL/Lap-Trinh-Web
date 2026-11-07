/* JavaScript dùng chung cho trang khách (tách riêng khỏi file PHP).
   Được nạp ở cuối includes/footer.php. */
(function () {
    'use strict';

    /* 1. Ảnh lỗi thì thay bằng ảnh mẫu. Đường dẫn ảnh mẫu lấy từ thuộc tính data-fallback của thẻ <img>. */
    function useFallback(img) {
        var fallback = img.getAttribute('data-fallback');
        if (fallback && img.getAttribute('src') !== fallback) {
            img.setAttribute('src', fallback);
        }
    }

    // Sự kiện error không nổi bọt lên, nên phải bắt ở giai đoạn capture.
    document.addEventListener('error', function (event) {
        if (event.target instanceof HTMLImageElement) {
            useFallback(event.target);
        }
    }, true);

    // Ảnh đã lỗi từ trước khi file này chạy thì xử lý luôn.
    document.querySelectorAll('img[data-fallback]').forEach(function (img) {
        if (img.complete && img.naturalWidth === 0) {
            useFallback(img);
        }
    });

    /* 2. Ô chọn có thuộc tính data-autosubmit: đổi lựa chọn thì gửi form luôn (ví dụ sắp xếp sản phẩm). */
    document.addEventListener('change', function (event) {
        var el = event.target;
        if (el.matches && el.matches('[data-autosubmit]') && el.form) {
            el.form.submit();
        }
    });

    /* 3. Trang chi tiết sản phẩm: bấm ảnh nhỏ thì đổi ảnh lớn. */
    var main = document.getElementById('kz-main-img');
    var thumbs = document.querySelectorAll('.kz-thumb');
    if (main && thumbs.length) {
        thumbs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                main.src = btn.getAttribute('data-src');
                thumbs.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
            });
        });
    }
})();
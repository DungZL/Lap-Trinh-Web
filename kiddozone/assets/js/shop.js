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

    /* 4. Nút trái tim yêu thích (F17): gửi AJAX qua fetch để không tải lại trang. */
    var toastEl = null;
    var toastTimer = null;
    function showToast(text) {
        if (!text) return;
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.className = 'kz-toast';
            toastEl.setAttribute('role', 'status');
            toastEl.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastEl);
        }
        toastEl.textContent = text;
        toastEl.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toastEl.classList.remove('show');
        }, 2500);
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.classList || !form.classList.contains('kz-wish-form')) {
            return;
        }
        event.preventDefault();

        var btn = form.querySelector('.kz-wish-btn');
        if (btn) btn.disabled = true;

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
            credentials: 'same-origin'
        })
            .then(function (res) {
                return res.json().catch(function () { return null; }).then(function (data) {
                    return { status: res.status, data: data };
                });
            })
            .then(function (result) {
                var data = result.data;
                if (!data) {
                    form.submit();
                    return;
                }
                if (data.login) {
                    window.location.href = data.login;
                    return;
                }
                if (!data.ok) {
                    showToast(data.message || 'Không thể thực hiện.');
                    return;
                }

                var active = !!data.active;
                var actionInput = form.querySelector('input[name="action"]');
                if (actionInput) {
                    actionInput.value = active ? 'remove' : 'add';
                }

                if (btn) {
                    var label = active ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích';
                    btn.classList.toggle('active', active);
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    btn.setAttribute('aria-label', label);
                    btn.setAttribute('title', label);
                    var icon = btn.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('bi-heart-fill', active);
                        icon.classList.toggle('bi-heart', !active);
                    }
                    var span = btn.querySelector('span');
                    if (span) {
                        span.textContent = label;
                    }
                }

                // Nếu đang ở trang danh sách yêu thích và vừa bỏ thích thì xóa thẻ khỏi lưới.
                var wishlistGrid = form.closest('[data-wishlist-page]');
                if (wishlistGrid && !active) {
                    var cardCol = form.closest('.col');
                    if (cardCol) cardCol.remove();
                    var remaining = wishlistGrid.querySelectorAll('.col').length;
                    var countEl = document.querySelector('[data-wishlist-count]');
                    if (countEl) countEl.textContent = String(remaining);
                    if (remaining === 0) {
                        window.location.reload();
                        return;
                    }
                }

                showToast(data.message);
            })
            .catch(function () {
                form.submit();
            })
            .finally(function () {
                if (btn) btn.disabled = false;
            });
    });
})();

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

<<<<<<< HEAD
    /* 4. Nút trái tim yêu thích: gửi bằng fetch, đổi tim ngay không tải lại trang.
          Nếu fetch lỗi (mạng, phản hồi lạ) thì gửi form kiểu thường như cũ. */
    var toastTimer;
    function showToast(message) {
        if (!message) { return; }
        var toast = document.getElementById('kz-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'kz-toast';
            toast.className = 'kz-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.classList.remove('show'); }, 2200);
    }

    function setWishState(form, active) {
        var btn = form.querySelector('.kz-wish-btn');
        var text = active ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích';
        form.querySelector('input[name="action"]').value = active ? 'remove' : 'add';
        btn.classList.toggle('active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.setAttribute('aria-label', text);
        btn.setAttribute('title', text);
        var icon = btn.querySelector('i');
        icon.classList.toggle('bi-heart-fill', active);
        icon.classList.toggle('bi-heart', !active);
        var label = btn.querySelector('span');
        if (label) { label.textContent = text; }
    }

    // Trang danh sách yêu thích: bỏ tim thì gỡ luôn thẻ khỏi trang.
    function removeCardFromWishlistPage(form) {
        var page = document.querySelector('[data-wishlist-page]');
        var card = form.closest('.col');
        if (!page || !card || !page.contains(card)) { return; }
        card.remove();
        var remaining = page.querySelectorAll('.kz-product').length;
        if (remaining === 0) {
            window.location.reload(); // hiện thông báo danh sách trống
            return;
        }
        var counter = document.querySelector('[data-wishlist-count]');
        if (counter) { counter.textContent = remaining; }
    }

    // Cập nhật giao diện theo kết quả máy chủ trả về.
    function applyWishResult(form, data) {
        if (data.login) { // khách vãng lai: chuyển sang đăng nhập
            window.location.href = data.login;
            return;
        }
        if (!data.ok) {
            showToast(data.message || 'Không thực hiện được, vui lòng thử lại.');
            return;
        }
        setWishState(form, data.active);
        showToast(data.message);
        if (!data.active) { removeCardFromWishlistPage(form); }
=======
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
>>>>>>> 6906e0b6743eb2ec4090211b90b90b570ee74136
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
<<<<<<< HEAD
        if (!form.classList || !form.classList.contains('kz-wish-form') || !window.fetch) { return; }
        event.preventDefault();

        var btn = form.querySelector('.kz-wish-btn');
        btn.disabled = true;

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (res) {
            return res.text().then(function (text) {
                var data = null;
                try { data = JSON.parse(text); } catch (e) { /* không phải JSON */ }
                if (!data) {
                    // Máy chủ trả HTML (thường là lỗi PHP): ghi ra Console để biết nguyên nhân.
                    console.error('[yêu thích] Phản hồi không phải JSON (HTTP ' + res.status + '):', text.slice(0, 300));
                    throw new Error('not-json');
                }
                return data;
            });
        }).then(function (data) {
            btn.disabled = false;
            try {
                applyWishResult(form, data);
            } catch (err) {
                // Máy chủ đã xử lý xong, chỉ lỗi hiển thị: không gửi lại form để tránh tải lại trang.
                console.error('[yêu thích] Lỗi khi cập nhật giao diện:', err);
            }
        }).catch(function (err) {
            if (err.message !== 'not-json') { console.error('[yêu thích] Không gửi được:', err); }
            btn.disabled = false;
            form.submit(); // mạng lỗi hoặc phản hồi lạ: gửi form kiểu thường
        });
    });
})();
=======
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
>>>>>>> 6906e0b6743eb2ec4090211b90b90b570ee74136

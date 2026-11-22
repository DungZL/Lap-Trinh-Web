/* JavaScript trang quản trị (nạp ở cuối admin/admin_footer.php, trước Alpine.js).
 * - Store theme (sáng/tối), notify (chuông đơn hàng mới #13, hỏi máy chủ mỗi 20 giây)
 * - Form có data-confirm: hỏi lại trước khi gửi
 * - Hiệu ứng: số đếm tăng dần, khối hiện dần
 */
(function () {
    'use strict';

    var KZA = window.KZA || { api: {}, urls: {}, csrf: '' };
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var POLL_MS = 20000;

    function request(url, data) {
        var init = { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } };
        if (data) {
            var body = new FormData();
            Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
            body.append('csrf_token', KZA.csrf);
            init.method = 'POST';
            init.body = body;
        }
        return fetch(url, init).then(function (r) { return r.json(); });
    }

    function showToast(message, type) {
        var root = document.querySelector('[data-toast-root]');
        if (!root || !message) { return; }
        var el = document.createElement('div');
        el.className = 'alert ' + (type === 'danger' ? 'alert-danger' : 'alert-info') + ' pointer-events-auto w-full max-w-sm animate-pop shadow-soft';
        el.setAttribute('role', 'status');
        el.innerHTML = '<i aria-hidden="true" class="ph-fill ph-bell-ringing mt-0.5 text-lg"></i><span class="flex-1"></span>';
        el.querySelector('span').textContent = message;
        root.appendChild(el);
        setTimeout(function () { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 300); }, 4000);
    }

    document.addEventListener('alpine:init', function () {
        var Alpine = window.Alpine;

        Alpine.store('theme', {
            dark: document.documentElement.classList.contains('dark'),
            toggle: function () {
                this.dark = !this.dark;
                document.documentElement.classList.toggle('dark', this.dark);
                try { localStorage.setItem('kz-theme', this.dark ? 'dark' : 'light'); } catch (e) { /* bỏ qua */ }
                document.dispatchEvent(new CustomEvent('kz:theme', { detail: this.dark }));
            }
        });

        /* Chuông thông báo đơn hàng mới (#13) */
        Alpine.store('notify', {
            open: false,
            ring: false,
            count: 0,
            items: [],
            lastMax: null,
            init: function () {
                var self = this;
                if (!KZA.api.notifications) { return; }
                self.load(true);
                setInterval(function () { if (!document.hidden) { self.load(false); } }, POLL_MS);
            },
            load: function (first) {
                var self = this;
                return request(KZA.api.notifications).then(function (d) {
                    if (!d || !d.ok) { return; }
                    var newest = d.items.length ? d.items[0].id : 0;
                    if (!first && self.lastMax !== null && newest > self.lastMax) {
                        self.ring = true;
                        setTimeout(function () { self.ring = false; }, 1200);
                        showToast('Có đơn hàng mới: ' + d.items[0].code + ' (' + d.items[0].total + ')');
                    }
                    self.lastMax = Math.max(self.lastMax || 0, newest);
                    self.count = d.count;
                    self.items = d.items;
                }).catch(function () { /* mất mạng: thử lại lần sau */ });
            },
            toggle: function () { this.open = !this.open; if (this.open) { this.load(false); } },
            markAll: function () {
                var self = this;
                request(KZA.api.notifications, { action: 'mark_all' }).then(function () { self.count = 0; self.items = []; });
            }
        });
    });

    // Form có data-confirm: hỏi lại trước khi gửi (ví dụ nút Xóa).
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.getAttribute && form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) { event.preventDefault(); }
    });

    // Ô chọn có data-autosubmit: đổi lựa chọn thì gửi form luôn.
    document.addEventListener('change', function (event) {
        var el = event.target;
        if (el.matches && el.matches('[data-autosubmit]') && el.form) { el.form.submit(); }
    });

    // Ảnh lỗi thì thay bằng ảnh mẫu.
    document.addEventListener('error', function (event) {
        var img = event.target;
        if (img instanceof HTMLImageElement && img.dataset.fallback && img.getAttribute('src') !== img.dataset.fallback) {
            img.setAttribute('src', img.dataset.fallback);
        }
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        var gsap = window.gsap;
        if (!gsap || reduceMotion) { return; }
        gsap.utils.toArray('[data-count]').forEach(function (el) {
            var end = parseFloat(el.getAttribute('data-count')) || 0;
            var obj = { v: 0 };
            gsap.to(obj, { v: end, duration: 1.4, ease: 'power2.out', onUpdate: function () {
                el.textContent = Math.round(obj.v).toLocaleString('vi-VN') + (el.getAttribute('data-suffix') || '');
            } });
        });
        var blocks = document.querySelectorAll('[data-reveal]');
        if (blocks.length) {
            gsap.from(blocks, { y: 20, opacity: 0, duration: 0.5, stagger: 0.05, ease: 'power3.out', clearProps: 'all' });
        }
    });
})();

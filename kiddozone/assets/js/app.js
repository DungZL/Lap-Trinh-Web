/* KiddoZone - JavaScript trang khách.
 * Nạp ở cuối includes/footer.php, TRƯỚC Alpine.js (các script đều defer nên chạy theo thứ tự).
 * - Alpine stores: theme (sáng/tối), toast, cart (ngăn kéo giỏ hàng), quick (xem nhanh)
 * - Component: siteHeader (mega menu), heroSlider (banner trượt), stickyBuy (thanh mua dính),
 *   liveSearch, couponBox, countdown, giftFinder, productGallery
 * - Hiệu ứng: hiện dần khi cuộn, banner, bay vào giỏ, đếm số, pháo giấy, thẻ nghiêng 3D,
 *   chuyển trang (View Transitions), trạng thái đang tải khi lọc
 * Cấu hình server truyền qua window.KZ (includes/header.php).
 */
(function () {
    'use strict';

    var KZ = window.KZ || { api: {}, urls: {}, csrf: '' };
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- Tiện ích gọi API ---------- */
    function request(url, options) {
        options = options || {};
        var headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
        var init = { method: options.method || 'GET', credentials: 'same-origin', headers: headers };
        if (options.data) {
            var body = new FormData();
            Object.keys(options.data).forEach(function (k) { body.append(k, options.data[k]); });
            body.append('csrf_token', KZ.csrf);
            init.method = 'POST';
            init.body = body;
        }
        return fetch(url, init).then(function (res) {
            return res.text().then(function (text) {
                var data;
                try { data = JSON.parse(text); } catch (e) {
                    console.error('[KiddoZone] Phản hồi không phải JSON (HTTP ' + res.status + '):', text.slice(0, 300));
                    throw new Error('Máy chủ phản hồi không hợp lệ, vui lòng thử lại.');
                }
                if (data && data.login) { window.location.href = data.login; throw new Error('login'); }
                return data;
            });
        });
    }
    window.kzRequest = request;

    /* ---------- Hộp thoại (ngăn kéo giỏ hàng, xem nhanh): focus và inert ---------- */
    var dialogReturnFocus = null;
    var openDialogs = 0;
    function focusables(root) {
        return Array.prototype.filter.call(
            root.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])'),
            function (el) { return el.getClientRects().length > 0; }
        );
    }
    function setPageInert(on) {
        document.querySelectorAll('[data-page-chrome]').forEach(function (el) {
            if (on) { el.setAttribute('inert', ''); } else { el.removeAttribute('inert'); }
        });
        document.documentElement.style.overflow = on ? 'hidden' : '';
    }
    function openDialog(id) {
        if (openDialogs === 0) {
            dialogReturnFocus = document.activeElement;
            setPageInert(true);
        }
        openDialogs++;
        // Chờ Alpine hiện hộp thoại rồi mới đặt focus vào nút Đóng.
        var tries = 0;
        (function tryFocus() {
            var d = document.getElementById(id);
            var target = d && (d.querySelector('[data-autofocus]') || focusables(d)[0]);
            if (target && target.getClientRects().length) { target.focus(); return; }
            if (++tries < 40) { setTimeout(tryFocus, 25); }
        })();
    }
    function closeDialog() {
        openDialogs = Math.max(0, openDialogs - 1);
        if (openDialogs > 0) { return; }
        setPageInert(false);
        var back = dialogReturnFocus;
        dialogReturnFocus = null;
        if (back && document.contains(back) && back.focus) { back.focus(); }
    }
    // Giữ phím Tab vòng trong hộp thoại đang mở.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab' || openDialogs === 0) { return; }
        var d = Array.prototype.filter.call(document.querySelectorAll('[data-dialog]'), function (el) {
            return el.getClientRects().length > 0;
        }).pop();
        if (!d) { return; }
        var items = focusables(d);
        if (!items.length) { return; }
        var first = items[0], last = items[items.length - 1];
        if (!d.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
        else if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    function money(n) { return Number(n || 0).toLocaleString('vi-VN') + ' ₫'; }
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------- Alpine: store và component ---------- */
    document.addEventListener('alpine:init', function () {
        var Alpine = window.Alpine;

        /* Chế độ sáng / tối (#10) */
        Alpine.store('theme', {
            dark: document.documentElement.classList.contains('dark'),
            toggle: function () {
                this.dark = !this.dark;
                document.documentElement.classList.toggle('dark', this.dark);
                try { localStorage.setItem('kz-theme', this.dark ? 'dark' : 'light'); } catch (e) { /* bỏ qua */ }
            }
        });

        /* Header: mega menu danh mục. Máy có chuột thì rê để mở; bấm và bàn phím luôn dùng được. */
        Alpine.data('siteHeader', function () {
            var timer = null;
            var openedAt = 0;
            var canHover = window.matchMedia('(hover: hover)').matches;
            return {
                menu: false,
                mega: false,
                hoverMega: function (on) {
                    var self = this;
                    if (!canHover) { return; }
                    clearTimeout(timer);
                    timer = setTimeout(function () {
                        if (on && !self.mega) { openedAt = Date.now(); }
                        self.mega = on;
                    }, on ? 120 : 220);
                },
                toggleMega: function (event) {
                    clearTimeout(timer);
                    // Vừa mở bằng rê chuột thì cú bấm ngay sau đó không đóng lại.
                    if (this.mega && Date.now() - openedAt < 600) { return; }
                    this.mega = !this.mega;
                    openedAt = 0;
                    // Mở bằng bàn phím (Enter/Space cho click có detail = 0): đưa focus vào link đầu tiên của bảng.
                    if (this.mega && event && event.detail === 0) {
                        this.$nextTick(function () {
                            setTimeout(function () {
                                var first = document.querySelector('#kz-mega a[href]');
                                if (first) { first.focus(); }
                            }, 30);
                        });
                    }
                },
                closeMega: function (focusButton) {
                    clearTimeout(timer);
                    if (!this.mega) { return; }
                    this.mega = false;
                    if (focusButton && this.$refs.megaBtn) { this.$refs.megaBtn.focus(); }
                }
            };
        });

        /* Banner trượt trang chủ: tự chuyển, dừng khi rê chuột / focus / tab ẩn, vuốt được, có nút tạm dừng. */
        Alpine.data('heroSlider', function (count) {
            var DURATION = 6500;
            var STEP = 100;
            return {
                active: 0,
                count: count,
                playing: !reduceMotion && count > 1,
                hover: false,
                visible: true,
                elapsed: 0,
                startX: null,
                timer: null,
                get progress() { return Math.min(100, this.elapsed / DURATION * 100); },
                init: function () {
                    var self = this;
                    if (count < 2) { return; }
                    if ('IntersectionObserver' in window) {
                        new IntersectionObserver(function (entries) { self.visible = entries[0].isIntersecting; }).observe(self.$el);
                    }
                    self.timer = setInterval(function () {
                        if (!self.playing || self.hover || !self.visible || document.hidden) { return; }
                        self.elapsed += STEP;
                        if (self.elapsed >= DURATION) { self.next(); }
                    }, STEP);
                },
                destroy: function () { clearInterval(this.timer); },
                go: function (i) { this.active = (i + this.count) % this.count; this.elapsed = 0; },
                next: function () { this.go(this.active + 1); },
                prev: function () { this.go(this.active - 1); },
                swipeStart: function (e) { this.startX = e.clientX; },
                swipeEnd: function (e) {
                    if (this.startX === null) { return; }
                    var dx = e.clientX - this.startX;
                    this.startX = null;
                    if (Math.abs(dx) > 60) { if (dx < 0) { this.next(); } else { this.prev(); } }
                }
            };
        });

        /* Thanh "Thêm vào giỏ" dính dưới màn hình (trang chi tiết): hiện khi đã cuộn qua form mua chính. */
        Alpine.data('stickyBuy', function () {
            return {
                show: false,
                init: function () {
                    var self = this;
                    var form = document.getElementById('kz-buy-form');
                    if (!form || !('IntersectionObserver' in window)) { return; }
                    new IntersectionObserver(function (entries) {
                        var e = entries[0];
                        self.show = !e.isIntersecting && e.boundingClientRect.top < 0;
                    }).observe(form);
                }
            };
        });

        /* Thông báo nhỏ góc màn hình */
        Alpine.store('toast', {
            show: function (message, type) { showToast(message, type); }
        });

        /* Giỏ hàng (#1): ngăn kéo bên phải, cập nhật bằng api/cart.php */
        Alpine.store('cart', {
            isOpen: false,
            loading: false,
            loaded: false,
            busy: null,
            bump: false,
            count: Number(KZ.cartCount || 0),
            items: [],
            subtotal: 0,
            subtotalText: '0 ₫',
            discountText: '0 ₫',
            totalText: '0 ₫',
            coupon: null,

            apply: function (data) {
                if (!data || !data.cart) { return; }
                var c = data.cart;
                this.items = c.items;
                this.subtotal = c.subtotal;
                this.subtotalText = c.subtotal_text;
                this.discountText = c.discount_text;
                this.totalText = c.total_text;
                this.coupon = c.coupon;
                if (this.count !== c.count) {
                    this.count = c.count;
                    this.bump = false;
                    var self = this;
                    requestAnimationFrame(function () { self.bump = true; setTimeout(function () { self.bump = false; }, 500); });
                }
                this.loaded = true;
                document.dispatchEvent(new CustomEvent('kz:cart-updated', { detail: c }));
            },
            load: function () {
                var self = this;
                self.loading = true;
                return request(KZ.api.cart).then(function (d) { self.apply(d); })
                    .catch(function (e) { if (e.message !== 'login') { showToast(e.message, 'danger'); } })
                    .finally(function () { self.loading = false; });
            },
            show: function () {
                if (this.isOpen) { return; }
                this.isOpen = true;
                openDialog('kz-cart-drawer');
                if (!this.loaded) { this.load(); }
            },
            hide: function () {
                if (!this.isOpen) { return; }
                this.isOpen = false;
                closeDialog();
            },
            send: function (action, productId, quantity) {
                var self = this;
                self.busy = productId;
                return request(KZ.api.cart, { data: { action: action, product_id: productId, quantity: quantity || 1 } })
                    .then(function (d) {
                        self.apply(d);
                        if (d.message && action !== 'update') { showToast(d.message, d.type || (d.ok ? 'success' : 'danger')); }
                        return d;
                    })
                    .catch(function (e) { if (e.message !== 'login') { showToast(e.message, 'danger'); } throw e; })
                    .finally(function () { self.busy = null; });
            },
            add: function (productId, quantity, sourceEl) {
                var self = this;
                flyToCart(sourceEl);
                return this.send('add', productId, quantity).then(function (d) {
                    if (d.ok) { setTimeout(function () { self.show(); }, reduceMotion ? 0 : 450); }
                    return d;
                });
            },
            setQty: function (item, qty) {
                if (qty < 1) { return this.remove(item); }
                return this.send('update', item.id, Math.min(qty, item.max));
            },
            remove: function (item) { return this.send('remove', item.id, 1); }
        });

        /* Xem nhanh sản phẩm (#9) */
        Alpine.store('quick', {
            isOpen: false,
            loading: false,
            product: null,
            show: function (id) {
                var self = this;
                if (!self.isOpen) { openDialog('kz-quick-view'); }
                self.isOpen = true;
                self.loading = true;
                self.product = null;
                request(KZ.api.product + '?id=' + encodeURIComponent(id))
                    .then(function (d) {
                        if (!d.ok) { throw new Error(d.message || 'Không tải được sản phẩm.'); }
                        self.product = d.product;
                    })
                    .catch(function (e) { showToast(e.message, 'danger'); self.hide(); })
                    .finally(function () { self.loading = false; });
            },
            hide: function () {
                if (!this.isOpen) { return; }
                this.isOpen = false;
                closeDialog();
            }
        });

        /* Tìm kiếm tức thì (#2) */
        Alpine.data('liveSearch', function (initial) {
            var timer = null;
            var lastQuery = '';
            return {
                q: initial || '',
                results: [],
                open: false,
                loading: false,
                active: -1,
                search: function () {
                    var self = this;
                    var q = self.q.trim();
                    if (q.length < 2) { self.open = false; self.results = []; return; }
                    if (q === lastQuery) { self.open = true; return; }
                    lastQuery = q;
                    self.loading = true;
                    self.open = true;
                    clearTimeout(timer);
                    request(KZ.api.search + '?q=' + encodeURIComponent(q))
                        .then(function (d) {
                            if (self.q.trim() !== q) { return; } // đã gõ tiếp, bỏ kết quả cũ
                            self.results = d.items || [];
                            self.active = -1;
                        })
                        .catch(function () { self.results = []; })
                        .finally(function () { self.loading = false; });
                },
                move: function (step) {
                    if (!this.open || !this.results.length) { return; }
                    this.active = (this.active + step + this.results.length) % this.results.length;
                },
                choose: function (event) {
                    if (this.open && this.active >= 0 && this.results[this.active]) {
                        event.preventDefault();
                        window.location.href = this.results[this.active].url;
                    }
                },
                close: function () { this.open = false; this.active = -1; },
                highlight: function (name) {
                    var safe = escapeHtml(name);
                    var q = this.q.trim();
                    if (!q) { return safe; }
                    var re = new RegExp('(' + escapeHtml(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
                    return safe.replace(re, '<mark class="rounded bg-sun-200 px-0.5 text-ink-900">$1</mark>');
                }
            };
        });

        /* Ô nhập mã giảm giá (#6) - dùng ở cart.php và checkout.php */
        Alpine.data('couponBox', function (current) {
            return {
                code: current || '',
                applied: current || '',
                loading: false,
                message: '',
                ok: null,
                submit: function () {
                    var self = this;
                    if (!self.code.trim()) { return; }
                    self.loading = true;
                    request(KZ.api.coupon, { data: { action: 'apply', code: self.code.trim() } })
                        .then(function (d) {
                            self.ok = d.ok;
                            self.message = d.message;
                            if (d.ok) {
                                self.applied = d.code;
                                self.code = d.code;
                                celebrate();
                                // Tải lại để tổng tiền phía server hiển thị đúng.
                                setTimeout(function () { window.location.reload(); }, 900);
                            }
                        })
                        .catch(function (e) { self.ok = false; self.message = e.message; })
                        .finally(function () { self.loading = false; });
                },
                clear: function () {
                    var self = this;
                    self.loading = true;
                    request(KZ.api.coupon, { data: { action: 'remove' } })
                        .then(function () { window.location.reload(); })
                        .finally(function () { self.loading = false; });
                }
            };
        });

        /* Đồng hồ đếm ngược flash sale (#5). end: chuỗi ISO 8601 */
        Alpine.data('countdown', function (end) {
            var endAt = new Date(end).getTime();
            return {
                d: '00', h: '00', m: '00', s: '00', done: false,
                init: function () {
                    var self = this;
                    var pad = function (n) { return String(n).padStart(2, '0'); };
                    var tick = function () {
                        var left = Math.max(0, endAt - Date.now());
                        self.done = left <= 0;
                        self.d = pad(Math.floor(left / 86400000));
                        self.h = pad(Math.floor(left / 3600000) % 24);
                        self.m = pad(Math.floor(left / 60000) % 60);
                        self.s = pad(Math.floor(left / 1000) % 60);
                        if (self.done) { clearInterval(timer); }
                    };
                    var timer = setInterval(tick, 1000);
                    tick();
                }
            };
        });

        /* Trợ lý chọn quà (#4) - gift-finder.php */
        Alpine.data('giftFinder', function (config) {
            return {
                step: 0,
                age: '',
                category: '',
                budget: '',
                loading: false,
                items: [],
                total: 0,
                ages: config.ages,
                categories: config.categories,
                budgets: config.budgets,
                get progress() { return Math.min(100, (this.step / 3) * 100); },
                pick: function (field, value) {
                    this[field] = value;
                    var self = this;
                    setTimeout(function () { self.next(); }, reduceMotion ? 0 : 220);
                },
                next: function () {
                    this.step++;
                    if (this.step === 3) { this.fetchResults(); }
                    this.$nextTick(function () { animateStep(); });
                },
                back: function () {
                    if (this.step > 0) { this.step--; this.$nextTick(function () { animateStep(); }); }
                },
                restart: function () {
                    this.step = 0; this.age = ''; this.category = ''; this.budget = ''; this.items = [];
                    this.$nextTick(function () { animateStep(); });
                },
                label: function (list, key) {
                    var found = list.filter(function (o) { return String(o.key) === String(key); })[0];
                    return found ? found.label : 'Tất cả';
                },
                moreUrl: function () {
                    var p = new URLSearchParams();
                    if (this.age) { p.set('age', this.age); }
                    if (this.category) { p.set('category', this.category); }
                    if (this.budget) { p.set('max', this.budget); }
                    return KZ.urls.products + (p.toString() ? '?' + p.toString() : '');
                },
                fetchResults: function () {
                    var self = this;
                    self.loading = true;
                    var p = new URLSearchParams({ age: self.age, category: self.category, max: self.budget, limit: 8 });
                    request(KZ.api.products + '?' + p.toString())
                        .then(function (d) {
                            self.items = d.items || [];
                            self.total = d.total || 0;
                            if (self.items.length) { celebrate(); }
                            self.$nextTick(function () { revealIn(document.querySelectorAll('[data-gift-result]')); });
                        })
                        .catch(function (e) { showToast(e.message, 'danger'); })
                        .finally(function () { self.loading = false; });
                },
                addToCart: function (item, event) { Alpine.store('cart').add(item.id, 1, event.currentTarget); }
            };
        });

        /* Thư viện ảnh trang chi tiết */
        Alpine.data('productGallery', function (images) {
            return {
                images: images,
                current: 0,
                zoom: false,
                origin: '50% 50%',
                go: function (i) { this.current = (i + this.images.length) % this.images.length; },
                track: function (e) {
                    var r = e.currentTarget.getBoundingClientRect();
                    this.origin = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%';
                }
            };
        });
    });

    /* ---------- Toast tạo bằng JS ---------- */
    function showToast(message, type) {
        if (!message) { return; }
        var root = document.querySelector('[data-toast-root]');
        if (!root) { return; }
        var tones = { success: 'alert-success', danger: 'alert-danger', warning: 'alert-warning', info: 'alert-info' };
        var icons = { success: 'ph-check-circle', danger: 'ph-warning-circle', warning: 'ph-warning', info: 'ph-info' };
        type = tones[type] ? type : 'success';
        var el = document.createElement('div');
        el.className = 'alert ' + tones[type] + ' pointer-events-auto w-full max-w-sm animate-pop shadow-soft backdrop-blur transition duration-300';
        var sticky = type === 'danger' || type === 'warning';
        el.setAttribute('role', sticky ? 'alert' : 'status');
        el.innerHTML = '<i aria-hidden="true" class="ph-fill ' + icons[type] + ' mt-0.5 text-lg"></i><span class="flex-1"></span>' +
            '<button type="button" class="opacity-60 hover:opacity-100" aria-label="Đóng"><i aria-hidden="true" class="ph-bold ph-x"></i></button>';
        el.querySelector('span').textContent = message;
        var remove = function () { el.style.opacity = '0'; el.style.transform = 'translateY(-6px)'; setTimeout(function () { el.remove(); }, 300); };
        el.querySelector('button').addEventListener('click', remove);
        root.appendChild(el);
        if (!sticky) {
            var timer = setTimeout(remove, 4000);
            el.addEventListener('mouseenter', function () { clearTimeout(timer); });
            el.addEventListener('focusin', function () { clearTimeout(timer); });
        }
    }
    window.kzToast = showToast;

    /* ---------- Hiệu ứng: sản phẩm bay vào giỏ ---------- */
    function flyToCart(sourceEl) {
        var target = document.querySelector('[data-cart-target]');
        if (!sourceEl || !target || reduceMotion || !window.gsap) { return; }
        var card = sourceEl.closest('[data-product-card], [data-fly-source]');
        var img = card ? card.querySelector('img') : document.querySelector('[data-fly-source] img');
        if (!img) { return; }
        var from = img.getBoundingClientRect();
        var to = target.getBoundingClientRect();
        var ghost = img.cloneNode();
        ghost.removeAttribute('loading');
        ghost.setAttribute('aria-hidden', 'true');
        ghost.style.cssText = 'position:fixed;z-index:80;pointer-events:none;object-fit:cover;border-radius:24px;transform-origin:0 0;' +
            'box-shadow:0 20px 40px -10px rgba(0,0,0,.35);' +
            'left:' + from.left + 'px;top:' + from.top + 'px;width:' + from.width + 'px;height:' + from.height + 'px;';
        document.body.appendChild(ghost);
        var size = 36;
        var scale = size / Math.max(from.width, 1);
        window.gsap.timeline({ onComplete: function () {
            ghost.remove();
            window.gsap.fromTo(target, { scale: 1 }, { scale: 1.12, yoyo: true, repeat: 1, duration: 0.15, ease: 'power2.out' });
        } })
            .to(ghost, { duration: 0.22, scale: 0.6, rotate: -8, ease: 'power2.out' })
            .to(ghost, {
                duration: 0.55,
                x: to.left + to.width / 2 - from.left - size / 2,
                y: to.top + to.height / 2 - from.top - size / 2,
                scale: scale, rotate: 20, opacity: 0.5,
                ease: 'power3.in'
            });
    }

    /* ---------- Hiệu ứng: pháo giấy (không cần thư viện) ---------- */
    function celebrate(originEl) {
        if (reduceMotion) { return; }
        var canvas = document.createElement('canvas');
        canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:90';
        document.body.appendChild(canvas);
        var ctx = canvas.getContext('2d');
        var dpr = window.devicePixelRatio || 1;
        canvas.width = innerWidth * dpr;
        canvas.height = innerHeight * dpr;
        ctx.scale(dpr, dpr);
        var colors = ['#ffc93c', '#4f93fb', '#ff6b6b', '#2ecc9a', '#9b7bff'];
        var ox = innerWidth / 2, oy = innerHeight / 3;
        if (originEl) { var r = originEl.getBoundingClientRect(); ox = r.left + r.width / 2; oy = r.top + r.height / 2; }
        var parts = [];
        for (var i = 0; i < 140; i++) {
            var a = Math.random() * Math.PI * 2, v = 4 + Math.random() * 8;
            parts.push({ x: ox, y: oy, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 4, w: 6 + Math.random() * 6, h: 4 + Math.random() * 4,
                c: colors[i % colors.length], r: Math.random() * 6, vr: (Math.random() - .5) * .3, life: 0 });
        }
        var frame = 0;
        (function loop() {
            ctx.clearRect(0, 0, innerWidth, innerHeight);
            parts.forEach(function (p) {
                p.vy += 0.22; p.vx *= 0.99; p.x += p.vx; p.y += p.vy; p.r += p.vr; p.life++;
                ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r);
                ctx.globalAlpha = Math.max(0, 1 - p.life / 110);
                ctx.fillStyle = p.c; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); ctx.restore();
            });
            if (++frame < 120) { requestAnimationFrame(loop); } else { canvas.remove(); }
        })();
    }
    window.kzCelebrate = celebrate;

    /* ---------- Hiệu ứng GSAP khi tải trang và khi cuộn ---------- */
    function revealIn(elements) {
        if (!window.gsap || reduceMotion || !elements.length) { return; }
        window.gsap.fromTo(elements, { y: 28, opacity: 0, scale: 0.97 },
            { y: 0, opacity: 1, scale: 1, duration: 0.6, ease: 'back.out(1.6)', stagger: 0.06, clearProps: 'transform' });
    }

    function animateStep() {
        var el = document.querySelector('[data-step-active]');
        if (el && window.gsap && !reduceMotion) {
            window.gsap.fromTo(el.querySelectorAll('[data-step-item]'), { y: 24, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.45, ease: 'back.out(1.7)', stagger: 0.05 });
        }
    }

    function initMotion() {
        var gsap = window.gsap;
        if (!gsap || reduceMotion) { return; }
        if (window.ScrollTrigger) { gsap.registerPlugin(window.ScrollTrigger); }

        // Banner trang chủ: chữ hiện lần lượt, đồ chơi nảy vào
        var hero = document.querySelector('[data-hero]');
        if (hero) {
            var tl = gsap.timeline({ defaults: { ease: 'back.out(1.7)' } });
            var heroText = hero.querySelectorAll('[data-hero-text]');
            var heroToys = hero.querySelectorAll('[data-hero-toy]');
            if (heroText.length) { tl.from(heroText, { y: 40, opacity: 0, duration: 0.7, stagger: 0.12, ease: 'power3.out' }); }
            if (heroToys.length) { tl.from(heroToys, { scale: 0, rotate: -40, opacity: 0, duration: 0.8, stagger: 0.08 }, heroText.length ? '-=0.5' : 0); }

            // Đồ chơi lệch theo chuột (hiệu ứng chiều sâu)
            hero.addEventListener('pointermove', function (e) {
                var r = hero.getBoundingClientRect();
                var x = (e.clientX - r.left) / r.width - 0.5;
                var y = (e.clientY - r.top) / r.height - 0.5;
                hero.querySelectorAll('[data-depth]').forEach(function (el) {
                    var d = parseFloat(el.getAttribute('data-depth')) || 10;
                    gsap.to(el, { x: x * d, y: y * d, duration: 0.6, ease: 'power2.out' });
                });
            });
        }

        // Các khối có data-reveal hiện dần khi cuộn tới
        var items = gsap.utils.toArray('[data-reveal]');
        if (window.ScrollTrigger && items.length) {
            gsap.set(items, { opacity: 0, y: 30 });
            window.ScrollTrigger.batch(items, {
                start: 'top 92%',
                once: true,
                onEnter: function (batch) {
                    gsap.to(batch, { opacity: 1, y: 0, duration: 0.7, ease: 'power3.out', stagger: 0.07, overwrite: true, clearProps: 'transform' });
                }
            });
        }

        // Số đếm tăng dần (data-count="1234")
        gsap.utils.toArray('[data-count]').forEach(function (el) {
            var end = parseFloat(el.getAttribute('data-count')) || 0;
            var obj = { v: 0 };
            gsap.to(obj, {
                v: end, duration: 1.6, ease: 'power2.out',
                scrollTrigger: window.ScrollTrigger ? { trigger: el, start: 'top 90%', once: true } : undefined,
                onUpdate: function () { el.textContent = Math.round(obj.v).toLocaleString('vi-VN') + (el.getAttribute('data-suffix') || ''); }
            });
        });

        // Thanh tiến độ dòng thời gian đơn hàng chạy từ trái sang
        var bar = document.querySelector('[data-timeline-bar]');
        if (bar) {
            gsap.from(bar, { scaleX: 0, duration: 1.2, ease: 'power3.inOut', delay: 0.2 });
            if (document.querySelector('[data-timeline-step]')) gsap.from('[data-timeline-step]', { y: 16, opacity: 0, duration: 0.5, stagger: 0.12, ease: 'back.out(1.7)', delay: 0.2 });
        }

        // Thanh bước mua hàng: đoạn nối tới bước hiện tại chạy dần
        var stepFill = document.querySelector('[data-step-fill]');
        if (stepFill) { gsap.from(stepFill, { width: 0, duration: 0.8, ease: 'power2.out', delay: 0.3 }); }

        if (document.querySelector('[data-celebrate]')) { setTimeout(function () { celebrate(); }, 400); }
    }

    /* ---------- Nút thêm vào giỏ: hiện dấu tích một lúc ---------- */
    function cartButtonDone(btn) {
        var icon = btn.querySelector('i');
        if (!icon || !btn.classList.contains('btn-icon')) { return; }
        var old = icon.className;
        icon.className = 'ph-bold ph-check text-lg';
        btn.classList.add('kz-added');
        if (window.gsap && !reduceMotion) {
            window.gsap.fromTo(btn, { scale: 0.7 }, { scale: 1, duration: 0.6, ease: 'elastic.out(1.1, 0.45)' });
        }
        setTimeout(function () { icon.className = old; btn.classList.remove('kz-added'); }, 1600);
    }

    /* ---------- Thẻ sản phẩm nghiêng theo chuột + vệt sáng (chỉ máy có chuột; tắt khi giảm chuyển động) ---------- */
    if (!reduceMotion && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        var tiltCard = null;
        var tiltFrame = 0;
        var resetTilt = function (card) {
            card.classList.remove('is-tilting');
            ['--rx', '--ry'].forEach(function (k) { card.style.removeProperty(k); });
        };
        document.addEventListener('pointermove', function (e) {
            var card = e.target.closest ? e.target.closest('[data-tilt]') : null;
            if (tiltCard && tiltCard !== card) { resetTilt(tiltCard); }
            tiltCard = card;
            if (!card) { return; }
            cancelAnimationFrame(tiltFrame);
            tiltFrame = requestAnimationFrame(function () {
                var r = card.getBoundingClientRect();
                var x = (e.clientX - r.left) / r.width;
                var y = (e.clientY - r.top) / r.height;
                card.classList.add('is-tilting');
                card.style.setProperty('--ry', ((x - 0.5) * 10).toFixed(2) + 'deg');
                card.style.setProperty('--rx', ((0.5 - y) * 8).toFixed(2) + 'deg');
                card.style.setProperty('--mx', (x * 100).toFixed(1) + '%');
                card.style.setProperty('--my', (y * 100).toFixed(1) + '%');
            });
        }, { passive: true });
        document.addEventListener('mouseout', function (e) {
            if (!e.relatedTarget && tiltCard) { resetTilt(tiltCard); tiltCard = null; }
        });
    }

    /* ---------- Chuyển trang: ảnh thẻ sản phẩm "bay" thành ảnh lớn ở trang chi tiết (View Transitions) ---------- */
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var link = e.target.closest && e.target.closest('[data-product-card] h3 a');
        if (!link) { return; }
        var img = link.closest('[data-product-card]').querySelector('img');
        document.querySelectorAll('[data-vt-named]').forEach(function (el) { el.style.viewTransitionName = ''; el.removeAttribute('data-vt-named'); });
        if (img) { img.style.viewTransitionName = 'kz-product-img'; img.setAttribute('data-vt-named', ''); }
    });

    /* ---------- Form có data-loading="#vung": làm mờ vùng kết quả trong lúc tải trang mới ---------- */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var sel = form.getAttribute && form.getAttribute('data-loading');
        if (!sel || event.defaultPrevented) { return; }
        var target = document.querySelector(sel);
        if (target) { target.classList.add('is-loading'); target.setAttribute('aria-busy', 'true'); }
    });
    // Quay lại trang bằng nút Back (bfcache): bỏ trạng thái đang tải.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('.is-loading').forEach(function (el) { el.classList.remove('is-loading'); el.removeAttribute('aria-busy'); });
    });

    /* ---------- Hành vi chung (không cần Alpine) ---------- */

    // Ảnh lỗi thì thay bằng ảnh mẫu (thuộc tính data-fallback).
    function useFallback(img) {
        var fallback = img.getAttribute('data-fallback');
        if (fallback && img.getAttribute('src') !== fallback) { img.setAttribute('src', fallback); }
    }
    document.addEventListener('error', function (event) {
        if (event.target instanceof HTMLImageElement) { useFallback(event.target); }
    }, true);

    // Ô chọn có data-autosubmit: đổi lựa chọn thì gửi form luôn.
    // Riêng radio đổi bằng phím mũi tên thì không gửi (để duyệt qua các lựa chọn); bấm Enter hoặc nút "Áp dụng" để lọc.
    var lastPointer = 0;
    document.addEventListener('pointerdown', function () { lastPointer = Date.now(); }, true);
    document.addEventListener('change', function (event) {
        var el = event.target;
        if (!el.matches || !el.matches('[data-autosubmit]') || !el.form) { return; }
        if (el.type === 'radio' && Date.now() - lastPointer > 1000) { return; }
        if (el.form.requestSubmit) { el.form.requestSubmit(); } else { el.form.submit(); }
    });

    // Form "Thêm vào giỏ" có data-cart-form: gửi bằng fetch, mở ngăn kéo giỏ hàng.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches || !form.matches('[data-cart-form]') || !window.Alpine) { return; }
        event.preventDefault();
        var fd = new FormData(form);
        var btn = event.submitter || form.querySelector('[type="submit"]');
        if (btn && btn.getAttribute('aria-busy') === 'true') { return; }
        if (btn) { btn.setAttribute('aria-busy', 'true'); }
        window.Alpine.store('cart').add(fd.get('product_id'), fd.get('quantity') || 1, btn)
            .then(function (d) { if (d && d.ok && btn) { cartButtonDone(btn); } })
            .catch(function () { /* đã báo lỗi bằng toast */ })
            .finally(function () { if (btn) { btn.removeAttribute('aria-busy'); } });
    });

    // Nút trái tim yêu thích: gửi bằng fetch, đổi tim ngay không tải lại trang.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.classList || !form.classList.contains('kz-wish-form')) { return; }
        event.preventDefault();
        var btn = form.querySelector('.kz-wish-btn');
        btn.disabled = true;
        fetch(form.action, {
            method: 'POST', body: new FormData(form), credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (res) { return res.json(); })
          .then(function (data) {
              btn.disabled = false;
              if (data.login) { window.location.href = data.login; return; }
              if (!data.ok) { showToast(data.message || 'Không thực hiện được, vui lòng thử lại.', 'danger'); return; }
              var active = !!data.active;
              var text = active ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích';
              form.querySelector('input[name="action"]').value = active ? 'remove' : 'add';
              btn.classList.toggle('is-active', active);
              btn.classList.toggle('text-price', active);
              btn.classList.toggle('text-muted', !active);
              btn.setAttribute('aria-pressed', active ? 'true' : 'false');
              btn.setAttribute('aria-label', text);
              btn.setAttribute('title', text);
              var icon = btn.querySelector('i');
              icon.classList.toggle('ph-fill', active);
              icon.classList.toggle('ph-bold', !active);
              var label = btn.querySelector('span');
              if (label) { label.textContent = text; }
              if (active && window.gsap && !reduceMotion) {
                  window.gsap.fromTo(icon, { scale: 0.4 }, { scale: 1, duration: 0.6, ease: 'elastic.out(1.2, 0.4)' });
              }
              showToast(data.message, 'success');
              // Trang yêu thích: bỏ tim thì gỡ thẻ khỏi trang.
              var page = document.querySelector('[data-wishlist-page]');
              var card = form.closest('[data-product-card]');
              if (!active && page && card && page.contains(card)) {
                  var done = function () {
                      card.remove();
                      var left = page.querySelectorAll('[data-product-card]').length;
                      if (!left) { window.location.reload(); return; }
                      var counter = document.querySelector('[data-wishlist-count]');
                      if (counter) { counter.textContent = left; }
                  };
                  if (window.gsap && !reduceMotion) { window.gsap.to(card, { scale: 0.8, opacity: 0, duration: 0.3, onComplete: done }); } else { done(); }
              }
          })
          .catch(function () { btn.disabled = false; form.submit(); });
    });

    // Chạy hiệu ứng sau khi GSAP (defer) đã nạp.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMotion);
    } else {
        initMotion();
    }
})();

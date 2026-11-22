/* Nâng cấp mọi <select class="select"> thành dropdown tùy biến đồng bộ giao diện KiddoZone.
 * Giữ nguyên <select> gốc (ẩn trực quan) để form submit, required, và data-autosubmit hoạt động bình thường.
 */
(function () {
    'use strict';

    var openWrap = null;
    var uid = 0;

    function closeCurrent(returnFocus) {
        if (!openWrap) { return; }
        var wrap = openWrap;
        openWrap = null;
        wrap.classList.remove('is-open');
        var btn = wrap.querySelector('.kz-select-btn');
        if (btn) {
            btn.setAttribute('aria-expanded', 'false');
            if (returnFocus) { btn.focus(); }
        }
        wrap.querySelectorAll('.kz-select-opt.is-focused').forEach(function (el) {
            el.classList.remove('is-focused');
        });
    }

    function enhanceSelect(select) {
        if (select.dataset.kzSelect) { return; }
        select.dataset.kzSelect = '1';

        var menuId = 'kz-select-menu-' + (++uid);
        var wrap = document.createElement('div');
        wrap.className = 'kz-select ' + (select.classList.contains('w-auto') ? 'kz-select-auto' : 'w-full');

        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.classList.add('kz-select-native');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'kz-select-btn' + (select.className.indexOf('rounded-full') !== -1 ? ' is-pill' : '');
        btn.disabled = select.disabled;
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', menuId);
        if (select.getAttribute('aria-label')) {
            btn.setAttribute('aria-label', select.getAttribute('aria-label'));
        }

        var labelSpan = document.createElement('span');
        labelSpan.className = 'kz-select-label';
        labelSpan.id = menuId + '-val';

        if (select.id) {
            var extLabel = document.querySelector('label[for="' + CSS.escape(select.id) + '"]');
            if (extLabel) {
                if (!extLabel.id) { extLabel.id = menuId + '-lbl'; }
                btn.setAttribute('aria-labelledby', extLabel.id + ' ' + labelSpan.id);
                extLabel.addEventListener('click', function (e) {
                    if (!btn.disabled) {
                        e.preventDefault();
                        btn.focus();
                    }
                });
            }
        }

        var iconEl = document.createElement('i');
        iconEl.className = 'kz-select-icon ph-fill hidden';
        iconEl.setAttribute('aria-hidden', 'true');

        var caret = document.createElement('i');
        caret.className = 'ph-bold ph-caret-down kz-select-caret';
        caret.setAttribute('aria-hidden', 'true');

        btn.appendChild(iconEl);
        btn.appendChild(labelSpan);
        btn.appendChild(caret);

        var menu = document.createElement('div');
        menu.id = menuId;
        menu.className = 'kz-select-menu';
        menu.setAttribute('role', 'listbox');

        var optionButtons = [];

        function buildOptions() {
            menu.innerHTML = '';
            optionButtons = [];
            Array.prototype.forEach.call(select.options, function (opt, index) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'kz-select-opt';
                item.setAttribute('role', 'option');
                item.setAttribute('tabindex', '-1');
                item.dataset.index = String(index);
                if (opt.disabled) {
                    item.disabled = true;
                    item.classList.add('opacity-40', 'cursor-not-allowed');
                }

                var optIcon = opt.getAttribute('data-icon');
                if (optIcon) {
                    var ic = document.createElement('i');
                    ic.className = 'ph-fill ' + optIcon + ' shrink-0 text-base';
                    ic.setAttribute('aria-hidden', 'true');
                    item.appendChild(ic);
                }

                var txt = document.createElement('span');
                txt.className = 'flex-1 truncate text-left';
                txt.textContent = opt.textContent.trim();
                item.appendChild(txt);

                var chk = document.createElement('i');
                chk.className = 'ph-bold ph-check kz-select-check';
                chk.setAttribute('aria-hidden', 'true');
                item.appendChild(chk);

                item.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (!opt.disabled) { choose(index); }
                });
                item.addEventListener('mouseenter', function () {
                    setFocused(index);
                });

                menu.appendChild(item);
                optionButtons.push(item);
            });
        }

        function setFocused(idx) {
            optionButtons.forEach(function (el, i) {
                el.classList.toggle('is-focused', i === idx);
            });
            if (idx >= 0 && optionButtons[idx]) {
                optionButtons[idx].scrollIntoView({ block: 'nearest' });
            }
        }

        function currentFocusedIndex() {
            for (var i = 0; i < optionButtons.length; i++) {
                if (optionButtons[i].classList.contains('is-focused')) { return i; }
            }
            return select.selectedIndex;
        }

        function moveFocus(step) {
            if (!optionButtons.length) { return; }
            var cur = currentFocusedIndex();
            var total = optionButtons.length;
            for (var n = 0; n < total; n++) {
                var next = ((cur < 0 ? (step > 0 ? -1 : 0) : cur) + step * (n + 1) + total * 2) % total;
                if (!optionButtons[next].disabled) {
                    setFocused(next);
                    return;
                }
            }
        }

        function sync() {
            var idx = select.selectedIndex;
            var opt = idx >= 0 ? select.options[idx] : null;
            var text = opt ? opt.textContent.trim() : '';
            var icon = opt ? opt.getAttribute('data-icon') : '';

            labelSpan.textContent = text;
            labelSpan.classList.toggle('text-muted', !!opt && opt.value === '' && text.indexOf('--') === 0);

            if (icon) {
                iconEl.className = 'kz-select-icon ph-fill ' + icon;
            } else {
                iconEl.className = 'kz-select-icon ph-fill hidden';
            }

            optionButtons.forEach(function (el, i) {
                el.setAttribute('aria-selected', i === idx ? 'true' : 'false');
            });
        }

        function openMenu() {
            if (btn.disabled) { return; }
            if (openWrap && openWrap !== wrap) { closeCurrent(false); }

            var rect = btn.getBoundingClientRect();
            var menuH = Math.min(menu.scrollHeight + 16, 288);
            var spaceBelow = window.innerHeight - rect.bottom;
            var spaceAbove = rect.top;

            menu.classList.toggle('is-up', spaceBelow < menuH && spaceAbove > spaceBelow);
            menu.classList.toggle('is-right', rect.left + Math.max(rect.width, 208) > window.innerWidth - 16);

            openWrap = wrap;
            wrap.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
            setFocused(select.selectedIndex >= 0 ? select.selectedIndex : 0);
        }

        function choose(idx) {
            var prev = select.selectedIndex;
            select.selectedIndex = idx;
            sync();
            closeCurrent(true);
            if (prev !== idx) {
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (openWrap === wrap) { closeCurrent(false); } else { openMenu(); }
        });

        select.addEventListener('focus', function () {
            if (!btn.disabled) { btn.focus(); }
        });
        select.addEventListener('change', sync);

        wrap.addEventListener('keydown', function (e) {
            var isOpen = openWrap === wrap;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!isOpen) { openMenu(); } else { moveFocus(1); }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!isOpen) { openMenu(); } else { moveFocus(-1); }
            } else if (e.key === 'Home' && isOpen) {
                e.preventDefault();
                for (var i = 0; i < optionButtons.length; i++) {
                    if (!optionButtons[i].disabled) { setFocused(i); break; }
                }
            } else if (e.key === 'End' && isOpen) {
                e.preventDefault();
                for (var j = optionButtons.length - 1; j >= 0; j--) {
                    if (!optionButtons[j].disabled) { setFocused(j); break; }
                }
            } else if (e.key === 'Enter' || e.key === ' ') {
                if (isOpen) {
                    e.preventDefault();
                    var idx = currentFocusedIndex();
                    if (idx >= 0 && optionButtons[idx] && !optionButtons[idx].disabled) {
                        choose(idx);
                    } else {
                        closeCurrent(true);
                    }
                } else {
                    e.preventDefault();
                    openMenu();
                }
            } else if (e.key === 'Escape' && isOpen) {
                e.preventDefault();
                e.stopPropagation();
                closeCurrent(true);
            } else if (e.key === 'Tab' && isOpen) {
                closeCurrent(false);
            }
        });

        wrap.appendChild(btn);
        wrap.appendChild(menu);
        buildOptions();
        sync();
    }

    document.addEventListener('click', function (e) {
        if (openWrap && !openWrap.contains(e.target)) {
            closeCurrent(false);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('select.select:not([multiple]):not([data-native])').forEach(enhanceSelect);
    });
})();

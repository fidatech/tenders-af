// ERP core script. Plain JavaScript: no jQuery, no Bootstrap JS, no plugins.
// Source file. public/js/app.js is generated from it by: php tools/build-assets.php
// Write comments on their own lines, starting with two slashes.
(function () {
    'use strict';

    var root = document.documentElement;
    var body = document.body;
    var ERP = (window.ERP = {});
    var SPRITE = root.getAttribute('data-icons') || '/icons.svg';
    var T = window.ERP_TEXT || {};

    var $ = function (sel, scope) { return (scope || document).querySelector(sel); };
    var $$ = function (sel, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(sel)); };
    var mq = function (q) { return window.matchMedia(q).matches; };
    var appMode = function () { return mq('(max-width: 991.98px)'); };
    var phone = function () { return mq('(max-width: 767.98px)'); };

    ERP.escape = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    ERP.icon = function (id, cls) {
        return '<i class="fi ' + (cls || '') + '"><svg aria-hidden="true"><use href="' + SPRITE + '#' + id + '"/></svg></i>';
    };
    function setIcon(el, id) {
        var use = el && $('use', el);
        if (use) use.setAttribute('href', SPRITE + '#' + id);
    }

    // ---------------------------------------------------------------- settings
    var settings = {};
    try { settings = JSON.parse(localStorage.getItem('erp.ui') || '{}'); } catch (e) { settings = {}; }
    function save() {
        try { localStorage.setItem('erp.ui', JSON.stringify(settings)); } catch (e) { return; }
    }
    function theme() { return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light'; }
    function syncTheme() {
        $$('[data-theme-toggle]').forEach(function (b) { setIcon(b, theme() === 'dark' ? 's-sun' : 's-moon'); });
    }
    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-theme-toggle]')) return;
        settings.theme = theme() === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', settings.theme);
        save();
        syncTheme();
    });

    // ----------------------------------------------------------------- sidebar
    function toggleLayout() {
        settings.layout = root.getAttribute('data-layout') === 'collapsed' ? 'vertical' : 'collapsed';
        root.setAttribute('data-layout', settings.layout);
        save();
    }
    function closeDrawer() { body.classList.remove('sidebar-open'); }
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (t.closest('#sidebarToggle')) {
            if (appMode()) body.classList.toggle('sidebar-open'); else toggleLayout();
        } else if (t.closest('#sidebarPin')) {
            toggleLayout();
        } else if (t.closest('#tabMenu')) {
            body.classList.add('sidebar-open');
        } else if (t.closest('#sidebarBackdrop')) {
            closeDrawer();
        } else if (t.closest('#appBack')) {
            var same = false;
            try { same = !!document.referrer && new URL(document.referrer).origin === location.origin; } catch (err) { same = false; }
            if (history.length > 1 && same) history.back(); else location.href = root.getAttribute('data-home') || '/';
        }
    });

    // ---------------------------------------------------------------- collapse
    function setCollapse(link, open) {
        var target = $(link.getAttribute('data-bs-target') || link.getAttribute('href'));
        if (!target) return;
        target.classList.toggle('show', open);
        link.setAttribute('aria-expanded', open ? 'true' : 'false');
        var item = link.closest('.menu-item');
        if (item) item.classList.toggle('open', open);
    }
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-bs-toggle="collapse"]');
        if (!link) return;
        e.preventDefault();
        var open = link.getAttribute('aria-expanded') !== 'true';
        var menu = link.closest('#sidebarMenu');
        // the sidebar is an accordion: one open group at a time
        if (menu && open) {
            $$('[data-bs-toggle="collapse"][aria-expanded="true"]', menu).forEach(function (other) { setCollapse(other, false); });
        }
        setCollapse(link, open);
    });

    // --------------------------------------------------------------- dropdowns
    function closeDropdowns(except) {
        $$('.dropdown-menu.show').forEach(function (menu) {
            if (menu === except) return;
            menu.classList.remove('show');
            // a bottom sheet was moved to <body>: put it back next to its button
            if (menu._home) { menu._home.appendChild(menu); menu._home = null; }
            var toggle = $('[data-bs-toggle="dropdown"]', menu.parentNode);
            if (toggle) { toggle.classList.remove('show'); toggle.setAttribute('aria-expanded', 'false'); }
        });
        if (!except) $$('.sheet-backdrop').forEach(function (b) { b.remove(); });
    }
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-bs-toggle="dropdown"]');
        if (toggle) {
            e.preventDefault();
            var menu = $('.dropdown-menu', toggle.parentNode);
            if (!menu) return;
            var open = !menu.classList.contains('show');
            closeDropdowns();
            if (open) {
                menu.setAttribute('data-bs-popper', 'static');
                menu.classList.add('show');
                toggle.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
                // on phones and tablets the menu is a bottom sheet. It moves to <body>
                // because the blurred top bar would otherwise trap its fixed position.
                if (appMode()) {
                    var back = document.createElement('div');
                    back.className = 'sheet-backdrop';
                    body.appendChild(back);
                    menu._home = menu.parentNode;
                    body.appendChild(menu);
                }
            }
            return;
        }
        var inside = e.target.closest('.dropdown-menu.show');
        if (inside && inside.getAttribute('data-bs-auto-close') === 'outside' && !e.target.closest('a, button[type="submit"]')) return;
        closeDropdowns();
    });

    // ------------------------------------------------------------------ modals
    ERP.showModal = function (modal) {
        if (typeof modal === 'string') modal = $(modal);
        if (!modal) return;
        closeDropdowns();
        var back = document.createElement('div');
        back.className = 'modal-backdrop fade';
        body.appendChild(back);
        modal._back = back;
        modal.style.display = 'block';
        modal.removeAttribute('aria-hidden');
        body.classList.add('modal-open');
        // force a reflow so the fade transition runs
        void modal.offsetWidth;
        modal.classList.add('show');
        back.classList.add('show');
        var focus = $('[autofocus], input:not([type="hidden"]), .btn-primary, .btn', modal);
        if (focus) focus.focus();
        modal.dispatchEvent(new CustomEvent('erp:shown'));
    };
    ERP.hideModal = function (modal) {
        if (!modal || !modal.classList.contains('show')) return;
        modal.classList.remove('show');
        if (modal._back) modal._back.classList.remove('show');
        setTimeout(function () {
            modal.style.display = '';
            modal.setAttribute('aria-hidden', 'true');
            if (modal._back) { modal._back.remove(); modal._back = null; }
            if (!$('.modal.show')) body.classList.remove('modal-open');
            modal.dispatchEvent(new CustomEvent('erp:hidden'));
        }, 160);
    };
    document.addEventListener('click', function (e) {
        var open = e.target.closest('[data-bs-toggle="modal"]');
        if (open) { e.preventDefault(); closeDrawer(); ERP.showModal(open.getAttribute('data-bs-target')); return; }
        var dismiss = e.target.closest('[data-bs-dismiss="modal"]');
        if (dismiss) { ERP.hideModal(dismiss.closest('.modal')); return; }
        if (e.target.classList && e.target.classList.contains('modal')) ERP.hideModal(e.target);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var modals = $$('.modal.show');
        if (modals.length) ERP.hideModal(modals[modals.length - 1]);
        else if ($('.dropdown-menu.show')) closeDropdowns();
        else closeDrawer();
    });

    // -------------------------------------------------------------------- tabs
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-bs-toggle="tab"]');
        if (!tab) return;
        e.preventDefault();
        var pane = $(tab.getAttribute('data-bs-target') || tab.getAttribute('href'));
        if (!pane) return;
        $$('[data-bs-toggle="tab"]', tab.closest('.nav')).forEach(function (t) { t.classList.toggle('active', t === tab); });
        $$('.tab-pane', pane.parentNode).forEach(function (p) {
            p.classList.toggle('active', p === pane);
            p.classList.toggle('show', p === pane);
        });
    });

    // ------------------------------------------------------------------ toasts
    ERP.toast = function (message, type) {
        var box = $('#toasts');
        if (!box) { box = document.createElement('div'); box.id = 'toasts'; box.className = 'erp-toasts'; body.appendChild(box); }
        type = type || 'success';
        var ico = { success: 's-circle-check', danger: 's-circle-xmark', warning: 's-triangle-exclamation', info: 's-circle-info' }[type] || 's-circle-info';
        var el = document.createElement('div');
        el.className = 'erp-toast is-' + type;
        el.setAttribute('role', 'status');
        el.innerHTML = ERP.icon(ico) + '<span>' + ERP.escape(message) + '</span>';
        box.appendChild(el);
        setTimeout(function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 250); }, type === 'danger' ? 6000 : 3200);
    };
    $$('[data-toast]').forEach(function (el) { ERP.toast(el.textContent.trim(), el.getAttribute('data-toast')); el.remove(); });

    // ----------------------------------------------------------------- confirm
    // <form data-confirm="Sign out?" data-confirm-text="..." data-confirm-ok="Sign out" data-confirm-style="primary">
    ERP.confirm = function (o, onOk) {
        var style = o.style || 'danger';
        var modal = document.createElement('div');
        modal.className = 'modal fade erp-confirm';
        modal.tabIndex = -1;
        modal.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-body text-center">'
            + '<div class="erp-confirm-icon text-' + style + '">' + ERP.icon(style === 'danger' ? 's-triangle-exclamation' : 's-circle-question') + '</div>'
            + '<h5 class="mb-1">' + ERP.escape(o.title || T.confirmTitle || 'Are you sure?') + '</h5>'
            + (o.text ? '<p class="text-muted mb-0">' + ERP.escape(o.text) + '</p>' : '')
            + '<div class="d-flex gap-2 mt-4"><button type="button" class="btn btn-light-secondary flex-fill" data-bs-dismiss="modal">' + ERP.escape(T.cancel || 'Cancel') + '</button>'
            + '<button type="button" class="btn btn-' + style + ' flex-fill" data-ok>' + ERP.escape(o.ok || T.confirmOk || 'Yes') + '</button></div>'
            + '</div></div></div>';
        body.appendChild(modal);
        modal.addEventListener('erp:hidden', function () { modal.remove(); });
        $('[data-ok]', modal).addEventListener('click', function () { ERP.hideModal(modal); onOk(); });
        ERP.showModal(modal);
        $('[data-bs-dismiss]', modal).focus();
    };
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.hasAttribute('data-confirm') && !form._confirmed) {
            e.preventDefault();
            ERP.confirm({
                title: form.getAttribute('data-confirm'), text: form.getAttribute('data-confirm-text'),
                ok: form.getAttribute('data-confirm-ok'), style: form.getAttribute('data-confirm-style'),
            }, function () { form._confirmed = true; if (form.requestSubmit) form.requestSubmit(); else form.submit(); });
            return;
        }
        // a form can be sent only once: stops double posting on slow connections
        if (form._sent) { e.preventDefault(); return; }
        form._sent = true;
        $$('button[type="submit"], button:not([type])', form).forEach(function (b) { b.classList.add('is-busy'); b.setAttribute('aria-disabled', 'true'); });
    });
    // coming back with the browser back button re-enables the form
    window.addEventListener('pageshow', function () {
        $$('form').forEach(function (f) { f._sent = false; f._confirmed = false; });
        $$('.is-busy').forEach(function (b) { b.classList.remove('is-busy'); b.removeAttribute('aria-disabled'); });
    });

    // ------------------------------------------------------- password show/hide
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-toggle-password]');
        if (!btn) return;
        var input = $('input', btn.closest('.auth-field, .input-group, .input-icon'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        setIcon(btn, show ? 'r-eye-slash' : 'r-eye');
    });

    // --------------------------------------------------------- command search
    var results = $('#searchResults');
    var input = $('#searchInput');
    if (results && input) {
        var index = $$('#sidebarMenu a[href]').filter(function (a) { return a.getAttribute('href').charAt(0) !== '#'; }).map(function (a) {
            var group = a.closest('.has-sub');
            var text = $('.menu-text', a);
            var use = $('.menu-icon use', group || a);
            return {
                label: (text ? text.textContent : a.textContent).trim(),
                parent: group ? $('.menu-text', group).textContent.trim() : '',
                href: a.getAttribute('href'),
                icon: use ? use.getAttribute('href').split('#')[1] : 's-file',
            };
        });
        var focus = 0;
        var render = function (q) {
            q = (q || '').trim().toLowerCase();
            var hits = index.filter(function (i) { return !q || (i.label + ' ' + i.parent).toLowerCase().indexOf(q) > -1; }).slice(0, 30);
            focus = 0;
            results.innerHTML = hits.length ? hits.map(function (h, n) {
                return '<a class="search-item ' + (n === 0 ? 'focus' : '') + '" href="' + ERP.escape(h.href) + '"><span class="si-icon">' + ERP.icon(h.icon) + '</span>'
                    + '<span><strong>' + ERP.escape(h.label) + '</strong><small>' + ERP.escape(h.parent) + '</small></span>' + ERP.icon('s-arrow-right', 'si-go') + '</a>';
            }).join('') : '<div class="search-empty">' + ERP.escape(T.noResults || 'No results') + '</div>';
        };
        $('#searchModal').addEventListener('erp:shown', function () { input.value = ''; input.focus(); render(''); });
        input.addEventListener('input', function () { render(input.value); });
        input.addEventListener('keydown', function (e) {
            var items = $$('.search-item', results);
            if (!items.length) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                focus = (focus + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items.forEach(function (a, n) { a.classList.toggle('focus', n === focus); });
                items[focus].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                location.href = items[focus].getAttribute('href');
            }
        });
        document.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); ERP.showModal('#searchModal'); }
        });
    }

    // ------------------------------------------------------- amount boxes
    // Every amount box (inputmode="decimal") accepts digits only and shows thousands
    // with commas while typing: 12500.5 becomes 12,500.5. The server removes the commas.
    ERP.formatNumber = function (value) {
        var text = String(value == null ? '' : value).replace(/[^0-9.]/g, '');
        var dot = text.indexOf('.');
        var whole = dot < 0 ? text : text.slice(0, dot);
        // only the first dot counts
        var fraction = dot < 0 ? null : text.slice(dot + 1).replace(/\./g, '');
        whole = whole.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        if (dot === 0) whole = '0';
        return fraction === null ? whole : whole + '.' + fraction;
    };
    ERP.numberInput = function (input) {
        var before = input.value;
        var caret = input.selectionStart == null ? before.length : input.selectionStart;
        // how many digits and dots are left of the caret: the caret stays after the same one
        var keep = before.slice(0, caret).replace(/[^0-9.]/g, '').length;
        var after = ERP.formatNumber(before);
        if (after === before) return;
        input.value = after;
        var position = 0;
        for (var seen = 0; position < after.length && seen < keep; position++) {
            if (/[0-9.]/.test(after.charAt(position))) seen++;
        }
        try { input.setSelectionRange(position, position); } catch (err) { return; }
    };
    var AMOUNT = 'input[inputmode="decimal"]';
    document.addEventListener('input', function (e) {
        if (e.target.matches && e.target.matches(AMOUNT)) ERP.numberInput(e.target);
    });

    // ------------------------------------------------ select with search box
    // <select data-search> becomes a box you can type in. No library needed.
    ERP.searchSelect = function (select) {
        if (select._ss || select.disabled) return;
        select._ss = true;
        var wrap = document.createElement('div');
        wrap.className = 'ss' + (select.classList.contains('form-select-sm') ? ' ss-sm' : '');
        var box = document.createElement('input');
        box.type = 'text';
        box.className = 'form-control ' + (select.classList.contains('is-invalid') ? 'is-invalid' : '');
        box.autocomplete = 'off';
        box.placeholder = select.options.length && !select.options[0].value ? select.options[0].text : '';
        if (select.required) { box.required = true; select.required = false; }
        if (select.id) { box.id = select.id; select.removeAttribute('id'); }
        var list = document.createElement('div');
        list.className = 'ss-list';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(box);
        // a small cross empties the choice, when the list has an empty first line
        var clear = null;
        if (select.options.length && !select.options[0].value) {
            clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'ss-clear';
            clear.tabIndex = -1;
            clear.setAttribute('aria-label', T.cancel || 'Clear');
            clear.innerHTML = '&times;';
            wrap.appendChild(clear);
        }
        wrap.appendChild(list);
        wrap.appendChild(select);
        select.hidden = true;
        var focus = 0;
        var current = function () { var o = select.options[select.selectedIndex]; return o && o.value ? o.text : ''; };
        var close = function () { wrap.classList.remove('open', 'up'); };
        var mark = function () { wrap.classList.toggle('has-value', !!select.value); };
        var show = function (all) {
            var q = all ? '' : box.value.trim().toLowerCase();
            var html = '';
            var n = 0;
            for (var i = 0; i < select.options.length && n < 60; i++) {
                var o = select.options[i];
                if (!o.value || o.disabled || o.hidden || (q && o.text.toLowerCase().indexOf(q) < 0)) continue;
                html += '<div class="ss-item' + (n === 0 ? ' focus' : '') + (o.value === select.value ? ' selected' : '') + '" data-value="' + ERP.escape(o.value) + '">' + ERP.escape(o.text) + '</div>';
                n++;
            }
            list.innerHTML = html || '<div class="ss-empty">' + ERP.escape(T.noResults || 'No results') + '</div>';
            focus = 0;
            wrap.classList.add('open');
            // open upwards when there is no room under the box
            var rect = wrap.getBoundingClientRect();
            wrap.classList.toggle('up', window.innerHeight - rect.bottom < 280 && rect.top > 280);
            var chosen = $('.ss-item.selected', list);
            if (chosen && all) chosen.scrollIntoView({ block: 'nearest' });
        };
        var pick = function (value) {
            select.value = value;
            box.value = current();
            mark();
            close();
            select.dispatchEvent(new Event('change', { bubbles: true }));
        };
        box.value = current();
        mark();
        if (clear) clear.addEventListener('mousedown', function (e) { e.preventDefault(); pick(''); box.focus(); });
        box.addEventListener('focus', function () { box.select(); show(true); });
        // clicking a box that already has the cursor opens the list again, ready to type over
        box.addEventListener('click', function () { if (!wrap.classList.contains('open')) { box.select(); show(true); } });
        box.addEventListener('input', function () { show(false); });
        box.addEventListener('keydown', function (e) {
            var items = $$('.ss-item', list);
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!items.length) return;
                focus = (focus + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items.forEach(function (it, n) { it.classList.toggle('focus', n === focus); });
                items[focus].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && wrap.classList.contains('open')) {
                e.preventDefault();
                if (items[focus]) pick(items[focus].getAttribute('data-value'));
            } else if (e.key === 'Escape') {
                close();
            }
        });
        // mousedown comes before blur, so the choice is not lost
        list.addEventListener('mousedown', function (e) {
            var item = e.target.closest('.ss-item');
            if (item) { e.preventDefault(); pick(item.getAttribute('data-value')); }
        });
        // the options or the value were changed from outside: show the chosen text again
        select._ssSync = function () { box.value = current(); mark(); };
        // a value set by another script (with a change event) shows in the box too
        select.addEventListener('change', function () { if (document.activeElement !== box) select._ssSync(); });
        box.addEventListener('blur', function () {
            if (box.value.trim() === '' && select.value !== '') pick('');
            else box.value = current();
            close();
        });
    };
    // Lists marked data-search, and every list long enough to need it (8 choices or more), become searchable.
    // With Select2 on the page (the panels) every list is a Select2 list instead.
    var enhance = function (scope) {
        if (window.initSelect2) { window.initSelect2(scope); return; }
        $$('select', scope).forEach(function (select) {
            if (select.multiple || select.hasAttribute('data-plain') || select.closest('.input-group')) return;
            if (select.hasAttribute('data-search') || select.options.length >= 9) ERP.searchSelect(select);
        });
    };

    // ------------------------------------------------------- Excel and PDF
    // Every page that shows a table (a list or a report, not a form) gets two buttons in its title bar:
    // Excel downloads the table with the filters that are on the screen, PDF opens the page as a paper
    // with the print window, where "Save as PDF" makes the file.
    (function () {
        var main = $('.app-content');
        var header = main ? $('.page-header', main) : null;
        if (!header || body.hasAttribute('data-no-export')) return;
        var tables = $$('table.table', main).filter(function (table) {
            return !table.hasAttribute('data-rows') && !table.classList.contains('dash-table') && !table.closest('form[method="POST"], form[method="post"]') && $('tbody tr td', table);
        });
        if (!tables.length) return;
        var actions = $('.page-header-actions', header);
        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'page-header-actions';
            header.appendChild(actions);
        }
        var link = function (params, icon, text, blank) {
            var url = new URL(window.location.href);
            Object.keys(params).forEach(function (key) { url.searchParams.set(key, params[key]); });
            var a = document.createElement('a');
            a.className = 'btn btn-outline-soft';
            a.href = url.toString();
            if (blank) { a.target = '_blank'; a.rel = 'noopener'; }
            a.innerHTML = ERP.icon(icon, 'me-1') + ' ' + ERP.escape(text);
            return a;
        };
        var group = document.createElement('span');
        group.className = 'export-buttons';
        group.appendChild(link({ export: 'xlsx' }, 's-file-excel', 'Excel', false));
        group.appendChild(link({ print: '1', pdf: '1' }, 's-file-pdf', 'PDF', true));
        actions.insertBefore(group, actions.firstChild);
    })();

    // ------------------------------------------------------- quick add
    // A pick list that can get a new record without leaving the page:
    //   <select data-quick-add="/suppliers/create" data-quick-title="New supplier">   a "+ New" link appears in its label
    //   <button data-quick-add="/items/raw/create" data-quick-target="select[data-item]">   for lists inside rows
    // The form opens in a pop-up, is saved in the background, and the new record is chosen in the list.
    var quickModal = null;
    var quickFor = null;
    var quickOpen = function (trigger) {
        var url = trigger.getAttribute('data-quick-add');
        if (!url || !window.fetch) return;
        quickFor = trigger;
        if (!quickModal) {
            quickModal = document.createElement('div');
            quickModal.className = 'modal fade erp-quick';
            quickModal.setAttribute('tabindex', '-1');
            quickModal.setAttribute('aria-hidden', 'true');
            quickModal.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5>'
                + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + ERP.escape(T.cancel || 'Cancel') + '"></button></div><div class="modal-body"></div></div></div>';
            body.appendChild(quickModal);
        }
        var box = $('.modal-body', quickModal);
        $('.modal-title', quickModal).textContent = trigger.getAttribute('data-quick-title') || trigger.textContent.trim() || '';
        box.innerHTML = '<div class="text-center text-muted py-5">\u2026</div>';
        ERP.showModal(quickModal);
        fetch(url + (url.indexOf('?') < 0 ? '?' : '&') + 'modal=1', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { if (!response.ok) throw new Error(String(response.status)); return response.text(); })
            .then(function (html) {
                box.innerHTML = html;
                // the pop-up must not reuse the ids of the page under it
                $$('[id]', box).forEach(function (el) { el.id = 'q-' + el.id; });
                $$('label[for]', box).forEach(function (el) { el.setAttribute('for', 'q-' + el.getAttribute('for')); });
                enhance(box);
                var form = $('form[data-quick-form]', box);
                if (form) form.addEventListener('submit', quickSend);
                var first = $('input:not([type="hidden"]):not([type="checkbox"]), select, textarea', box);
                if (first) first.focus();
            })
            .catch(function () { ERP.hideModal(quickModal); ERP.toast(T.failed || 'Could not open the form.', 'danger'); });
    };
    var quickSend = function (e) {
        e.preventDefault();
        e.stopPropagation();
        var form = e.currentTarget;
        if (form._busy) return;
        form._busy = true;
        var button = $('button[type="submit"]', form);
        if (button) button.classList.add('is-busy');
        var errors = $('[data-quick-errors]', form);
        var fail = function (messages) {
            form._busy = false;
            if (button) button.classList.remove('is-busy');
            if (!errors) return;
            errors.innerHTML = messages.map(function (m) { return '<div>' + ERP.escape(m) + '</div>'; }).join('');
            errors.hidden = false;
            errors.scrollIntoView({ block: 'nearest' });
        };
        fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data || {} }; }); })
            .then(function (result) {
                if (!result.ok) {
                    var list = [];
                    var bag = result.data.errors || {};
                    Object.keys(bag).forEach(function (key) { list = list.concat(bag[key]); });
                    fail(list.length ? list : [result.data.message || T.failed || 'Could not save.']);
                    return;
                }
                ERP.hideModal(quickModal);
                if (result.data.message) ERP.toast(result.data.message, 'success');
                quickRefresh(quickFor, (result.data.ids || []).concat(result.data.names || []).map(String));
            })
            .catch(function () { fail([T.failed || 'Could not save.']); });
    };
    // Read the page again in the background and copy the fresh choices into the lists, then pick the new record.
    var quickRefresh = function (trigger, ids) {
        var selector = trigger.getAttribute('data-quick-target');
        var targets = selector ? $$(selector) : [trigger];
        var templates = selector ? $$('template') : [];
        fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var fresh = new DOMParser().parseFromString(html, 'text/html');
                var find = function (query) {
                    var found = fresh.querySelector(query);
                    for (var i = 0, all = fresh.querySelectorAll('template'); !found && i < all.length; i++) found = all[i].content.querySelector(query);
                    return found;
                };
                var copy = function (select, source) {
                    var keep = select.value;
                    select.innerHTML = source.innerHTML;
                    select.value = keep;
                    if (select.value !== keep) select.selectedIndex = 0;
                };
                var picked = false;
                targets.forEach(function (select) {
                    var source = (select.name && fresh.querySelector('select[name="' + select.name.replace(/"/g, '\\"') + '"]')) || (selector ? find(selector) : null);
                    if (!source) return;
                    copy(select, source);
                    // the new record goes into the list that asked for it, or into the first empty row
                    if (!picked && (!selector || !select.value)) {
                        for (var i = 0; i < ids.length; i++) {
                            select.value = ids[i];
                            if (select.value === ids[i]) { picked = true; break; }
                        }
                        if (!picked) select.value = '';
                    }
                    if (select._ssSync) select._ssSync();
                    if (select.value) select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                templates.forEach(function (template) {
                    var inside = template.content.querySelector(selector);
                    var source = inside ? find(selector) : null;
                    if (source) inside.innerHTML = source.innerHTML;
                });
            })
            .catch(function () { return null; });
    };
    // a small "+ New" link in the label of every pick list that allows it
    $$('select[data-quick-add]').forEach(function (select) {
        if (select.closest('.modal')) return;
        var label = select.id ? $('label[for="' + select.id + '"]') : null;
        if (!label) return;
        var link = document.createElement('button');
        link.type = 'button';
        link.className = 'quick-add';
        link.innerHTML = ERP.icon('s-plus') + ' ' + ERP.escape(T.addNew || 'New');
        link.title = select.getAttribute('data-quick-title') || '';
        link.addEventListener('click', function () { quickOpen(select); });
        label.classList.add('has-quick-add');
        label.appendChild(link);
    });
    document.addEventListener('click', function (e) {
        var button = e.target.closest('button[data-quick-add]');
        if (button) { e.preventDefault(); quickOpen(button); }
    });

    // ------------------------------------------------------- a list that follows another list
    // <select data-follows="#f-country" data-choices='{"Afghanistan":["Kabul", ...]}' data-plain>
    // When the first list changes, the second shows the choices of that value, in a box you can
    // type in to search. A value without
    // choices gets a box to type in instead (same field name).
    $$('select[data-follows]').forEach(function (select) {
        var parent = document.querySelector(select.getAttribute('data-follows'));
        var choices = {};
        try { choices = JSON.parse(select.getAttribute('data-choices') || '{}'); } catch (e) { choices = {}; }
        if (!parent) return;
        var current = select.getAttribute('data-value') || select.value || '';
        var box = document.createElement('input');
        box.type = 'text';
        box.className = 'form-control';
        box.name = select.name;
        box.maxLength = 100;
        select.parentNode.insertBefore(box, select.nextSibling);
        var fill = function () {
            var list = choices[parent.value] || null;
            select.disabled = !list;
            if (!select._ss) select.hidden = !list;
            if (select._ss) select.parentNode.style.display = list ? '' : 'none';
            box.hidden = !!list; box.disabled = !!list;
            if (!list) { box.value = current; return; }
            select.innerHTML = '';
            var blank = document.createElement('option');
            blank.value = ''; blank.textContent = '\u2026';
            select.appendChild(blank);
            list.concat(current && list.indexOf(current) < 0 ? [current] : []).forEach(function (name) {
                var option = document.createElement('option');
                option.value = name; option.textContent = name;
                if (name === current) option.selected = true;
                select.appendChild(option);
            });
            // the list is one you can type in to search, like the country above it
            if (!select._ss) ERP.searchSelect(select);
            if (select._ssSync) select._ssSync();
        };
        parent.addEventListener('change', function () { current = ''; fill(); });
        select.addEventListener('change', function () { current = select.value; });
        box.addEventListener('input', function () { current = box.value; });
        fill();
    });

    // ------------------------------------------------------- rows of a paper
    // <table data-rows> with <tbody data-rows-body>, <template data-row-template> (uses __i__ as
    // the row number), a [data-add-row] button and a [data-remove-row] button in every row.
    var rowCounter = 1000;
    document.addEventListener('click', function (e) {
        var add = e.target.closest('[data-add-row]');
        if (add) {
            // a form can have several groups of rows: data-add-row="costs" works on data-rows-body="costs"
            var form = add.closest('form');
            var group = add.getAttribute('data-add-row');
            var pick = group ? '="' + group + '"' : '';
            var body = $('[data-rows-body' + pick + ']', form);
            var template = $('[data-row-template' + pick + ']', form);
            if (!body || !template) return;
            var holder = document.createElement('tbody');
            holder.innerHTML = template.innerHTML.replace(/__i__/g, String(rowCounter++));
            var row = holder.firstElementChild;
            body.appendChild(row);
            enhance(row);
            var first = $('input:not([type="hidden"]), select', row);
            if (first) first.focus();
            return;
        }
        var remove = e.target.closest('[data-remove-row]');
        if (remove) {
            var tr = remove.closest('[data-row]');
            var tbody = tr.parentNode;
            if ($$('[data-row]', tbody).length > 1) tr.remove();
            else $$('input:not([type="hidden"])', tr).forEach(function (i) { i.value = ''; });
            recalc(tbody.closest('form'));
        }
    });

    // Totals of a sale or purchase. Only for showing: the server calculates again when saving.
    var num = function (v) { var n = parseFloat(String(v == null ? '' : v).replace(/,/g, '')); return isFinite(n) ? n : 0; };
    var show2 = function (n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    function recalc(form) {
        if (!form || !form.hasAttribute('data-totals')) return;
        var subtotal = 0;
        $$('[data-row]', form).forEach(function (row) {
            var qty = $('input[data-qty]', row);
            var price = $('input[data-price]', row);
            var out = $('[data-line-total]', row);
            if (!qty || !price) return;
            var total = Math.round(num(qty.value) * num(price.value) * 100) / 100;
            subtotal += total;
            if (out) out.textContent = show2(total);
        });
        var discount = num(($('input[data-discount]', form) || {}).value);
        var set = function (sel, value) { var el = $(sel, form); if (el) el.textContent = show2(value); };
        set('[data-subtotal]', subtotal);
        set('[data-grand]', subtotal - discount);
        var paid = num(($('input[data-paid]', form) || {}).value);
        set('[data-remaining]', subtotal - discount - paid);
    }
    document.addEventListener('input', function (e) { recalc(e.target.closest('form')); });
    // choosing an item fills its usual price and unit
    document.addEventListener('change', function (e) {
        var select = e.target;
        if (!select.matches || !select.matches('select[data-item]')) return;
        var option = select.options[select.selectedIndex];
        var row = select.closest('[data-row]');
        if (!option || !row) return;
        var price = $('input[data-price]', row);
        var form = select.closest('form');
        var party = $('select[data-party]', form);
        var partyOption = party ? party.options[party.selectedIndex] : null;
        var inBase = !party || (partyOption && partyOption.getAttribute('data-cur') === form.getAttribute('data-base'));
        // a customer's own price (Customer Price Lists) comes first, then the usual price
        var own = null;
        try { own = ((JSON.parse(form.getAttribute('data-prices') || '{}')[party ? party.value : ''] || {})[select.value]) || null; } catch (err) { own = null; }
        if (price && !price.value && own) price.value = own;
        if (price && !price.value && inBase && option.getAttribute('data-price')) price.value = option.getAttribute('data-price');
        var unit = $('[data-unit]', row);
        if (unit) unit.textContent = option.getAttribute('data-unit') || '';
        recalc(select.closest('form'));
    });
    // the currency of the chosen customer or supplier is shown next to the totals
    var showCurrency = function (form) {
        var party = $('select[data-party]', form);
        var option = party ? party.options[party.selectedIndex] : null;
        var code = option && option.value ? option.getAttribute('data-cur') : '';
        $$('[data-party-cur]', form).forEach(function (el) { el.textContent = code; });
    };
    document.addEventListener('change', function (e) {
        if (e.target.matches && e.target.matches('select[data-party]')) showCurrency(e.target.closest('form'));
    });
    $$('form[data-totals]').forEach(function (form) { recalc(form); showCurrency(form); });

    // Currency exchange form: the currencies follow the chosen account, the rate comes from the
    // rates table (or is typed by hand), and the buying amount is worked out while typing.
    $$('form[data-exchange]').forEach(function (form) {
        var rates = {};
        try { rates = JSON.parse(form.getAttribute('data-rates') || '{}') || {}; } catch (err) { rates = {}; }
        var holder = $('[name="holder"]', form);
        var sellCur = $('[data-exchange-sell]', form);
        var buyCur = $('[data-exchange-buy]', form);
        var sellAmount = $('[name="sell_amount"]', form);
        var buyAmount = $('[name="buy_amount"]', form);
        var rate = $('[name="rate"]', form);
        var operation = $('[name="operation"]', form);
        var date = $('[name="date"]', form);
        var hint = $('[data-rate-hint]', form);
        var sum = $('[data-exchange-sum]', form);
        if (!holder || !sellCur || !buyCur) return;

        var number = function (input) { var v = parseFloat(String(input.value).replace(/,/g, '')); return isFinite(v) && v > 0 ? v : 0; };
        var show = function (value, decimals) {
            if (!(value > 0) || !isFinite(value)) return '';
            var text = value.toFixed(decimals);
            if (decimals > 2) text = text.replace(/0+$/, '').replace(/\.$/, '');
            return ERP.formatNumber(text);
        };
        var code = function (select) { var o = select.options[select.selectedIndex]; return o ? o.getAttribute('data-code') : ''; };
        var say = function (key) { if (hint) hint.textContent = key ? form.getAttribute('data-text-' + key) || '' : ''; };

        var summary = function () {
            $$('[data-sell-code]', form).forEach(function (el) { el.textContent = '(' + code(sellCur) + ')'; });
            $$('[data-buy-code]', form).forEach(function (el) { el.textContent = '(' + code(buyCur) + ')'; });
            if (!sum) return;
            var sold = number(sellAmount), bought = number(buyAmount), r = number(rate);
            sum.hidden = !(sold && bought && r);
            if (!sum.hidden) {
                sum.textContent = show(sold, 2) + ' ' + code(sellCur) + ' ' + (operation.value === 'divide' ? '\u00f7' : '\u00d7') + ' ' + show(r, 6) + ' = ' + show(bought, 2) + ' ' + code(buyCur);
            }
        };
        var calcBuy = function () {
            var sold = number(sellAmount), r = number(rate);
            buyAmount.value = sold && r ? show(operation.value === 'divide' ? sold / r : sold * r, 2) : '';
            summary();
        };
        var calcRate = function () {
            var sold = number(sellAmount), bought = number(buyAmount);
            if (sold && bought) { rate.value = show(operation.value === 'divide' ? sold / bought : bought / sold, 6); say('manual'); }
            summary();
        };
        var tableRate = function () {
            if (!holder.value) { say(''); summary(); return; }
            var s = parseFloat(rates[sellCur.value]), b = parseFloat(rates[buyCur.value]);
            if (!(s > 0) || !(b > 0)) { rate.value = ''; say('none'); calcBuy(); return; }
            operation.value = b >= s ? 'multiply' : 'divide';
            rate.value = show(b >= s ? b / s : s / b, 6);
            say('table');
            calcBuy();
        };
        // only the currencies the chosen account has can be picked, and never the same one twice
        var limit = function () {
            var option = holder.options[holder.selectedIndex];
            var allowed = option && option.value ? String(option.getAttribute('data-currencies') || '').split(',') : null;
            [sellCur, buyCur].forEach(function (select) {
                Array.prototype.forEach.call(select.options, function (o) {
                    var ok = !allowed || allowed.indexOf(o.value) >= 0;
                    o.hidden = !ok;
                    o.disabled = !ok;
                });
                if (select.options[select.selectedIndex] && select.options[select.selectedIndex].disabled) {
                    var first = Array.prototype.filter.call(select.options, function (o) { return !o.disabled; })[0];
                    if (first) select.value = first.value;
                }
            });
        };
        var differ = function (changed) {
            if (sellCur.value !== buyCur.value) return;
            var other = changed === buyCur ? sellCur : buyCur;
            var next = Array.prototype.filter.call(other.options, function (o) { return !o.disabled && o.value !== changed.value; })[0];
            if (next) other.value = next.value;
        };

        holder.addEventListener('change', function () { limit(); differ(sellCur); tableRate(); });
        sellCur.addEventListener('change', function () { differ(sellCur); tableRate(); });
        buyCur.addEventListener('change', function () { differ(buyCur); tableRate(); });
        sellAmount.addEventListener('input', calcBuy);
        rate.addEventListener('input', function () { say('manual'); calcBuy(); });
        operation.addEventListener('change', calcBuy);
        buyAmount.addEventListener('input', calcRate);
        var again = $('[data-table-rate]', form);
        if (again) again.addEventListener('click', tableRate);
        if (date) date.addEventListener('change', function () {
            var url = form.getAttribute('data-rates-url');
            if (!url || !date.value || !window.fetch) return;
            var query = '?date=' + encodeURIComponent(date.value) + ($('input[name="_shamsi[]"]', form) ? '&_shamsi[]=date' : '');
            fetch(url + query, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) { if (data) { rates = data; tableRate(); } })
                .catch(function () { return null; });
        });

        limit();
        differ(sellCur);
        if (rate.value) summary(); else tableRate();
    });

    // Money form: show the second amount only when the two accounts use different currencies.
    $$('form[data-money-form]').forEach(function (form) {
        var sync = function () {
            var cur = {};
            $$('select[data-currency-of]', form).forEach(function (s) {
                var o = s.options[s.selectedIndex];
                cur[s.getAttribute('data-currency-of')] = o && o.value ? o.getAttribute('data-cur') : '';
            });
            $$('[data-cur-label]', form).forEach(function (el) { var c = cur[el.getAttribute('data-cur-label')]; el.textContent = c ? '(' + c + ')' : ''; });
            var box = $('[data-other-amount]', form);
            if (box) {
                var differ = !!cur.money && !!cur.other && cur.money !== cur.other;
                box.hidden = !differ;
                if (!differ) $('input', box).value = '';
            }
        };
        // Money in / money out: choosing the person picks the cash box of the same currency.
        // A money account the user already chose in that currency is left alone.
        var pickMoney = function () {
            var other = $('select[data-currency-of="other"]', form);
            var money = $('select[data-currency-of="money"]', form);
            if (!other || !money) return;
            var chosen = other.options[other.selectedIndex];
            var cur = chosen && chosen.value ? chosen.getAttribute('data-cur') : '';
            var now = money.options[money.selectedIndex];
            if (!cur || (now && now.value && now.getAttribute('data-cur') === cur)) return;
            var match = null;
            for (var i = 0; i < money.options.length; i++) {
                var o = money.options[i];
                if (!o.value || o.getAttribute('data-cur') !== cur) continue;
                // a cash box comes before a bank or sarafi
                if (o.hasAttribute('data-cash')) { match = o; break; }
                match = match || o;
            }
            if (match) { money.value = match.value; if (money._ssSync) money._ssSync(); }
        };
        form.addEventListener('change', function (e) {
            if (form.hasAttribute('data-auto-money') && e.target.matches && e.target.matches('select[data-currency-of="other"]')) pickMoney();
            sync();
        });
        if (form.hasAttribute('data-auto-money')) pickMoney();
        sync();
    });

    // ------------------------------------------- tables become cards on phones
    var SKIP = '.keep-table, .table-sm';
    ERP.cardify = function (table) {
        if (table.matches(SKIP)) return;
        if (!phone()) { table.classList.remove('table-cards'); return; }
        var heads = $$('thead tr:first-child th', table).map(function (th) { return th.textContent.trim(); });
        $$('tbody > tr', table).forEach(function (tr) {
            var cells = Array.prototype.slice.call(tr.children);
            cells.forEach(function (td, i) {
                if (td.tagName !== 'TD') return;
                td.setAttribute('data-label', heads[i] || '');
                td.classList.toggle('cell-primary', i === 0);
                td.classList.toggle('cell-actions', i === cells.length - 1 && !!$('.btn, .btn-act, .dropdown', td));
            });
        });
        table.classList.add('table-cards');
    };
    var cardifyAll = function () { $$('.app-content table.table').forEach(ERP.cardify); };

    // ------------------------------------------- wide tables scroll, long texts wrap
    // A table wider than its card used to be cut off at the edge. Every table now sits in a box
    // that scrolls sideways, and a cell with a long text (details, a note) wraps onto more lines.
    ERP.fitTable = function (table) {
        if (table._fit) return;
        table._fit = true;
        if (!table.closest('.table-responsive, .table-scroll, .dt-container')) {
            var box = document.createElement('div');
            box.className = 'table-scroll';
            table.parentNode.insertBefore(box, table);
            box.appendChild(table);
        } else {
            var old = table.closest('.table-responsive');
            if (old) old.classList.add('table-scroll');
        }
        $$('tbody > tr > td', table).forEach(function (td) {
            if (td.children.length > 1 || $('.btn, input, select, textarea, .badge', td)) return;
            if (td.textContent.trim().length > 42) td.classList.add('cell-long');
        });
    };
    var fitAll = function () { $$('.app-content table.table').forEach(ERP.fitTable); };
    var resizeTimer;
    window.addEventListener('resize', function () { clearTimeout(resizeTimer); resizeTimer = setTimeout(cardifyAll, 200); });

    // ------------------------------------------------------ Solar Hijri date picker
    // A Sarafi on the Solar Hijri calendar types 1405/08/05 into its date boxes; this gives the
    // same boxes a calendar to pick from. (Gregorian date boxes use the picker of the browser.)
    (function () {
        var div = function (a, b) { return Math.floor(a / b); };
        var g2j = function (gy, gm, gd) {
            var cum = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
            var gy2 = gm > 2 ? gy + 1 : gy;
            var days = 355666 + 365 * gy + div(gy2 + 3, 4) - div(gy2 + 99, 100) + div(gy2 + 399, 400) + gd + cum[gm - 1];
            var jy = -1595 + 33 * div(days, 12053);
            days %= 12053;
            jy += 4 * div(days, 1461);
            days %= 1461;
            if (days > 365) { jy += div(days - 1, 365); days = (days - 1) % 365; }
            return days < 186 ? [jy, 1 + div(days, 31), 1 + (days % 31)] : [jy, 7 + div(days - 186, 30), 1 + ((days - 186) % 30)];
        };
        var j2g = function (jy, jm, jd) {
            jy += 1595;
            var days = -355668 + 365 * jy + div(jy, 33) * 8 + div((jy % 33) + 3, 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
            var gy = 400 * div(days, 146097);
            days %= 146097;
            if (days > 36524) { gy += 100 * div(--days, 36524); days %= 36524; if (days >= 365) days++; }
            gy += 4 * div(days, 1461);
            days %= 1461;
            if (days > 365) { gy += div(days - 1, 365); days = (days - 1) % 365; }
            var gd = days + 1;
            var leap = (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0;
            var len = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            var gm = 0;
            for (; gm < 13 && gd > len[gm]; gm++) gd -= len[gm];
            return [gy, gm, gd];
        };
        ERP.shamsi = { fromGregorian: g2j, toGregorian: j2g };
        var monthLength = function (y, m) {
            if (m < 7) return 31;
            if (m < 12) return 30;
            var g = j2g(y, 12, 30), back = g2j(g[0], g[1], g[2]);
            return back[1] === 12 && back[2] === 30 ? 30 : 29;
        };
        var lang = (document.documentElement.lang || 'en').slice(0, 2);
        var MONTHS = {
            en: ['Hamal', 'Sawr', 'Jawza', 'Saratan', 'Asad', 'Sunbula', 'Mizan', 'Aqrab', 'Qaws', 'Jadi', 'Dalw', 'Hoot'],
            fa: ['\u062d\u0645\u0644', '\u062b\u0648\u0631', '\u062c\u0648\u0632\u0627', '\u0633\u0631\u0637\u0627\u0646', '\u0627\u0633\u062f', '\u0633\u0646\u0628\u0644\u0647', '\u0645\u06cc\u0632\u0627\u0646', '\u0639\u0642\u0631\u0628', '\u0642\u0648\u0633', '\u062c\u062f\u06cc', '\u062f\u0644\u0648', '\u062d\u0648\u062a'],
            ps: ['\u0648\u0631\u06cc', '\u063a\u0648\u06cc\u06cc', '\u063a\u0628\u0631\u06ab\u0648\u0644\u06cc', '\u0686\u0646\u06ab\u0627\u069a', '\u0632\u0645\u0631\u06cc', '\u0648\u0696\u06cc', '\u062a\u0644\u0647', '\u0644\u0693\u0645', '\u0644\u06cc\u0646\u062f\u06cd', '\u0645\u0631\u063a\u0648\u0645\u06cc', '\u0633\u0644\u0648\u0627\u063a\u0647', '\u06a9\u0628']
        }[lang] || null;
        if (!MONTHS) MONTHS = ['Hamal', 'Sawr', 'Jawza', 'Saratan', 'Asad', 'Sunbula', 'Mizan', 'Aqrab', 'Qaws', 'Jadi', 'Dalw', 'Hoot'];
        // the week starts on Saturday
        var DAYS = lang === 'en' ? ['Sa', 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr'] : ['\u0634', '\u06cc', '\u062f', '\u0633', '\u0686', '\u067e', '\u062c'];
        var WORDS = { en: ['Today', 'Clear'], fa: ['\u0627\u0645\u0631\u0648\u0632', '\u067e\u0627\u06a9 \u06a9\u0631\u062f\u0646'], ps: ['\u0646\u0646', '\u067e\u0627\u06a9\u0648\u0644'] }[lang] || ['Today', 'Clear'];
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var latin = function (s) { return String(s).replace(/[\u06f0-\u06f9]/g, function (d) { return '\u06f0\u06f1\u06f2\u06f3\u06f4\u06f5\u06f6\u06f7\u06f8\u06f9'.indexOf(d); }).replace(/[\u0660-\u0669]/g, function (d) { return '\u0660\u0661\u0662\u0663\u0664\u0665\u0666\u0667\u0668\u0669'.indexOf(d); }); };
        var read = function (value) {
            var m = latin(value).trim().match(/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/);
            return m && +m[2] >= 1 && +m[2] <= 12 && +m[3] >= 1 && +m[3] <= 31 ? [+m[1], +m[2], +m[3]] : null;
        };
        var now = function () { var d = new Date(); return g2j(d.getFullYear(), d.getMonth() + 1, d.getDate()); };

        var pop = null, input = null, view = null;
        var close = function () { if (pop) { pop.remove(); pop = null; input = null; } };
        var place = function () {
            if (!pop || !input) return;
            var r = input.getBoundingClientRect(), w = pop.offsetWidth, h = pop.offsetHeight;
            var left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8));
            var below = window.innerHeight - r.bottom > h + 12 || r.top < h + 12;
            pop.style.left = (left + window.scrollX) + 'px';
            pop.style.top = ((below ? r.bottom + 6 : r.top - h - 6) + window.scrollY) + 'px';
        };
        var draw = function () {
            var y = view[0], m = view[1], chosen = read(input.value), today = now();
            var g = j2g(y, m, 1);
            var first = (new Date(g[0], g[1] - 1, g[2]).getDay() + 1) % 7;
            var html = '<div class="dp-head"><button type="button" class="dp-nav" data-dp="prev" aria-label="\u2039">\u2039</button>'
                + '<select class="dp-month" data-dp="month">' + MONTHS.map(function (name, i) { return '<option value="' + (i + 1) + '"' + (i + 1 === m ? ' selected' : '') + '>' + name + '</option>'; }).join('') + '</select>'
                + '<input class="dp-year" data-dp="year" type="number" value="' + y + '" min="1300" max="1500" aria-label="Year">'
                + '<button type="button" class="dp-nav" data-dp="next" aria-label="\u203a">\u203a</button></div><div class="dp-grid">';
            DAYS.forEach(function (d, i) { html += '<span class="dp-dow' + (i === 6 ? ' dp-fri' : '') + '">' + d + '</span>'; });
            for (var b = 0; b < first; b++) html += '<span></span>';
            for (var d = 1, n = monthLength(y, m); d <= n; d++) {
                var cls = 'dp-day' + ((first + d - 1) % 7 === 6 ? ' dp-fri' : '')
                    + (today[0] === y && today[1] === m && today[2] === d ? ' dp-today' : '')
                    + (chosen && chosen[0] === y && chosen[1] === m && chosen[2] === d ? ' dp-chosen' : '');
                html += '<button type="button" class="' + cls + '" data-day="' + d + '">' + d + '</button>';
            }
            html += '</div><div class="dp-foot"><button type="button" class="dp-link" data-dp="today">' + WORDS[0] + '</button>'
                + (input.required ? '' : '<button type="button" class="dp-link" data-dp="clear">' + WORDS[1] + '</button>') + '</div>';
            pop.innerHTML = html;
            place();
        };
        var set = function (value) {
            var box = input;
            box.value = value;
            close();
            box.dispatchEvent(new Event('input', { bubbles: true }));
            box.dispatchEvent(new Event('change', { bubbles: true }));
        };
        var open = function (box) {
            if (box.readOnly || box.disabled) return;
            if (input === box) return;
            close();
            input = box;
            var have = read(box.value) || now();
            view = [have[0], have[1]];
            pop = document.createElement('div');
            pop.className = 'dp';
            pop.setAttribute('dir', document.documentElement.dir || 'ltr');
            // mousedown inside keeps the cursor in the date box (except in the month and year boxes)
            pop.addEventListener('mousedown', function (e) { if (!e.target.closest('select, input')) e.preventDefault(); });
            pop.addEventListener('click', function (e) {
                var day = e.target.closest('[data-day]'), act = e.target.closest('button[data-dp]');
                if (day) { set(view[0] + '/' + pad(view[1]) + '/' + pad(+day.getAttribute('data-day'))); return; }
                if (!act) return;
                var what = act.getAttribute('data-dp');
                if (what === 'today') { var t = now(); set(t[0] + '/' + pad(t[1]) + '/' + pad(t[2])); return; }
                if (what === 'clear') { set(''); return; }
                var month = view[1] + (what === 'next' ? 1 : -1);
                view = month > 12 ? [view[0] + 1, 1] : (month < 1 ? [view[0] - 1, 12] : [view[0], month]);
                draw();
            });
            pop.addEventListener('change', function (e) {
                var what = e.target.getAttribute('data-dp');
                if (what === 'month') view[1] = +e.target.value;
                if (what === 'year' && +e.target.value >= 1300 && +e.target.value <= 1500) view[0] = +e.target.value;
                draw();
            });
            document.body.appendChild(pop);
            draw();
        };
        document.addEventListener('focusin', function (e) { if (e.target.matches && e.target.matches('input[data-shamsi-date]')) open(e.target); });
        document.addEventListener('click', function (e) { if (e.target.matches && e.target.matches('input[data-shamsi-date]')) open(e.target); });
        // what is typed moves the calendar to that month
        document.addEventListener('input', function (e) {
            if (!pop || e.target !== input) return;
            var typed = read(input.value);
            if (typed) { view = [typed[0], typed[1]]; draw(); }
        });
        document.addEventListener('mousedown', function (e) { if (pop && e.target !== input && !pop.contains(e.target)) close(); });
        document.addEventListener('keydown', function (e) { if (pop && (e.key === 'Escape' || (e.key === 'Tab' && e.target === input))) close(); });
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);
    })();

    // ------------------------------------------------------------ help "?" marks
    // A tap on the "?" shows its explanation; inside a label it must not jump to the input.
    document.addEventListener('click', function (e) {
        var tip = e.target.closest && e.target.closest('.help-tip');
        if (tip) { e.preventDefault(); tip.focus(); }
    });

    // ------------------------------------------------------- several files
    // <div data-file-drop> around <input type="file" multiple>: files can be dropped or chosen in
    // several rounds; each one is listed with its size and can be taken out before the form is sent.
    var fileSize = function (bytes) {
        if (bytes < 1024 * 1024) return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        return (bytes / 1024 / 1024).toFixed(1).replace(/\.0$/, '') + ' MB';
    };
    var fileDrop = function (box) {
        var input = $('.fd-input', box);
        if (!input || box._fileDrop || typeof DataTransfer === 'undefined') return;
        box._fileDrop = true;
        var kept = new DataTransfer();
        var max = +box.getAttribute('data-max') || 20;
        var maxBytes = (+box.getAttribute('data-max-mb') || 20) * 1024 * 1024;
        var accept = (input.getAttribute('accept') || '').split(',').map(function (x) { return x.trim().toLowerCase(); }).filter(Boolean);
        var list = $('.fd-list', box), warn = $('.fd-warn', box), total = $('.fd-total', box), zone = $('.fd-zone', box);
        var text = function (key, name) { return (box.getAttribute('data-text-' + key) || '').replace('%name%', name || ''); };
        var allowed = function (file) {
            if (!accept.length) return true;
            var name = file.name.toLowerCase(), type = (file.type || '').toLowerCase();
            return accept.some(function (rule) {
                if (rule.charAt(0) === '.') return name.slice(-rule.length) === rule;
                if (rule.slice(-2) === '/*') return type.indexOf(rule.slice(0, -1)) === 0;
                return type === rule;
            });
        };
        var draw = function () {
            list.innerHTML = '';
            var bytes = 0;
            Array.prototype.forEach.call(kept.files, function (file, index) {
                bytes += file.size;
                var li = document.createElement('li');
                li.innerHTML = ERP.icon('s-file-lines', 'fd-file') + '<span class="fd-name"></span><span class="fd-size">' + fileSize(file.size) + '</span>'
                    + '<button type="button" class="btn btn-sm btn-soft-danger" aria-label="' + ERP.escape(text('remove')) + '">' + ERP.icon('s-xmark') + '</button>';
                $('.fd-name', li).textContent = file.name;
                $('button', li).addEventListener('click', function () {
                    var next = new DataTransfer();
                    Array.prototype.forEach.call(kept.files, function (other, at) { if (at !== index) next.items.add(other); });
                    kept = next;
                    sync();
                });
                list.appendChild(li);
            });
            total.hidden = kept.files.length < 2;
            total.textContent = text('chosen').replace('%count%', kept.files.length).replace('%size%', fileSize(bytes));
            box.classList.toggle('has-files', kept.files.length > 0);
        };
        var sync = function () { input.files = kept.files; draw(); };
        var add = function (files) {
            var problems = [];
            Array.prototype.forEach.call(files, function (file) {
                var twice = Array.prototype.some.call(kept.files, function (other) { return other.name === file.name && other.size === file.size; });
                if (twice) return;
                if (!allowed(file)) { problems.push(text('type', file.name)); return; }
                if (file.size > maxBytes) { problems.push(text('big', file.name)); return; }
                if (kept.files.length >= max) { if (problems.indexOf(text('many')) < 0) problems.push(text('many')); return; }
                kept.items.add(file);
            });
            warn.hidden = !problems.length;
            warn.textContent = problems.join(' ');
            sync();
        };
        // choosing again adds to the files already chosen instead of replacing them
        input.addEventListener('change', function () {
            if (input.files === kept.files) return;
            var picked = Array.prototype.slice.call(input.files);
            input.files = kept.files;
            add(picked);
        });
        ['dragenter', 'dragover'].forEach(function (type) {
            zone.addEventListener(type, function (e) { e.preventDefault(); box.classList.add('is-over'); });
        });
        ['dragleave', 'dragend', 'drop'].forEach(function (type) {
            zone.addEventListener(type, function () { box.classList.remove('is-over'); });
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            if (e.dataTransfer && e.dataTransfer.files) add(e.dataTransfer.files);
        });
    };
    $$('[data-file-drop]').forEach(fileDrop);

    // -------------------------------------------------------------------- boot
    if (!$('.app-content h1')) body.classList.add('no-large-title');
    var onScroll = function () { body.classList.toggle('is-scrolled', window.scrollY > 28); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    syncTheme();
    $$(AMOUNT).forEach(function (input) { if (input.value) input.value = ERP.formatNumber(input.value); });
    enhance(document);
    fitAll();
    cardifyAll();
    var active = $('#sidebarMenu .menu-sublink.active, #sidebarMenu .menu-item.active > .menu-link');
    var scroller = $('#sidebarScroll');
    if (active && scroller) scroller.scrollTop = Math.max(0, active.getBoundingClientRect().top - scroller.clientHeight / 2);
    body.classList.add('is-ready', 'app-ready');
})();

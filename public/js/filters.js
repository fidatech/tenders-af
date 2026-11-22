/*
 * The filter boxes of the list pages (form.filters) on a phone: the search box stays in view with a
 * "Filters" button beside it; the pick lists fold into a panel with "Apply" and "Clear all", and the
 * filters in use are shown as chips that remove themselves. On a computer nothing changes: the
 * wrappers use display: contents (public/css/site.css), so the row of filters looks as before.
 */
(function () {
    'use strict';
    var phone = window.matchMedia('(max-width: 767.98px)');

    document.querySelectorAll('form.filters').forEach(function (form, n) {
        var search = form.querySelector('input[type=search]');
        var submit = form.querySelector('button[type=submit]');
        var controls = Array.prototype.filter.call(form.querySelectorAll('select, input[type=checkbox]'), function (el) { return el.name; });
        if (!controls.length) return;

        form.classList.add('fx');
        var bar = document.createElement('div');
        bar.className = 'fx-bar';
        var panel = document.createElement('div');
        panel.className = 'fx-panel';
        panel.id = 'fxPanel' + n;

        // everything except the search box goes into the panel, in the same order
        Array.prototype.slice.call(form.childNodes).forEach(function (node) {
            if (node === search) return;
            panel.appendChild(node);
        });
        if (search) bar.appendChild(search);

        var active = controls.filter(function (el) { return el.type === 'checkbox' ? el.checked : el.value !== ''; });
        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'fx-toggle';
        toggle.setAttribute('aria-controls', panel.id);
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg><span>Filters</span>' + (active.length ? '<b>' + active.length + '</b>' : '');
        bar.appendChild(toggle);

        var actions = document.createElement('div');
        actions.className = 'fx-actions';
        var clear = document.createElement('a');
        clear.href = location.pathname;
        clear.className = 'fx-clear';
        clear.textContent = 'Clear all';
        actions.appendChild(clear);
        var submitText = submit ? submit.textContent : '';
        if (submit) actions.appendChild(submit);
        panel.appendChild(actions);

        form.appendChild(bar);
        form.appendChild(panel);

        toggle.addEventListener('click', function () {
            var open = form.classList.toggle('fx-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        // on a phone a choice waits for "Apply" instead of reloading the page at once
        var mode = function () {
            if (submit) submit.textContent = phone.matches ? 'Apply' : submitText;
            controls.forEach(function (el) {
                if (phone.matches) {
                    if (el.hasAttribute('onchange')) { el.setAttribute('data-onchange', el.getAttribute('onchange')); el.removeAttribute('onchange'); el.onchange = null; }
                } else if (el.hasAttribute('data-onchange')) {
                    el.setAttribute('onchange', el.getAttribute('data-onchange'));
                }
            });
        };
        mode();
        if (phone.addEventListener) phone.addEventListener('change', mode);

        // the chips of the filters in use, each a link without that filter
        if (active.length) {
            var chips = document.createElement('div');
            chips.className = 'fx-chips';
            chips.setAttribute('aria-label', 'Filters in use');
            active.forEach(function (el) {
                var params = new URLSearchParams(location.search);
                params.delete(el.name);
                params.delete('page');
                var label = el.type === 'checkbox'
                    ? (el.closest('label') ? el.closest('label').textContent.trim() : el.name)
                    : el.options[el.selectedIndex].text;
                var chip = document.createElement('a');
                chip.href = location.pathname + (params.toString() ? '?' + params.toString() : '');
                chip.innerHTML = '<span></span><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>';
                chip.querySelector('span').textContent = label;
                chip.setAttribute('aria-label', 'Remove the filter ' + label);
                chips.appendChild(chip);
            });
            form.insertAdjacentElement('afterend', chips);
        }
    });
})();

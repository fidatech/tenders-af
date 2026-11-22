/*
 * The bottom bar and menu sheet of phones (resources/views/partials/mobile-nav.blade.php).
 *  - the "Menu" / "Account" tab opens the sheet; the backdrop, the close button, Escape and a
 *    swipe down close it, and the focus goes back to the tab
 *  - while the keyboard is open (a text box has the focus) the bar steps aside, so it never
 *    covers what is being typed
 */
(function () {
    'use strict';
    var sheet = document.getElementById('mnavSheet');
    var backdrop = document.querySelector('.mnav-backdrop');
    var opener = document.querySelector('[data-mnav-open]');
    var root = document.documentElement;
    if (!sheet || !opener) return;

    function open() {
        sheet.hidden = false;
        backdrop.hidden = false;
        root.classList.add('mnav-locked');
        opener.setAttribute('aria-expanded', 'true');
        requestAnimationFrame(function () { sheet.classList.add('in'); backdrop.classList.add('in'); });
        var first = sheet.querySelector('.mnav-x');
        if (first) first.focus();
    }

    function close() {
        if (sheet.hidden) return;
        sheet.classList.remove('in');
        backdrop.classList.remove('in');
        root.classList.remove('mnav-locked');
        opener.setAttribute('aria-expanded', 'false');
        setTimeout(function () { sheet.hidden = true; backdrop.hidden = true; sheet.style.transform = ''; }, 220);
        opener.focus();
    }

    opener.addEventListener('click', open);
    document.querySelectorAll('[data-mnav-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) {
        if (sheet.hidden) return;
        if (e.key === 'Escape') close();
        // keep the focus inside the open sheet
        if (e.key === 'Tab') {
            var items = sheet.querySelectorAll('a, button');
            var first = items[0], last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    // swipe the sheet down to close it
    var startY = null;
    sheet.addEventListener('touchstart', function (e) { if (sheet.querySelector('.mnav-sheet-body').scrollTop <= 0) startY = e.touches[0].clientY; }, { passive: true });
    sheet.addEventListener('touchmove', function (e) {
        if (startY === null) return;
        var dy = e.touches[0].clientY - startY;
        if (dy > 0) sheet.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    sheet.addEventListener('touchend', function (e) {
        if (startY === null) return;
        var dy = e.changedTouches[0].clientY - startY;
        startY = null;
        if (dy > 90) close(); else sheet.style.transform = '';
    });

    // the keyboard is open while a text box has the focus
    var typing = function (el) {
        return el && (el.tagName === 'TEXTAREA' || el.isContentEditable || (el.tagName === 'INPUT' && !/^(checkbox|radio|button|submit|reset|file|range|color|image|hidden)$/i.test(el.type)));
    };
    document.addEventListener('focusin', function (e) { if (typing(e.target)) root.classList.add('mnav-typing'); });
    document.addEventListener('focusout', function () { setTimeout(function () { if (!typing(document.activeElement)) root.classList.remove('mnav-typing'); }, 60); });
})();

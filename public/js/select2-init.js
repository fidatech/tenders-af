/*
 * Every pick list of the panels is a Select2 list: the same look everywhere, and a box to
 * search in when the list is long.
 *   <select data-native>             stays a plain browser list
 *   <select data-placeholder="...">  the grey text while nothing is chosen
 * window.initSelect2(scope) is also called for forms that are loaded later (pop-ups).
 */
(function (window, document) {
    'use strict';
    var $ = window.jQuery;
    if (!$ || !$.fn || !$.fn.select2) return;

    var text = window.ERP_TEXT || {};

    var setUp = function (select) {
        if (select.hasAttribute('data-native') || select.classList.contains('select2-hidden-accessible')) return;
        var $select = $(select);
        var modal = select.closest('.modal');
        var empty = select.querySelector('option[value=""]');

        $select.select2({
            width: select.classList.contains('w-auto') ? 'auto' : '100%',
            dir: document.documentElement.getAttribute('dir') || 'ltr',
            // a short list needs no search box
            minimumResultsForSearch: 8,
            placeholder: select.getAttribute('data-placeholder') || (empty && !select.multiple ? empty.textContent : null),
            allowClear: false,
            dropdownParent: modal ? $(modal) : $(document.body),
            language: { noResults: function () { return text.noResults || 'Nothing found'; } },
            // a choice that a script hid (the categories of another sector) is left out
            templateResult: function (item) { return item.element && item.element.hidden ? null : item.text; }
        });

        // the box gets the list's name, so screen readers say what it is for
        var name = select.getAttribute('aria-label')
            || (select.labels && select.labels[0] ? select.labels[0].textContent.replace(/\s+/g, ' ').trim() : '')
            || select.getAttribute('data-placeholder') || (empty ? empty.textContent : '') || select.name;
        var box = $select.data('select2') && $select.data('select2').$selection;
        if (box && name) {
            box.attr('aria-label', name);
            box.find('.select2-selection__rendered').attr('aria-label', name);
        }

        // Select2 tells jQuery about a change; scripts written with addEventListener hear it too.
        // (A list with onchange="..." already hears jQuery's own event.)
        if (!select.getAttribute('onchange')) {
            $select.on('select2:select select2:unselect select2:clear', function () {
                select._fromSelect2 = true;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                select._fromSelect2 = false;
            });
        }
    };

    window.initSelect2 = function (scope) {
        Array.prototype.forEach.call((scope || document).querySelectorAll('select'), setUp);
    };

    // When a script changes a list (sets its value, hides choices), the Select2 box shows it.
    document.addEventListener('change', function (event) {
        var form = event.target && event.target.closest ? event.target.closest('form') : null;
        Array.prototype.forEach.call((form || document).querySelectorAll('select.select2-hidden-accessible'), function (select) {
            if (select !== event.target || !select._fromSelect2) $(select).trigger('change.select2');
        });
    });

    // the search box is ready to type in as soon as a list opens
    $(document).on('select2:open', function () {
        var field = document.querySelector('.select2-container--open .select2-search__field');
        if (field) field.focus();
    });

    var start = function () { window.initSelect2(document); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})(window, document);

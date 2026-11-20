{{-- Select2 (with jQuery) only matters once someone opens a list, so it loads after the page
     has shown, and only on pages that have a list. The plain lists work until then. --}}
<script>
(function () {
    if (!document.querySelector('select:not([data-native])')) return;
    var files = [@json(asset_v('vendor/jquery/jquery.min.js')), @json(asset_v('vendor/select2/select2.min.js')), @json(asset_v('js/select2-init.js'))];
    var next = function () {
        if (!files.length) return;
        var s = document.createElement('script');
        s.src = files.shift();
        s.onload = next;
        document.body.appendChild(s);
    };
    if (document.readyState === 'complete') next(); else window.addEventListener('load', next);
})();
</script>

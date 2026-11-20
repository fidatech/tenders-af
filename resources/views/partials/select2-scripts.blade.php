{{-- Select2 (with jQuery) only matters once someone uses the page, so it loads on the first scroll,
     touch, mouse move or key press, and only on pages that have a list. The plain lists, styled the
     same, work until then. --}}
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
    var events = ['pointerdown', 'pointermove', 'touchstart', 'keydown', 'scroll', 'focusin', 'wheel'];
    var start = function () {
        events.forEach(function (e) { window.removeEventListener(e, start, true); });
        next();
    };
    events.forEach(function (e) { window.addEventListener(e, start, { capture: true, passive: true }); });
})();
</script>

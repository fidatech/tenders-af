/*
 * The province map (resources/views/site/_map.blade.php).
 * With a mouse: the name and the number of open tenders follow the pointer; a click opens the province.
 * With a finger: the first tap on a province shows its name, its number and a "See tenders" link;
 * a second tap on the same province opens it. The tip never leaves the map's box.
 */
(function () {
    'use strict';
    var touch = false;
    window.addEventListener('touchstart', function () { touch = true; }, { passive: true, once: true });

    document.querySelectorAll('.afmap-stage').forEach(function (stage) {
        var tip = stage.querySelector('.afmap-tip');
        var go = tip.querySelector('.afmap-go');
        var chosen = null;

        function label(n) {
            return n === 0 ? 'No open tenders yet' : n === 1 ? '1 open tender' : n.toLocaleString() + ' open tenders';
        }

        function show(link, x, y, withLink) {
            tip.querySelector('b').textContent = link.getAttribute('data-name');
            tip.querySelector('span').textContent = label(+link.getAttribute('data-count'));
            go.hidden = !withLink;
            go.href = link.getAttribute('href');
            tip.hidden = false;
            var box = stage.getBoundingClientRect();
            var half = tip.offsetWidth / 2 + 4;
            var left = Math.min(Math.max(x - box.left, half), box.width - half);
            var top = y - box.top;
            // too close to the top edge: show the tip below the finger instead of above it
            tip.classList.toggle('below', top < tip.offsetHeight + 20);
            tip.style.left = left + 'px';
            tip.style.top = top + 'px';
        }

        function hide() {
            tip.hidden = true;
            chosen = null;
        }

        stage.querySelectorAll('.afmap a').forEach(function (link) {
            link.addEventListener('mousemove', function (e) { if (!touch) show(link, e.clientX, e.clientY, false); });
            link.addEventListener('mouseleave', function () { if (!touch) hide(); });
            link.addEventListener('focus', function () {
                var r = link.getBoundingClientRect();
                show(link, r.left + r.width / 2, r.top + r.height / 2, false);
            });
            link.addEventListener('blur', function () { if (!touch) hide(); });
            link.addEventListener('click', function (e) {
                if (!touch || chosen === link) return; // a mouse click, or the second tap: open the province
                e.preventDefault();
                chosen = link;
                var r = link.getBoundingClientRect();
                show(link, e.clientX || r.left + r.width / 2, e.clientY || r.top + r.height / 2, true);
            });
        });

        document.addEventListener('click', function (e) { if (chosen && !stage.contains(e.target)) hide(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
        window.addEventListener('resize', hide);
    });
})();

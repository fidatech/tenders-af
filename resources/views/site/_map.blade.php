{{--
    The 34 provinces. The whole map always fits its box.
    With a mouse, a click on a province opens its tenders. On a touch screen (and on phones, where the
    names are too small to read) the first tap picks the province and shows its name and count in a
    card under the map, with a button to its tenders; a second tap on the same province opens them.
--}}
<div class="afmap-box" data-afmap>
    <svg class="afmap" role="group" aria-label="Map of Afghanistan with its 34 provinces. Choose a province to see its open tenders." viewBox="-10 -10 1020 781">
        @foreach ($map as $shape)
            <a href="{{ route('site.province', ['slug' => $shape['slug']]) }}" data-name="{{ $shape['name'] }}" data-count="{{ $shape['count'] }}" aria-label="{{ $shape['name'] }}: {{ $shape['count'] }} open tenders"><title>{{ $shape['name'] }}: {{ $shape['count'] }} open tenders</title><path d="{{ $shape['d'] }}" style="fill-opacity: {{ $shape['shade'] }}"/></a>
        @endforeach
        @foreach ($map as $shape)
            @if ($shape['w'] >= 100)
                <text x="{{ $shape['x'] }}" y="{{ $shape['y'] - 1 }}" font-size="13">{{ $shape['name'] }}</text><text class="n" x="{{ $shape['x'] }}" y="{{ $shape['y'] + 14 }}">{{ $shape['count'] }}</text>
            @else
                <text class="s" x="{{ $shape['x'] }}" y="{{ $shape['y'] + 3 }}" font-size="10.5">{{ $shape['name'] }}</text>
            @endif
        @endforeach
    </svg>
    <div class="afmap-pick" data-afmap-pick aria-live="polite">
        <p class="afmap-hint" data-afmap-hint>
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11V5a2 2 0 1 1 4 0v5"/><path d="M13 10V8a2 2 0 1 1 4 0v3"/><path d="M17 11a2 2 0 1 1 4 0v3a7 7 0 0 1-7 7h-1a7 7 0 0 1-6-3.4L4.6 14a2 2 0 0 1 3.4-2l1 1.5"/></svg>
            Tap a province to see its tenders.
        </p>
        <div class="afmap-card" data-afmap-card hidden>
            <div class="afmap-info"><b data-afmap-name></b><span data-afmap-count></span></div>
            <a class="btn btn-green btn-sm" data-afmap-link href="#">See tenders</a>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    // touch screens and phones: the first tap on a province picks it, the second opens it
    var touch = window.matchMedia('(hover: none), (max-width: 640px)');
    document.querySelectorAll('[data-afmap]').forEach(function (box) {
        var card = box.querySelector('[data-afmap-card]'), hint = box.querySelector('[data-afmap-hint]');
        var name = box.querySelector('[data-afmap-name]'), count = box.querySelector('[data-afmap-count]'), link = box.querySelector('[data-afmap-link]');
        var picked = null;
        box.querySelectorAll('.afmap a').forEach(function (province) {
            province.addEventListener('click', function (e) {
                if (!touch.matches || picked === province) return;
                e.preventDefault();
                if (picked) picked.classList.remove('on');
                picked = province;
                province.classList.add('on');
                var n = +province.getAttribute('data-count');
                name.textContent = province.getAttribute('data-name');
                count.textContent = n === 0 ? 'No open tenders yet' : n === 1 ? '1 open tender' : n.toLocaleString() + ' open tenders';
                link.href = province.getAttribute('href');
                link.textContent = n === 0 ? 'See the province' : 'See tenders';
                card.hidden = false;
                hint.hidden = true;
            });
        });
    });
})();
</script>
@endpush
@endonce

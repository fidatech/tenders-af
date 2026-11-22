{{--
    The 34 provinces; a click on one opens its tenders. The drawing scales with its box (viewBox and
    width: 100%), so it always fits the width of the page. On a phone the small provinces lose their
    printed names (they would be too small to read) and a tap shows the name and the number of open
    tenders first; a second tap, or the link in the tip, opens the province (public/js/afmap.js).
--}}
<div class="afmap-stage">
    <svg class="afmap" role="group" aria-label="Map of Afghanistan with its 34 provinces. Choose a province to see its open tenders." viewBox="-10 -10 1020 781" preserveAspectRatio="xMidYMid meet">
        @foreach ($map as $shape)
            <a href="{{ route('site.province', ['slug' => $shape['slug']]) }}" data-name="{{ $shape['name'] }}" data-count="{{ $shape['count'] }}" aria-label="{{ $shape['name'] }}: {{ $shape['count'] }} open tenders"><title>{{ $shape['name'] }}: {{ $shape['count'] }} open tenders</title><path d="{{ $shape['d'] }}" style="fill-opacity: {{ $shape['shade'] }}"/></a>
        @endforeach
        @foreach ($map as $shape)
            @if ($shape['w'] >= 100)
                <text class="lbl big" x="{{ $shape['x'] }}" y="{{ $shape['y'] - 1 }}" font-size="13">{{ $shape['name'] }}</text><text class="n" x="{{ $shape['x'] }}" y="{{ $shape['y'] + 14 }}">{{ $shape['count'] }}</text>
            @else
                <text class="lbl small" x="{{ $shape['x'] }}" y="{{ $shape['y'] + 3 }}" font-size="10.5">{{ $shape['name'] }}</text>
            @endif
        @endforeach
    </svg>
    <div class="afmap-tip" role="status" aria-live="polite" hidden><b></b><span></span><a class="afmap-go" href="#">See tenders</a></div>
</div>
@once
    @push('scripts')
        <script src="{{ asset_v('js/afmap.js') }}" defer></script>
    @endpush
@endonce

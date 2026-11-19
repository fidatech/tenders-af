{{-- the 34 provinces; a click on one opens its tenders --}}
<svg class="afmap" role="group" aria-label="Map of Afghanistan with its 34 provinces. Choose a province to see its open tenders." viewBox="-10 -10 1020 781">
    @foreach ($map as $shape)
        <a href="{{ route('site.province', ['slug' => $shape['slug']]) }}" aria-label="{{ $shape['name'] }}: {{ $shape['count'] }} open tenders"><title>{{ $shape['name'] }}: {{ $shape['count'] }} open tenders</title><path d="{{ $shape['d'] }}" style="fill-opacity: {{ $shape['shade'] }}"/></a>
    @endforeach
    @foreach ($map as $shape)
        @if ($shape['w'] >= 100)
            <text x="{{ $shape['x'] }}" y="{{ $shape['y'] - 1 }}" font-size="13">{{ $shape['name'] }}</text><text class="n" x="{{ $shape['x'] }}" y="{{ $shape['y'] + 14 }}">{{ $shape['count'] }}</text>
        @else
            <text x="{{ $shape['x'] }}" y="{{ $shape['y'] + 3 }}" font-size="10.5">{{ $shape['name'] }}</text>
        @endif
    @endforeach
</svg>

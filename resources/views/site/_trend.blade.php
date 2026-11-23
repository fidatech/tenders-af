{{--
    A trend over days: $points = [key => [label, first, second]]. The first series is a filled area,
    the second a line, on one scale. The drawing stretches with its box; the labels are ordinary text.
    $names = [first series, second series]. A hidden table gives the numbers to screen readers.
--}}
@php
    $pts = array_values($points);
    $n = count($pts);
    $max = max(1, max(array_column($pts, 1)), max(array_column($pts, 2)));
    $mag = 10 ** floor(log10($max));
    $step = $max <= 5 ? 1 : (int) max(1, $mag * ($max / $mag <= 2 ? 0.5 : ($max / $mag <= 5 ? 1 : 2)));
    $top = max($step, (int) ceil($max / $step) * $step);
    $x = fn (int $i) => $n > 1 ? round(1000 * $i / ($n - 1), 1) : 500;
    $y = fn ($v) => round(100 - 100 * $v / $top, 2);
    $line = fn (int $col) => collect($pts)->map(fn ($p, $i) => $x($i).','.$y($p[$col]))->join(' ');
    $area = '0,100 '.$line(1).' 1000,100';
    $every = $n > 60 ? 15 : ($n > 20 ? 7 : 1);
@endphp
<figure class="trend">
    <div class="trend-legend" aria-hidden="true"><span class="a">{{ $names[0] }}</span><span class="b">{{ $names[1] }}</span></div>
    <div class="colchart-plot">
        <div class="colchart-y" aria-hidden="true">
            @for ($v = $top; $v >= 0; $v -= $step)<span style="bottom: {{ 100 * $v / $top }}%">{{ number_format($v) }}</span>@endfor
        </div>
        <svg viewBox="0 0 1000 100" preserveAspectRatio="none" role="img" aria-label="{{ $label }}">
            <defs><linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#18A05A" stop-opacity=".35"/><stop offset="1" stop-color="#18A05A" stop-opacity=".02"/></linearGradient></defs>
            @for ($v = 0; $v <= $top; $v += $step)
                <line x1="0" x2="1000" y1="{{ $y($v) }}" y2="{{ $y($v) }}" class="grid" vector-effect="non-scaling-stroke"/>
            @endfor
            <polygon points="{{ $area }}" fill="url(#trendFill)"/>
            <polyline points="{{ $line(2) }}" class="trend-b" vector-effect="non-scaling-stroke"/>
            <polyline points="{{ $line(1) }}" class="trend-a" vector-effect="non-scaling-stroke"/>
        </svg>
    </div>
    <div class="colchart-x" style="--cols: {{ $n }}" aria-hidden="true">
        @foreach ($pts as $i => [$name])
            <span>{{ $i % $every === 0 ? $name : '' }}</span>
        @endforeach
    </div>
    <div class="sr"><table><caption>{{ $label }}</caption><thead><tr><th>Day</th><th>{{ $names[0] }}</th><th>{{ $names[1] }}</th></tr></thead><tbody>@foreach ($pts as [$name, $a, $b])<tr><th scope="row">{{ $name }}</th><td>{{ $a }}</td><td>{{ $b }}</td></tr>@endforeach</tbody></table></div>
</figure>

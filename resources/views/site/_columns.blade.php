{{--
    A column chart: $columns = [key => [label, number]]. The bars are an SVG that stretches with the
    box; the labels are ordinary text around it, so they stay readable on a phone. A hidden table gives
    the same numbers to screen readers.
--}}
@php
    $cols = array_values($columns);
    $n = count($cols);
    $max = max(1, max(array_column($cols, 1)));
    // a round top value for the scale, in 2 to 5 steps
    $mag = 10 ** floor(log10($max));
    $step = $max <= 5 ? 1 : (int) max(1, $mag * ($max / $mag <= 2 ? 0.5 : ($max / $mag <= 5 ? 1 : 2)));
    $top = max($step, (int) ceil($max / $step) * $step);
    $gap = $n > 20 ? 0.25 : 0.18; // share of each slot left empty
    $every = $n > 20 ? 7 : ($n > 12 ? 3 : 2);
@endphp
<figure class="colchart" style="--cols: {{ $n }}">
    <div class="colchart-plot">
        <div class="colchart-y" aria-hidden="true">
            @for ($v = $top; $v >= 0; $v -= $step)<span style="bottom: {{ 100 * $v / $top }}%">{{ number_format($v) }}</span>@endfor
        </div>
        <svg viewBox="0 0 {{ $n * 10 }} 100" preserveAspectRatio="none" role="img" aria-label="{{ $label }}">
            @for ($v = 0; $v <= $top; $v += $step)
                <line x1="0" x2="{{ $n * 10 }}" y1="{{ 100 - 100 * $v / $top }}" y2="{{ 100 - 100 * $v / $top }}" class="grid" vector-effect="non-scaling-stroke"/>
            @endfor
            @foreach ($cols as $i => [$name, $value])
                @php($bh = 100 * $value / $top)
                <rect x="{{ $i * 10 + 10 * $gap / 2 }}" y="{{ 100 - $bh }}" width="{{ 10 * (1 - $gap) }}" height="{{ $value > 0 ? max(0.8, $bh) : 0 }}"><title>{{ $name }}: {{ number_format($value) }}</title></rect>
            @endforeach
        </svg>
    </div>
    <div class="colchart-x" aria-hidden="true">
        @foreach ($cols as $i => [$name])
            <span>{{ $i % $every === 0 || ($i === $n - 1 && ($n - 1) % $every >= $every * 0.6) ? $name : '' }}</span>
        @endforeach
    </div>
    <div class="sr"><table><caption>{{ $label }}</caption><tbody>@foreach ($cols as [$name, $value])<tr><th scope="row">{{ $name }}</th><td>{{ $value }}</td></tr>@endforeach</tbody></table></div>
</figure>

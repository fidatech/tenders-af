{{-- A ring of shares with its legend: $parts = [label => number]. --}}
@php
    $palette = ['#0B6B3A', '#18A05A', '#5FE0A0', '#C1121F', '#F59E0B', '#94A3B8'];
    $total = max(1, array_sum($parts));
    $at = 0;
    $stops = [];
    $i = 0;
    foreach ($parts as $part => $n) {
        $end = $at + 100 * $n / $total;
        $stops[] = $palette[$i % count($palette)].' '.round($at, 2).'% '.round($end, 2).'%';
        $at = $end;
        $i++;
    }
@endphp
<div class="donut">
    <div class="donut-ring" style="background: radial-gradient(closest-side, #fff 64%, transparent 65%), conic-gradient({{ implode(', ', $stops) }})" role="img" aria-label="{{ $label }}: {{ collect($parts)->map(fn ($n, $k) => $k.' '.round(100 * $n / $total).'%')->join(', ') }}"><b>{{ number_format(array_sum($parts)) }}</b><small>{{ $unit ?? '' }}</small></div>
    <ul class="donut-legend">
        @foreach (array_keys($parts) as $k => $part)
            <li><i style="background: {{ $palette[$k % count($palette)] }}"></i><span>{{ $part }}</span><b>{{ round(100 * $parts[$part] / $total) }}%</b></li>
        @endforeach
    </ul>
</div>

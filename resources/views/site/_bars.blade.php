{{-- A ranked list with bars: $rows = [label => number], or [[label, number, url?, prefix?], ...]. $unit names what is counted. --}}
@php
    $items = collect($rows)->map(fn ($v, $k) => is_array($v) ? $v : [$k, $v])->values();
    $max = max(1, (int) $items->max(1));
    $total = max(1, (int) $items->sum(1));
@endphp
<ol class="bars" aria-label="{{ $label ?? '' }}">
    @foreach ($items as $item)
        <li>
            <span class="bars-name">@if (! empty($item[3]))<span class="bars-flag" aria-hidden="true">{{ $item[3] }}</span>@endif @if (! empty($item[2]))<a href="{{ $item[2] }}">{{ $item[0] }}</a>@else{{ $item[0] }}@endif</span>
            <span class="bars-track" aria-hidden="true"><i style="width: {{ max(2, round(100 * $item[1] / $max)) }}%"></i></span>
            <span class="bars-n">{{ number_format($item[1]) }}@if (! empty($share))<small>{{ round(100 * $item[1] / $total) }}%</small>@endif</span>
        </li>
    @endforeach
</ol>

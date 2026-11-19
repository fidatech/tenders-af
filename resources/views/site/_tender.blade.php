{{-- one tender as a card: the date it closes, what it is, who buys, where --}}
@php
    $state = $tender->state();
    $tone = ['soon' => 'red', 'open' => 'green'][$state] ?? 'ink';
    $pill = ['soon' => 'soon', 'open' => 'open'][$state] ?? 'done';
    $day = $tender->deadline ?? $tender->published_on;
@endphp
<a class="tender" href="{{ route('site.tenders.show', ['slug' => $tender->slug]) }}">
    <div class="date bg-{{ $tone }}">
        <small>{{ $tender->deadline ? ($tender->isOpen() ? 'Closes' : 'Closed') : 'Posted' }}</small>
        <b>{{ $day ? $day->format('d') : '–' }}</b>
        <span>{{ $day ? $day->format('M') : '' }}</span>
    </div>
    <div class="body">
        <div class="row"><span class="tag">{{ $tender->typeLabel() }} · {{ $tender->number() }}</span><span class="pill {{ $pill }}">{{ $tender->stateLabel() }}</span></div>
        <h3>{{ $tender->title }}</h3>
        <div class="buyer">{{ $tender->buyer_name }}</div>
        <div class="foot"><span class="soft">{{ $tender->industry?->name }}</span><span class="soft">{{ $tender->placeText() }}</span><span class="left t-{{ $pill }}">{{ $tender->timeLeft() }}</span></div>
    </div>
</a>

{{-- one tender as a line of a short list (dashboard) --}}
@php
    $state = $tender->state();
    $tone = ['soon' => 'text-danger', 'open' => 'text-success', 'closed' => 'text-muted', 'awarded' => 'text-muted', 'cancelled' => 'text-muted', 'draft' => 'text-muted'][$state];
@endphp
<li>
    <a href="{{ route('tenders.show', ['id' => $tender->id]) }}" class="d-flex gap-3 align-items-center min-w-0 flex-grow-1 text-decoration-none text-reset">
        <span class="badge bg-secondary-subtle text-secondary-emphasis flex-shrink-0">{{ $tender->typeLabel() }}</span>
        <span class="min-w-0 flex-grow-1">
            <span class="d-block fw-semibold text-truncate">{{ $tender->title }}</span>
            <span class="d-block small text-muted text-truncate">{{ $tender->buyer_name }} · {{ $tender->industry?->name }} · {{ $tender->placeText() }}</span>
        </span>
        <span class="small fw-semibold text-nowrap {{ $tone }}">{{ $tender->timeLeft() }}</span>
    </a>
</li>

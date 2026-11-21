@extends('layouts.admin', ['title' => __('Messages')])

@section('content')
<x-page-header :title="__('Messages')" icon="envelope" />

<div class="card">
    <div class="card-body pb-0">
        <x-filter-bar :action="route('admin.messages.index')" :term="$search"
            :placeholder="__('Search company, bidder or email…')" :limit="150"
            :reset="$search !== '' ? route('admin.messages.index') : null" />
    </div>
    <div class="list-group list-group-flush">
        @forelse ($people as $person)
            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3 py-3" href="{{ route('admin.messages.show', $person) }}">
                <span class="min-w-0">
                    <strong class="d-block" dir="auto">{{ $person->name }}</strong>
                    <span class="d-block small text-muted text-break">{{ $person->company->name }} · {{ $person->email }}</span>
                </span>
                @if ($person->unread_messages)
                    <span class="badge bg-danger rounded-pill">{{ $person->unread_messages }}</span>
                @endif
                <span class="small {{ $person->unread_messages ? '' : 'ms-auto' }}">{{ __('Open chat') }}</span>
            </a>
        @empty
            <p class="text-muted text-center my-4">{{ __('No contacts found.') }}</p>
        @endforelse
    </div>
</div>

{{ $people->links('partials.pagination') }}
@endsection

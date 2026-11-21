@extends('layouts.admin', ['title' => __('Messages')])

@section('content')
<x-page-header :title="__('Messages')" icon="envelope" />

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('admin.messages.index') }}" class="d-flex gap-2">
            <input name="q" value="{{ $search }}" class="form-control" aria-label="{{ __('Search contacts') }}" placeholder="{{ __('Search company, bidder or email') }}" maxlength="150">
            <button class="btn btn-primary">{{ __('Search') }}</button>
        </form>
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

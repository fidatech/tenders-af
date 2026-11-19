@extends('layouts.app', ['title' => __('Notifications')])

@section('content')
<x-page-header :title="__('Notifications')" icon="bell">
    <button type="submit" form="noticesReadForm" class="btn btn-outline-soft">{{ __('Mark all as read') }}</button>
</x-page-header>

<div class="card">
    <div class="card-body">
        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="bell" /></div>
                <h3>{{ __('No notifications yet') }}</h3>
                <p>{{ __('Reminders about payments that are coming up or late will appear here.') }}</p>
            </div>
        @else
            <ul class="list-clean">
                @foreach ($rows as $notice)
                    <li>
                        <span class="rounded-circle flex-shrink-0" style="width:9px;height:9px;background:{{ $notice->read_at ? 'var(--bs-border-color)' : 'var(--bs-primary)' }}"></span>
                        <a href="{{ route('notices.open', ['id' => $notice->id]) }}" class="min-w-0 flex-grow-1 text-decoration-none text-reset">
                            <span class="d-block {{ $notice->read_at ? '' : 'fw-semibold' }}">{{ $notice->title }}</span>
                            @if ($notice->body)<span class="d-block small text-muted text-truncate">{{ $notice->body }}</span>@endif
                        </a>
                        <span class="small text-muted text-nowrap">{{ $notice->created_at?->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection

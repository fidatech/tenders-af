@extends('layouts.admin', ['title' => __('Notifications')])

@section('content')
<x-page-header :title="__('Notifications')" icon="bell">
    @if ($adminUnread)
        <button type="submit" form="adminNoticesRead" class="btn btn-outline-soft"><x-icon name="check-double" class="me-1" /> {{ __('Mark all as read') }}</button>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body p-0">
        @forelse ($rows as $notice)
            <a href="{{ route('admin.notices.open', ['id' => $notice->id]) }}" class="nt-row {{ $notice->read_at ? '' : 'unread' }}">
                <span class="nt-ico nt-{{ $notice->kind }}"><x-icon :name="$notice->icon()" /></span>
                <span class="min-w-0 flex-grow-1"><span class="d-block nt-title">{{ $notice->title }}</span>@if ($notice->body)<span class="d-block small text-muted text-truncate">{{ $notice->body }}</span>@endif</span>
                <span class="small text-muted text-nowrap">{{ $notice->created_at?->diffForHumans() }}</span>
            </a>
        @empty
            <div class="empty-state"><div class="es-icon"><x-icon name="bell" /></div><h3>{{ __('No notifications yet') }}</h3><p>{{ __('New companies, licences, tenders and payments that wait for you appear here.') }}</p></div>
        @endforelse
    </div>
</div>
{{ $rows->links('partials.pagination') }}
@endsection

@extends('layouts.admin', ['title' => __('Notification')])

@section('content')
<x-page-header :title="__('Notification')" icon="bell" :crumbs="[[__('Notifications'), route('admin.notices.index')], [__('Notification')]]">
    <a class="btn btn-outline-soft" href="{{ route('admin.notices.index') }}"><x-icon name="chevron-left" class="me-1" /> {{ __('All notifications') }}</a>
</x-page-header>

<article class="card h-auto nt-page">
    <div class="card-body">
        <div class="d-flex align-items-start gap-3">
            <span class="nt-ico nt-ico-lg nt-{{ $notice->kind }}"><x-icon :name="$notice->icon()" /></span>
            <div class="min-w-0 flex-grow-1">
                <h2 class="nt-page-title" dir="auto">{{ $notice->title }}</h2>
                @if ($notice->created_at)
                    <div class="small text-muted">
                        <time datetime="{{ $notice->created_at->toIso8601String() }}">{{ fmt_datetime($notice->created_at) }}</time>
                        · {{ $notice->created_at->diffForHumans() }}
                    </div>
                @endif
            </div>
        </div>
        @if ($notice->body)
            <div class="nt-page-body" dir="auto">{{ $notice->full_body ?? $notice->body }}</div>
        @endif
    </div>
    @if ($notice->url)
        <div class="card-footer d-flex flex-wrap align-items-center gap-2">
            @if (str_starts_with($notice->url, 'mailto:'))
                <a class="btn btn-primary" href="{{ $notice->url }}"><x-icon name="reply" class="me-1" /> {{ __('Reply by email') }}</a>
                <span class="small text-muted text-break" dir="ltr">{{ rawurldecode(\Illuminate\Support\Str::before(substr($notice->url, 7), '?')) }}</span>
            @else
                <a class="btn btn-primary" href="{{ $notice->url }}"><x-icon name="up-right-from-square" class="me-1" /> {{ __('Open related page') }}</a>
            @endif
        </div>
    @endif
</article>
@endsection

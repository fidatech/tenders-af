@extends('layouts.admin', ['title' => __('Notification')])

@section('content')
<x-page-header :title="__('Notification')" icon="bell">
    <a class="btn btn-outline-soft" href="{{ route('admin.notices.index') }}">{{ __('All notifications') }}</a>
</x-page-header>

<div class="card">
    <div class="card-body">
        <h2 class="h5" dir="auto" style="overflow-wrap:anywhere">{{ $notice->title }}</h2>
        @if ($notice->created_at)
            <time class="small text-muted" datetime="{{ $notice->created_at->toIso8601String() }}">{{ $notice->created_at->format('d M Y, H:i') }}</time>
        @endif
        @if ($notice->body)
            <p class="mt-3 mb-0" dir="auto" style="white-space:pre-wrap;overflow-wrap:anywhere;unicode-bidi:plaintext">{{ $notice->full_body ?? $notice->body }}</p>
        @endif
    </div>
    @if ($notice->url)
        <div class="card-footer">
            @if (str_starts_with($notice->url, 'mailto:'))
                <a class="btn btn-outline-soft" href="{{ $notice->url }}">{{ __('Reply by email') }}</a>
                <span class="small text-muted ms-2" dir="ltr">{{ rawurldecode(substr($notice->url, 7)) }}</span>
            @else
                <a class="btn btn-primary" href="{{ $notice->url }}">{{ __('Open related page') }}</a>
            @endif
        </div>
    @endif
</div>
@endsection

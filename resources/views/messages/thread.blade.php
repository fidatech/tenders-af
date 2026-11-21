@extends($isAdmin ? 'layouts.admin' : 'layouts.app', ['title' => __('Messages')])

@section('content')
@php
    $threadUrl = $isAdmin ? route('admin.messages.show', $recipient) : route('messages.show');
    $updatesUrl = $isAdmin ? route('admin.messages.updates', $recipient) : route('messages.updates');
    $sendUrl = $isAdmin ? route('admin.messages.store', $recipient) : route('messages.store');
    $canSend = $recipient->is_active && (! $isAdmin || auth('admin')->user()->role !== 'viewer');
@endphp
<link rel="stylesheet" href="{{ asset_v('css/messages.css') }}">

<x-page-header :title="$isAdmin ? $recipient->name : __('Messages')" :subtitle="$isAdmin ? $recipient->company->name : __('Tenders.af team')" icon="envelope">
    @if ($isAdmin)
        <a class="btn btn-outline-soft" href="{{ route('admin.messages.index') }}">{{ __('All messages') }}</a>
    @endif
</x-page-header>

<section class="card" data-chat @unless ($before) data-updates="{{ $updatesUrl }}" data-last="{{ $messages->last()?->id ?? 0 }}" @endunless>
    @if ($older || $before)
        <nav class="card-header d-flex justify-content-between gap-2">
            @if ($older)<a href="{{ $threadUrl }}?before={{ $messages->first()->id }}">{{ __('Older messages') }}</a>@endif
            @if ($before)<a href="{{ $threadUrl }}">{{ __('Latest messages') }}</a>@endif
        </nav>
    @endif
    <div class="card-body chat-history" data-chat-history role="log" aria-label="{{ __('Conversation') }}" tabindex="0">
        @if ($messages->isEmpty())
            <p class="text-muted text-center" data-chat-empty>{{ __('No messages yet.') }}</p>
        @endif
        @include('messages.rows')
    </div>
    @if ($canSend)
        <form class="card-footer" method="POST" action="{{ $sendUrl }}" enctype="multipart/form-data" data-chat-form>
            @csrf
            @if ($errors->any())
                <ul class="text-danger small mb-2" role="alert">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
            <label class="visually-hidden" for="chat-body">{{ __('Message') }}</label>
            <textarea class="form-control mb-2" id="chat-body" name="body" rows="2" maxlength="5000" dir="auto" placeholder="{{ __('Write a message') }}">{{ old('body') }}</textarea>
            <div class="chat-tools">
                <div>
                    <label class="btn btn-sm btn-outline-soft mb-0" for="chat-files">{{ __('Attach files') }}</label>
                    <input class="visually-hidden" id="chat-files" type="file" name="files[]" data-chat-files multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.webp,.zip">
                </div>
                <button class="btn btn-sm btn-outline-soft" type="button" data-chat-record
                    data-start="{{ __('Record voice') }}" data-stop="{{ __('Stop recording') }}"
                    data-error="{{ __('Microphone unavailable. You can upload an audio file.') }}"
                    data-capturing="{{ __('Recording…') }}" hidden>{{ __('Record voice') }}</button>
                <div>
                    <label class="btn btn-sm btn-outline-soft mb-0" for="chat-voice">{{ __('Upload audio') }}</label>
                    <input class="visually-hidden" id="chat-voice" type="file" name="voice" accept="audio/*,.webm,.m4a" data-chat-voice>
                </div>
                <button class="btn btn-primary ms-auto" type="submit">{{ __('Send') }}</button>
            </div>
            <div class="small text-muted mt-2" role="status" data-chat-status
                data-limit="{{ __('Choose up to five files and one voice note, 10 MB each.') }}">{{ __('Up to five files and one voice note, 10 MB each.') }}</div>
            <div class="chat-preview mt-2" data-chat-preview hidden>
                <audio controls aria-label="{{ __('Preview voice note') }}"></audio>
                <button type="button" class="btn btn-sm btn-outline-soft" data-chat-remove>{{ __('Remove audio') }}</button>
            </div>
        </form>
    @endif
</section>
<script defer src="{{ asset_v('js/messages.js') }}"></script>
@endsection

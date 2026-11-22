@extends($isAdmin ? 'layouts.admin' : 'layouts.app', ['title' => __('Messages')])

@section('content')
@php
    $threadUrl = $isAdmin ? route('admin.messages.show', $recipient) : route('messages.show');
    $updatesUrl = $isAdmin ? route('admin.messages.updates', $recipient) : route('messages.updates');
    $sendUrl = $isAdmin ? route('admin.messages.store', $recipient) : route('messages.store');
    $canSend = $recipient->is_active && (! $isAdmin || auth('admin')->user()->role !== 'viewer');
    $canSeeCompany = $isAdmin && $recipient->company && \App\Support\AdminAccess::allows(auth('admin')->user(), 'admin.companies.edit');
@endphp

<x-page-header :title="$isAdmin ? $recipient->name : __('Messages')" :subtitle="$isAdmin ? $recipient->company?->name : __('Tenders.af team')" icon="envelope"
    :crumbs="$isAdmin ? [[__('Messages'), route('admin.messages.index')], [$recipient->name]] : []">
    @if ($isAdmin)
        @if ($canSeeCompany)
            <a class="btn btn-outline-soft" href="{{ route('admin.companies.edit', ['id' => $recipient->company_id]) }}"><x-icon name="building" class="me-1" /> {{ __('Company profile') }}</a>
        @endif
        <a class="btn btn-outline-soft" href="{{ route('admin.messages.index') }}"><x-icon name="chevron-left" class="me-1" /> {{ __('All messages') }}</a>
    @endif
</x-page-header>

<section class="card h-auto chat-thread" data-chat @unless ($before) data-updates="{{ $updatesUrl }}" data-last="{{ $messages->last()?->id ?? 0 }}" @endunless>
    @if ($older || $before)
        <nav class="card-header py-2">
            @if ($older)<a class="btn btn-sm btn-light-secondary" href="{{ $threadUrl }}?before={{ $messages->first()->id }}"><x-icon name="arrow-up" class="me-1" />{{ __('Older messages') }}</a>@endif
            @if ($before)<a class="btn btn-sm btn-light-secondary ms-auto" href="{{ $threadUrl }}"><x-icon name="arrow-down" class="me-1" />{{ __('Latest messages') }}</a>@endif
        </nav>
    @endif
    <div class="chat-body" data-chat-history role="log" aria-label="{{ __('Conversation') }}" aria-live="polite" tabindex="0">
        @if ($messages->isEmpty())
            <div class="empty-state my-auto" data-chat-empty>
                <div class="es-icon"><x-icon name="comments" /></div>
                <h3>{{ __('No messages yet.') }}</h3>
                @if ($canSend)<p>{{ __('Write the first message below. Files and voice notes can go with it.') }}</p>@endif
            </div>
        @endif
        @include('messages.rows')
    </div>
    @if ($canSend)
        <form class="chat-compose" method="POST" action="{{ $sendUrl }}" enctype="multipart/form-data" data-chat-form>
            @csrf
            @if ($errors->any())
                <div class="alert alert-danger py-2 small mb-2" role="alert">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <label class="visually-hidden" for="chat-body">{{ __('Message') }}</label>
            <textarea class="form-control" id="chat-body" name="body" rows="2" maxlength="5000" dir="auto" placeholder="{{ __('Write a message') }}" data-chat-text>{{ old('body') }}</textarea>
            <div class="chat-tools">
                <label class="btn btn-sm btn-light-secondary mb-0" for="chat-files" title="{{ __('Attach files') }}"><x-icon name="paperclip" /><span class="btn-label ms-1">{{ __('Attach files') }}</span></label>
                <input class="visually-hidden" id="chat-files" type="file" name="files[]" data-chat-files multiple
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.webp,.zip">
                <button class="btn btn-sm btn-light-secondary" type="button" data-chat-record
                    data-start="{{ __('Record voice') }}" data-stop="{{ __('Stop recording') }}"
                    data-error="{{ __('Microphone unavailable. You can upload an audio file.') }}"
                    data-capturing="{{ __('Recording…') }}" title="{{ __('Record voice') }}" hidden><x-icon name="microphone" /><span class="btn-label ms-1" data-chat-record-label>{{ __('Record voice') }}</span></button>
                <label class="btn btn-sm btn-light-secondary mb-0" for="chat-voice" title="{{ __('Upload audio') }}"><x-icon name="file-audio" /><span class="btn-label ms-1">{{ __('Upload audio') }}</span></label>
                <input class="visually-hidden" id="chat-voice" type="file" name="voice" accept="audio/*,.webm,.m4a" data-chat-voice>
                <button class="btn btn-primary ms-auto" type="submit"><x-icon name="paper-plane" class="me-1" />{{ __('Send') }}</button>
            </div>
            <div class="chat-hint" role="status" data-chat-status
                data-limit="{{ __('Choose up to five files and one voice note, 10 MB each.') }}">{{ __('Up to five files and one voice note, 10 MB each.') }} <span class="d-none d-md-inline">· {{ __('Ctrl + Enter sends.') }}</span></div>
            <div class="chat-preview" data-chat-preview hidden>
                <audio controls aria-label="{{ __('Preview voice note') }}"></audio>
                <button type="button" class="btn btn-sm btn-soft-danger" data-chat-remove title="{{ __('Remove audio') }}"><x-icon name="trash" class="me-1" />{{ __('Remove audio') }}</button>
            </div>
        </form>
    @elseif (! $recipient->is_active)
        <div class="chat-compose small text-muted"><x-icon name="circle-info" class="me-1" />{{ __('This account is switched off, so it cannot receive messages.') }}</div>
    @endif
</section>
<script defer src="{{ asset_v('js/messages.js') }}"></script>
@endsection

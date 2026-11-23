@extends('layouts.app', ['title' => $enquiry->subject])

@section('content')
<x-page-header :title="$enquiry->subject" icon="comments" :crumbs="[[__('Enquiries'), route('enquiries.index')], [$other?->name ?? '']]">
    @if ($other?->is_listed && module_on('directory'))
        <a class="btn btn-outline-soft" href="{{ route('site.companies.show', ['slug' => $other->slug]) }}" target="_blank" rel="noopener"><x-icon name="building" class="me-1" /> {{ $other->name }}</a>
    @endif
</x-page-header>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card mb-0">
            <div class="card-body d-flex flex-column gap-3">
                @foreach ($enquiry->messages as $message)
                    @php($mine = $message->company_id === $companyId)
                    <div class="d-flex {{ $mine ? 'justify-content-end' : '' }}">
                        <div class="p-3 rounded-4 {{ $mine ? 'bg-primary-subtle' : 'bg-light border' }}" style="max-width:min(560px,92%)">
                            <div class="small fw-semibold mb-1">{{ $message->sender_name }} <span class="text-muted fw-normal">· {{ $mine ? __('your company') : ($other?->name ?? '') }} · {{ fmt_datetime($message->created_at) }}</span></div>
                            <div style="white-space:pre-line" dir="auto">{{ $message->body }}</div>
                            @if ($message->attachment_path)
                                <a class="d-inline-flex align-items-center gap-1 small mt-2" href="{{ route('enquiries.file', ['id' => $enquiry->id, 'message' => $message->id]) }}"><x-icon name="paperclip" /> {{ $message->attachment_name }}</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($enquiry->status === 'open')
                <form method="POST" action="{{ route('enquiries.reply', ['id' => $enquiry->id]) }}" enctype="multipart/form-data" class="card-footer d-flex flex-column gap-2">
                    @csrf
                    <label class="visually-hidden" for="e-body">{{ __('Your answer') }}</label>
                    <textarea class="form-control @error('body') is-invalid @enderror" id="e-body" name="body" rows="3" maxlength="5000" required dir="auto" placeholder="{{ __('Write your answer…') }}">{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <input type="file" class="form-control form-control-sm w-auto flex-grow-1 @error('attachment') is-invalid @enderror" name="attachment" accept="application/pdf,image/jpeg,image/png" aria-label="{{ __('Attach a file (PDF, JPG or PNG, up to 5 MB)') }}">
                        <button type="submit" class="btn btn-primary"><x-icon name="paper-plane" class="me-1" /> {{ __('Send') }}</button>
                    </div>
                    @error('attachment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </form>
            @else
                <div class="card-footer text-muted small">{{ __('This enquiry is closed.') }}</div>
            @endif
        </div>
    </div>
    <div class="col-12 col-xl-4 d-flex flex-column gap-3">
        <div class="card mb-0">
            <div class="card-body d-flex flex-column gap-2">
                <form method="POST" action="{{ route('enquiries.status', ['id' => $enquiry->id]) }}">@csrf<button class="btn btn-light-secondary w-100" type="submit">{{ $enquiry->status === 'open' ? __('Close the enquiry') : __('Open it again') }}</button></form>
                @if ($other)
                    <form method="POST" action="{{ route('enquiries.block', ['id' => $enquiry->id]) }}" @unless ($blocked) data-confirm="{{ __('Block :company?', ['company' => $other->name]) }}" data-confirm-text="{{ __('They can no longer send you enquiries or messages. You can undo this here.') }}" data-confirm-ok="{{ __('Block') }}" data-confirm-style="danger" @endunless>@csrf<button class="btn {{ $blocked ? 'btn-light-secondary' : 'btn-light-danger' }} w-100" type="submit"><x-icon name="ban" class="me-1" /> {{ $blocked ? __('Unblock :company', ['company' => $other->name]) : __('Block this company') }}</button></form>
                @endif
            </div>
        </div>
        <div class="card mb-0">
            <div class="card-header"><h6 class="mb-0">{{ __('Report to Tenders.af') }}</h6><div class="form-text mt-0">{{ __('Spam, fraud or abuse. The administrators can read this thread only after you report it.') }}</div></div>
            @if ($enquiry->reported_at)
                <div class="card-body small text-muted">{{ __('Reported :date.', ['date' => fmt_datetime($enquiry->reported_at)]) }}</div>
            @else
                <form method="POST" action="{{ route('enquiries.report', ['id' => $enquiry->id]) }}" class="card-body d-flex flex-column gap-2">
                    @csrf
                    <label class="visually-hidden" for="e-reason">{{ __('What is wrong') }}</label>
                    <textarea class="form-control form-control-sm @error('reason') is-invalid @enderror" id="e-reason" name="reason" rows="2" maxlength="500" required placeholder="{{ __('What is wrong?') }}"></textarea>
                    <button class="btn btn-sm btn-outline-danger" type="submit"><x-icon name="flag" class="me-1" /> {{ __('Report') }}</button>
                </form>
            @endif
        </div>
        <p class="small text-muted mb-0"><x-icon name="lock" class="me-1" /> {{ __('Only the people of the two companies read this thread.') }}</p>
    </div>
</div>
@endsection

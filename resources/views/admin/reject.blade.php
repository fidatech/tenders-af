@extends('layouts.admin', ['title' => __('Reject the payment')])

@section('content')
<x-page-header :title="__('Reject the payment')" icon="ban" :crumbs="[[__('Subscriptions'), route('admin.subscriptions.index', ['paid' => 'no'])], [$subscription->company?->name], [__('Reject')]]" />

<form method="POST" action="{{ route('admin.subscriptions.reject.store', ['id' => $subscription->id]) }}" class="card h-auto" style="max-width:720px">
    @csrf
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 pb-3 mb-3 border-bottom">
            <span class="avatar avatar-md avatar-soft-primary flex-shrink-0">{{ \App\Models\User::initialsOf($subscription->company?->name) }}</span>
            <div class="min-w-0 flex-grow-1">
                <div class="fw-semibold">{{ $subscription->company?->name }}</div>
                <div class="small text-muted">{{ money($subscription->amount, $subscription->currency) }} · {{ __('sent :date', ['date' => fmt_date($subscription->sent_on ?? $subscription->created_at)]) }}@if ($subscription->reference) · {{ $subscription->reference }}@endif</div>
            </div>
            @if ($subscription->receipt_path)
                <a class="btn btn-sm btn-light-secondary flex-shrink-0" target="_blank" rel="noopener" href="{{ route('admin.subscriptions.receipt', ['id' => $subscription->id]) }}"><x-icon name="paperclip" class="me-1" />{{ __('Screenshot') }}</a>
            @endif
        </div>

        <label class="form-label">{{ __('Why is it rejected?') }} <span class="text-danger">*</span></label>
        <div class="d-flex flex-column gap-2">
            @foreach ($reasons as $key => $reason)
                <div>
                    <input type="radio" class="btn-check" name="reject_reason" id="reason-{{ $key }}" value="{{ $key }}" autocomplete="off" @checked(old('reject_reason') === $key) required>
                    <label class="btn btn-outline-primary w-100 text-start py-2" for="reason-{{ $key }}">
                        <span class="d-block fw-semibold">{{ __($reason['label']) }}</span>
                        <span class="d-block small fw-normal opacity-75">{{ __($reason['text']) }}</span>
                    </label>
                </div>
            @endforeach
        </div>
        @error('reject_reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

        <label class="form-label mt-3" for="reject-note">{{ __('Anything to add') }} <span class="text-muted fw-normal">({{ __('not needed') }})</span></label>
        <input type="text" class="form-control @error('reject_note') is-invalid @enderror" id="reject-note" name="reject_note" value="{{ old('reject_note') }}" maxlength="255">
        @error('reject_note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="form-text">{{ __('The company gets an email with the reason and sees it on its Subscription page, where it can send a new screenshot.') }}</div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('admin.subscriptions.index', ['paid' => 'no']) }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-soft-danger"><x-icon name="ban" class="me-1" /> {{ __('Reject the payment') }}</button>
    </div>
</form>
@endsection

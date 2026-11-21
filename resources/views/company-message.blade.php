@extends('layouts.app', ['title' => __('Contact admin')])

@section('content')
<x-page-header :title="__('Contact admin')" icon="envelope" />

<form method="POST" action="{{ route('company-message.store') }}" class="card" style="max-width:720px">
    @csrf
    <div class="card-body">
        <p class="text-muted">{{ __('Your message will include your name and company. Administrators can reply to your account email.') }}</p>
        <div class="mb-3">
            <label class="form-label" for="message-subject">{{ __('Subject') }}</label>
            <input class="form-control @error('subject') is-invalid @enderror" id="message-subject" name="subject" value="{{ old('subject') }}" dir="auto" maxlength="150" required>
            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="message-body">{{ __('Message') }}</label>
            <textarea class="form-control @error('message') is-invalid @enderror" id="message-body" name="message" dir="auto" rows="7" maxlength="5000" required>{{ old('message') }}</textarea>
            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">{{ __('Send message') }}</button>
    </div>
</form>
@endsection

@extends('layouts.auth', ['title' => __('Choose a new password')])

@section('content')
<div class="auth-card">
    <div class="ac-icon"><x-icon name="key" /></div>
    <h2>{{ __('Choose a new password') }}</h2>
    <p class="ac-sub">{{ __('At least 8 characters') }}</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3">
            <label class="form-label" for="email">{{ __('app.auth.email') }}</label>
            <div class="auth-field"><x-icon name="envelope" set="regular" /><input type="email" id="email" name="email" value="{{ old('email', $email) }}" class="form-control @error('email') is-invalid @enderror" autocomplete="username" required></div>
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">{{ __('New password') }}</label>
            <div class="auth-field"><x-icon name="lock" /><input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="8" required autofocus><button type="button" class="field-eye" data-toggle-password aria-label="{{ __('app.auth.show_password') }}"><x-icon name="eye" set="regular" /></button></div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="form-label" for="password_confirmation">{{ __('New password again') }}</label>
            <div class="auth-field"><x-icon name="lock" /><input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="8" required></div>
        </div>
        <button type="submit" class="btn btn-gradient btn-lg w-100">{{ __('Save the new password') }}</button>
    </form>
</div>
@endsection

@extends('layouts.auth', ['title' => __('app.auth.title')])

@section('content')
<div class="auth-card">
    <div class="ac-icon"><x-icon name="right-to-bracket" /></div>
    <h2>{{ __('app.auth.welcome') }}</h2>
    <p class="ac-sub">{{ __('app.auth.subtitle') }}</p>

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">{{ __('app.auth.email') }}</label>
            <div class="auth-field"><x-icon name="envelope" set="regular" /><input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="username" required autofocus></div>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">{{ __('app.auth.password') }}</label>
            <div class="auth-field"><x-icon name="lock" /><input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required><button type="button" class="field-eye" data-toggle-password aria-label="{{ __('app.auth.show_password') }}"><x-icon name="eye" set="regular" /></button></div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" checked>
                <label class="form-check-label small" for="remember">{{ __('app.auth.remember') }}</label>
            </div>
            <a class="small text-decoration-none" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
        </div>
        <button type="submit" class="btn btn-gradient btn-lg w-100">{{ __('app.auth.submit') }} <x-icon name="arrow-right" class="ms-1" /></button>
    </form>
    @if (\App\Support\Platform::bool('registration'))
        <p class="text-center small text-muted mt-4 mb-0">{{ __('New here?') }} <a class="text-decoration-none fw-semibold" href="{{ route('register') }}">{{ __('Create an account') }}</a></p>
    @endif
</div>
@endsection

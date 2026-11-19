@extends('layouts.auth', ['title' => __('Forgot your password?')])

@section('content')
<div class="auth-card">
    <div class="ac-icon"><x-icon name="key" /></div>
    <h2>{{ __('Forgot your password?') }}</h2>
    <p class="ac-sub">{{ __('Write your email address and we will send you a link to choose a new one.') }}</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-4">
            <label class="form-label" for="email">{{ __('app.auth.email') }}</label>
            <div class="auth-field"><x-icon name="envelope" set="regular" /><input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="username" required autofocus></div>
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-gradient btn-lg w-100">{{ __('Send the link') }}</button>
    </form>
    <p class="text-center small mt-4 mb-0"><a class="text-decoration-none" href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
</div>
@endsection

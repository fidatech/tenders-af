@extends('layouts.auth', ['title' => __('Register your company')])

@section('content')
<div class="auth-card">
    <div class="ac-icon"><x-icon name="building" /></div>
    <h2>{{ __('Register your company') }}</h2>
    <p class="ac-sub">{{ __('Free. No card needed.') }}</p>

    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="company">{{ __('Name of your company or organisation') }}</label>
            <input type="text" id="company" name="company" value="{{ old('company') }}" class="form-control @error('company') is-invalid @enderror" maxlength="150" required autofocus>
            @error('company')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="kind">{{ __('You are') }}</label>
            <select id="kind" name="kind" class="form-select @error('kind') is-invalid @enderror" required data-plain>
                @foreach ($kinds as $key => [$label, $currency])
                    <option value="{{ $key }}" @selected(old('kind', 'company') === $key)>{{ __($label) }} ({{ __('plans in :currency', ['currency' => $currency]) }})</option>
                @endforeach
            </select>
            @error('kind')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6" data-for-kind="company">
                <label class="form-label" for="province_id">{{ __('Province') }}</label>
                <select id="province_id" name="province_id" class="form-select @error('province_id') is-invalid @enderror" data-plain>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($provinces as $id => $province)
                        <option value="{{ $id }}" @selected((string) old('province_id') === (string) $id)>{{ $province }}</option>
                    @endforeach
                </select>
                @error('province_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="country">{{ __('Country') }}</label>
                <input type="text" id="country" name="country" value="{{ old('country', 'Afghanistan') }}" class="form-control @error('country') is-invalid @enderror" maxlength="100">
                @error('country')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="name">{{ __('Your name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" maxlength="150" autocomplete="name" required>
            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
                <label class="form-label" for="email">{{ __('app.auth.email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="username" required>
                @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="phone">{{ __('Phone or WhatsApp') }}</label>
                <input type="text" id="phone" name="phone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror" maxlength="50" dir="ltr" placeholder="+93 70 000 0000" autocomplete="tel">
                @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">{{ __('app.auth.password') }}</label>
            <div class="auth-field"><x-icon name="lock" /><input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="8" required><button type="button" class="field-eye" data-toggle-password aria-label="{{ __('app.auth.show_password') }}"><x-icon name="eye" set="regular" /></button></div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password_confirmation">{{ __('Password again') }}</label>
            <div class="auth-field"><x-icon name="lock" /><input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="8" required></div>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" id="terms" name="terms" value="1" @checked(old('terms')) required>
            <label class="form-check-label small" for="terms">{{ __('I accept the') }} <a href="{{ route('site.terms') }}" target="_blank" rel="noopener">{{ __('terms') }}</a> {{ __('and the') }} <a href="{{ route('site.privacy') }}" target="_blank" rel="noopener">{{ __('privacy policy') }}</a>.</label>
            @error('terms')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-gradient btn-lg w-100">{{ __('Register my company') }} <x-icon name="arrow-right" class="ms-1" /></button>
    </form>
    <p class="text-center small text-muted mt-4 mb-0">{{ __('Already registered?') }} <a class="text-decoration-none fw-semibold" href="{{ route('login') }}">{{ __('app.auth.submit') }}</a></p>
</div>
@endsection

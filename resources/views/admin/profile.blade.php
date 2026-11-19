@extends('layouts.admin', ['title' => __('My account')])

@section('content')
<x-page-header :title="__('My account')" icon="user" />

<form method="POST" action="{{ route('admin.profile.update') }}" class="card h-auto" style="max-width:720px">
    @csrf
    @method('PUT')
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="a-name">{{ __('Full name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="a-name" name="name" value="{{ old('name', $admin->name) }}" maxlength="150" required>
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="a-email">{{ __('Email address') }} <span class="text-danger">*</span></label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="a-email" name="email" value="{{ old('email', $admin->email) }}" maxlength="255" required dir="ltr">
                @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('You sign in with it, and payment requests are emailed to it.') }}</div>
            </div>

            <div class="col-12"><div class="border-top pt-3 mt-2"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ __('Change the password') }}</h6><div class="form-text">{{ __('Leave these empty to keep your password.') }}</div></div></div>
            @foreach (['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'New password again'] as $name => $label)
                <div class="col-12 col-md-4">
                    <label class="form-label" for="a-{{ $name }}">{{ __($label) }}</label>
                    <input type="password" class="form-control @error($name) is-invalid @enderror" id="a-{{ $name }}" name="{{ $name }}" autocomplete="{{ $name === 'current_password' ? 'current-password' : 'new-password' }}">
                    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            @endforeach
        </div>
    </div>
    <div class="card-footer text-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
</form>
@endsection

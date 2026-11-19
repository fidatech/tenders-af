@extends('layouts.app', ['title' => __('Change Password')])

@section('content')
<x-page-header :title="__('Change Password')" icon="key" />

<form method="POST" action="{{ route('profile.password.update') }}" class="card" style="max-width:520px">
    @csrf
    @method('PUT')
    <div class="card-body">
        <div class="row g-3">
            @foreach (['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'New password again'] as $name => $label)
                <div class="col-12">
                    <label class="form-label" for="pw-{{ $name }}">{{ __($label) }} <span class="text-danger">*</span></label>
                    <input type="password" class="form-control @error($name) is-invalid @enderror" id="pw-{{ $name }}" name="{{ $name }}" required autocomplete="{{ $name === 'current_password' ? 'current-password' : 'new-password' }}">
                    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @if ($name === 'password')<div class="form-text">{{ __('At least 8 characters') }}</div>@endif
                </div>
            @endforeach
        </div>
    </div>
    <div class="card-footer text-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Change Password') }}</button></div>
</form>
@endsection

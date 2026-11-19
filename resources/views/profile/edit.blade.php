@extends('layouts.app', ['title' => __('My Profile')])

@section('content')
<x-page-header :title="__('My Profile')" icon="user" />

<form method="POST" action="{{ route('profile.update') }}" class="card" style="max-width:720px">
    @csrf
    @method('PUT')
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="p-name">{{ __('Full name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="p-name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255">
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="p-email">{{ __('Email address') }} <span class="text-danger">*</span></label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="p-email" name="email" value="{{ old('email', $user->email) }}" required dir="ltr">
                @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6"><label class="form-label">{{ __('Company') }}</label><input type="text" class="form-control" value="{{ $user->company?->name }}" disabled></div>
            <div class="col-12 col-md-6"><label class="form-label">{{ __('Role') }}</label><input type="text" class="form-control" value="{{ $user->isOwner() ? __('Owner') : __('Member') }}" disabled></div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('profile.password') }}">{{ __('Change Password') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection

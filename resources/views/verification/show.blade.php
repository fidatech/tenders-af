@extends('layouts.app', ['title' => __('Verification')])

@php
    $status = $company->verify_status;
    $tones = ['none' => ['secondary', 'shield-halved'], 'pending' => ['warning', 'hourglass-half'], 'verified' => ['success', 'circle-check'], 'rejected' => ['danger', 'circle-xmark']];
    [$tone, $icon] = $tones[$status] ?? $tones['none'];
@endphp

@section('content')
<x-page-header :title="__('Verification')" icon="shield-halved" :subtitle="__('A verified company carries a badge in the directory, and buyers can filter for it.')" />

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card h-auto">
            <div class="card-body d-flex align-items-start gap-3">
                <span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:44px;height:44px"><x-icon :name="$icon" /></span>
                <div class="min-w-0">
                    <div class="fs-5 fw-bold">{{ $company->verifyLabel() }}</div>
                    @if ($status === 'verified')
                        <div class="text-muted">{{ __('Verified on :date. Your profile shows the badge.', ['date' => fmt_date($company->verified_at)]) }}</div>
                    @elseif ($status === 'pending')
                        <div class="text-muted">{{ __('You sent your licence on :date. We are checking it.', ['date' => fmt_date($company->verify_requested_at)]) }}</div>
                    @elseif ($status === 'rejected')
                        <div class="text-danger">{{ $company->verify_note ?: __('The document could not be accepted.') }}</div>
                        <div class="small text-muted">{{ __('Please send a clear, valid licence again.') }}</div>
                    @else
                        <div class="text-muted">{{ __('Send your business licence once. We check it, usually within two working days.') }}</div>
                    @endif
                    @if ($company->verify_doc_path)
                        <a class="btn btn-sm btn-light-secondary mt-2" href="{{ route('verification.document') }}" target="_blank" rel="noopener"><x-icon name="file-lines" class="me-1" />{{ __('The document you sent') }}</a>
                    @endif
                </div>
            </div>
        </div>

        @if (in_array($status, ['none', 'rejected'], true))
            <div class="card h-auto">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Send your licence') }}</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('verification.store') }}" enctype="multipart/form-data" class="row g-3">
                        @csrf
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="v-license">{{ __('Licence number') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('license_no') is-invalid @enderror" id="v-license" name="license_no" value="{{ old('license_no', $company->license_no) }}" maxlength="100" dir="ltr" required>
                            @error('license_no')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="v-tin">{{ __('Tax number (TIN)') }}</label>
                            <input type="text" class="form-control @error('tin') is-invalid @enderror" id="v-tin" name="tin" value="{{ old('tin', $company->tin) }}" maxlength="100" dir="ltr">
                            @error('tin')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="v-document">{{ __('Scan or photo of the licence') }} <span class="text-danger">*</span></label>
                            <input type="file" class="form-control @error('document') is-invalid @enderror" id="v-document" name="document" accept="application/pdf,image/*" required>
                            @error('document')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('PDF or picture, up to 10 MB. Only our staff see it; it is never shown on your public page.') }}</div>
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-primary"><x-icon name="paper-plane" class="me-1" /> {{ __('Send for verification') }}</button></div>
                    </form>
                </div>
            </div>
        @endif
    </div>
    <div class="col-12 col-xl-5">
        <div class="card h-auto">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Why get verified?') }}</h5></div>
            <div class="card-body">
                <ul class="list-clean">
                    <li><x-icon name="circle-check" class="text-success" /><span class="flex-grow-1">{{ __('A "Verified" badge on your public profile') }}</span></li>
                    <li><x-icon name="circle-check" class="text-success" /><span class="flex-grow-1">{{ __('Buyers can filter the directory for verified companies') }}</span></li>
                    <li><x-icon name="circle-check" class="text-success" /><span class="flex-grow-1">{{ __('Verification is free') }}</span></li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

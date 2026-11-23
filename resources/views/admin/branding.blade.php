@extends('layouts.admin', ['title' => __('Branding & Logo')])

@php
    [$first, $last] = \App\Support\Branding::nameParts();
@endphp

@section('content')
<x-page-header :title="__('Branding & Logo')" icon="image" :subtitle="__('The name and pictures of the platform. Until a logo is uploaded, the site shows the text brand.')" />

<form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data" class="card h-auto" style="max-width:980px">
    @csrf
    @method('PUT')
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ __('Name') }}</h6><div class="form-text">{{ __('Used in page titles, emails, PDFs and the text brand.') }}</div></div>
            <div class="col-12 col-md-5">
                <label class="form-label" for="b-name">{{ __('Website name') }}</label>
                <input type="text" class="form-control @error('brand_name') is-invalid @enderror" id="b-name" name="brand_name" value="{{ old('brand_name', $values['brand_name']) }}" placeholder="{{ config('app.name') }}" maxlength="60">
                @error('brand_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('Empty: :name.', ['name' => config('app.name')]) }}</div>
            </div>
            <div class="col-12 col-md-7">
                <label class="form-label" for="b-tagline">{{ __('Tagline') }}</label>
                <input type="text" class="form-control @error('brand_tagline') is-invalid @enderror" id="b-tagline" name="brand_tagline" value="{{ old('brand_tagline', $values['brand_tagline']) }}" placeholder="{{ config('site.tagline') }}" maxlength="160">
                @error('brand_tagline')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-12"><div class="border-top pt-3 mt-2"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ __('Pictures') }}</h6><div class="form-text">{{ __('PNG, JPG or WebP, up to :kb KB. Each picture is checked and saved again as PNG; nothing else in the file is kept.', ['kb' => \App\Support\Branding::MAX_KB]) }}</div></div></div>
            @foreach (\App\Support\Branding::ASSETS as $asset => [$label, $help])
                @php($url = \App\Support\Branding::url($asset))
                <div class="col-12 col-lg-6">
                    <div class="border rounded-3 p-3 h-100 d-flex flex-column gap-2">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <label class="form-label mb-0 fw-semibold" for="b-file-{{ $asset }}">{{ __($label) }}</label>
                            @if ($url)<span class="badge bg-success-subtle text-success-emphasis">{{ __('Uploaded') }}</span>@else<span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Not set') }}</span>@endif
                        </div>
                        <div class="brand-preview {{ in_array($asset, ['logo_dark'], true) ? 'on-dark' : '' }}" aria-label="{{ __('Preview') }}">
                            @if ($url)
                                <img src="{{ $url }}" alt="{{ __($label) }}">
                            @else
                                <span class="text-muted small">{{ in_array($asset, ['logo', 'logo_dark', 'logo_mobile'], true) ? __('Text brand is shown:') : __('Built-in mark is used') }}</span>
                                @if (in_array($asset, ['logo', 'logo_dark', 'logo_mobile'], true))<strong class="brand-preview-text">{{ $first }}<b>{{ $last }}</b></strong>@endif
                            @endif
                        </div>
                        <input type="file" class="form-control form-control-sm @error('file_'.$asset) is-invalid @enderror" id="b-file-{{ $asset }}" name="file_{{ $asset }}" accept="image/png,image/jpeg,image/webp">
                        @error('file_'.$asset)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text mt-0">{{ __($help) }}</div>
                        @if ($url)
                            <div><button type="submit" form="remove-{{ $asset }}" class="btn btn-sm btn-outline-danger"><x-icon name="trash" class="me-1" /> {{ __('Remove') }}</button></div>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="col-12"><div class="border-top pt-3 mt-2"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ __('How the logo is shown') }}</h6></div></div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="b-h">{{ __('Height on computers (px)') }}</label>
                <input type="number" min="16" max="120" class="form-control @error('brand_logo_height') is-invalid @enderror" id="b-h" name="brand_logo_height" value="{{ old('brand_logo_height', $values['brand_logo_height']) }}">
                @error('brand_logo_height')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="b-hm">{{ __('Height on phones (px)') }}</label>
                <input type="number" min="16" max="120" class="form-control @error('brand_logo_height_mobile') is-invalid @enderror" id="b-hm" name="brand_logo_height_mobile" value="{{ old('brand_logo_height_mobile', $values['brand_logo_height_mobile']) }}">
                @error('brand_logo_height_mobile')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="b-display">{{ __('Show') }}</label>
                <select class="form-select" id="b-display" name="brand_display" data-native>
                    <option value="logo" @selected(old('brand_display', $values['brand_display']) === 'logo')>{{ __('The logo alone') }}</option>
                    <option value="logo_name" @selected(old('brand_display', $values['brand_display']) === 'logo_name')>{{ __('The logo with the website name beside it') }}</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="b-alt">{{ __('Text for screen readers (alt text)') }}</label>
                <input type="text" class="form-control @error('brand_logo_alt') is-invalid @enderror" id="b-alt" name="brand_logo_alt" value="{{ old('brand_logo_alt', $values['brand_logo_alt']) }}" maxlength="120" placeholder="{{ \App\Support\Branding::name() }}">
                <div class="form-text">{{ __('Empty: the website name.') }}</div>
            </div>
        </div>
    </div>
    <div class="card-footer text-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
</form>

@foreach (array_keys(\App\Support\Branding::ASSETS) as $asset)
    @if (\App\Support\Branding::has($asset))
        <form id="remove-{{ $asset }}" method="POST" action="{{ route('admin.branding.destroy', ['asset' => $asset]) }}" hidden
              data-confirm="{{ __('Remove this picture?') }}" data-confirm-text="{{ __('The site falls back to the text brand or the main logo.') }}" data-confirm-ok="{{ __('Remove') }}">@csrf @method('DELETE')</form>
    @endif
@endforeach

<style>
.brand-preview { min-height: 88px; border-radius: 10px; background: repeating-conic-gradient(#f1f3f2 0 25%, #fff 0 50%) 0 0 / 16px 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 12px; }
.brand-preview.on-dark { background: linear-gradient(135deg, #060B08 0%, #0A2A1A 52%, #0B5A33 100%); }
.brand-preview.on-dark .text-muted { color: #B9CCC1 !important; }
.brand-preview img { max-height: 72px; max-width: 100%; object-fit: contain; }
.brand-preview-text { font: 800 22px/1 system-ui; color: #0E1512; }
.brand-preview.on-dark .brand-preview-text { color: #fff; }
.brand-preview-text b { color: #18A05A; }
</style>
@endsection

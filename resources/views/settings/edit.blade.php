@extends('layouts.app', ['title' => __('Company profile')])

@php
    $sizes = \App\Models\Company::SIZES;
    $pickedIndustries = array_map('intval', old('industries', $mineIndustries));
    $pickedCategories = array_map('intval', old('categories', $mineCategories));
@endphp

@section('content')
<x-page-header :title="__('Company profile')" icon="building" :subtitle="__('What buyers see about your company in the directory, and the sectors your tender alerts follow.')">
    <a class="btn btn-outline-soft" href="{{ route('site.companies.show', ['slug' => $company->slug]) }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('See the public page') }}</a>
</x-page-header>

<form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="row g-3">
        <div class="col-12 col-xl-8 d-flex flex-column gap-3">
            <div class="card mb-0">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft primary"><x-icon name="building" /></span><div><h5>{{ __('Your company') }}</h5><p>{{ __('The name, the contact details and a few words about what you do.') }}</p></div></div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-name">{{ __('Name of the company') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="s-name" name="name" value="{{ old('name', $company->name) }}" maxlength="150" required>
                            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-kind">{{ __('Registered as') }}</label>
                            <input type="text" class="form-control" id="s-kind" value="{{ $company->kindLabel() }} · {{ __('pays in :currency', ['currency' => $company->currency]) }}" readonly>
                            <div class="form-text">{{ __('To change this, please contact support.') }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-phone">{{ __('Phone') }}</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="s-phone" name="phone" value="{{ old('phone', $company->phone) }}" maxlength="50" dir="ltr" placeholder="+93 70 000 0000">
                            @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-website">{{ __('Website') }}</label>
                            <input type="url" class="form-control @error('website') is-invalid @enderror" id="s-website" name="website" value="{{ old('website', $company->website) }}" maxlength="255" dir="ltr" placeholder="https://">
                            @error('website')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-country">{{ __('Country') }}</label>
                            <input type="text" class="form-control @error('country') is-invalid @enderror" id="s-country" name="country" value="{{ old('country', $company->country) }}" maxlength="100">
                            @error('country')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-province">{{ __('Province') }}</label>
                            <select class="form-select @error('province_id') is-invalid @enderror" id="s-province" name="province_id" data-search>
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($provinces as $id => $name)
                                    <option value="{{ $id }}" @selected((string) old('province_id', $company->province_id) === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('province_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="s-address">{{ __('Address') }}</label>
                            <input type="text" class="form-control @error('address') is-invalid @enderror" id="s-address" name="address" value="{{ old('address', $company->address) }}" maxlength="255" dir="auto">
                            @error('address')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="s-about">{{ __('About the company') }}</label>
                            <textarea class="form-control @error('about') is-invalid @enderror" id="s-about" name="about" rows="4" maxlength="3000" dir="auto">{{ old('about', $company->about) }}</textarea>
                            @error('about')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('What you supply or build, for whom, and where. Buyers read this first.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft success"><x-icon name="id-card" /></span><div><h5>{{ __('Registration details') }}</h5><p>{{ __('Buyers often ask for these in a tender.') }}</p></div></div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-license">{{ __('Licence number') }}</label>
                            <input type="text" class="form-control @error('license_no') is-invalid @enderror" id="s-license" name="license_no" value="{{ old('license_no', $company->license_no) }}" maxlength="100" dir="ltr">
                            @error('license_no')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-tin">{{ __('Tax number (TIN)') }}</label>
                            <input type="text" class="form-control @error('tin') is-invalid @enderror" id="s-tin" name="tin" value="{{ old('tin', $company->tin) }}" maxlength="100" dir="ltr">
                            @error('tin')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-year">{{ __('Year founded') }}</label>
                            <input type="number" class="form-control @error('founded_year') is-invalid @enderror" id="s-year" name="founded_year" value="{{ old('founded_year', $company->founded_year) }}" min="1900" max="{{ date('Y') }}" dir="ltr">
                            @error('founded_year')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-size">{{ __('Size of the company') }}</label>
                            <select class="form-select @error('size') is-invalid @enderror" id="s-size" name="size" data-plain>
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($sizes as $key => $label)
                                    <option value="{{ $key }}" @selected(old('size', $company->size) === $key)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                            @error('size')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-0" id="sectors">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft warning"><x-icon name="tags" /></span><div><h5>{{ __('Sectors and categories') }}</h5><p>{{ __('Tick what your company does. Your tender alerts follow these, and buyers find you by them.') }}</p></div></div>
                </div>
                <div class="card-body">
                    @error('industries')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                    @forelse ($industries as $industry)
                        <div class="border rounded-3 p-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="ind-{{ $industry->id }}" name="industries[]" value="{{ $industry->id }}" @checked(in_array($industry->id, $pickedIndustries, true)) data-industry="{{ $industry->id }}">
                                <label class="form-check-label fw-semibold" for="ind-{{ $industry->id }}">{{ $industry->name }}</label>
                            </div>
                            @if ($industry->categories->isNotEmpty())
                                <div class="d-flex flex-wrap gap-3 mt-2 ps-4" data-categories-of="{{ $industry->id }}">
                                    @foreach ($industry->categories as $category)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="cat-{{ $category->id }}" name="categories[]" value="{{ $category->id }}" @checked(in_array($category->id, $pickedCategories, true))>
                                            <label class="form-check-label small" for="cat-{{ $category->id }}">{{ $category->name }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('No sectors are set up yet.') }}</p>
                    @endforelse
                    <div class="form-text">{{ __('A sector without ticked categories means every tender of that sector.') }}</div>
                </div>
            </div>

            <div class="save-bar">
                <span class="small text-muted">{{ __('Changes are kept when you press Save.') }}</span>
                <button type="submit" class="btn btn-primary px-4"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card h-auto">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Logo') }}</h5></div>
                <div class="card-body">
                    @if ($company->logo_path)
                        <img src="{{ route('site.companies.logo', ['slug' => $company->slug]) }}" alt="{{ __('Logo of :name', ['name' => $company->name]) }}" class="border rounded-3 mb-3" style="width:96px;height:96px;object-fit:contain;background:#fff">
                    @endif
                    <label class="form-label" for="s-logo">{{ $company->logo_path ? __('Replace the logo') : __('Add a logo') }}</label>
                    <input type="file" class="form-control @error('logo') is-invalid @enderror" id="s-logo" name="logo" accept="image/*">
                    @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('A square picture, up to 2 MB.') }}</div>
                </div>
            </div>
            <div class="card h-auto">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">{{ __('In the directory') }}</h5>
                    <span class="badge {{ $company->isVerified() ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">{{ $company->verifyLabel() }}</span>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="is_listed" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="s-listed" name="is_listed" value="1" @checked(old('is_listed', $company->is_listed))>
                        <label class="form-check-label" for="s-listed">{{ __('Show my company in the public directory') }}</label>
                    </div>
                    <div class="d-flex justify-content-between small fw-semibold mb-1"><span>{{ __('Profile complete') }}</span><span>{{ $profile['percent'] }}%</span></div>
                    <div class="progress mb-3" style="height:8px" role="progressbar" aria-valuenow="{{ $profile['percent'] }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: {{ $profile['percent'] }}%"></div></div>
                    @unless ($company->isVerified())
                        <a class="btn btn-soft-primary w-100" href="{{ route('verification.show') }}"><x-icon name="shield-halved" class="me-1" /> {{ __('Get verified') }}</a>
                    @endunless
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

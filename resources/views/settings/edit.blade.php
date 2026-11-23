@extends('layouts.app', ['title' => __('Company profile')])

@php
    $sizes = \App\Models\Company::SIZES;
    $pickedIndustries = array_map('intval', old('industries', $mineIndustries));
    $pickedCategories = array_map('intval', old('categories', $mineCategories));
    $pickedTypes = old('business_types', (array) $company->business_types);
    $pickedFields = old('contact_fields', $company->approvedContactFields());
    $services = old('services', $company->serviceList());
    $services = array_pad(array_values($services), max(3, count($services) + 1), ['name' => '', 'text' => '']);
@endphp

@section('content')
<x-page-header :title="__('Company profile')" icon="building" :subtitle="__('What buyers see about your company in the directory, and the sectors your tender alerts follow.')">
    @if (module_on('directory'))<a class="btn btn-outline-soft" href="{{ route('site.companies.show', ['slug' => $company->slug]) }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('See the public page') }}</a>@endif
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
                            <div class="input-group domain-group">
                                <span class="input-group-text" aria-hidden="true"><x-icon name="lock" class="me-1" />https://</span>
                                <input type="text" class="form-control @error('website') is-invalid @enderror" id="s-website" name="website" value="{{ old('website', \App\Models\Company::websiteHost($company->website)) }}" maxlength="200" dir="ltr" inputmode="url" autocomplete="url" autocapitalize="off" spellcheck="false" placeholder="yourcompany.af" data-domain aria-describedby="s-website-help">
                            </div>
                            <div class="form-text" id="s-website-help">{{ __('Only the address, like yourcompany.af. No www or https.') }}</div>
                            @error('website')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-email">{{ __('Business email') }}</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="s-email" name="email" value="{{ old('email', $company->email) }}" maxlength="255" dir="ltr" autocomplete="email">
                            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-country">{{ __('Country') }}</label>
                            <x-country-select id="s-country" :value="old('country', $company->country)" :class="$errors->has('country') ? 'form-select is-invalid' : 'form-select'" />
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

            <div class="card mb-0" id="business-type">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft info"><x-icon name="industry" /></span><div><h5>{{ __('What kind of business') }}</h5><p>{{ __('Tick every kind that fits. Buyers filter the directory by these.') }}</p></div></div>
                </div>
                <div class="card-body">
                    <input type="hidden" name="business_types_shown" value="1">
                    <div class="row g-2">
                        @foreach (\App\Models\Company::BUSINESS_TYPES as $key => $label)
                            <div class="col-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bt-{{ $key }}" name="business_types[]" value="{{ $key }}" @checked(in_array($key, $pickedTypes, true))>
                                    <label class="form-check-label" for="bt-{{ $key }}">{{ __($label) }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('business_types.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            @if (module_on('mini_websites'))
            <div class="card mb-0" id="page">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft primary"><x-icon name="window-maximize" /></span><div><h5>{{ __('Your public page') }}</h5><p>{{ __('How your page looks to buyers. Write only what is true; nothing is added for you.') }}</p></div></div>
                </div>
                <div class="card-body">
                    <input type="hidden" name="page_shown" value="1">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="s-tagline">{{ __('Slogan') }}</label>
                            <input type="text" class="form-control @error('tagline') is-invalid @enderror" id="s-tagline" name="tagline" value="{{ old('tagline', $company->tagline) }}" maxlength="160" dir="auto" placeholder="{{ __('For example: Roads and bridges across the north since 2008') }}">
                            @error('tagline')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-template">{{ __('Template') }}</label>
                            <select class="form-select" id="s-template" name="page_template" data-plain>
                                @foreach (\App\Models\Company::TEMPLATES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('page_template', $company->template()) === $key)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="form-label d-block" id="s-accent-label">{{ __('Colour') }}</span>
                            <div class="d-flex flex-wrap gap-2" role="radiogroup" aria-labelledby="s-accent-label">
                                @foreach (\App\Models\Company::ACCENTS as $hex => $label)
                                    <label class="accent-pick" title="{{ __($label) }}">
                                        <input type="radio" name="page_accent" value="{{ $hex }}" @checked(old('page_accent', $company->accent()) === $hex)>
                                        <span style="background: {{ $hex }}"></span><em class="visually-hidden">{{ __($label) }}</em>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="s-cover">{{ __('Cover picture') }}</label>
                            @if ($company->cover_path)
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <img src="{{ route('site.companies.cover', ['slug' => $company->slug]) }}" alt="{{ __('Cover picture') }}" class="rounded-3 border" style="width:160px;height:60px;object-fit:cover">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_cover" value="1" id="s-remove-cover"><label class="form-check-label" for="s-remove-cover">{{ __('Remove it') }}</label></div>
                                </div>
                            @endif
                            <input type="file" class="form-control @error('cover') is-invalid @enderror" id="s-cover" name="cover" accept="image/png,image/jpeg,image/webp">
                            @error('cover')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('A wide photo of your work, at least 600 pixels wide, up to 4 MB.') }}</div>
                        </div>
                        <div class="col-12">
                            <span class="form-label d-block">{{ __('Services') }}</span>
                            <div class="d-flex flex-column gap-2">
                                @foreach ($services as $i => $service)
                                    <div class="row g-2">
                                        <div class="col-12 col-md-4"><input type="text" class="form-control" name="services[{{ $i }}][name]" value="{{ $service['name'] ?? '' }}" maxlength="100" dir="auto" placeholder="{{ __('Service') }}" aria-label="{{ __('Service :n', ['n' => $i + 1]) }}"></div>
                                        <div class="col-12 col-md-8"><input type="text" class="form-control" name="services[{{ $i }}][text]" value="{{ $service['text'] ?? '' }}" maxlength="300" dir="auto" placeholder="{{ __('One sentence about it (optional)') }}" aria-label="{{ __('About service :n', ['n' => $i + 1]) }}"></div>
                                    </div>
                                @endforeach
                            </div>
                            @error('services.*.name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('Save to get another empty row. Up to 20 services.') }}</div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="card mb-0" id="contact">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft danger"><x-icon name="address-card" /></span><div><h5>{{ __('Contact details on your page') }}</h5><p>{{ __('You decide which details are shown, and to whom. Nothing is shown without your tick.') }}</p></div></div>
                </div>
                <div class="card-body">
                    <input type="hidden" name="contact_shown" value="1">
                    <div class="small fw-semibold text-muted text-uppercase mb-2">{{ __('Details I allow on the page') }}</div>
                    <div class="row g-2 mb-3">
                        @foreach (\App\Models\Company::CONTACT_FIELDS as $field => $label)
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cf-{{ $field }}" name="contact_fields[]" value="{{ $field }}" @checked(in_array($field, $pickedFields, true))>
                                    <label class="form-check-label" for="cf-{{ $field }}">{{ __($label) }} <span class="text-muted small" dir="ltr">{{ $company->{$field} ? '· '.preg_replace('#^https?://#', '', $company->{$field}) : '· '.__('not filled in') }}</span></label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="small fw-semibold text-muted text-uppercase mb-2">{{ __('Who may see them') }}</div>
                    @foreach (\App\Models\Company::CONTACT_VISIBILITY as $key => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="cv-{{ $key }}" name="contact_visibility" value="{{ $key }}" @checked(old('contact_visibility', $company->contact_visibility ?: 'public') === $key)>
                            <label class="form-check-label" for="cv-{{ $key }}">{{ __($label) }}</label>
                        </div>
                    @endforeach
                    <div class="form-text">{{ __('Restricted details are left out of the page itself, of search engines and of the PDF profile, not just hidden on screen. Details you publish elsewhere may still be found there.') }}</div>
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
                    <div class="small fw-semibold text-muted text-uppercase mb-2">{{ __('What you do here') }}</div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="s-supplier" name="is_supplier" value="1" @checked(old('is_supplier', $company->is_supplier))>
                        <label class="form-check-label" for="s-supplier">{{ __('We look for tenders to bid on') }}</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="s-buyer" name="is_buyer" value="1" @checked(old('is_buyer', $company->is_buyer))>
                        <label class="form-check-label" for="s-buyer">{{ __('We post our own tenders') }}</label>
                    </div>
                    <hr>
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

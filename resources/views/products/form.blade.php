@extends('layouts.app', ['title' => $product ? $product->name : __('Add a product')])

@php
    $value = fn (string $key, $default = null) => old($key, $product?->{$key} ?? $default);
    $status = $product?->status ?? 'draft';
    $locked = $status === 'hidden';
    $shown = in_array($status, ['published', 'pending'], true);
    $room = \App\Models\Product::MAX_IMAGES - ($product ? $product->images->count() : 0);
    $pill = ['draft' => 'bg-secondary-subtle text-secondary-emphasis', 'pending' => 'bg-warning-subtle text-warning-emphasis', 'published' => 'bg-success-subtle text-success-emphasis', 'rejected' => 'bg-danger-subtle text-danger-emphasis', 'hidden' => 'bg-secondary-subtle text-secondary-emphasis'];
    $price = fn ($amount) => $amount === null ? '' : rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.');
@endphp

@section('content')
<x-page-header :title="$product ? $product->name : __('Add a product')" icon="boxes-stacked" :crumbs="[[__('My products'), route('products.index')], [$product ? __('Edit') : __('New')]]">
    @if ($status === 'published')
        <a class="btn btn-outline-soft" href="{{ route('site.products.show', ['slug' => $product->slug]) }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('See on the site') }}</a>
    @endif
</x-page-header>

@if ($status === 'rejected')
    <div class="alert alert-danger d-flex align-items-start gap-3"><x-icon name="circle-exclamation" class="fs-4 mt-1" /><div><div class="fw-bold">{{ __('This product was sent back') }}</div><div class="small">{{ __($product->reject_reason) }} {{ __('Correct it and send it again.') }}</div></div></div>
@elseif ($status === 'pending')
    <div class="alert alert-warning d-flex align-items-start gap-3"><x-icon name="hourglass-half" class="fs-4 mt-1" /><div><div class="fw-bold">{{ __('Waiting for approval') }}</div><div class="small">{{ __('We check every product before it is shown, usually within one working day.') }}</div></div></div>
@elseif ($status === 'published')
    <div class="alert alert-success small py-2"><x-icon name="circle-check" class="me-1" /> {{ __('This product is on the site. If you change it, we check it again before the change is shown.') }}</div>
@elseif ($locked)
    <div class="alert alert-danger">{{ __('This product was taken off the site. Please contact support.') }}</div>
@endif

<form method="POST" action="{{ $product ? route('products.update', ['id' => $product->id]) : route('products.store') }}" enctype="multipart/form-data">
    @csrf
    @if ($product)
        @method('PUT')
    @endif
    <fieldset @disabled($locked) class="border-0 p-0 m-0">
    <div class="row g-3">
        <div class="col-12 col-xl-8 d-flex flex-column gap-3">
            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('The product') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="p-name">{{ __('Name of the product') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="p-name" name="name" value="{{ $value('name') }}" maxlength="150" required dir="auto" placeholder="{{ __('For example: Portland cement, 50 kg bag') }}">
                            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="p-industry">{{ __('Sector') }} <span class="text-danger">*</span></label>
                            <select class="form-select @error('industry_id') is-invalid @enderror" id="p-industry" name="industry_id" required data-plain>
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($industries as $id => $name)
                                    <option value="{{ $id }}" @selected((string) $value('industry_id') === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('industry_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="p-province">{{ __('Made or stocked in') }}</label>
                            <select class="form-select @error('province_id') is-invalid @enderror" id="p-province" name="province_id" data-plain>
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($provinces as $id => $name)
                                    <option value="{{ $id }}" @selected((string) $value('province_id', $company->province_id) === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('province_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="p-summary">{{ __('In one or two sentences') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('summary') is-invalid @enderror" id="p-summary" name="summary" rows="2" maxlength="300" required dir="auto">{{ $value('summary') }}</textarea>
                            @error('summary')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('What it is and who it is for. Up to 300 letters.') }}</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="p-description">{{ __('Details') }}</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="p-description" name="description" rows="6" dir="auto">{{ $value('description') }}</textarea>
                            @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('Sizes, grades, standards, packing, delivery. Do not write phone numbers here: buyers reach you through the contact buttons.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Pictures and video') }}</h5></div>
                <div class="card-body">
                    @if ($product && $product->images->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach ($product->images as $image)
                                <div class="position-relative">
                                    <img src="{{ route('site.products.image', ['id' => $image->id]) }}" alt="" width="132" height="99" class="rounded border" style="object-fit:cover">
                                    @unless ($locked)
                                        <button type="submit" form="image-delete-{{ $image->id }}" class="btn btn-sm btn-soft-danger position-absolute top-0 end-0 m-1" aria-label="{{ __('Remove this picture') }}"><x-icon name="trash" /></button>
                                    @endunless
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if ($room > 0)
                        <label class="form-label" for="p-images">{{ __('Add pictures') }}@unless ($product && $product->images->isNotEmpty()) <span class="text-danger">*</span>@endunless</label>
                        <input type="file" class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror" id="p-images" name="images[]" multiple accept="image/jpeg,image/png,image/webp">
                        @error('images')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('images.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('Up to :count pictures of the product itself, JPG, PNG or WebP, 4 MB each. The first one is the cover.', ['count' => \App\Models\Product::MAX_IMAGES]) }}</div>
                    @else
                        <div class="form-text">{{ __('This product has all :count pictures. Remove one to add another.', ['count' => \App\Models\Product::MAX_IMAGES]) }}</div>
                    @endif

                    <hr>
                    <label class="form-label" for="p-video">{{ __('Short video') }}</label>
                    @if ($product?->video_path)
                        <video class="d-block rounded border mb-2" controls preload="metadata" style="max-width:100%;max-height:260px"><source src="{{ route('site.products.video', ['id' => $product->id]) }}"></video>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="p-remove-video" name="remove_video" value="1">
                            <label class="form-check-label" for="p-remove-video">{{ __('Remove this video') }}</label>
                        </div>
                    @endif
                    <input type="file" class="form-control @error('video') is-invalid @enderror" id="p-video" name="video" accept="video/mp4,video/webm,video/quicktime">
                    @error('video')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('One video, MP4 or WebM, up to 30 MB. Under one minute is best: the product, the factory, the packing.') }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4 d-flex flex-column gap-3">
            <div class="card mb-0 h-auto">
                <div class="card-header d-flex align-items-center justify-content-between"><h5 class="card-title mb-0">{{ __('Wholesale terms') }}</h5>@if ($product)<span class="badge {{ $pill[$status] }}">{{ $product->statusLabel() }}</span>@endif</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="p-unit">{{ __('Sold by the') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('unit') is-invalid @enderror" id="p-unit" name="unit" value="{{ $value('unit') }}" maxlength="50" required dir="auto" placeholder="{{ __('bag, ton, carton') }}" list="p-units">
                            <datalist id="p-units">@foreach (['bag', 'ton', 'kg', 'carton', 'box', 'piece', 'dozen', 'litre', 'metre', 'square metre', 'roll', 'pallet', 'container'] as $unit)<option value="{{ $unit }}">@endforeach</datalist>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="p-min">{{ __('Smallest order') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('min_order') is-invalid @enderror" id="p-min" name="min_order" value="{{ $value('min_order') }}" maxlength="100" required dir="auto" placeholder="{{ __('200 bags') }}">
                        </div>
                        @error('unit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('min_order')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="col-12">
                            <label class="form-label" for="p-capacity">{{ __('How much you can supply') }}</label>
                            <input type="text" class="form-control @error('capacity') is-invalid @enderror" id="p-capacity" name="capacity" value="{{ $value('capacity') }}" maxlength="150" dir="auto" placeholder="{{ __('3,000 tons a month') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="p-price-min">{{ __('Price, or from') }}</label>
                            <input type="text" inputmode="decimal" class="form-control @error('price_min') is-invalid @enderror" id="p-price-min" name="price_min" value="{{ old('price_min', $price($product?->price_min)) }}" dir="ltr" autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="p-price-max">{{ __('To') }}</label>
                            <input type="text" inputmode="decimal" class="form-control @error('price_max') is-invalid @enderror" id="p-price-max" name="price_max" value="{{ old('price_max', $price($product?->price_max)) }}" dir="ltr" autocomplete="off">
                        </div>
                        @error('price_min')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('price_max')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="col-12">
                            <label class="form-label" for="p-currency">{{ __('Currency') }}</label>
                            <select class="form-select @error('price_currency') is-invalid @enderror" id="p-currency" name="price_currency" data-plain>
                                @foreach (config('tenders.currencies') as $code)
                                    <option value="{{ $code }}" @selected($value('price_currency', $company->currency) === $code)>{{ $code }}</option>
                                @endforeach
                            </select>
                            @error('price_currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('The price is for one unit. Leave it empty to show "Price on request".') }}</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="p-made" name="made_in_afghanistan" value="1" @checked(old('made_in_afghanistan', $product?->made_in_afghanistan ?? true))>
                                <label class="form-check-label" for="p-made">{{ __('Made in Afghanistan') }}</label>
                            </div>
                        </div>
                    </div>
                    @unless ($locked)
                        <button type="submit" name="action" value="send" class="btn btn-primary w-100 mt-3"><x-icon name="paper-plane" class="me-1" /> {{ $shown ? __('Save') : __('Send for approval') }}</button>
                        @unless ($shown)
                            <button type="submit" name="action" value="draft" class="btn btn-outline-soft w-100 mt-2">{{ __('Save as a draft') }}</button>
                        @endunless
                    @endunless
                </div>
            </div>
        </div>
    </div>
    </fieldset>
</form>

@if ($product)
    @foreach ($product->images as $image)
        <form id="image-delete-{{ $image->id }}" method="POST" action="{{ route('products.image.destroy', ['id' => $product->id, 'image' => $image->id]) }}" hidden data-confirm="{{ __('Remove this picture?') }}" data-confirm-ok="{{ __('Remove') }}">@csrf @method('DELETE')</form>
    @endforeach
    <div class="card h-auto mt-3" style="max-width:560px">
        <div class="card-body d-flex align-items-center justify-content-between gap-3">
            <div class="min-w-0"><div class="fw-semibold">{{ __('Delete this product') }}</div><div class="small text-muted">{{ __('It leaves the site, with its pictures and its video.') }}</div></div>
            <form method="POST" action="{{ route('products.destroy', ['id' => $product->id]) }}" data-confirm="{{ __('Delete this product?') }}" data-confirm-ok="{{ __('Delete') }}">@csrf @method('DELETE')<button type="submit" class="btn btn-soft-danger flex-shrink-0"><x-icon name="trash" class="me-1" /> {{ __('Delete') }}</button></form>
        </div>
    </div>
@endif
@endsection

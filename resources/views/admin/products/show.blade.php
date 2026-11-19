@extends('layouts.admin', ['title' => $product->name])

@php
    $pill = ['draft' => 'bg-secondary-subtle text-secondary-emphasis', 'pending' => 'bg-warning-subtle text-warning-emphasis', 'published' => 'bg-success-subtle text-success-emphasis', 'rejected' => 'bg-danger-subtle text-danger-emphasis', 'hidden' => 'bg-secondary-subtle text-secondary-emphasis'];
    $company = $product->company;
    $facts = array_filter([
        'Price' => $product->priceText(),
        'Smallest order' => $product->min_order,
        'Sold by the' => $product->unit,
        'Capacity' => $product->capacity,
        'Sector' => $product->industry?->name,
        'Made or stocked in' => $product->province?->name,
        'Origin' => $product->made_in_afghanistan ? 'Made in Afghanistan' : null,
        'Sent on' => fmt_datetime($product->submitted_at),
    ]);
@endphp

@section('content')
<x-page-header :title="$product->name" icon="boxes-stacked" :crumbs="[[__('Products'), route('admin.products.index')], [$product->statusLabel()]]">
    @if ($product->status === 'published' && \App\Support\Platform::bool('products_enabled'))
        <a class="btn btn-outline-soft" href="{{ route('site.products.show', ['slug' => $product->slug]) }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('See on the site') }}</a>
    @endif
</x-page-header>

<div class="row g-3">
    <div class="col-12 col-xl-8 d-flex flex-column gap-3">
        <div class="card mb-0 h-auto">
            <div class="card-header d-flex align-items-center justify-content-between"><h5 class="card-title mb-0">{{ __('Pictures and video') }}</h5><span class="badge {{ $pill[$product->status] }}">{{ $product->statusLabel() }}</span></div>
            <div class="card-body">
                @if ($product->images->isEmpty())
                    <p class="text-muted mb-0">{{ __('No pictures.') }}</p>
                @else
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($product->images as $image)
                            <a href="{{ route('site.products.image', ['id' => $image->id]) }}" target="_blank" rel="noopener"><img src="{{ route('site.products.image', ['id' => $image->id]) }}" alt="" width="200" height="150" class="rounded border" style="object-fit:cover"></a>
                        @endforeach
                    </div>
                @endif
                @if ($product->video_path)
                    <video class="d-block rounded border mt-3" controls preload="metadata" style="max-width:100%;max-height:360px"><source src="{{ route('site.products.video', ['id' => $product->id]) }}"></video>
                @endif
            </div>
        </div>
        <div class="card mb-0 h-auto">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('What it says') }}</h5></div>
            <div class="card-body">
                <p class="fw-semibold" dir="auto">{{ $product->summary }}</p>
                @if ($product->description)<p class="mb-0" style="white-space:pre-line" dir="auto">{{ $product->description }}</p>@endif
                <hr>
                <div class="row g-3">
                    @foreach ($facts as $label => $value)
                        <div class="col-6 col-md-4"><div class="small text-muted">{{ __($label) }}</div><div class="fw-semibold" dir="auto">{{ $value }}</div></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4 d-flex flex-column gap-3">
        <div class="card mb-0 h-auto">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Decision') }}</h5></div>
            <div class="card-body">
                @if ($product->status === 'pending')
                    <form method="POST" action="{{ route('admin.products.approve', ['id' => $product->id]) }}">@csrf<button type="submit" class="btn btn-success w-100"><x-icon name="check" class="me-1" /> {{ __('Publish') }}</button></form>
                    <form method="POST" action="{{ route('admin.products.reject', ['id' => $product->id]) }}" class="mt-3">
                        @csrf
                        <select class="form-select mb-2" name="reason" required aria-label="{{ __('Reason') }}" data-plain>
                            <option value="">{{ __('Reason to send back…') }}</option>
                            @foreach ($reasons as $key => $text)
                                <option value="{{ $key }}">{{ __($text) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-soft-danger w-100">{{ __('Send back') }}</button>
                    </form>
                @elseif (in_array($product->status, ['published', 'hidden'], true))
                    <form method="POST" action="{{ route('admin.products.hide', ['id' => $product->id]) }}">@csrf<button type="submit" class="btn {{ $product->status === 'hidden' ? 'btn-success' : 'btn-soft-danger' }} w-100">{{ $product->status === 'hidden' ? __('Put it back on the site') : __('Take it off the site') }}</button></form>
                @elseif ($product->status === 'rejected')
                    <p class="small text-muted mb-0">{{ __('Sent back:') }} {{ __($product->reject_reason) }}</p>
                @else
                    <p class="small text-muted mb-0">{{ __('The company has not sent this product yet.') }}</p>
                @endif
                <div class="d-flex justify-content-between small mt-3 pt-3 border-top"><span class="text-muted">{{ __('Views') }}</span><b>{{ number_format($product->views) }}</b></div>
                <div class="d-flex justify-content-between small mt-1"><span class="text-muted">{{ __('Contacts') }}</span><b>{{ number_format($product->contacts) }}</b></div>
                <div class="d-flex justify-content-between small mt-1"><span class="text-muted">{{ __('Sponsored until') }}</span><b>{{ $product->isSponsored() ? fmt_date($product->sponsored_until) : '—' }}</b></div>
            </div>
        </div>
        <div class="card mb-0 h-auto">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Company') }}</h5></div>
            <div class="card-body">
                <div class="fw-bold">{{ $company?->name }} @if ($company?->isVerified())<span class="badge bg-success-subtle text-success-emphasis">{{ __('Verified') }}</span>@else<span class="badge bg-warning-subtle text-warning-emphasis">{{ __('Not verified') }}</span>@endif</div>
                <div class="small text-muted">{{ $company?->kindLabel() }}@if ($company?->province) · {{ $company->province->name }}@endif</div>
                <div class="small mt-2" dir="ltr">{{ $company?->phone }}</div>
                <div class="small" dir="ltr">{{ $company?->email }}</div>
                @if ($company)<a class="btn btn-sm btn-outline-soft mt-3" href="{{ route('admin.companies.edit', ['id' => $company->id]) }}">{{ __('Open the company') }}</a>@endif
            </div>
        </div>
        @if ($product->promotions->isNotEmpty())
            <div class="card mb-0 h-auto">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Sponsoring payments') }}</h5></div>
                <div class="card-body">
                    @foreach ($product->promotions->sortByDesc('id') as $payment)
                        <div class="d-flex justify-content-between gap-2 py-2 border-bottom small">
                            <div><div class="fw-semibold">{{ number_format((float) $payment->amount) }} {{ $payment->currency }} · {{ __(\App\Models\ProductPromotion::PERIODS[$payment->period][0]) }}</div><div class="text-muted">@if ($payment->status === 'approved'){{ fmt_date($payment->starts_on) }} – {{ fmt_date($payment->ends_on) }}@elseif ($payment->status === 'rejected'){{ $payment->note }}@else{{ fmt_date($payment->created_at) }}@endif</div></div>
                            <a href="{{ route('admin.products.payments.receipt', ['id' => $payment->id]) }}" target="_blank" rel="noopener">{{ __($payment->status === 'pending' ? 'Waiting' : ($payment->status === 'approved' ? 'Confirmed' : 'Refused')) }}</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        <div class="card mb-0 h-auto">
            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div class="min-w-0"><div class="fw-semibold">{{ __('Delete this product') }}</div><div class="small text-muted">{{ __('With its pictures and its video.') }}</div></div>
                <form method="POST" action="{{ route('admin.products.destroy', ['id' => $product->id]) }}" data-confirm="{{ __('Delete this product?') }}" data-confirm-ok="{{ __('Delete') }}">@csrf @method('DELETE')<button type="submit" class="btn btn-soft-danger flex-shrink-0"><x-icon name="trash" /></button></form>
            </div>
        </div>
    </div>
</div>
@endsection

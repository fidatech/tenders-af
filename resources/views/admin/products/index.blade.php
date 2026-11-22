@extends('layouts.admin', ['title' => __('Products')])

@php
    $pill = ['draft' => 'bg-secondary-subtle text-secondary-emphasis', 'pending' => 'bg-warning-subtle text-warning-emphasis', 'published' => 'bg-success-subtle text-success-emphasis', 'rejected' => 'bg-danger-subtle text-danger-emphasis', 'hidden' => 'bg-secondary-subtle text-secondary-emphasis'];
    $on = module_on('products');
@endphp

@section('content')
<x-page-header :title="__('Products')" icon="boxes-stacked" :subtitle="__(':published published, :pending waiting, :sponsored sponsored now', ['published' => number_format($counts['published'] ?? 0), 'pending' => number_format($counts['pending'] ?? 0), 'sponsored' => number_format($sponsored)])">
    @if ($on)
        <a class="btn btn-outline-soft" href="{{ route('site.products') }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('Products page') }}</a>
    @endif
</x-page-header>

@unless ($on)
    <div class="alert alert-warning d-flex align-items-center gap-3">
        <x-icon name="circle-exclamation" class="fs-4" />
        <div class="flex-grow-1 min-w-0"><div class="fw-bold">{{ __('The products module is switched off') }}</div><div class="small">{{ __('There is no Products page on the site and companies cannot list products. Switch it on in the settings when you are ready.') }}</div></div>
        <a class="btn btn-warning flex-shrink-0" href="{{ route('admin.settings.edit') }}">{{ __('Settings') }}</a>
    </div>
@endunless

@if ($payments->isNotEmpty())
    <div class="card h-auto mb-3">
        <div class="card-header"><h5 class="card-title mb-0">{{ __('Payments for sponsoring, waiting') }}</h5></div>
        <div class="card-body">
            <table class="table w-100 align-middle mb-0">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Company') }}</th><th>{{ __('Paid') }}</th><th>{{ __('To') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td><a class="fw-semibold text-decoration-none" href="{{ route('admin.products.show', ['id' => $payment->product_id]) }}">{{ $payment->product?->name }}</a><span class="d-block small text-muted">{{ __(\App\Models\ProductPromotion::PERIODS[$payment->period][0]) }} · {{ fmt_date($payment->created_at) }}</span></td>
                            <td>{{ $payment->company?->name }}</td>
                            <td class="text-nowrap fw-semibold">{{ number_format((float) $payment->amount) }} {{ $payment->currency }}@if ($payment->reference)<span class="d-block small text-muted fw-normal" dir="ltr">{{ $payment->reference }}</span>@endif</td>
                            <td>{{ $payment->account?->name ?? '—' }}</td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap gap-2 justify-content-end">
                                    <a class="btn btn-sm btn-light-secondary" href="{{ route('admin.products.payments.receipt', ['id' => $payment->id]) }}" target="_blank" rel="noopener"><x-icon name="receipt" class="me-1" /> {{ __('Proof') }}</a>
                                    <form method="POST" action="{{ route('admin.products.payments.reject', ['id' => $payment->id]) }}" class="d-flex gap-1">@csrf<input type="text" class="form-control form-control-sm" name="note" maxlength="255" required placeholder="{{ __('Why not…') }}" style="width:160px"><button type="submit" class="btn btn-sm btn-soft-danger">{{ __('Refuse') }}</button></form>
                                    <form method="POST" action="{{ route('admin.products.payments.approve', ['id' => $payment->id]) }}" data-confirm="{{ __('The money arrived? The product is sponsored from today.') }}" data-confirm-ok="{{ __('Confirm') }}">@csrf<button type="submit" class="btn btn-sm btn-success"><x-icon name="check" class="me-1" /> {{ __('Confirm') }}</button></form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search by product or company…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col col-md-auto">
                <select class="form-select" name="status" aria-label="{{ __('Status') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Any status') }}</option>
                    @foreach (\App\Models\Product::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ __($label) }} ({{ number_format($counts[$key] ?? 0) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="boxes-stacked" /></div>
                <h3>{{ __('No products here') }}</h3>
                <p>{{ __('Products that companies send for approval appear in this list.') }}</p>
            </div>
        @else
            <table class="table table-hover w-100 align-middle">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Company') }}</th><th>{{ __('Price') }}</th><th>{{ __('Status') }}</th><th>{{ __('Views') }}</th><th>{{ __('Contacts') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $product)
                        <tr>
                            <td>
                                <a class="d-flex align-items-center gap-3 text-decoration-none text-reset" href="{{ route('admin.products.show', ['id' => $product->id]) }}">
                                    @if ($cover = $product->images->first())
                                        <img src="{{ route('site.products.image', ['id' => $cover->id]) }}" alt="" width="56" height="42" class="rounded flex-shrink-0" style="object-fit:cover">
                                    @else
                                        <span class="sc-icon icon-soft secondary flex-shrink-0" style="width:56px;height:42px"><x-icon name="image" /></span>
                                    @endif
                                    <span class="min-w-0"><span class="d-block fw-semibold text-truncate">{{ $product->name }}</span><span class="d-block small text-muted text-truncate">{{ $product->industry?->name }} · {{ __('from :order', ['order' => $product->min_order]) }}</span></span>
                                </a>
                            </td>
                            <td>{{ $product->company?->name }}@if ($product->company?->isVerified()) <span class="badge bg-success-subtle text-success-emphasis">{{ __('Verified') }}</span>@endif</td>
                            <td class="text-nowrap">{{ $product->priceText() }}</td>
                            <td><span class="badge {{ $pill[$product->status] }}">{{ $product->statusLabel() }}</span>@if ($product->isSponsored())<span class="d-block small text-success mt-1">{{ __('Sponsored until :date', ['date' => fmt_date($product->sponsored_until)]) }}</span>@endif</td>
                            <td>{{ number_format($product->views) }}</td>
                            <td>{{ number_format($product->contacts) }}</td>
                            <td class="text-end text-nowrap">
                                <div class="act-group">
                                    @if ($product->status === 'pending')
                                        <form method="POST" action="{{ route('admin.products.approve', ['id' => $product->id]) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-soft-success" title="{{ __('Publish') }}"><x-icon name="check" /></button></form>
                                    @endif
                                    <x-act kind="view" :href="route('admin.products.show', ['id' => $product->id])" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection

@extends('layouts.app', ['title' => __('My products')])

@php
    $pill = ['draft' => 'bg-secondary-subtle text-secondary-emphasis', 'pending' => 'bg-warning-subtle text-warning-emphasis', 'published' => 'bg-success-subtle text-success-emphasis', 'rejected' => 'bg-danger-subtle text-danger-emphasis', 'hidden' => 'bg-secondary-subtle text-secondary-emphasis'];
    $full = $limit > 0 && $rows->total() >= $limit;
@endphp

@section('content')
<x-page-header :title="__('My products')" icon="boxes-stacked" :subtitle="__('What you make or supply, for wholesale. NGOs, UN agencies, government offices and traders see it on the Products page and contact you directly.')">
    <a class="btn btn-outline-soft" href="{{ route('site.products') }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('Products page') }}</a>
    @unless ($full)
        <a class="btn btn-primary" href="{{ route('products.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add a product') }}</a>
    @endunless
</x-page-header>

@if ($limit > 0)
    <div class="alert alert-success small py-2"><x-icon name="gift" class="me-1" /> {{ __('Listing is free: :used of :limit products used.', ['used' => min($rows->total(), $limit), 'limit' => $limit]) }}@if ($canSponsor) {{ __('A sponsored product is shown first, with a "Sponsored" label.') }}@endif</div>
@endif

<div class="card">
    <div class="card-body">
        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="boxes-stacked" /></div>
                <h3>{{ __('No products yet') }}</h3>
                <p>{{ __('Add your first product with a few pictures or a short video.') }}</p>
                <a class="btn btn-primary" href="{{ route('products.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add a product') }}</a>
            </div>
        @else
            <table class="table table-hover w-100 align-middle">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Price') }}</th><th>{{ __('Status') }}</th><th>{{ __('Views') }}</th><th>{{ __('Contacts') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $product)
                        <tr>
                            <td>
                                <a class="d-flex align-items-center gap-3 text-decoration-none text-reset" href="{{ route('products.edit', ['id' => $product->id]) }}">
                                    @if ($cover = $product->images->first())
                                        <img src="{{ route('site.products.image', ['id' => $cover->id]) }}" alt="" width="56" height="42" class="rounded flex-shrink-0" style="object-fit:cover">
                                    @else
                                        <span class="sc-icon icon-soft secondary flex-shrink-0" style="width:56px;height:42px"><x-icon name="image" /></span>
                                    @endif
                                    <span class="min-w-0"><span class="d-block fw-semibold text-truncate">{{ $product->name }}</span><span class="d-block small text-muted text-truncate">{{ $product->industry?->name }} · {{ __('from :order', ['order' => $product->min_order]) }}</span></span>
                                </a>
                                @if ($product->status === 'rejected')<span class="d-block small text-danger mt-1">{{ __($product->reject_reason) }}</span>@endif
                            </td>
                            <td class="text-nowrap">{{ $product->priceText() }}</td>
                            <td>
                                <span class="badge {{ $pill[$product->status] }}">{{ $product->statusLabel() }}</span>
                                @if ($product->isSponsored())<span class="d-block small text-success mt-1">{{ __('Sponsored until :date', ['date' => fmt_date($product->sponsored_until)]) }}</span>@elseif ($product->waiting)<span class="d-block small text-muted mt-1">{{ __('Payment is being checked') }}</span>@endif
                            </td>
                            <td>{{ number_format($product->views) }}</td>
                            <td>{{ number_format($product->contacts) }}</td>
                            <td class="text-end text-nowrap">
                                <div class="act-group">
                                    @if ($canSponsor && $product->status === 'published' && ! $product->waiting)
                                        <a class="btn btn-sm btn-soft-success" href="{{ route('products.sponsor', ['id' => $product->id]) }}"><x-icon name="bullhorn" class="me-1" /> {{ $product->isSponsored() ? __('Extend') : __('Sponsor') }}</a>
                                    @endif
                                    @if ($product->status === 'published')
                                        <x-act kind="view" :href="route('site.products.show', ['slug' => $product->slug])" blank />
                                    @endif
                                    <x-act kind="edit" :href="route('products.edit', ['id' => $product->id])" />
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

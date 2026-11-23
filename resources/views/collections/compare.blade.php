@extends('layouts.app', ['title' => __('Compare')])

@php
    $rows = [
        __('Province or country') => fn ($c) => $c->province?->name ?: $c->country,
        __('Kind of business') => fn ($c) => implode(', ', $c->businessTypeLabels()) ?: '—',
        __('Sectors') => fn ($c) => $c->industries->pluck('name')->join(', ') ?: '—',
        __('Services') => fn ($c) => module_on('mini_websites') ? (collect($c->serviceList())->pluck('name')->join(', ') ?: '—') : null,
        __('Founded') => fn ($c) => $c->founded_year ?: '—',
        __('Size') => fn ($c) => $c->size ? (\App\Models\Company::SIZES[$c->size] ?? '—') : '—',
        __('Business licence') => fn ($c) => $c->isVerified() ? __('Checked by :site', ['site' => \App\Support\Branding::name()]) : __('Not checked'),
        __('Published projects') => fn ($c) => module_on('portfolios') ? (string) ($projects[$c->id] ?? collect())->count() : null,
        __('Projects checked') => fn ($c) => module_on('portfolios') ? (string) ($projects[$c->id] ?? collect())->whereIn('review_status', ['evidence_reviewed', 'client_confirmed'])->count() : null,
        __('Contact') => fn ($c) => ($contact = $c->contactFor($viewer))['state'] === 'open' ? (collect($contact['fields'])->join(' · ') ?: '—') : ($contact['state'] === 'locked' ? __('For paid subscribers') : __('Enquiries only')),
    ];
@endphp

@section('content')
<x-page-header :title="__('Compare')" icon="table-columns" :crumbs="[[__('Saved suppliers'), route('collections.index')], [__($collection->name), route('collections.show', ['id' => $collection->id])], [__('Compare')]]" :subtitle="__('Only facts the companies entered and what Tenders.af checked. No scores or ratings are made up.')" />

<div class="card">
    <div class="table-responsive">
        <table class="table align-top mb-0">
            <thead>
                <tr>
                    <th scope="col" style="min-width:140px"></th>
                    @foreach ($items as $item)
                        <th scope="col" style="min-width:200px"><a href="{{ route('site.companies.show', ['slug' => $item->supplier->slug]) }}" target="_blank" rel="noopener">{{ $item->supplier->name }}</a><span class="d-block small text-muted fw-normal">{{ __(\App\Models\SupplierCollectionItem::STATUSES[$item->status] ?? '') }}</span></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $label => $value)
                    @continue($value($items->first()->supplier) === null)
                    <tr><th scope="row" class="small text-muted fw-semibold">{{ $label }}</th>@foreach ($items as $item)<td dir="auto">{{ $value($item->supplier) }}</td>@endforeach</tr>
                @endforeach
                <tr><th scope="row" class="small text-muted fw-semibold">{{ __('Your note') }}</th>@foreach ($items as $item)<td class="small" style="white-space:pre-line" dir="auto">{{ $item->note ?: '—' }}</td>@endforeach</tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

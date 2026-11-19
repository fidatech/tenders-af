@extends('layouts.app', ['title' => __('app.dashboard.title')])

@php
    $cards = [
        [__('Open tenders'), 'gavel', 'primary', $counts['open'], route('tenders.index')],
        [__('Matching my alerts'), 'bell', 'success', $counts['matching'], route('tenders.matching')],
        [__('Closing this week'), 'hourglass-half', 'danger', $counts['closing'], $hasAlerts ? route('tenders.matching') : route('tenders.index')],
        [__('Saved tenders'), 'bookmark', 'warning', $counts['saved'], route('tenders.saved')],
    ];
@endphp

@section('content')
<x-page-header :title="__('app.dashboard.empty_title', ['name' => auth()->user()->name])" icon="gauge-high" :subtitle="$company->name.' · '.fmt_date(today_iso())">
    <a class="btn btn-primary" href="{{ route('tenders.index') }}"><x-icon name="magnifying-glass" class="me-1" /> {{ __('Find tenders') }}</a>
</x-page-header>

@include('partials.plan-status', ['company' => $company, 'link' => true])

@unless ($hasAlerts)
    <div class="alert alert-warning d-flex align-items-center gap-3">
        <x-icon name="bell" class="fs-4" />
        <div class="flex-grow-1 min-w-0">
            <div class="fw-bold">{{ __('Choose the sectors you work in') }}</div>
            <div class="small">{{ __('We match new tenders to your sectors and send them to you. Until you choose, nothing can be matched.') }}</div>
        </div>
        @if (auth()->user()->isOwner())
            <a class="btn btn-warning flex-shrink-0" href="{{ route('settings.edit') }}#sectors">{{ __('Choose sectors') }}</a>
        @endif
    </div>
@endunless

<div class="row g-3 mb-3">
    @foreach ($cards as [$label, $icon, $tone, $count, $url])
        <div class="col-6 col-xl-3">
            <a href="{{ $url }}" class="card card-hover h-100 mb-0 text-decoration-none text-reset">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:38px;height:38px"><x-icon :name="$icon" /></span>
                        <span class="small fw-semibold text-muted">{{ $label }}</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1">{{ number_format($count) }}</div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ $hasAlerts ? __('Tenders for your company') : __('Newest tenders') }}</h5>
                <a class="small text-decoration-none" href="{{ $hasAlerts ? route('tenders.matching') : route('tenders.index') }}">{{ __('See all') }}</a>
            </div>
            <div class="card-body">
                @if ($tenders->isEmpty())
                    <p class="text-muted mb-0">{{ $hasAlerts ? __('No open tender matches your sectors today. New ones are sent to you as they are published.') : __('No open tenders yet.') }}</p>
                @else
                    <ul class="list-clean">
                        @foreach ($tenders as $tender)
                            @include('tenders._row', ['tender' => $tender])
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ __('Your company profile') }}</h5>
                <span class="badge {{ $company->isVerified() ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">{{ $company->verifyLabel() }}</span>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between small fw-semibold mb-1"><span>{{ __('Profile complete') }}</span><span>{{ $profile['percent'] }}%</span></div>
                <div class="progress mb-3" style="height:8px" role="progressbar" aria-valuenow="{{ $profile['percent'] }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: {{ $profile['percent'] }}%"></div></div>
                @if ($profile['missing'])
                    <div class="small text-muted mb-2">{{ __('Still missing:') }}</div>
                    <ul class="small ps-3 mb-3">
                        @foreach ($profile['missing'] as $item)
                            <li>{{ __($item) }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="small text-muted">{{ __('Your profile is complete. Buyers see it in the company directory.') }}</p>
                @endif
                <div class="d-grid gap-2">
                    @if (auth()->user()->isOwner())
                        <a class="btn btn-soft-primary" href="{{ route('settings.edit') }}"><x-icon name="building" class="me-1" /> {{ __('Edit the profile') }}</a>
                    @endif
                    <a class="btn btn-light-secondary" href="{{ route('site.companies.show', ['slug' => $company->slug]) }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('See the public page') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

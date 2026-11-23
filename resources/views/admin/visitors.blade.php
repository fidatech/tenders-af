@extends('layouts.admin', ['title' => __('Visitors')])

@php
    $max = max(1, (int) $daily->max('v'));
    $phones = (int) ($devices['mobile'] ?? 0);
    $allDevices = max(1, (int) $devices->sum());
@endphp

@section('content')
<x-page-header :title="__('Visitors')" icon="chart-line" :subtitle="__('People who opened the public website, counted once a day each, without cookies or IP addresses. Robots, administrators and browsers that ask not to be tracked are left out.')">
    <div class="btn-group" role="group" aria-label="{{ __('Period') }}">
        @foreach ([7, 30, 90, 365] as $option)
            <a class="btn btn-sm {{ $days === $option ? 'btn-primary' : 'btn-light-secondary' }}" href="{{ route('admin.visitors.index', ['days' => $option]) }}">{{ $option === 365 ? __('Year') : __(':n days', ['n' => $option]) }}</a>
        @endforeach
    </div>
</x-page-header>

@unless ($located)
    <div class="alert alert-warning small d-flex gap-2"><x-icon name="triangle-exclamation" class="mt-1" /><span>{{ __('Countries and cities are not found yet: the location library or its database file is missing on the server. Visits are still counted. See “Visitor locations” in the README.') }}</span></div>
@endunless

<div class="row g-3 mb-3">
    @foreach ([[__('Visitors'), $visitors], [__('Pages opened'), $views], [__('Pages per visitor'), $visitors ? number_format($views / $visitors, 1) : '0'], [__('On phones'), round(100 * $phones / $allDevices).'%']] as [$label, $value])
        <div class="col-6 col-lg-3"><div class="card mb-0 h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="fs-3 fw-bold">{{ is_int($value) ? number_format($value) : $value }}</div></div></div></div>
    @endforeach
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">{{ __('Visitors each day') }}</h6></div>
    <div class="card-body">
        @if ($daily->isEmpty())
            <p class="text-muted mb-0">{{ __('No visits counted in this period yet.') }}</p>
        @else
            <div class="d-flex align-items-end gap-1" style="height:160px" role="img" aria-label="{{ __('Visitors each day') }}">
                @foreach ($daily as $day)
                    <div class="flex-fill bg-primary rounded-top" style="height: {{ max(2, round(100 * $day->v / $max)) }}%; min-width:2px" title="{{ fmt_date($day->visited_on) }}: {{ $day->v }} {{ __('visitors') }}, {{ $day->p }} {{ __('pages') }}"></div>
                @endforeach
            </div>
            <div class="d-flex justify-content-between small text-muted mt-1"><span>{{ fmt_date($daily->first()->visited_on) }}</span><span>{{ fmt_date($daily->last()->visited_on) }}</span></div>
        @endif
    </div>
</div>

<div class="row g-3">
    @foreach ([[__('Countries'), $countries, true], [__('Cities'), $cities, false]] as [$title, $rows, $isCountry])
        <div class="col-12 col-xl-6">
            <div class="card mb-0 h-100">
                <div class="card-header"><h6 class="mb-0">{{ $title }}</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ $isCountry ? __('Country') : __('City') }}</th>@unless ($isCountry)<th>{{ __('Country') }}</th>@endunless<th class="text-end">{{ __('Visitors') }}</th>@if ($isCountry)<th class="text-end">{{ __('Pages') }}</th>@endif</tr></thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td><span aria-hidden="true">{{ $isCountry ? $row['flag'] : '' }}</span> {{ $row['name'] }}</td>
                                    @unless ($isCountry)<td class="small"><span aria-hidden="true">{{ $row['flag'] }}</span> {{ $row['country'] }}</td>@endunless
                                    <td class="text-end fw-semibold">{{ number_format($row['v']) }}</td>
                                    @if ($isCountry)<td class="text-end">{{ number_format($row['p']) }}</td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center py-3">{{ __('Nothing yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

    <div class="col-12 col-xl-7">
        <div class="card mb-0 h-100">
            <div class="card-header"><h6 class="mb-0">{{ __('Pages opened most') }}</h6></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Page') }}</th><th class="text-end">{{ __('Opened') }}</th><th class="text-end">{{ __('Visitors') }}</th></tr></thead>
                    <tbody>
                        @forelse ($pages as $page)
                            <tr><td class="text-truncate" style="max-width:360px"><a href="{{ url($page->page) }}" target="_blank" rel="noopener">{{ $page->page }}</a></td><td class="text-end">{{ number_format($page->p) }}</td><td class="text-end">{{ number_format($page->v) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">{{ __('Nothing yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card mb-0 h-100">
            <div class="card-header"><h6 class="mb-0">{{ __('Came from') }}</h6><div class="form-text mt-0">{{ __('Other websites that linked visitors here.') }}</div></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        @forelse ($referrers as $row)
                            <tr><td>{{ $row->referrer }}</td><td class="text-end fw-semibold">{{ number_format($row->v) }}</td></tr>
                        @empty
                            <tr><td class="text-muted text-center py-3">{{ __('Nothing yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.admin', ['title' => __('Growth')])

@php
    $maxPeople = max(1, max(array_column($daily, 'human')));
    $maxRobots = max(1, max(array_map(fn ($d) => $d['bot'] + $d['pending'], $daily)));
    $firstDay = array_key_first($daily);
    $lastDay = array_key_last($daily);
    $phones = (int) ($devices['mobile'] ?? 0);
    $allDevices = max(1, (int) $devices->sum());
    $palette = ['#0B6B3A', '#18A05A', '#5FE0A0', '#F59E0B', '#94A3B8', '#C1121F'];
    $maxMonth = max(1, ...array_values(array_map(fn ($m) => max($m['companies'], $m['tenders'], $m['products']), $platform['months'])));
@endphp

@section('content')
<x-page-header :title="__('Growth')" icon="chart-line" :subtitle="__('Reach and growth of the platform. Real visitors, known robots and unconfirmed traffic are counted apart; the public statistics page shows real visitors only.')">
    <div class="btn-group" role="group" aria-label="{{ __('Period') }}">
        @foreach (\App\Http\Controllers\Admin\GrowthController::PERIODS as $option)
            <a class="btn btn-sm {{ $days === $option ? 'btn-primary' : 'btn-light-secondary' }}" href="{{ route('admin.growth.index', ['days' => $option]) }}" @if ($days === $option) aria-current="true" @endif>{{ $option === 365 ? __('Year') : __(':n days', ['n' => $option]) }}</a>
        @endforeach
    </div>
</x-page-header>

@unless ($located)
    <div class="alert alert-warning small d-flex gap-2"><x-icon name="triangle-exclamation" class="mt-1" /><span>{{ __('Countries and cities cannot be found yet: the location database file is missing on the server (php artisan geoip:update). Visits are still counted.') }}</span></div>
@endunless

{{-- the four headline numbers --}}
<div class="row g-3 mb-3">
    @foreach ([
        [__('Real visitors'), $totals['people'], __('People, once a day each'), 'success'],
        [__('Pages opened by people'), $totals['humanViews'], $totals['people'] ? __(':n pages per visitor', ['n' => number_format($totals['humanViews'] / $totals['people'], 1)]) : '—', 'primary'],
        [__('Robot requests'), $totals['botHits'], trans_choice('{0} no known robot|{1} from 1 known robot|[2,*] from :count known robots', $totals['botNames']), 'secondary'],
        [__('Unconfirmed requests'), $totals['pendingHits'], __('Never confirmed a real browser'), 'warning'],
    ] as [$label, $value, $note, $tone])
        <div class="col-6 col-xl-3">
            <div class="card mb-0 h-100 border-start border-4 border-{{ $tone }}">
                <div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-3 fw-bold">{{ number_format($value) }}</div>
                    <div class="small text-muted">{{ $note }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
<p class="small text-muted mb-4">{{ __(':p% of all requests to the public pages came from robots or were never confirmed.', ['p' => $totals['botShare']]) }} @if ($since){{ __('Counting started on :date.', ['date' => fmt_date($since)]) }}@endif</p>

{{-- day by day --}}
<div class="row g-3 mb-4">
    @foreach ([[__('Real visitors per day'), 'people'], [__('Robot and unconfirmed requests per day'), 'robots']] as [$title, $chart])
        <div class="col-12 col-xl-6">
            <div class="card mb-0 h-100">
                <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
                    <h6 class="mb-0">{{ $title }}</h6>
                    @if ($chart === 'robots')
                        <div class="small d-flex gap-3"><span class="d-inline-flex align-items-center gap-1"><span class="rounded-1" style="display:inline-block;width:10px;height:10px;background:#94A3B8"></span>{{ __('Known robots') }}</span><span class="d-inline-flex align-items-center gap-1"><span class="rounded-1" style="display:inline-block;width:10px;height:10px;background:#F59E0B"></span>{{ __('Unconfirmed') }}</span></div>
                    @endif
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-end gap-1" style="height:170px" role="img" aria-label="{{ $title }}">
                        @foreach ($daily as $day => $d)
                            @if ($chart === 'people')
                                <div class="flex-fill rounded-top" style="background:#18A05A;height:{{ $d['human'] ? max(2, round(100 * $d['human'] / $maxPeople)) : 0 }}%;min-width:2px" title="{{ fmt_date($day) }}: {{ $d['human'] }} {{ __('visitors') }}"></div>
                            @else
                                <div class="flex-fill d-flex flex-column justify-content-end" style="height:100%;min-width:2px" title="{{ fmt_date($day) }}: {{ $d['bot'] }} {{ __('robot requests') }}, {{ $d['pending'] }} {{ __('unconfirmed') }}">
                                    <div class="rounded-top" style="background:#F59E0B;height:{{ round(100 * $d['pending'] / $maxRobots, 2) }}%"></div>
                                    <div style="background:#94A3B8;height:{{ round(100 * $d['bot'] / $maxRobots, 2) }}%"></div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-between small text-muted mt-1"><span>{{ fmt_date($firstDay) }}</span><span>{{ __('Highest: :n', ['n' => number_format($chart === 'people' ? $maxPeople : $maxRobots)]) }}</span><span>{{ fmt_date($lastDay) }}</span></div>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- ======== real visitors ======== --}}
<h5 class="mb-3 d-flex align-items-center gap-2"><span class="rounded-2" style="display:inline-block;width:14px;height:14px;background:#18A05A"></span>{{ __('Real visitors') }}</h5>
<div class="row g-3 mb-4">
    @foreach ([[__('Countries'), $countries, 'country'], [__('Cities'), $cities, 'city']] as [$title, $rows, $kind])
        <div class="col-12 col-xl-6">
            <div class="card mb-0 h-100">
                <div class="card-header"><h6 class="mb-0">{{ $title }}</h6></div>
                <div class="table-responsive" style="max-height:420px">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="sticky-top bg-white"><tr><th>{{ $kind === 'country' ? __('Country') : __('City') }}</th><th class="text-end">{{ __('Visitors') }}</th>@if ($kind === 'country')<th class="text-end">{{ __('Pages') }}</th>@endif</tr></thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td><span aria-hidden="true">{{ $row['flag'] }}</span> {{ $row['name'] }}@if ($kind === 'city')<span class="d-block small text-muted">{{ $row['country'] }}</span>@endif</td>
                                    <td class="text-end fw-semibold">{{ number_format($row['v']) }}</td>
                                    @if ($kind === 'country')<td class="text-end">{{ number_format($row['p']) }}</td>@endif
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

    <div class="col-12 col-md-6 col-xl-4">
        <div class="card mb-0 h-100">
            <div class="card-header"><h6 class="mb-0">{{ __('How they found the site') }}</h6></div>
            <div class="card-body">
                @php($sourceTotal = max(1, array_sum($sources)))
                @forelse ($sources as $label => $n)
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span>{{ __($label) }}</span><b>{{ number_format($n) }} · {{ round(100 * $n / $sourceTotal) }}%</b></div>
                        <div class="progress" style="height:8px"><div class="progress-bar" style="width:{{ round(100 * $n / $sourceTotal) }}%;background:{{ $palette[$loop->index % 6] }}"></div></div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">{{ $days > 90 ? __('Shown for periods up to 90 days.') : __('Nothing yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card mb-0 h-100">
            <div class="card-header"><h6 class="mb-0">{{ __('Devices') }}</h6></div>
            <div class="card-body">
                @foreach ([__('Phones') => $phones, __('Computers and tablets') => $allDevices - $phones] as $label => $n)
                    @continue($devices->isEmpty())
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span>{{ $label }}</span><b>{{ round(100 * $n / $allDevices) }}%</b></div>
                        <div class="progress" style="height:8px"><div class="progress-bar" style="width:{{ round(100 * $n / $allDevices) }}%;background:{{ $palette[$loop->index] }}"></div></div>
                    </div>
                @endforeach
                @if ($devices->isEmpty())<p class="text-muted small mb-0">{{ __('Nothing yet.') }}</p>@endif
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card mb-0 h-100">
            <div class="card-header"><h6 class="mb-0">{{ __('Websites that sent visitors') }}</h6></div>
            <div class="table-responsive" style="max-height:300px">
                <table class="table table-sm align-middle mb-0"><tbody>
                    @forelse ($referrers as $row)
                        <tr><td class="text-truncate" style="max-width:220px">{{ $row->referrer }}</td><td class="text-end fw-semibold">{{ number_format($row->v) }}</td></tr>
                    @empty
                        <tr><td class="text-muted text-center py-3">{{ __('Nothing yet.') }}</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card mb-0">
            <div class="card-header"><h6 class="mb-0">{{ __('Pages people opened most') }}</h6></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Page') }}</th><th class="text-end">{{ __('Opened') }}</th><th class="text-end">{{ __('Visitors') }}</th></tr></thead>
                    <tbody>
                        @forelse ($pages as $page)
                            <tr><td><a href="{{ url($page['page']) }}" target="_blank" rel="noopener">{{ $page['label'] }}</a> <span class="text-muted small d-none d-md-inline">{{ $page['page'] }}</span></td><td class="text-end">{{ number_format($page['p']) }}</td><td class="text-end">{{ number_format($page['v']) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">{{ __('Nothing yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ======== robots and crawlers ======== --}}
<h5 class="mb-3 d-flex align-items-center gap-2"><span class="rounded-2" style="display:inline-block;width:14px;height:14px;background:#94A3B8"></span>{{ __('Robots and crawlers') }}</h5>
<div class="row g-3 mb-4">
    <div class="col-12 col-xl-4">
        <div class="card mb-0 h-100">
            <div class="card-header"><h6 class="mb-0">{{ __('By kind') }}</h6></div>
            <div class="card-body">
                @php($botTotal = max(1, $botTypes->sum('hits')))
                @forelse ($botTypes as $type)
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small gap-2"><span>{{ __($type['label']) }}</span><b class="text-nowrap">{{ number_format($type['hits']) }}</b></div>
                        <div class="progress" style="height:8px"><div class="progress-bar bg-secondary" style="width:{{ round(100 * $type['hits'] / $botTotal) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">{{ __('No robot has visited in this period.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card mb-0 h-100">
            <div class="card-header"><h6 class="mb-0">{{ __('Robots by name') }}</h6></div>
            <div class="table-responsive" style="max-height:420px">
                <table class="table table-sm align-middle mb-0">
                    <thead class="sticky-top bg-white"><tr><th>{{ __('Robot') }}</th><th>{{ __('Kind') }}</th><th class="text-end">{{ __('Requests') }}</th><th class="text-end">{{ __('Pages') }}</th><th class="text-end">{{ __('Last seen') }}</th></tr></thead>
                    <tbody>
                        @forelse ($bots as $bot)
                            <tr><td class="fw-semibold">{{ $bot->bot_name }}</td><td class="small text-muted">{{ __(\App\Support\Robots::typeLabel($bot->bot_type)) }}</td><td class="text-end">{{ number_format($bot->hits) }}</td><td class="text-end">{{ number_format($bot->pages) }}</td><td class="text-end small text-nowrap">{{ $bot->last_seen ? \Illuminate\Support\Carbon::parse($bot->last_seen)->diffForHumans(short: true) : '' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center py-3">{{ __('No robot has visited in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card mb-0">
            <div class="card-header"><h6 class="mb-0">{{ __('What robots read most') }}</h6><div class="form-text mt-0">{{ __('Includes the sitemap, robots.txt and llms.txt, which crawlers read to find the pages.') }}</div></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Page') }}</th><th class="text-end">{{ __('Requests') }}</th><th class="text-end">{{ __('Robots') }}</th></tr></thead>
                    <tbody>
                        @forelse ($botPages as $page)
                            <tr><td>{{ $page['label'] }} <span class="text-muted small d-none d-md-inline">{{ $page['page'] !== $page['label'] ? $page['page'] : '' }}</span></td><td class="text-end">{{ number_format($page['hits']) }}</td><td class="text-end">{{ number_format($page['names']) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">{{ __('Nothing yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ======== unconfirmed ======== --}}
<h5 class="mb-3 d-flex align-items-center gap-2"><span class="rounded-2" style="display:inline-block;width:14px;height:14px;background:#F59E0B"></span>{{ __('Unconfirmed traffic') }}</h5>
<div class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
        <div class="card mb-0 h-100">
            <div class="card-body small">
                <p class="mb-2"><strong>{{ trans_choice('{0} No unconfirmed requests.|{1} 1 request from :v sources|[2,*] :count requests from :v sources', $totals['pendingHits'], ['v' => number_format($totals['pendingVisitors'])]) }}</strong></p>
                <p class="mb-0 text-muted">{{ __('These opened a page with an ordinary browser name but never ran the page’s check that a real browser is there. Most are scripts, scrapers and robots that hide their name; a few are people with JavaScript switched off or who left within a second. They are never counted as visitors.') }}</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card mb-0 h-100">
            <div class="card-header"><h6 class="mb-0">{{ __('Where they came from') }}</h6></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
                @forelse ($pendingCountries as $row)
                    <tr><td><span aria-hidden="true">{{ $row['flag'] }}</span> {{ $row['name'] }}</td><td class="text-end fw-semibold">{{ number_format($row['hits']) }}</td></tr>
                @empty
                    <tr><td class="text-muted text-center py-3">{{ __('Nothing yet.') }}</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>
</div>

{{-- ======== the platform ======== --}}
<h5 class="mb-3 d-flex align-items-center gap-2"><span class="rounded-2" style="display:inline-block;width:14px;height:14px;background:#0E5A8A"></span>{{ __('The platform') }}</h5>
<div class="row g-3 mb-3">
    @foreach ($platform['totals'] as $label => $value)
        <div class="col-6 col-md"><div class="card mb-0 h-100"><div class="card-body"><div class="text-muted small">{{ __($label) }}</div><div class="fs-4 fw-bold">{{ number_format($value) }}</div></div></div></div>
    @endforeach
</div>
<div class="card">
    <div class="card-header"><h6 class="mb-0">{{ __('New each month') }}</h6><div class="form-text mt-0">{{ __('The last 12 months, whatever period is chosen above.') }}</div></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Companies registered') }}</th><th class="text-end">{{ __('Verified') }}</th><th>{{ __('Tenders published') }}</th>@if ($platform['withProducts'])<th>{{ __('Products') }}</th>@endif</tr></thead>
            <tbody>
                @foreach (array_reverse($platform['months']) as $month)
                    <tr>
                        <td class="text-nowrap">{{ $month['label'] }}</td>
                        <td style="min-width:160px"><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar bg-success" style="width:{{ round(100 * $month['companies'] / $maxMonth) }}%"></div></div><b class="small" style="min-width:2.5em;text-align:right">{{ $month['companies'] }}</b></div></td>
                        <td class="text-end">{{ $month['verified'] }}</td>
                        <td style="min-width:160px"><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar" style="width:{{ round(100 * $month['tenders'] / $maxMonth) }}%"></div></div><b class="small" style="min-width:2.5em;text-align:right">{{ $month['tenders'] }}</b></div></td>
                        @if ($platform['withProducts'])<td style="min-width:140px"><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar bg-warning" style="width:{{ round(100 * $month['products'] / $maxMonth) }}%"></div></div><b class="small" style="min-width:2.5em;text-align:right">{{ $month['products'] }}</b></div></td>@endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

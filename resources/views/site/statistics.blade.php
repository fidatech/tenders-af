@php
    $site = \App\Support\Branding::name();
    $schema = [[
        '@type' => 'Dataset', '@id' => url()->current().'#dataset', 'name' => 'Tenders, companies and visitors of '.$site,
        'description' => 'Counts of tenders published and open by month, sector, province and buyer, registered and verified companies, and visitors by country, counted live from '.$site.'.',
        'url' => url()->current(), 'creator' => ['@id' => url('/').'#organization'], 'spatialCoverage' => ['@type' => 'Country', 'name' => 'Afghanistan'],
        'isAccessibleForFree' => true, 'dateModified' => now()->toIso8601String(), 'license' => 'https://creativecommons.org/licenses/by/4.0/',
        'distribution' => [['@type' => 'DataDownload', 'encodingFormat' => 'text/csv', 'contentUrl' => route('site.statistics.csv')]],
    ]];
    $tones = ['gt-green', 'gt-red', 'gt-ink'];
    $hasVisitors = ($visitors['views'] ?? 0) > 0;
@endphp
@extends('layouts.site', ['schema' => $schema])

@section('hero')
@include('site._pagehead', ['heading' => 'Statistics', 'lead' => 'Who visits '.$site.' and from where, and what is published on it. Counted from the site itself: nothing is estimated, no cookies are used.', 'crumbs' => [['Statistics', null]]])
@endsection

@section('content')
<div class="wrap stats-page">
    {{-- visitor analytics first: who opens the site, from where, on what, and which pages --}}
    <section class="block stats-visitors" id="visitors">
        <div class="stats-head">
            <div>
                <h2>Visitors</h2>
                <p class="stats-note">Real people who opened the website, counted once a day each, without cookies and without keeping IP addresses. Robots, crawlers and scripts are not counted. Updated every ten minutes.</p>
            </div>
            <nav class="stats-period" aria-label="Period">
                @foreach (\App\Support\Statistics::PERIODS as $period)
                    <a href="{{ route('site.statistics', $period === 30 ? [] : ['days' => $period]) }}#visitors" @class(['on' => $days === $period]) @if ($days === $period) aria-current="true" @endif>{{ $period }} days</a>
                @endforeach
            </nav>
        </div>

        @if (! $hasVisitors)
            <div class="card-plain stats-empty">
                <b>Counting has started.</b>
                <span>{{ $visitors['since'] ? 'The first visits were counted on '.fmt_date($visitors['since']).'.' : 'Visits are counted from today.' }} Charts appear here as soon as there are visits in the chosen period.</span>
            </div>
        @else
            @php
                $change = $visitors['before'] ? round(100 * ($visitors['visitors'] - $visitors['before']) / $visitors['before']) : null;
                $countriesKnown = collect($visitors['countries'])->whereNotNull('code')->count();
                $phoneShare = $visitors['devices'] ? round(100 * ($visitors['devices']['mobile'] ?? 0) / max(1, array_sum($visitors['devices']))) : 0;
            @endphp
            <div class="statgrid small">
                <div class="statcard"><b class="gt-green">{{ number_format($visitors['visitors']) }}</b><span>Visitors</span>
                    <small>@if ($change !== null)<em @class(['up' => $change >= 0, 'down' => $change < 0])>{{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}%</em> against the {{ $days }} days before @else One per person per day @endif</small></div>
                <div class="statcard"><b class="gt-ink">{{ number_format($visitors['views']) }}</b><span>Pages opened</span><small>{{ number_format($visitors['views'] / max(1, $visitors['visitors']), 1) }} pages per visitor</small></div>
                <div class="statcard"><b class="gt-red">{{ number_format($countriesKnown) }}</b><span>{{ $countriesKnown === 1 ? 'Country' : 'Countries' }}</span><small>Where visitors were</small></div>
                <div class="statcard"><b class="gt-ink">{{ $phoneShare }}%</b><span>On phones</span><small>The rest on computers and tablets</small></div>
            </div>

            <div class="card-plain">
                <h3 class="stats-h3">Visitors and pages opened, day by day</h3>
                @include('site._trend', ['points' => $visitors['daily'], 'names' => ['Visitors', 'Pages opened'], 'label' => 'Visitors and pages opened each day over the last '.$days.' days'])
            </div>

            <div class="stats-two">
                <div class="card-plain">
                    <h3 class="stats-h3">Visitors by country</h3>
                    @include('site._bars', ['rows' => collect($visitors['countries'])->take(12)->map(fn ($c) => [$c['name'], $c['visitors'], null, $c['flag']])->all(), 'label' => 'Visitors by country', 'share' => true])
                </div>
                <div class="card-plain">
                    <h3 class="stats-h3">Visitors by city</h3>
                    @if ($visitors['cities'])
                        @include('site._bars', ['rows' => collect($visitors['cities'])->take(12)->map(fn ($c) => [$c['city'].($c['region'] && $c['region'] !== $c['city'] ? ', '.$c['region'] : '').' · '.$c['country'], $c['visitors'], null, $c['flag']])->all(), 'label' => 'Visitors by city'])
                        <p class="stats-note">Cities with at least {{ \App\Support\Statistics::CITY_MIN }} visitors.</p>
                    @else
                        <p class="stats-note">A city is shown once it has at least {{ \App\Support\Statistics::CITY_MIN }} visitors, so no single person can be pointed at.</p>
                    @endif
                </div>
            </div>

            <div class="stats-two">
                @if ($visitors['sources'])
                    <div class="card-plain">
                        <h3 class="stats-h3">How visitors found the site</h3>
                        @include('site._donut', ['parts' => $visitors['sources'], 'label' => 'Visitors by source', 'unit' => 'visitors'])
                    </div>
                @endif
                @if ($visitors['devices'])
                    <div class="card-plain">
                        <h3 class="stats-h3">Devices</h3>
                        @include('site._donut', ['parts' => collect($visitors['devices'])->mapWithKeys(fn ($n, $d) => [$d === 'mobile' ? 'Phones' : 'Computers and tablets' => $n])->all(), 'label' => 'Visitors by device', 'unit' => 'visitors'])
                    </div>
                @endif
            </div>

            @if ($visitors['pages'])
                <div class="card-plain">
                    <h3 class="stats-h3">Pages opened most</h3>
                    @include('site._bars', ['rows' => collect($visitors['pages'])->map(fn ($p) => [$p['label'], $p['views'], url($p['page'])])->all(), 'label' => 'Pages opened most'])
                </div>
            @endif

            <p class="stats-note">
                @if ($visitors['since'])Counted since {{ fmt_date($visitors['since']) }}.@endif
                @if (\App\Support\GeoIp::available())Places from <a href="{{ config('geoip.attribution.1') }}" rel="noopener" target="_blank">{{ config('geoip.attribution.0') }}</a>.@endif
            </p>
        @endif
    </section>

    <h2 class="stats-part">Tenders and companies</h2>
    @if ($headline)
        <div class="statgrid small">
            @foreach (array_values($headline) as $index => [$label, $number, $explain])
                <div class="statcard"><b class="{{ $tones[$index % 3] }}">{{ number_format($number) }}</b><span>{{ $label }}</span><small>{{ $explain }}</small></div>
            @endforeach
        </div>
        <p class="count">Counted on {{ $updated }}, Kabul time. <a href="{{ route('site.statistics.csv') }}" rel="nofollow">Download the figures (CSV)</a></p>
    @endif

    @unless ($hasTenders)
        {{-- early days: say plainly what is covered and how the figures will fill --}}
        <section class="block stats-launch">
            <div>
                <h2>Tender figures start with the first tenders</h2>
                <p class="text">{{ $site }} follows tenders in all {{ $coverage['provinces'] }} provinces of Afghanistan and {{ $coverage['sectors'] }} sectors. As tenders are published, this page shows them by month, sector, province, buyer and deadline. Every figure is counted, never estimated.</p>
            </div>
            <ul class="stats-cover">
                <li><b>{{ $coverage['provinces'] }}</b><span>provinces covered</span></li>
                <li><b>{{ $coverage['sectors'] }}</b><span>sectors tracked</span></li>
                <li><b>Free</b><span>to publish a tender</span></li>
            </ul>
            <p class="stats-cta">
                <a class="btn btn-green btn-sm" href="{{ \App\Support\Platform::bool('registration') ? route('register', ['as' => 'buyer']) : route('site.contact') }}">Publish a tender</a>
                <a class="btn btn-line btn-sm" href="{{ route('register') }}">Register your company</a>
            </p>
        </section>
    @endunless

    @if ($months)
        <section class="block">
            <h2>Tenders published each month</h2>
            <p class="stats-note">The last 12 months, by the day each tender was published.</p>
            <div class="card-plain">@include('site._columns', ['columns' => $months, 'label' => 'Tenders published each month over the last 12 months'])</div>
        </section>
    @endif

    @if ($hasTenders)
        <div class="stats-two">
            @if ($sectors->isNotEmpty())
                <section class="block">
                    <h2>By sector</h2>
                    <p class="stats-note">All tenders published in each sector.</p>
                    <div class="card-plain">@include('site._bars', ['rows' => $sectors->take(12)->map(fn ($s) => [$s->name, $s->all_count, route('site.sector', ['slug' => $s->slug])])->all(), 'label' => 'Tenders by sector'])</div>
                </section>
            @endif
            @if ($buyers)
                <section class="block">
                    <h2>Buyers that publish the most</h2>
                    <p class="stats-note">Organisations by the number of their tenders on the site.</p>
                    <div class="card-plain">@include('site._bars', ['rows' => $buyers, 'label' => 'Top buyers'])</div>
                </section>
            @endif
            @if ($buyerKinds)
                <section class="block">
                    <h2>Who is buying</h2>
                    <div class="card-plain">@include('site._bars', ['rows' => $buyerKinds, 'label' => 'Tenders by kind of buyer', 'share' => true])</div>
                </section>
            @endif
            @if ($tenderKinds)
                <section class="block">
                    <h2>Kinds of tender</h2>
                    <div class="card-plain">@include('site._bars', ['rows' => $tenderKinds, 'label' => 'Tenders by kind', 'share' => true])</div>
                </section>
            @endif
        </div>

        @if ($deadlines['windows'] || $deadlines['median'] !== null)
            <section class="block">
                <h2>Deadlines</h2>
                <div class="statgrid small">
                    @foreach ($deadlines['windows'] as $window => $n)
                        <div class="statcard"><b class="gt-ink">{{ number_format($n) }}</b><span>Closing within {{ $window }}</span></div>
                    @endforeach
                    @if ($deadlines['median'] !== null)
                        <div class="statcard"><b class="gt-green">{{ $deadlines['median'] }} days</b><span>Typical time to bid</span><small>The middle value from publication to deadline, over {{ number_format($deadlines['sample']) }} tenders of the last year.</small></div>
                    @endif
                </div>
            </section>
        @endif

        <section class="block">
            <h2>Open tenders by province</h2>
            <div class="dark panel" style="margin-top:16px">
                <div class="map-head">
                    <div class="eyebrow">Darker green means more open tenders</div>
                    <div class="legend"><span>Fewer</span><i style="background:rgba(95,224,160,.14)"></i><i style="background:rgba(95,224,160,.36)"></i><i style="background:rgba(95,224,160,.62)"></i><span>More tenders</span></div>
                </div>
                <div class="mapwrap"><div class="mapscroll">@include('site._map', ['map' => $map])</div></div>
            </div>
        </section>
    @endif

    @php($activeProvinces = $provinces->filter(fn ($p) => $p['all'] + $p['companies'] + $p['products'] > 0))
    @if ($activeProvinces->isNotEmpty())
        <section class="block">
            <h2>By province</h2>
            <p class="stats-note">Provinces with tenders or companies; the others have none yet.</p>
            <div class="tablewrap">
                <table class="numbers">
                    <thead><tr><th scope="col">Province</th><th scope="col">Open tenders</th><th scope="col">All tenders</th><th scope="col">Companies</th>@if ($withProducts)<th scope="col">Products</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($activeProvinces as $province)
                            <tr><td><a href="{{ route('site.province', ['slug' => $province['slug']]) }}">{{ $province['name'] }}</a></td><td>{{ number_format($province['open']) }}</td><td>{{ number_format($province['all']) }}</td><td>{{ number_format($province['companies']) }}</td>@if ($withProducts)<td>{{ number_format($province['products']) }}</td>@endif</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($companies['verified'] > 0 || $companies['types'])
        <section class="block">
            <h2>Companies</h2>
            <div class="stats-two">
                @if ($companies['verified'] > 0)
                <div class="card-plain stats-verified">
                    <div class="stats-ring" style="--p: {{ round(100 * $companies['verified'] / $companies['listed']) }}" role="img" aria-label="{{ $companies['verified'] }} of {{ $companies['listed'] }} companies in the directory are verified"><b>{{ round(100 * $companies['verified'] / $companies['listed']) }}%</b></div>
                    <p><b>{{ number_format($companies['verified']) }} of {{ number_format($companies['listed']) }}</b> companies in the directory had their business licence checked.</p>
                </div>
                @endif
                @if ($companies['types'])
                    <div class="card-plain">
                        <h3 class="stats-h3">Kinds of business</h3>
                        @include('site._bars', ['rows' => $companies['types'], 'label' => 'Companies by kind of business'])
                    </div>
                @endif
            </div>
        </section>
    @endif


    <section class="block">
        <h2>How we count</h2>
        <p class="text">Every number on this page is counted from the tenders, companies and products on {{ $site }} at this moment. A tender counts as open until its deadline passes. A company counts as verified after we checked its business licence. Visitors are people who opened a public page, counted once per day each; no cookie is set, no IP address is kept, and browsers that ask not to be tracked are not counted. You may quote these figures; please name {{ $site }} as the source.</p>
    </section>
    @include('site._cta')
</div>
@endsection

@php
    $schema = [[
        '@type' => 'Dataset', '@id' => url()->current().'#dataset', 'name' => 'Tenders and companies in Afghanistan by province and sector',
        'description' => 'Counts of tenders published and open, buyers and registered companies on '.config('app.name').', by province and by sector. Updated live.',
        'url' => url()->current(), 'creator' => ['@id' => url('/').'#organization'], 'spatialCoverage' => ['@type' => 'Country', 'name' => 'Afghanistan'],
        'isAccessibleForFree' => true, 'dateModified' => now()->toIso8601String(),
    ]];
@endphp
@extends('layouts.site', ['schema' => $schema])

@section('hero')
@include('site._pagehead', ['heading' => 'Statistics', 'lead' => 'Real numbers from '.config('app.name').', counted from the site itself and updated every time this page opens. Nothing is estimated.', 'crumbs' => [['Statistics', null]]])
@endsection

@section('content')
<div class="wrap">
    @if (empty($figures))
        <div class="empty">
            <h2>The numbers are still growing</h2>
            <p>We show a figure here once it is big enough to mean something. Please come back soon.</p>
        </div>
    @else
        <div class="statgrid">
            @foreach ($figures as $index => [$label, $number, $explain])
                <div class="statcard"><b class="{{ ['gt-green', 'gt-red', 'gt-ink'][$index % 3] }}">{{ number_format($number) }}</b><span>{{ $label }}</span><small>{{ $explain }}</small></div>
            @endforeach
        </div>
        <p class="count">Counted on {{ $updated }}, Kabul time.</p>
    @endif

    @if ($showTables)
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

        <section class="block">
            <h2>By province</h2>
            <div class="tablewrap">
                <table class="numbers">
                    <thead><tr><th>Province</th><th>Open tenders</th><th>All tenders</th><th>Companies</th>@if ($withProducts)<th>Products</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($provinces as $province)
                            <tr><td><a href="{{ route('site.province', ['slug' => $province['slug']]) }}">{{ $province['name'] }}</a></td><td>{{ number_format($province['open']) }}</td><td>{{ number_format($province['all']) }}</td><td>{{ number_format($province['companies']) }}</td>@if ($withProducts)<td>{{ number_format($province['products']) }}</td>@endif</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="block">
            <h2>By sector</h2>
            <div class="tablewrap">
                <table class="numbers">
                    <thead><tr><th>Sector</th><th>Open tenders</th><th>All tenders</th></tr></thead>
                    <tbody>
                        @foreach ($sectors as $sector)
                            <tr><td><a href="{{ route('site.sector', ['slug' => $sector->slug]) }}">{{ $sector->name }}</a></td><td>{{ number_format($sector->open_count) }}</td><td>{{ number_format($sector->all_count) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="block">
        <h2>How we count</h2>
        <p class="text">Every number on this page is counted from the tenders, companies and products that are on {{ config('app.name') }} at this moment. A tender counts as open until its deadline passes. A company counts as verified after we checked its business licence. You may quote these figures; please name {{ config('app.name') }} as the source.</p>
    </section>
    @include('site._cta')
</div>
@endsection

@extends('layouts.site', ['crumbs' => [['Tenders', route('site.tenders')], ['Provinces', null]]])

@section('hero')
@include('site._pagehead', ['heading' => 'Tenders by province', 'lead' => 'Open tenders in each of the 34 provinces of Afghanistan. Click a province on the map, or pick it from the list.', 'crumbs' => [['Tenders', route('site.tenders')], ['Provinces', null]]])
@endsection

@section('content')
<div class="wrap" style="margin-top:-30px;position:relative">
    <div class="dark panel">
        <div class="map-head">
            <div class="eyebrow">Darker green means more open tenders</div>
            <div class="legend"><span>Fewer</span><i style="background:rgba(95,224,160,.14)"></i><i style="background:rgba(95,224,160,.36)"></i><i style="background:rgba(95,224,160,.62)"></i><span>More tenders</span></div>
        </div>
        <div class="mapwrap"><div class="mapscroll">@include('site._map', ['map' => $map])</div></div>
    </div>

    <div class="listgrid">
        @foreach ($list as $province)
            <a href="{{ route('site.province', ['slug' => $province['slug']]) }}">{{ $province['name'] }}<span>{{ number_format($province['count']) }}</span></a>
        @endforeach
    </div>
    @include('site._cta')
</div>
@endsection

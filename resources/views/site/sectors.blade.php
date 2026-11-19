@extends('layouts.site', ['crumbs' => [['Tenders', route('site.tenders')], ['Sectors', null]]])

@php
    $grads = ['bg-green', 'bg-red', 'bg-ink'];
@endphp

@section('hero')
@include('site._pagehead', ['heading' => 'Tenders by sector', 'lead' => 'Choose your sector to see its open tenders. Register your company to get new tenders of your sectors by email and WhatsApp.', 'crumbs' => [['Tenders', route('site.tenders')], ['Sectors', null]]])
@endsection

@section('content')
<div class="wrap" style="padding-top:8px">
    @if ($sectors->isEmpty())
        <div class="empty"><h2>The sectors are being set up</h2><p>Please come back soon.</p></div>
    @else
        <div class="grid-sectors">
            @foreach ($sectors as $sector)
                <a class="sector" href="{{ route('site.sector', ['slug' => $sector->slug]) }}">
                    <i class="{{ $grads[$loop->index % 3] }}" aria-hidden="true"><svg><use href="{{ asset_v('icons.svg') }}#s-{{ $sector->icon }}"/></svg></i>
                    <span><b>{{ $sector->name }}</b><small>{{ trans_choice('{0} No open tenders|{1} 1 open tender|[2,*] :count open tenders', $sector->open_count, ['count' => number_format($sector->open_count)]) }}</small></span>
                </a>
            @endforeach
        </div>
    @endif
    @include('site._cta')
</div>
@endsection

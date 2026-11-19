@extends('layouts.site', ['crumbs' => [['How it works', null]]])

@php
    $grads = ['bg-green', 'bg-red', 'bg-ink'];
@endphp

@section('hero')
@include('site._pagehead', ['heading' => 'How Tenders.af works', 'lead' => config('site.summary'), 'crumbs' => [['How it works', null]]])
@endsection

@section('content')
<div class="wrap" style="padding-top:8px">
    <section class="block" style="margin-top:0">
        <div class="eyebrow">For companies</div>
        <h2 style="margin-top:6px">Three steps to your next tender</h2>
        <div class="grid-3">
            @foreach (config('site.steps') as $index => [$stepTitle, $stepText])
                <div class="card"><div class="num {{ $grads[$index % 3] }}">{{ $index + 1 }}</div><h3>{{ $stepTitle }}</h3><p>{{ $stepText }}</p></div>
            @endforeach
        </div>
    </section>

    <section class="block">
        <div class="eyebrow red">What you get</div>
        <h2 style="margin-top:6px">Built for bidding in Afghanistan</h2>
        <div class="grid-3">
            @foreach (config('site.features') as [$icon, $featureTitle, $featureText])
                <div class="card"><div class="num {{ $grads[$loop->index % 3] }}"><svg aria-hidden="true" width="22" height="22" style="fill:currentColor"><use href="{{ asset_v('icons.svg') }}#s-{{ $icon }}"/></svg></div><h3>{{ $featureTitle }}</h3><p>{{ $featureText }}</p></div>
            @endforeach
        </div>
    </section>

    <section class="block prose">
        <div class="eyebrow">For buyers</div>
        <h2 style="margin-top:6px">Post a tender, find suppliers</h2>
        <p>NGOs, companies and other buyers can send us a tender to publish. It is free. We check it, publish it, and the companies of that sector are told by email and WhatsApp.</p>
        <p>The company directory is open to everyone. Search it by sector and province, and filter for verified companies.</p>
        <p><a class="btn btn-green" href="{{ route('site.contact') }}">Send us a tender</a></p>
    </section>
    @include('site._cta')
</div>
@endsection

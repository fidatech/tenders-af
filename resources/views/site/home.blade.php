@extends('layouts.site')

@php
    $grads = ['bg-green', 'bg-red', 'bg-ink'];
    $first = $latest->get(0);
    $second = $latest->get(1);
@endphp

@section('hero')
<section class="wrap hero">
    <div class="hero-text">
        <span class="chip">{{ $stats['today'] ? trans_choice('{1} 1 new tender added today|[2,*] :count new tenders added today', $stats['today'], ['count' => number_format($stats['today'])]) : 'New tenders added every day' }}</span>
        <h1>Find and win tenders across <span class="grad">Afghanistan.</span></h1>
        <p class="lead">Tenders from NGOs, UN agencies, government and private buyers, matched to your business and sent to you by email and WhatsApp.</p>
        <form class="search" method="GET" action="{{ route('site.tenders') }}" role="search">
            <label class="q"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5B6660" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><span class="sr">Search tenders</span><input type="search" name="q" placeholder="Search: solar panels, medicine, construction…"></label>
            <label class="p"><span class="sr">Province</span><select name="province"><option value="">All provinces</option>@foreach ($provinceOptions as $id => $provinceName)<option value="{{ $id }}">{{ $provinceName }}</option>@endforeach</select></label>
            <button type="submit" class="btn btn-green">Find tenders</button>
        </form>
        @if ($sectors->isNotEmpty())
            <div class="popular"><span>Popular:</span>@foreach ($sectors->take(4) as $sector)<a href="{{ route('site.sector', ['slug' => $sector->slug]) }}">{{ $sector->name }}</a>@endforeach</div>
        @endif
    </div>

    <div class="hero-side">
        <svg aria-hidden="true" viewBox="-10 -10 1020 781" preserveAspectRatio="xMidYMid meet">@foreach ($map as $shape)<path d="{{ $shape['d'] }}" fill="rgba(255,255,255,.04)" stroke="rgba(255,255,255,.22)" stroke-width="1.5"/>@endforeach</svg>
        @if ($first)
            <a class="float glass" href="{{ route('site.tenders.show', ['slug' => $first->slug]) }}">
                <div class="row"><span class="tag">{{ $first->typeLabel() }} · {{ $first->number() }}</span><span class="pill {{ $first->state() === 'soon' ? 'soon' : 'open' }}">{{ $first->timeLeft() }}</span></div>
                <h3>{{ \Illuminate\Support\Str::limit($first->title, 70) }}</h3>
                <div class="meta">{{ $first->buyer_name }} · {{ $first->placeText() }}</div>
            </a>
        @endif
        @if ($second)
            <a class="float white" href="{{ route('site.tenders.show', ['slug' => $second->slug]) }}">
                <div class="row"><span class="tag">{{ $second->typeLabel() }} · {{ $second->number() }}</span><span class="pill {{ $second->state() === 'soon' ? 'soon' : 'open' }}">{{ $second->timeLeft() }}</span></div>
                <h3>{{ \Illuminate\Support\Str::limit($second->title, 70) }}</h3>
                <div class="meta">{{ $second->buyer_name }} · {{ $second->placeText() }}</div>
            </a>
        @endif
        <div class="float alert">
            <span class="ico"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"><path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7M10 20a2 2 0 0 0 4 0"/></svg></span>
            <div><div style="font-weight:600;font-size:14px">New tender matches your alerts</div><div style="font-size:13px;color:#DCF3E6">Sent by email and WhatsApp</div></div>
        </div>
    </div>
</section>
@endsection

@section('content')
<div class="wrap stats">
    <div>
        <div class="stat"><b class="gt-green">{{ number_format($stats['open']) }}</b><span>open tenders</span></div>
        <div class="stat"><b class="gt-red">{{ number_format($stats['closing']) }}</b><span>closing this week</span></div>
        <div class="stat"><b class="gt-ink">{{ number_format($stats['buyers']) }}</b><span>buyers posting tenders</span></div>
        <div class="stat"><b class="gt-green">{{ number_format($stats['companies']) }}</b><span>verified companies</span></div>
    </div>
</div>

<section class="wrap section" id="tenders">
    <div class="section-head">
        <div><div class="eyebrow red">Updated daily</div><h2>Latest tenders</h2></div>
        <nav class="tabs" aria-label="Tenders by buyer">
            <a href="{{ route('site.tenders') }}" aria-current="true">All</a>
            @foreach (['ngo' => 'NGO', 'un' => 'UN', 'government' => 'Government', 'private' => 'Private'] as $key => $label)
                <a href="{{ route('site.tenders', ['buyer' => $key]) }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>
    @if ($latest->isEmpty())
        <div class="empty"><h2>The first tenders are on their way</h2><p>Register your company now and you are told as soon as tenders in your sectors are published.</p></div>
    @else
        <div class="grid-tenders">
            @foreach ($latest as $tender)
                @include('site._tender', ['tender' => $tender])
            @endforeach
        </div>
        <p class="center" style="margin-top:30px"><a class="btn btn-ink" href="{{ route('site.tenders') }}">See all open tenders</a></p>
    @endif
</section>

<div class="wrap"><div class="fade-line"></div></div>

@if ($sectors->isNotEmpty())
<section class="wrap section" id="sectors">
    <div class="section-head">
        <div><div class="eyebrow">By sector</div><h2>Browse by sector</h2></div>
        <a href="{{ route('site.sectors') }}" style="font-weight:600">All sectors</a>
    </div>
    <div class="grid-sectors">
        @foreach ($sectors as $sector)
            <a class="sector" href="{{ route('site.sector', ['slug' => $sector->slug]) }}">
                <i class="{{ $grads[$loop->index % 3] }}" aria-hidden="true"><svg><use href="{{ asset_v('icons.svg') }}#s-{{ $sector->icon }}"/></svg></i>
                <span><b>{{ $sector->name }}</b><small>{{ trans_choice('{0} No open tenders|{1} 1 open tender|[2,*] :count open tenders', $sector->open_count, ['count' => number_format($sector->open_count)]) }}</small></span>
            </a>
        @endforeach
    </div>
</section>
@endif

<section class="wrap" id="map" style="padding-bottom:64px">
    <div class="dark panel">
        <div class="map-head">
            <div><div class="eyebrow">34 provinces</div><h2 style="margin-top:6px;font-size:clamp(28px,3.4vw,36px);font-weight:800">Tenders across the country</h2><p>Click a province to see its open tenders.</p></div>
            <div class="legend"><span>Fewer</span><i style="background:rgba(95,224,160,.14)"></i><i style="background:rgba(95,224,160,.36)"></i><i style="background:rgba(95,224,160,.62)"></i><span>More tenders</span></div>
        </div>
        <div class="mapwrap">
            <div class="mapscroll">@include('site._map', ['map' => $map])</div>
            <div class="mappanel">
                <h3>Most open tenders</h3>
                @forelse ($top as $province)
                    <a class="line" href="{{ route('site.province', ['slug' => $province['slug']]) }}">{{ $province['name'] }}<span>{{ trans_choice('{1} 1 open tender|[2,*] :count open tenders', $province['count'], ['count' => number_format($province['count'])]) }}</span></a>
                @empty
                    <p class="muted" style="margin-top:8px;font-size:14px">The provinces fill in as tenders are published.</p>
                @endforelse
                <a class="btn btn-green btn-sm" style="display:block;margin-top:14px" href="{{ route('site.provinces') }}">List all 34 provinces</a>
            </div>
        </div>
    </div>
</section>

@if ($featured->isNotEmpty())
<section class="band" id="companies">
    <div class="wrap section">
        <div class="section-head">
            <div><div class="eyebrow">Free company directory</div><h2>Verified companies</h2></div>
            <a href="{{ route('site.companies') }}" style="font-weight:600">Open the company directory</a>
        </div>
        <div class="grid-companies">
            @foreach ($featured as $company)
                @include('site._company', ['company' => $company])
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="wrap section">
    <div class="center"><div class="eyebrow red">Three steps</div><h2>How it works</h2></div>
    <div class="grid-3">
        @foreach (config('site.steps') as $index => [$stepTitle, $stepText])
            <div class="card"><div class="num {{ $grads[$index % 3] }}">{{ $index + 1 }}</div><h3>{{ $stepTitle }}</h3><p>{{ $stepText }}</p></div>
        @endforeach
    </div>
    @include('site._cta')
</section>

<section class="wrap" style="padding-bottom:40px">
    <div class="eyebrow">Questions</div>
    <h2 style="margin-top:6px;font-size:clamp(26px,3vw,32px);font-weight:800">Common questions</h2>
    <div style="margin-top:10px">
        @foreach (array_slice($faqs, 0, 4) as [$question, $answer])
            <details class="qa"><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>
        @endforeach
    </div>
    <p style="margin-top:18px"><a href="{{ route('site.faq') }}" style="font-weight:600">All questions and answers</a></p>
</section>
@endsection

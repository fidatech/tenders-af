@extends('layouts.site')

@php
    $grads = ['bg-green', 'bg-red', 'bg-ink'];
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

    {{-- the map is the picture of the hero: every province is a link; the cards sit on its empty corners --}}
    <div class="hero-side">
        <div class="stage">
            <svg class="heromap" viewBox="-10 -10 1020 781" role="group" aria-label="Map of Afghanistan. Choose a province to see its open tenders.">
                @foreach ($map as $shape)
                    <a href="{{ route('site.province', ['slug' => $shape['slug']]) }}" data-name="{{ $shape['name'] }}" data-count="{{ $shape['count'] }}" aria-label="{{ $shape['name'] }}: {{ $shape['count'] }} open tenders"><path d="{{ $shape['d'] }}" style="fill-opacity: {{ max(0.14, $shape['shade']) }}"/></a>
                @endforeach
                @foreach ($map as $shape)
                    @if ($shape['count'] > 0)
                        <circle class="ping" cx="{{ $shape['x'] }}" cy="{{ $shape['y'] }}" r="7"/><circle class="dot" cx="{{ $shape['x'] }}" cy="{{ $shape['y'] }}" r="5"/>
                    @endif
                @endforeach
            </svg>
            <div class="maptip" role="status" aria-live="polite" hidden><b></b><span></span></div>
        </div>

        <span class="hint"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11V5a2 2 0 1 1 4 0v5"/><path d="M13 10V8a2 2 0 1 1 4 0v3"/><path d="M17 11a2 2 0 1 1 4 0v3a7 7 0 0 1-7 7h-1a7 7 0 0 1-6-3.4L4.6 14a2 2 0 0 1 3.4-2l1 1.5"/></svg> Choose a province</span>

        {{-- top left: the newest tenders, one after the other --}}
        @if ($latest->isNotEmpty())
            <div class="side-card tl rotator" aria-label="Newest tenders">
                <div class="side-label"><i></i> Just published</div>
                @foreach ($latest->take(3) as $index => $tender)
                    <a class="slide {{ $index === 0 ? 'on' : '' }}" href="{{ route('site.tenders.show', ['slug' => $tender->slug]) }}" @if ($index) aria-hidden="true" tabindex="-1" @endif>
                        <span class="row"><span class="tag">{{ $tender->typeLabel() }}</span><span class="pill {{ $tender->state() === 'soon' ? 'soon' : 'open' }}">{{ $tender->timeLeft() }}</span></span>
                        <b>{{ \Illuminate\Support\Str::limit($tender->title, 64) }}</b>
                        <small>{{ $tender->buyer_name }} · {{ $tender->placeText() }}</small>
                    </a>
                @endforeach
                @if ($latest->count() > 1)
                    <span class="dots" aria-hidden="true">@foreach ($latest->take(3) as $index => $tender)<i class="{{ $index === 0 ? 'on' : '' }}"></i>@endforeach</span>
                @endif
            </div>
        @elseif (\App\Support\Platform::bool('registration'))
            <a class="side-card tl" href="{{ route('register') }}">
                <div class="side-label"><i></i> Be the first to know</div>
                <b>Choose your sectors and provinces</b>
                <small>New tenders come to you by email and WhatsApp.</small>
            </a>
        @endif

        {{-- bottom right, in the empty south-east corner: what an alert looks like --}}
        <div class="side-card br alert">
            <span class="ico"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"><path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7M10 20a2 2 0 0 0 4 0"/></svg></span>
            <div><b>New tender matches your alerts</b><small>Sent by email and WhatsApp</small></div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    // the name and the number of open tenders of a province, next to the pointer
    var stage = document.querySelector('.hero .stage');
    if (stage) {
        var tip = stage.querySelector('.maptip');
        var show = function (link, x, y) {
            var n = +link.getAttribute('data-count');
            tip.querySelector('b').textContent = link.getAttribute('data-name');
            tip.querySelector('span').textContent = n === 0 ? 'No open tenders yet' : n === 1 ? '1 open tender' : n.toLocaleString() + ' open tenders';
            tip.hidden = false;
            var box = stage.getBoundingClientRect();
            tip.style.left = Math.min(Math.max(x - box.left, 70), box.width - 70) + 'px';
            tip.style.top = (y - box.top) + 'px';
        };
        stage.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('mousemove', function (e) { show(link, e.clientX, e.clientY); });
            link.addEventListener('focus', function () { var r = link.getBoundingClientRect(); show(link, r.left + r.width / 2, r.top + r.height / 2); });
            link.addEventListener('mouseleave', function () { tip.hidden = true; });
            link.addEventListener('blur', function () { tip.hidden = true; });
        });
    }
    // the newest tenders take turns in the top card
    var rotator = document.querySelector('.hero .rotator');
    var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (rotator && !still) {
        var slides = rotator.querySelectorAll('.slide'), dots = rotator.querySelectorAll('.dots i'), at = 0, paused = false;
        if (slides.length > 1) {
            rotator.addEventListener('mouseenter', function () { paused = true; });
            rotator.addEventListener('mouseleave', function () { paused = false; });
            setInterval(function () {
                if (paused) return;
                slides[at].classList.remove('on'); slides[at].setAttribute('aria-hidden', 'true'); slides[at].tabIndex = -1;
                if (dots[at]) dots[at].classList.remove('on');
                at = (at + 1) % slides.length;
                slides[at].classList.add('on'); slides[at].removeAttribute('aria-hidden'); slides[at].removeAttribute('tabindex');
                if (dots[at]) dots[at].classList.add('on');
            }, 5000);
        }
    }
})();
</script>
@endpush

@section('content')
<div class="wrap stats">
    <div>
        <div class="stat"><b class="gt-green">{{ number_format($stats['open']) }}</b><span>open tenders</span></div>
        <div class="stat"><b class="gt-red">{{ number_format($stats['closing']) }}</b><span>closing this week</span></div>
        <div class="stat"><b class="gt-ink">{{ number_format($stats['buyers']) }}</b><span>buyers posting tenders</span></div>
        <div class="stat"><b class="gt-green">{{ number_format($stats['companies']) }}</b><span>verified companies</span></div>
    </div>
    <p style="margin-top:10px;text-align:right;font-size:14px"><a href="{{ route('site.statistics') }}" style="font-weight:600">All statistics, by province and sector</a></p>
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
            <div><div class="eyebrow">34 provinces</div><h2 style="margin-top:6px;font-size:clamp(28px,3.4vw,36px);font-weight:800">Tenders across the country</h2><p>Choose a province to see its open tenders.</p></div>
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

@if ($sponsored->isNotEmpty())
<section class="wrap section" id="products">
    <div class="section-head">
        <div><div class="eyebrow red">Wholesale products</div><h2>From factories and producers in Afghanistan</h2></div>
        <a href="{{ route('site.products') }}" style="font-weight:600">See all products</a>
    </div>
    <div class="grid-products">
        @foreach ($sponsored as $product)
            @include('site._product', ['product' => $product])
        @endforeach
    </div>
</section>
@endif

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

@extends('layouts.site', ['crumbs' => [['Pricing', null]]])

@php
    $free = $plans->first(fn ($plan) => $plan->isFree());
    $mine = $plans->filter(fn ($plan) => ! $plan->isFree() && $plan->currency === $currency);
    $monthly = $mine->where('interval', 'monthly')->values();
    $yearly = $mine->where('interval', 'yearly')->keyBy('name');
@endphp

@section('hero')
@include('site._pagehead', ['heading' => 'Free to register. Pay only for the full tenders.', 'lead' => 'Every company gets a free profile and the summary of every tender. A paid plan opens the full details, the documents and the instant alerts.', 'crumbs' => [['Pricing', null]]])
@endsection

@section('content')
<div class="wrap" style="padding-top:8px">
    <div class="center">
        <nav class="switch" aria-label="Currency">
            <a href="{{ route('site.pricing') }}" @if ($currency === 'AFN') aria-current="true" @endif>Afghan companies · AFN</a>
            <a href="{{ route('site.pricing', ['currency' => 'USD']) }}" @if ($currency === 'USD') aria-current="true" @endif>NGOs and international · USD</a>
        </nav>
    </div>

    <div class="plans">
        @if ($free)
            <div class="plan">
                <h3>{{ $free->name }}</h3>
                <div class="price">0 <small>{{ $currency }}, always</small></div>
                <div class="year">No card, no end date</div>
                <ul>@foreach ($free->features() as $feature)<li>{{ $feature }}</li>@endforeach</ul>
                @if ($signup)<a class="btn btn-line" href="{{ route('register') }}">Register free</a>@endif
            </div>
        @endif
        @foreach ($monthly as $plan)
            <div class="plan {{ $loop->index === 1 ? 'best' : '' }}">
                <h3>{{ $plan->name }}</h3>
                <div class="price">{{ number_format((float) $plan->price) }} <small>{{ $plan->currency }} a month</small></div>
                <div class="year">@if ($yearly->has($plan->name))or {{ number_format((float) $yearly[$plan->name]->price) }} {{ $plan->currency }} a year@else&nbsp;@endif</div>
                <ul>@foreach ($plan->features() as $feature)<li>{{ $feature }}</li>@endforeach</ul>
                @if ($signup)<a class="btn {{ $loop->index === 1 ? 'btn-green' : 'btn-ink' }}" href="{{ route('register') }}">Start with {{ $plan->name }}</a>@endif
            </div>
        @endforeach
    </div>
    @if ($monthly->isEmpty())
        <div class="empty"><h2>The plans are being set up</h2><p>Registering a company is free today.</p></div>
    @endif

    <section class="block prose">
        <h2>How you pay</h2>
        <p>{{ $facts['payments'] }} After you pay, you upload a screenshot or a photo of the payment on your Subscription page. We check it and switch your plan on, usually the same day.</p>
        <p>Afghan companies pay in afghani (AFN). NGOs and international companies or organisations, from any country, pay in US dollars (USD).</p>
        <p>Payments are not refunded. You can move to a higher plan at any time; any other change is made when your paid period ends.</p>
    </section>

    <section class="block">
        <h2>Questions about prices</h2>
        <div style="margin-top:6px">
            @foreach ($faqSchema as [$question, $answer])
                <details class="qa"><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>
            @endforeach
        </div>
    </section>
    @include('site._cta')
</div>
@endsection

@extends('layouts.site')

@php
    $state = $tender->state();
    $pill = ['soon' => 'soon', 'open' => 'open'][$state] ?? 'done';
    $facts = array_filter([
        'Buyer' => $tender->buyer_name,
        'Kind of buyer' => $tender->buyerTypeLabel(),
        'Sector' => $tender->industry?->name.($tender->category ? ' · '.$tender->category->name : ''),
        'Location' => $tender->placeText().($tender->province && $tender->location ? ', '.$tender->location : ''),
        'Kind of tender' => $tender->typeLabel().' · '.$tender->typeName(),
        'Published' => fmt_date($tender->published_on),
        'Questions until' => fmt_date($tender->questions_until),
        'Budget' => $tender->budgetText().($tender->sizeLabel() ? ' · '.$tender->sizeLabel().' (estimated)' : ''),
        'Submission' => $tender->submissionLabel(),
    ]);
    $locked = array_filter([
        filled($tender->description) ? 'Scope of work and full description' : null,
        filled($tender->eligibility) ? 'Eligibility and required documents' : null,
        filled($tender->submission_details) ? 'Where and how to submit' : null,
        filled($tender->contact) ? 'Contact person of the buyer' : null,
        $tender->documents->isNotEmpty() ? trans_choice('{1} 1 tender document|[2,*] :count tender documents', $tender->documents->count(), ['count' => $tender->documents->count()]) : null,
        'Bid price calculator',
    ]);
    $member = auth('web')->check();
@endphp

@section('hero')
<section class="wrap pagehead">
    <nav class="crumbs" aria-label="You are here"><a href="{{ route('site.home') }}">Home</a>@foreach ($crumbs as [$crumbName, $crumbUrl]) / @if ($crumbUrl)<a href="{{ $crumbUrl }}">{{ $crumbName }}</a>@else<span>{{ $crumbName }}</span>@endif @endforeach</nav>
    <div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:8px;align-items:center"><span class="tag" style="background:rgba(255,255,255,.16);color:#fff">{{ $tender->typeLabel() }} · {{ $tender->number() }}</span><span class="pill {{ $pill }}">{{ $tender->stateLabel() }}</span></div>
    <h1>{{ $tender->title }}</h1>
    <p class="lead">@if ($tender->company?->is_listed)<a href="{{ route('site.companies.show', ['slug' => $tender->company->slug]) }}" style="color:inherit">{{ $tender->buyer_name }}</a>@else{{ $tender->buyer_name }}@endif · {{ $tender->placeText() }}@if ($tender->company_id) · <span style="opacity:.85">Posted by the buyer</span>@endif</p>
</section>
@endsection

@section('content')
<div class="wrap" style="margin-top:-30px;position:relative">
    <div class="cols">
        <article class="main">
            <dl class="facts">
                @foreach ($facts as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>

            <section class="block">
                <h2>Summary</h2>
                <p class="text">{{ $tender->summary }}</p>
                @if ($tender->status === 'awarded' && $tender->awarded_to)
                    <p class="text"><strong>Awarded to:</strong> {{ $tender->awarded_to }}</p>
                @endif
            </section>

            <section class="block">
                <h2>Full details</h2>
                <div class="locked">
                    <ul>
                        @foreach ($locked as $item)
                            <li><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>{{ $item }}</li>
                        @endforeach
                    </ul>
                    <div class="unlock">
                        <div><b>Open the full tender and its documents</b><span>Included in every paid plan. Afghan companies pay in AFN, NGOs and international clients in USD.</span></div>
                        <div style="display:flex;flex-wrap:wrap;gap:10px">
                            @if ($member)
                                <a class="btn btn-white" href="{{ route('tenders.show', ['id' => $tender->id]) }}">Open in my account</a>
                            @else
                                <a class="btn btn-white" href="{{ route('site.pricing') }}">See plans</a>
                                <a class="btn btn-ghost" href="{{ route('login') }}">Sign in</a>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <section class="block">
                <h2>Questions about this tender</h2>
                <div style="margin-top:6px">
                    @foreach ($tenderFaqs as [$question, $answer])
                        <details class="qa"><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>
                    @endforeach
                </div>
            </section>
        </article>

        <aside class="side">
            <div class="deadline {{ $state === 'soon' ? 'soon' : '' }}">
                <div class="eyebrow {{ $state === 'soon' ? 'red' : '' }}">Deadline</div>
                <div class="big t-{{ $pill }}">{{ $tender->timeLeft() }}</div>
                @if ($tender->deadline)
                    <div style="margin-top:8px;font-weight:600">{{ fmt_datetime($tender->deadline) }}</div>
                    <div class="muted" style="font-size:14px">Kabul time</div>
                @endif
                <div class="btns">
                    @if ($member)
                        <a class="btn btn-green" href="{{ route('tenders.show', ['id' => $tender->id]) }}">Open in my account</a>
                    @elseif ($signup)
                        <a class="btn btn-green" href="{{ route('register') }}">Register free to get alerts</a>
                    @endif
                    <a class="btn btn-line" href="https://wa.me/?text={{ urlencode($tender->title.' '.url()->current()) }}" target="_blank" rel="noopener nofollow">Share on WhatsApp</a>
                </div>
            </div>

            @if ($similar->isNotEmpty())
                <div class="sidecard">
                    <h2>Similar tenders</h2>
                    @foreach ($similar as $other)
                        <a class="line" href="{{ route('site.tenders.show', ['slug' => $other->slug]) }}"><b>{{ $other->title }}</b><span>{{ $other->typeLabel() }} · {{ $other->placeText() }} · {{ $other->timeLeft() }}</span></a>
                    @endforeach
                </div>
            @endif

            <div class="sidecard">
                <h2>Something wrong with this tender?</h2>
                <p class="muted" style="font-size:14px">If it looks false, or asks for money to take part, tell us.</p>
                <a class="line" href="{{ route('site.contact') }}"><b>Report this tender</b></a>
            </div>
        </aside>
    </div>
</div>
@endsection

@php
    $place = collect([$company->address, $company->province?->name, $company->country])->filter()->unique()->join(', ');
    $facts = array_filter([
        'Kind' => $company->kindLabel(),
        'Location' => $place,
        'Founded' => $company->founded_year,
        'Size' => $company->size ? \App\Models\Company::SIZES[$company->size] ?? null : null,
        'Licence number' => $company->isVerified() ? $company->license_no : null,
        'On Tenders.af since' => fmt_date($company->created_at),
    ]);
    $schema = [array_filter([
        '@type' => 'Organization', '@id' => url()->current().'#company', 'name' => $company->name, 'url' => $company->website ?: url()->current(),
        'description' => $company->about ? mb_substr(preg_replace('/\s+/', ' ', $company->about), 0, 500) : null,
        'logo' => $company->logo_path ? route('site.companies.logo', ['slug' => $company->slug]) : null,
        'telephone' => $company->phone, 'foundingDate' => $company->founded_year ? (string) $company->founded_year : null,
        'address' => $place ? ['@type' => 'PostalAddress', 'streetAddress' => $company->address, 'addressRegion' => $company->province?->name, 'addressCountry' => $company->country ?: 'Afghanistan'] : null,
    ])];
@endphp
@extends('layouts.site', ['schema' => $schema])

@section('hero')
<section class="wrap pagehead">
    <nav class="crumbs" aria-label="You are here"><a href="{{ route('site.home') }}">Home</a> / <a href="{{ route('site.companies') }}">Companies</a> / <span>{{ $company->name }}</span></nav>
    <div style="margin-top:20px;display:flex;flex-wrap:wrap;gap:20px;align-items:center">
        <div class="logo big">@if ($company->logo_path)<img src="{{ route('site.companies.logo', ['slug' => $company->slug]) }}" alt="Logo of {{ $company->name }}" width="96" height="96">@else{{ \App\Models\User::initialsOf($company->name) }}@endif</div>
        <div style="min-width:0;flex:1">
            <h1 style="margin-top:0">{{ $company->name }}</h1>
            <p class="lead" style="margin-top:8px">{{ collect([$company->industries->pluck('name')->take(3)->join(', '), $company->province?->name ?: $company->country])->filter()->join(' · ') }}</p>
            @if ($company->isVerified())<p style="margin-top:10px"><span class="verified">✓ Verified company</span></p>@endif
        </div>
    </div>
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
                <h2>About {{ $company->name }}</h2>
                <p class="text">{{ $company->about ?: $company->name.' has not written about itself yet.' }}</p>
            </section>

            @if ($company->industries->isNotEmpty())
                <section class="block">
                    <h2>Sectors</h2>
                    <div class="chips">
                        @foreach ($company->industries as $industry)
                            <a href="{{ route('site.sector', ['slug' => $industry->slug]) }}">{{ $industry->name }}</a>
                        @endforeach
                    </div>
                    @if ($company->categories->isNotEmpty())
                        <div class="chips">
                            @foreach ($company->categories as $category)
                                <span>{{ $category->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        </article>

        <aside class="side">
            <div class="sidecard">
                <h2>Contact</h2>
                @if ($company->phone)<a class="line" href="tel:{{ preg_replace('/[^0-9+]/', '', $company->phone) }}"><span>Phone</span><b dir="ltr">{{ $company->phone }}</b></a>@endif
                @if ($company->website)<a class="line" href="{{ $company->website }}" target="_blank" rel="noopener nofollow"><span>Website</span><b>{{ preg_replace('#^https?://#', '', rtrim($company->website, '/')) }}</b></a>@endif
                @if ($place)<div class="line" style="padding:12px 0;border-top:1px solid var(--line)"><span style="font-size:12px;color:var(--muted)">Address</span><b style="display:block;font-weight:600;font-size:15px">{{ $place }}</b></div>@endif
                @unless ($company->phone || $company->website || $place)<p class="muted" style="font-size:14px">No contact details were added yet.</p>@endunless
            </div>
            <div class="sidecard">
                <h2>Is this your company?</h2>
                <p class="muted" style="font-size:14px">Sign in to complete the profile and get verified.</p>
                <a class="line" href="{{ route('login') }}"><b>Sign in</b></a>
            </div>
        </aside>
    </div>
    @include('site._cta')
</div>
@endsection

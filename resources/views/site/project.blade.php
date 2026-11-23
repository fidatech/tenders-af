@php
    $facts = array_filter([
        'Company' => $company->name,
        'Sector' => $project->industry?->name,
        'Location' => collect([$project->location, $project->country])->filter()->join(', '),
        'Period' => $project->period(),
        'Status' => \App\Models\CompanyProject::PROGRESS[$project->progress] ?? null,
        'Client' => $project->publicClient(),
        'Contract value' => $project->publicValue(),
    ]);
@endphp
@extends('layouts.site')

@section('hero')
<section class="wrap pagehead" style="--accent: {{ $company->accent() }}">
    <nav class="crumbs" aria-label="You are here"><a href="{{ route('site.home') }}">Home</a> / <a href="{{ route('site.companies') }}">Companies</a> / <a href="{{ route('site.companies.show', ['slug' => $company->slug]) }}">{{ $company->name }}</a> / <span>Project</span></nav>
    <h1 dir="auto">{{ $project->title }}</h1>
    <p class="lead">A project by <a href="{{ route('site.companies.show', ['slug' => $company->slug]) }}" style="color:inherit">{{ $company->name }}</a></p>
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

            @if ($project->images->isNotEmpty())
                <section class="block">
                    <h2>Pictures</h2>
                    <div class="co-gallery">
                        @foreach ($project->images as $image)
                            <a href="{{ route('site.projects.image', ['id' => $image->id]) }}" target="_blank" rel="noopener"><img src="{{ route('site.projects.image', ['id' => $image->id]) }}" alt="{{ $image->caption ?: $project->title }}" loading="lazy" decoding="async"></a>
                        @endforeach
                    </div>
                </section>
            @endif
            @foreach (['About the project' => $project->description, 'Scope of work' => $project->scope, 'What the company did' => $project->responsibilities] as $heading => $text)
                @if ($text)
                    <section class="block"><h2>{{ $heading }}</h2><p class="text" dir="auto">{{ $text }}</p></section>
                @endif
            @endforeach
        </article>
        <aside class="side">
            <div class="sidecard co-checks">
                <h2>Checked by {{ \App\Support\Branding::name() }}?</h2>
                <ul><li class="{{ $project->isReviewed() ? 'ok' : '' }}"><i aria-hidden="true">{{ $project->isReviewed() ? '✓' : '–' }}</i><span><b>{{ $project->reviewLabel() }}</b><small>{{ $project->reviewMeaning() }}</small></span></li></ul>
            </div>
            <div class="sidecard">
                <h2>{{ $company->name }}</h2>
                <a class="line" href="{{ route('site.companies.show', ['slug' => $company->slug]) }}"><b>See the company page</b><span>Contact, services and more projects</span></a>
            </div>
            @if ($others->isNotEmpty())
                <div class="sidecard">
                    <h2>More projects</h2>
                    @foreach ($others as $other)
                        <a class="line" href="{{ route('site.projects.show', ['slug' => $company->slug, 'project' => $other->slug]) }}"><b dir="auto">{{ $other->title }}</b><span>{{ $other->period() }}</span></a>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>
</div>
@endsection

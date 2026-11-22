@php
    $place = collect([$company->address, $company->province?->name, $company->country])->filter()->unique()->join(', ');
    $types = $company->businessTypeLabels();
    $services = $mini ? $company->serviceList() : [];
    $template = $mini ? $company->template() : 'corporate';
    $accent = $mini ? $company->accent() : '#0B6B3A';
    $facts = array_filter([
        'Kind' => $company->kindLabel(),
        'Business' => $types ? implode(', ', $types) : null,
        'Location' => $place,
        'Founded' => $company->founded_year,
        'Size' => $company->size ? \App\Models\Company::SIZES[$company->size] ?? null : null,
        'Licence number' => $company->isVerified() ? $company->license_no : null,
        'On Tenders.af since' => fmt_date($company->created_at),
    ]);
    $reviewedProjects = $projects->filter->isReviewed()->count();
    // what Tenders.af has checked, each on its own line: never one badge for everything
    $checks = [
        ['Profile claimed by the company', $company->users->isNotEmpty(), $company->users->isNotEmpty() ? 'Someone from the company manages this page.' : 'Nobody from the company manages this page yet.'],
        ['Business licence checked', $company->isVerified(), $company->isVerified() ? 'Checked '.fmt_date($company->verified_at).'.' : 'Not checked by Tenders.af.'],
        ['Project references checked', $reviewedProjects > 0, $reviewedProjects > 0 ? trans_choice('{1} 1 project checked.|[2,*] :count projects checked.', $reviewedProjects, ['count' => $reviewedProjects]) : 'No project checked yet.'],
    ];
    // the order of the parts on the page: the template puts first what matters most for this kind of business
    $order = [
        'corporate' => ['about', 'services', 'projects', 'products', 'tenders', 'sectors'],
        'construction' => ['projects', 'about', 'services', 'sectors', 'products', 'tenders'],
        'industrial' => ['products', 'about', 'projects', 'services', 'sectors', 'tenders'],
        'trade' => ['products', 'about', 'services', 'sectors', 'projects', 'tenders'],
        'services' => ['services', 'about', 'projects', 'sectors', 'products', 'tenders'],
    ][$template];
    // search engines get the telephone only when the company made it public to everyone
    $public = $company->contactFor(null);
    $schema = [array_filter([
        '@type' => 'Organization', '@id' => url()->current().'#company', 'name' => $company->name,
        'url' => isset($public['fields']['website']) ? $public['fields']['website'] : url()->current(),
        'description' => $company->about ? mb_substr(preg_replace('/\s+/', ' ', $company->about), 0, 500) : null,
        'slogan' => $mini ? $company->tagline : null,
        'logo' => $company->logo_path ? route('site.companies.logo', ['slug' => $company->slug]) : null,
        'telephone' => $public['fields']['phone'] ?? null,
        'foundingDate' => $company->founded_year ? (string) $company->founded_year : null,
        'address' => $place ? ['@type' => 'PostalAddress', 'streetAddress' => $company->address, 'addressRegion' => $company->province?->name, 'addressCountry' => $company->country ?: 'Afghanistan'] : null,
    ])];
    $contactLabels = ['phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'website' => 'Website'];
@endphp
@extends('layouts.site', ['schema' => $schema])

@section('hero')
<section class="wrap pagehead co-head tpl-{{ $template }}" style="--accent: {{ $accent }}">
    <nav class="crumbs" aria-label="You are here"><a href="{{ route('site.home') }}">Home</a> / <a href="{{ route('site.companies') }}">Companies</a> / <span>{{ $company->name }}</span></nav>
    @if ($mini && $company->cover_path)
        <div class="co-cover"><img src="{{ route('site.companies.cover', ['slug' => $company->slug]) }}" alt="" loading="eager" decoding="async"></div>
    @endif
    <div class="co-id">
        <div class="logo big">@if ($company->logo_path)<img src="{{ route('site.companies.logo', ['slug' => $company->slug]) }}" alt="Logo of {{ $company->name }}" width="96" height="96">@else{{ \App\Models\User::initialsOf($company->name) }}@endif</div>
        <div class="co-name">
            <h1>{{ $company->name }}</h1>
            @if ($mini && $company->tagline)<p class="co-tagline" dir="auto">{{ $company->tagline }}</p>@endif
            <p class="lead">{{ collect([$company->industries->pluck('name')->take(3)->join(', '), $company->province?->name ?: $company->country])->filter()->join(' · ') }}</p>
            <div class="co-chips">
                @if ($company->isVerified())<span class="verified">✓ Business licence checked</span>@endif
                @foreach ($types as $label)<span class="co-type">{{ $label }}</span>@endforeach
            </div>
        </div>
        <div class="co-cta">
            <a class="btn btn-green btn-sm" href="#contact">Contact</a>
            @if (module_on('company_pdf'))<a class="btn btn-ghost btn-sm" href="{{ route('site.companies.pdf', ['slug' => $company->slug]) }}" rel="nofollow">Profile PDF</a>@endif
        </div>
    </div>
</section>
@endsection

@section('content')
<div class="wrap co-body tpl-{{ $template }}" style="--accent: {{ $accent }}">
    <div class="cols">
        <article class="main">
            <dl class="facts">
                @foreach ($facts as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>

            @foreach ($order as $part)
                @if ($part === 'about')
                    <section class="block" id="about">
                        <h2>About {{ $company->name }}</h2>
                        <p class="text" dir="auto">{{ $company->about ?: $company->name.' has not written about itself yet.' }}</p>
                    </section>
                @elseif ($part === 'services' && $services)
                    <section class="block" id="services">
                        <h2>Services</h2>
                        <div class="co-services">
                            @foreach ($services as $service)
                                <div class="co-service"><b dir="auto">{{ $service['name'] }}</b>@if ($service['text'])<p dir="auto">{{ $service['text'] }}</p>@endif</div>
                            @endforeach
                        </div>
                    </section>
                @elseif ($part === 'projects' && $projects->isNotEmpty())
                    <section class="block" id="projects">
                        <h2>Projects</h2>
                        <div class="co-projects">
                            @foreach ($projects as $project)
                                @include('site._project', ['project' => $project, 'company' => $company])
                            @endforeach
                        </div>
                    </section>
                @elseif ($part === 'products' && $products->isNotEmpty())
                    <section class="block" id="products">
                        <h2>Products for wholesale</h2>
                        <div class="grid-products">
                            @foreach ($products as $product)
                                @include('site._product', ['product' => $product])
                            @endforeach
                        </div>
                    </section>
                @elseif ($part === 'tenders' && $posted->isNotEmpty())
                    <section class="block" id="tenders">
                        <h2>Tenders from {{ $company->name }}</h2>
                        <div class="grid-tenders">
                            @foreach ($posted as $tender)
                                @include('site._tender', ['tender' => $tender])
                            @endforeach
                        </div>
                    </section>
                @elseif ($part === 'sectors' && $company->industries->isNotEmpty())
                    <section class="block" id="sectors">
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
            @endforeach
        </article>

        <aside class="side">
            <div class="sidecard" id="contact">
                <h2>Contact</h2>
                @if ($contact['state'] === 'open')
                    @forelse ($contact['fields'] as $field => $value)
                        @php
                            $href = match ($field) {
                                'phone' => 'tel:'.preg_replace('/[^0-9+]/', '', $value),
                                'whatsapp' => 'https://wa.me/'.preg_replace('/\D+/', '', $value),
                                'email' => 'mailto:'.$value,
                                default => $value,
                            };
                        @endphp
                        <a class="line" href="{{ $href }}" @if (in_array($field, ['website', 'whatsapp'], true)) target="_blank" rel="noopener nofollow" @endif><span>{{ $contactLabels[$field] }}</span><b dir="ltr">{{ $field === 'website' ? preg_replace('#^https?://#', '', rtrim($value, '/')) : $value }}</b></a>
                    @empty
                        <p class="muted" style="font-size:14px">No contact details are shown on this page.</p>
                    @endforelse
                @elseif ($contact['state'] === 'locked')
                    <p class="muted" style="font-size:14px">{{ $company->name }} shows its contact details to subscribers on a paid plan.</p>
                    <a class="line" href="{{ auth('web')->check() ? route('billing.show') : route('login') }}"><b>{{ auth('web')->check() ? 'See the plans' : 'Sign in to see them' }}</b></a>
                @else
                    <p class="muted" style="font-size:14px">{{ $company->name }} receives enquiries through {{ \App\Support\Branding::name() }} only.</p>
                    <a class="line" href="{{ route('site.contact', ['about' => $company->slug]) }}"><b>Send an enquiry</b></a>
                @endif
                @if ($place)<div class="line" style="padding:12px 0;border-top:1px solid var(--line)"><span style="font-size:12px;color:var(--muted)">Address</span><b style="display:block;font-weight:600;font-size:15px">{{ $place }}</b></div>@endif
            </div>

            <div class="sidecard co-checks">
                <h2>What {{ \App\Support\Branding::name() }} checked</h2>
                <ul>
                    @foreach ($checks as [$label, $ok, $text])
                        <li class="{{ $ok ? 'ok' : '' }}"><i aria-hidden="true">{{ $ok ? '✓' : '–' }}</i><span><b>{{ $label }}</b><small>{{ $text }}</small></span></li>
                    @endforeach
                </ul>
                <p class="muted" style="font-size:12.5px;margin-top:8px">A complete profile is not proof of quality. Check a supplier as you would anywhere else.</p>
            </div>

            @unless ($company->users->isNotEmpty() && auth('web')->user()?->company_id === $company->id)
                <div class="sidecard">
                    <h2>Is this your company?</h2>
                    <p class="muted" style="font-size:14px">Sign in to complete the profile and get verified.</p>
                    <a class="line" href="{{ route('login') }}"><b>Sign in</b></a>
                </div>
            @endunless
        </aside>
    </div>

    @if ($similar->isNotEmpty())
        <section class="block" id="similar">
            <h2>Similar companies</h2>
            <div class="grid-companies">
                @foreach ($similar as $other)
                    @include('site._company', ['company' => $other])
                @endforeach
            </div>
        </section>
    @endif
    @include('site._cta')
</div>
@endsection

@php
    $name = config('app.name');
    $pageTitle = $title.' | '.$name;
    $canonical = url()->current();
    $image = asset('img/og.png');
    $menu = ['site.tenders' => ['site.tenders', 'site.sector', 'site.province', 'site.tenders.show'], 'site.companies' => ['site.companies', 'site.companies.show'], 'site.sectors' => ['site.sectors'], 'site.provinces' => ['site.provinces'], 'site.pricing' => ['site.pricing']];
    $signedIn = auth('web')->check();

    // structured data: who runs the site and what it is. No ratings or reviews: there are none to quote.
    $graph = [
        ['@type' => 'Organization', '@id' => url('/').'#organization', 'name' => $name, 'url' => url('/'), 'logo' => $image, 'areaServed' => ['@type' => 'Country', 'name' => 'Afghanistan'],
            'parentOrganization' => ['@type' => 'Organization', 'name' => 'Fida Technologies', 'url' => 'https://fida.af']],
        ['@type' => 'WebSite', '@id' => url('/').'#website', 'url' => url('/'), 'name' => $name, 'description' => config('site.description'), 'inLanguage' => 'en', 'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('site.tenders').'?q={search_term_string}'], 'query-input' => 'required name=search_term_string']],
    ];
    if ($crumbs) {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => $name, 'item' => url('/')]];
        foreach ($crumbs as $index => [$crumbName, $crumbUrl]) {
            $items[] = ['@type' => 'ListItem', 'position' => $index + 2, 'name' => $crumbName, 'item' => $crumbUrl ?: $canonical];
        }
        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
    if (! empty($faqSchema)) {
        $graph[] = ['@type' => 'FAQPage', '@id' => $canonical.'#faq', 'mainEntity' => array_map(fn ($faq) => [
            '@type' => 'Question', 'name' => $faq[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
        ], $faqSchema)];
    }
    foreach ($schema ?? [] as $extra) {
        $graph[] = $extra;
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="{{ $noindex ? 'noindex, follow' : 'index, follow, max-snippet:-1, max-image-preview:large' }}">
<meta name="theme-color" content="#0A2A1A">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $name }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $name }}: {{ config('site.tagline') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">
<link rel="icon" href="{{ asset_v('img/favicon.svg') }}" type="image/svg+xml">
<link rel="alternate" type="text/plain" href="{{ url('/llms.txt') }}" title="{{ $name }} for AI assistants">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset_v('css/site.css') }}">
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</head>
<body>
<a class="sr" href="#content">Skip to the content</a>

<div class="dark">
    <div class="flagline"></div>
    <header class="wrap top">
        <a class="brand" href="{{ route('site.home') }}" aria-label="{{ $name }}, home"><i aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"><path d="M4 17 9 11l4 3 7-8"/><path d="M15 6h5v5"/></svg></i><span>Tenders<b>.af</b></span></a>
        <nav class="nav" aria-label="Main">
            @foreach ($menu as $route => $patterns)
                <a href="{{ route($route) }}" @if (request()->routeIs(...$patterns)) aria-current="page" @endif>{{ \App\Http\Controllers\SiteController::PAGES[$route][1] }}</a>
            @endforeach
        </nav>
        <div class="top-actions">
            @if ($signedIn)
                <a class="btn btn-red btn-sm" href="{{ route('dashboard') }}">My account</a>
            @else
                <a class="plain" href="{{ route('login') }}">Sign in</a>
                @if ($signup)<a class="btn btn-red btn-sm" href="{{ route('register') }}">Register free</a>@endif
            @endif
        </div>
    </header>
    @yield('hero')
</div>

<main id="content">
    @yield('content')
</main>

<footer class="foot">
    <div class="flagline" style="height:3px"></div>
    <div class="wrap">
        <div class="in">
            <div class="about">
                <a class="brand" href="{{ route('site.home') }}"><span>Tenders<b>.af</b></span></a>
                <p>{{ config('site.tagline') }} With a free directory of verified companies.</p>
            </div>
            <nav aria-label="Tenders">
                <h2>Tenders</h2>
                <a href="{{ route('site.tenders') }}">All open tenders</a>
                <a href="{{ route('site.sectors') }}">By sector</a>
                <a href="{{ route('site.provinces') }}">By province</a>
                <a href="{{ route('site.contact') }}">Post a tender</a>
            </nav>
            <nav aria-label="Companies">
                <h2>Companies</h2>
                <a href="{{ route('site.companies') }}">Company directory</a>
                @if ($signup)<a href="{{ route('register') }}">Register your company</a>@endif
                <a href="{{ route('login') }}">Sign in</a>
                <a href="{{ route('site.pricing') }}">Pricing</a>
            </nav>
            <nav aria-label="About">
                <h2>{{ $name }}</h2>
                <a href="{{ route('site.how') }}">How it works</a>
                <a href="{{ route('site.faq') }}">Questions</a>
                <a href="{{ route('site.terms') }}">Terms</a>
                <a href="{{ route('site.privacy') }}">Privacy</a>
            </nav>
            <div class="contact">
                <h2>Contact</h2>
                @if (platform('support_whatsapp'))<span dir="ltr">{{ platform('support_whatsapp') }}</span>@endif
                @if (platform('support_email'))<a href="mailto:{{ platform('support_email') }}">{{ platform('support_email') }}</a>@endif
                <a href="{{ route('site.contact') }}">Write to us</a>
                <span>Kabul, Afghanistan</span>
            </div>
        </div>
        <div class="bottom">
            <span>© {{ date('Y') }} {{ $name }} · A <a href="https://fida.af" rel="noopener">Fida Technologies</a> product</span>
            <span>Province boundaries: Natural Earth</span>
        </div>
    </div>
</footer>
</body>
</html>

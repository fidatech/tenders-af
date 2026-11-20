@php
    $name = config('app.name');
    $pageTitle = $title.' | '.$name;
    $canonical = url()->current();
    $image = asset('img/og.png');
    $menu = ['site.tenders' => ['site.tenders', 'site.sector', 'site.province', 'site.tenders.show'], 'site.companies' => ['site.companies', 'site.companies.show'], 'site.sectors' => ['site.sectors'], 'site.provinces' => ['site.provinces'], 'site.pricing' => ['site.pricing']];
    $productsOn = \App\Support\Platform::bool('products_enabled');
    $signedIn = auth('web')->check();
    // the bell of a signed-in company: its notifications and tender alerts
    $bellUnread = $signedIn ? \App\Models\Notice::where('user_id', auth('web')->id())->whereNull('read_at')->count() : 0;
    $bellLatest = $signedIn ? \App\Models\Notice::where('user_id', auth('web')->id())->orderByDesc('id')->limit(6)->get() : collect();

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
@if (platform('google_site_verification'))
<meta name="google-site-verification" content="{{ platform('google_site_verification') }}">
@endif
@if (platform('bing_site_verification'))
<meta name="msvalidate.01" content="{{ platform('bing_site_verification') }}">
@endif
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
@include('partials.head-font')
<link rel="stylesheet" href="{{ asset_v('vendor/select2/select2.min.css') }}" media="print" onload="this.media='all'">
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
            @if ($productsOn)<a href="{{ route('site.products') }}" @if (request()->routeIs('site.products', 'site.products.show')) aria-current="page" @endif>Products</a>@endif
        </nav>
        <div class="top-actions">
            @if ($signedIn)
                <details class="bell">
                    <summary aria-label="Notifications{{ $bellUnread ? ', '.$bellUnread.' new' : '' }}"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"><path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7M10 20a2 2 0 0 0 4 0"/></svg>@if ($bellUnread)<b>{{ $bellUnread > 99 ? '99+' : $bellUnread }}</b>@endif</summary>
                    <div class="bell-menu">
                        <div class="bell-head"><strong>Notifications</strong>@if ($bellUnread)<form method="POST" action="{{ route('notices.read') }}">@csrf<button type="submit">Mark all as read</button></form>@endif</div>
                        <div class="bell-list">
                            @forelse ($bellLatest as $notice)
                                <a href="{{ route('notices.open', ['id' => $notice->id]) }}" class="{{ $notice->read_at ? '' : 'new' }}"><i aria-hidden="true"></i><span><b>{{ $notice->title }}</b>@if ($notice->body)<small>{{ $notice->body }}</small>@endif<em>{{ $notice->created_at?->diffForHumans() }}</em></span></a>
                            @empty
                                <p class="bell-empty">No notifications yet. Tenders that match your sectors will appear here.</p>
                            @endforelse
                        </div>
                        <a class="bell-all" href="{{ route('notices.index') }}">All notifications</a>
                    </div>
                </details>
                <a class="btn btn-red btn-sm" href="{{ route('dashboard') }}">My account</a>
            @else
                <a class="plain" href="{{ route('login') }}">Sign in</a>
                @if ($signup)<a class="plain" href="{{ route('register', ['as' => 'buyer']) }}">Post a tender</a><a class="btn btn-red btn-sm" href="{{ route('register') }}">Register free</a>@endif
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
        <div class="foot-grid">
            <div class="foot-brand">
                <a class="brand" href="{{ route('site.home') }}"><i aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"><path d="M4 17 9 11l4 3 7-8"/><path d="M15 6h5v5"/></svg></i><span>Tenders<b>.af</b></span></a>
                <p>{{ config('site.tagline') }} With a free directory of verified companies.</p>
                <div class="foot-cta">
                    @if ($signedIn)
                        <a class="btn btn-green btn-sm" href="{{ route('dashboard') }}">My account</a>
                    @else
                        @if ($signup)<a class="btn btn-green btn-sm" href="{{ route('register') }}">Register free</a>@endif
                        <a class="foot-ghost" href="{{ route('login') }}">Sign in</a>
                    @endif
                </div>
            </div>
            <nav class="foot-col" aria-label="Tenders">
                <h2>Tenders</h2>
                <ul>
                    <li><a href="{{ route('site.tenders') }}">All open tenders</a></li>
                    <li><a href="{{ route('site.sectors') }}">By sector</a></li>
                    <li><a href="{{ route('site.provinces') }}">By province</a></li>
                    <li><a href="{{ $signup ? route('register', ['as' => 'buyer']) : route('site.contact') }}">Post a tender</a></li>
                </ul>
            </nav>
            <nav class="foot-col" aria-label="Companies">
                <h2>Companies</h2>
                <ul>
                    <li><a href="{{ route('site.companies') }}">Company directory</a></li>
                    @if ($productsOn)<li><a href="{{ route('site.products') }}">Wholesale products</a></li>@endif
                    <li><a href="{{ route('site.statistics') }}">Statistics</a></li>
                    <li><a href="{{ route('site.pricing') }}">Pricing</a></li>
                </ul>
            </nav>
            <nav class="foot-col" aria-label="Help">
                <h2>Help</h2>
                <ul>
                    <li><a href="{{ route('site.how') }}">How it works</a></li>
                    <li><a href="{{ route('site.faq') }}">Questions</a></li>
                    <li><a href="{{ route('site.contact') }}">Write to us</a></li>
                </ul>
            </nav>
            <div class="foot-col foot-contact">
                <h2>Contact</h2>
                <ul>
                    @if (platform('support_email'))<li><a href="mailto:{{ platform('support_email') }}"><span class="ci"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>{{ platform('support_email') }}</a></li>@endif
                    @if (platform('support_whatsapp'))<li><a href="https://wa.me/{{ preg_replace('/\D+/', '', platform('support_whatsapp')) }}" rel="noopener"><span class="ci"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-4.2A8.5 8.5 0 1 1 20 11.5Z"/><path d="M9 9.5c.3 1.7 1.8 3.4 3.6 4.2l1.2-1.1 2 .8-.4 1.7c-3.6.4-7.6-3.3-7.4-6.9l1.7-.4.8 2Z"/></svg></span><span dir="ltr">{{ platform('support_whatsapp') }}</span></a></li>@endif
                    <li><a href="{{ route('site.contact') }}"><span class="ci"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.5 7.2L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg></span>Send us a message</a></li>
                    <li><span class="ci"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg></span>Kabul, Afghanistan</li>
                </ul>
            </div>
        </div>
        <div class="foot-bottom">
            <span>© {{ date('Y') }} {{ $name }} · A <a href="https://fida.af" rel="noopener">Fida Technologies</a> product</span>
            <nav aria-label="Legal"><a href="{{ route('site.terms') }}">Terms</a><a href="{{ route('site.privacy') }}">Privacy</a><a href="{{ route('site.sitemap') }}">Sitemap</a></nav>
        </div>
    </div>
</footer>
{{-- every pick list of the website is a Select2 list --}}
<script src="{{ asset_v('vendor/jquery/jquery.min.js') }}" defer></script>
<script src="{{ asset_v('vendor/select2/select2.min.js') }}" defer></script>
<script src="{{ asset_v('js/select2-init.js') }}" defer></script>
<script>
// the bell closes when something else is clicked, or with Escape
(function () {
    var bell = document.querySelector('details.bell');
    if (!bell) return;
    document.addEventListener('click', function (e) { if (bell.open && !bell.contains(e.target)) bell.open = false; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') bell.open = false; });
})();
</script>
@stack('scripts')
</body>
</html>

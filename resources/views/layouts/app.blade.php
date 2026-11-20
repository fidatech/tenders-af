@php
    // The panel of companies and buyers sits inside the website: the site's header and colours,
    // the account menu on the left, the page on the right. (The admin panel keeps its own look.)
    $user = auth()->user();
    $company = company();
    $trialLeft = $company?->status === \App\Models\Company::TRIAL && $company->isUsable() ? $company->daysLeft() : null;
    $unread = \App\Models\Notice::where('user_id', $user->id)->whereNull('read_at')->count();
    $latest = \App\Models\Notice::where('user_id', $user->id)->orderByDesc('id')->limit(6)->get();
    $productsOn = \App\Support\Platform::bool('products_enabled');
    $siteNav = array_filter([
        [__('Tenders'), route('site.tenders')],
        [__('Companies'), route('site.companies')],
        $productsOn ? [__('Products'), route('site.products')] : null,
        [__('Sectors'), route('site.sectors')],
        [__('Provinces'), route('site.provinces')],
        [__('Pricing'), route('site.pricing')],
    ]);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" class="portal" data-bs-theme="light" data-primary="green" data-icons="{{ asset_v('icons.svg') }}" data-home="{{ route('dashboard') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F6F8F5">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? '' }}{{ isset($title) ? ' | ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset_v('img/favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset_v(text_dir() === 'rtl' ? 'css/app.rtl.css' : 'css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_v('vendor/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/select2-fida.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/portal.css') }}">
</head>
<body>
<a class="pt-skip" href="#content">{{ __('Skip to the page') }}</a>

<header class="pt-top" id="ptTop">
    <div class="pt-wrap pt-top-in">
        <a class="pt-brand" href="{{ route('site.home') }}" aria-label="{{ config('app.name') }}, {{ __('home') }}">
            <i aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"><path d="M4 17 9 11l4 3 7-8"/><path d="M15 6h5v5"/></svg></i>
            <span>Tenders<b>.af</b></span>
        </a>
        <nav class="pt-nav" id="ptNav" aria-label="{{ __('Website') }}">
            @foreach ($siteNav as [$label, $url])
                <a href="{{ $url }}">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="pt-actions">
            @if ($company?->is_buyer)
                <a class="btn btn-primary btn-sm pt-post" href="{{ route('posted.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Post a tender') }}</a>
            @endif

            <div class="dropdown">
                <button class="pt-icon" data-bs-toggle="dropdown" data-bs-auto-close="outside" type="button" aria-expanded="false" aria-label="{{ __('Notifications') }}">
                    <x-icon name="bell" />
                    @if ($unread)<span class="pt-dot">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0 pt-menu" style="width:340px;max-width:92vw">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <strong>{{ __('Notifications') }}</strong>
                        @if ($unread)<button type="submit" form="noticesReadForm" class="btn btn-sm btn-link p-0 text-decoration-none">{{ __('Mark all as read') }}</button>@endif
                    </div>
                    <div style="max-height:360px;overflow-y:auto">
                        @forelse ($latest as $notice)
                            <a href="{{ route('notices.open', ['id' => $notice->id]) }}" class="d-flex gap-2 align-items-start px-3 py-2 border-bottom text-decoration-none text-reset">
                                <span class="rounded-circle flex-shrink-0 mt-2" style="width:8px;height:8px;background:{{ $notice->read_at ? 'transparent' : 'var(--primary)' }}"></span>
                                <span class="min-w-0">
                                    <span class="d-block small {{ $notice->read_at ? '' : 'fw-semibold' }}">{{ $notice->title }}</span>
                                    @if ($notice->body)<span class="d-block small text-muted text-truncate">{{ $notice->body }}</span>@endif
                                    <span class="d-block text-muted" style="font-size:11px">{{ $notice->created_at?->diffForHumans() }}</span>
                                </span>
                            </a>
                        @empty
                            <div class="text-center text-muted small py-4">{{ __('No notifications yet') }}</div>
                        @endforelse
                    </div>
                    <a class="d-block text-center py-2 text-decoration-none" href="{{ route('notices.index') }}">{{ __('All notifications') }}</a>
                </div>
            </div>

            <div class="dropdown">
                <button class="pt-user" data-bs-toggle="dropdown" type="button" aria-expanded="false">
                    <span class="pt-avatar">{{ $user->initials() }}</span>
                    <span class="pt-user-meta"><strong>{{ $user->name }}</strong><small>{{ $company?->name }}</small></span>
                    <x-icon name="chevron-down" class="pt-caret" />
                </button>
                <ul class="dropdown-menu dropdown-menu-end pt-menu">
                    <li class="px-3 py-2"><strong class="d-block">{{ $user->name }}</strong><small class="text-muted">{{ $user->email }}</small></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('dashboard') }}"><x-icon name="gauge" /> {{ __('Dashboard') }}</a></li>
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><x-icon name="user" /> {{ __('My Profile') }}</a></li>
                    <li><a class="dropdown-item" href="{{ route('profile.password') }}"><x-icon name="key" /> {{ __('Change Password') }}</a></li>
                    @if ($company?->is_listed)
                        <li><a class="dropdown-item" href="{{ route('site.companies.show', ['slug' => $company->slug]) }}" target="_blank" rel="noopener"><x-icon name="eye" /> {{ __('Our public page') }}</a></li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item text-danger" type="submit" form="logoutForm"><x-icon name="right-from-bracket" /> {{ __('app.nav.sign_out') }}</button></li>
                </ul>
            </div>

            <button class="pt-icon pt-burger" type="button" aria-controls="ptNav" aria-expanded="false" aria-label="{{ __('Menu') }}" data-pt-burger><x-icon name="bars" /></button>
        </div>
    </div>
</header>

<div class="pt-band">
    <div class="pt-wrap pt-band-in">
        <div class="min-w-0">
            <div class="pt-eyebrow">{{ __('Your account') }}</div>
            <div class="pt-company">{{ $company?->name }}</div>
        </div>
        <div class="pt-badges">
            @if ($company)
                <span class="pt-badge">{{ $company->kindLabel() }}</span>
                @if ($company->isVerified())<span class="pt-badge ok"><x-icon name="circle-check" /> {{ __('Verified') }}</span>@else<a class="pt-badge warn" href="{{ route('verification.show') }}"><x-icon name="shield-halved" /> {{ __('Not verified') }}</a>@endif
                <a class="pt-badge" href="{{ route('billing.show') }}"><x-icon name="credit-card" /> {{ $company->plan?->name ?? __('Free') }}</a>
            @endif
        </div>
    </div>
</div>

<div class="pt-wrap pt-body">
    <aside class="pt-side" aria-label="{{ __('Your account') }}">
        <nav class="pt-side-card">
            @foreach (\App\Support\Menu::for($user) as $item)
                @if (isset($item['heading']))
                    <div class="pt-head">{{ __($item['heading']) }}</div>
                @else
                    @foreach (isset($item['children']) ? $item['children'] : [$item] as $link)
                        <a class="pt-link {{ ! empty($link['current']) ? 'active' : '' }}" href="{{ \App\Support\Menu::url($link) }}" @if (! empty($link['current'])) aria-current="page" @endif>
                            <x-icon :name="$link['icon'] ?? ($item['icon'] ?? 'circle')" />
                            <span>{{ __($link['label']) }}</span>
                            @if (($waiting = \App\Support\Menu::badge($link)) > 0)<em>{{ $waiting > 99 ? '99+' : $waiting }}</em>@endif
                        </a>
                    @endforeach
                @endif
            @endforeach
            <button class="pt-link pt-out" type="submit" form="logoutForm"><x-icon name="right-from-bracket" /><span>{{ __('app.nav.sign_out') }}</span></button>
        </nav>
    </aside>

    <main class="pt-main" id="content">
        @if ($trialLeft !== null && ! request()->routeIs('billing.*'))
            <a href="{{ route('billing.show') }}" class="alert alert-warning d-flex align-items-center gap-2 text-decoration-none py-2">
                <x-icon name="hourglass-half" />
                <span class="flex-grow-1">{{ $trialLeft > 0 ? trans_choice('{1} Your free trial ends tomorrow.|[2,*] :count days of your free trial are left.', $trialLeft, ['count' => $trialLeft]) : __('Your free trial ends today.') }} <strong>{{ __('Subscribe') }}</strong></span>
                <x-icon name="chevron-right" />
            </a>
        @endif
        @yield('content')
    </main>
</div>

<footer class="pt-foot">
    <div class="pt-wrap pt-foot-in">
        <span>© {{ date('Y') }} {{ config('app.name') }} · {{ __('app.by') }} <a href="https://fida.af" target="_blank" rel="noopener">Fida Technologies</a></span>
        <nav><a href="{{ route('site.how') }}">{{ __('How it works') }}</a><a href="{{ route('site.faq') }}">{{ __('Questions') }}</a><a href="{{ route('site.terms') }}">{{ __('Terms') }}</a><a href="{{ route('site.privacy') }}">{{ __('Privacy') }}</a><a href="{{ route('site.contact') }}">{{ __('Help') }}</a></nav>
    </div>
</footer>

<form id="logoutForm" method="POST" action="{{ route('logout') }}" hidden
      data-confirm="{{ __('app.nav.sign_out_question') }}" data-confirm-text="{{ __('app.nav.sign_out_text') }}"
      data-confirm-ok="{{ __('app.nav.sign_out') }}" data-confirm-style="primary">@csrf</form>
<form id="noticesReadForm" method="POST" action="{{ route('notices.read') }}" hidden>@csrf</form>

@include('partials.flash')
@include('partials.scripts')
<script>
// the website's links fold into a menu on a phone
(function () {
    var burger = document.querySelector('[data-pt-burger]'), top = document.getElementById('ptTop');
    if (!burger) return;
    burger.addEventListener('click', function () {
        var open = top.classList.toggle('nav-open');
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    // the active account link stays in view in the scrolling row on a phone
    var active = document.querySelector('.pt-link.active');
    if (active && window.matchMedia('(max-width: 991.98px)').matches) active.scrollIntoView({ block: 'nearest', inline: 'center' });
})();
</script>
</body>
</html>

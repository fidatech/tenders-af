@php
    $admin = auth('admin')->user();
    $can = fn (string $route, bool $read = true) => \App\Support\AdminAccess::allows($admin, $route, $read);
    $showNotices = $can('admin.notices.index');
    $waitingVerify = \App\Models\Company::where('verify_status', 'pending')->count();
    $waitingTenders = \App\Models\Tender::where('status', 'pending')->count();
    $productsOn = module_on('products');
    $productsInMenu = $productsOn || \App\Models\Product::exists();
    $waitingProducts = $productsOn ? \App\Models\Product::where('status', 'pending')->count() + \App\Models\ProductPromotion::where('status', 'pending')->count() : 0;
    $waitingPay = \App\Models\Subscription::waiting()->count();
    // the bell: what waits for an administrator, then the newest notifications
    $adminUnread ??= \App\Models\AdminNotice::where('admin_id', $admin->id)->whereNull('read_at')->count();
    $adminLatest = \App\Models\AdminNotice::where('admin_id', $admin->id)->orderByDesc('id')->limit(8)->get();
    $waitingList = array_filter([
        [__('Licences to check'), 'shield-halved', route('admin.verification.index'), $waitingVerify],
        [__('Tenders to approve'), 'gavel', route('admin.tenders.index', ['status' => 'pending']), $waitingTenders],
        [__('Payments to confirm'), 'receipt', route('admin.subscriptions.index'), $waitingPay],
        $productsOn ? [__('Products and sponsoring'), 'boxes-stacked', route('admin.products.index', ['status' => 'pending']), $waitingProducts] : null,
    ], fn ($row) => $row && $row[3] > 0);
    // the number on the bell: everything that waits for an administrator, plus unread news that is not
    // one of those waiting things already (a new company, a message), so nothing is counted twice
    $bellCount = array_sum(array_column($waitingList, 3))
        + \App\Models\AdminNotice::where('admin_id', $admin->id)->whereNull('read_at')->whereIn('kind', ['company', 'message', 'info'])->count();
    // [label, icon, route, active pattern, badge]
    $menu = [
        'Workspace' => [
            ['Dashboard', 'gauge-high', 'admin.dashboard', 'admin.dashboard', 0],
            ['Messages', 'envelope', 'admin.messages.index', 'admin.messages.*', $can('admin.messages.index') ? \App\Models\DirectMessage::where('from_admin', false)->whereNull('read_at')->count() : 0],
            ['Notifications', 'bell', 'admin.notices.index', 'admin.notices.*', $adminUnread],
        ],
        'Procurement' => [
            ['Tenders', 'gavel', 'admin.tenders.index', 'admin.tenders.*', $waitingTenders],
            // products: while the module is on, or while there are products to look after
            $productsInMenu ? ['Products', 'boxes-stacked', 'admin.products.index', 'admin.products.*', $waitingProducts] : null,
            ['Sectors', 'layer-group', 'admin.industries.index', 'admin.industries.*', 0],
            ['Categories', 'tags', 'admin.categories.index', 'admin.categories.*', 0],
        ],
        'Companies and bidders' => [
            ['Companies', 'building', 'admin.companies.index', 'admin.companies.*', 0],
            ['Verification', 'shield-halved', 'admin.verification.index', 'admin.verification.*', $waitingVerify],
        ],
        'Office and finance' => [
            ['Office register', 'receipt', 'admin.office.index', 'admin.office.*', 0],
            ['Office approvals', 'check-double', 'admin.office.approvals', 'admin.office.approvals', $can('admin.office.approvals') ? \App\Models\OfficeEntry::where('status', 'pending')->count() : 0],
            ['Subscriptions', 'receipt', 'admin.subscriptions.index', 'admin.subscriptions.*', $waitingPay],
            ['Plans', 'coins', 'admin.plans.index', 'admin.plans.*', 0],
            ['Payment methods', 'building-columns', 'admin.accounts.index', 'admin.accounts.*', 0],
            ['Expenses', 'money-bill-wave', 'admin.expenses.index', 'admin.expenses.*', 0],
            ['Reports', 'chart-line', 'admin.reports.index', 'admin.reports.*', 0],
        ],
        'Platform' => [
            ['Module Management', 'puzzle-piece', 'admin.modules.index', 'admin.modules.*', 0],
            ['Branding & Logo', 'image', 'admin.branding.edit', 'admin.branding.*', 0],
            ['Settings', 'gear', 'admin.settings.edit', 'admin.settings.*', 0],
        ],
        'Administration' => [
            ['Employees and roles', 'user-shield', 'admin.admins.index', 'admin.admins.*', 0],
            ['Activity log', 'clock-rotate-left', 'admin.logs.index', 'admin.logs.*', 0],
            ['My account', 'user', 'admin.profile.edit', 'admin.profile.*', 0],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" data-bs-theme="light" data-sidebar="light" data-layout="vertical" data-primary="green" data-width="fluid" data-icons="{{ asset_v('icons.svg') }}" data-home="{{ route($admin->homeRoute()) }}">
<head>
    @include('partials.head')
</head>
<body>
<div class="app-wrapper">
    <aside class="app-sidebar" id="appSidebar" aria-label="{{ __('app.nav.menu') }}">
        <div class="sidebar-brand">
            <a href="{{ route($admin->homeRoute()) }}" class="brand-link"><x-brand-logo /><span class="brand-text">{{ \App\Support\Branding::name() }}</span></a>
        </div>
        <div class="sidebar-scroll" id="sidebarScroll">
            <div class="drawer-profile">
                <span class="avatar avatar-md avatar-soft-primary">{{ $admin->initials() }}</span>
                <div class="min-w-0"><strong>{{ $admin->name }}</strong><small>{{ $admin->email }}</small></div>
            </div>
            <div class="drawer-quick">
                <button type="button" data-theme-toggle><x-icon name="moon" /><span>{{ __('app.nav.theme') }}</span></button>
                <button type="submit" form="logoutForm"><x-icon name="right-from-bracket" /><span>{{ __('app.nav.sign_out') }}</span></button>
            </div>
            <ul class="sidebar-menu" id="sidebarMenu">
                @foreach ($menu as $heading => $entries)
                    @php($entries = array_filter($entries, fn ($entry) => $entry && $can($entry[2])))
                    @continue(! $entries)
                    <li class="menu-heading"><span>{{ __($heading) }}</span></li>
                    @foreach ($entries as [$label, $icon, $route, $pattern, $waitingCount])
                        @php($current = request()->routeIs($pattern) && ! ($route === 'admin.office.index' && request()->routeIs('admin.office.approvals')))
                        <li class="menu-item {{ $current ? 'active' : '' }}">
                            <a class="menu-link" href="{{ route($route) }}"><span class="menu-icon"><x-icon :name="$icon" /></span><span class="menu-text">{{ __($label) }}</span>@if ($waitingCount)<span class="badge bg-danger rounded-pill ms-auto">{{ $waitingCount }}</span>@endif</a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
        <header class="app-topbar" id="appTopbar">
            <button class="btn-icon btn-ghost topbar-toggle" id="sidebarToggle" type="button" aria-label="{{ __('app.nav.toggle_sidebar') }}"><x-icon name="bars-staggered" /></button>
            <button class="appbar-back" type="button" id="appBack" aria-label="{{ __('app.nav.back') }}"><x-icon name="chevron-left" /></button>
            <div class="appbar-title" id="appbarTitle">{{ $title ?? config('app.name') }}</div>
            <div class="topbar-brand" aria-hidden="true">{{ __('System administration') }}</div>
            <div class="topbar-right">
                @if ($showNotices)
                <div class="dropdown">
                    <button class="topbar-icon position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" type="button" aria-expanded="false" aria-label="{{ $bellCount ? __('Notifications, :count waiting', ['count' => $bellCount]) : __('Notifications') }}" title="{{ __('Notifications') }}">
                        <x-icon name="bell" />
                        @if ($bellCount)<span class="nt-count">{{ $bellCount > 99 ? '99+' : $bellCount }}</span>@endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 nt-menu">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <strong>{{ __('Notifications') }}</strong>
                            @if ($adminUnread && $can('admin.notices.read', false))<button type="submit" form="adminNoticesRead" class="btn btn-sm btn-link p-0 text-decoration-none">{{ __('Mark all as read') }}</button>@endif
                        </div>
                        @if ($waitingList)
                            <div class="nt-waiting">
                                <div class="nt-label">{{ __('Waiting for you') }}</div>
                                @foreach ($waitingList as [$label, $icon, $url, $count])
                                    <a href="{{ $url }}"><x-icon :name="$icon" /><span>{{ $label }}</span><b>{{ $count }}</b></a>
                                @endforeach
                            </div>
                        @endif
                        <div class="nt-list">
                            @forelse ($adminLatest as $notice)
                                <a href="{{ route('admin.notices.open', ['id' => $notice->id]) }}" class="nt-row {{ $notice->read_at ? '' : 'unread' }}">
                                    <span class="nt-ico nt-{{ $notice->kind }}"><x-icon :name="$notice->icon()" /></span>
                                    <span class="min-w-0 flex-grow-1"><span class="d-block nt-title">{{ $notice->title }}</span>@if ($notice->body)<span class="d-block small text-muted text-truncate">{{ $notice->body }}</span>@endif<span class="d-block nt-time">{{ $notice->created_at?->diffForHumans() }}</span></span>
                                </a>
                            @empty
                                <div class="text-center text-muted small py-4">{{ __('No notifications yet') }}</div>
                            @endforelse
                        </div>
                        <a class="d-block text-center py-2 border-top text-decoration-none small fw-semibold" href="{{ route('admin.notices.index') }}">{{ __('All notifications') }}</a>
                    </div>
                </div>
                @endif
                <button class="topbar-icon" type="button" data-theme-toggle title="{{ __('app.nav.toggle_theme') }}"><x-icon name="moon" /></button>
                <div class="dropdown">
                    <button class="topbar-user" data-bs-toggle="dropdown" type="button" aria-expanded="false">
                        <span class="avatar avatar-sm avatar-soft-primary">{{ $admin->initials() }}</span>
                        <span class="topbar-user-meta"><strong>{{ $admin->name }}</strong><small>{{ $admin->roleLabel() }}</small></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end user-menu">
                        <li class="user-menu-head"><span class="avatar avatar-md avatar-soft-primary">{{ $admin->initials() }}</span><div><strong>{{ $admin->name }}</strong><small>{{ $admin->email }}</small></div></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('admin.profile.edit') }}"><x-icon name="user" /> {{ __('My account') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item text-danger" type="submit" form="logoutForm"><x-icon name="right-from-bracket" /> {{ __('app.nav.sign_out') }}</button></li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="app-content">
            <div class="content-container">
                @yield('content')
            </div>
        </main>
        <footer class="app-footer">
            <span>© {{ date('Y') }} {{ config('app.name') }}</span>
            <nav><span class="text-muted">{{ __('app.by') }} <a href="https://fida.af" target="_blank" rel="noopener">Fida Technologies</a></span></nav>
        </footer>
    </div>
</div>

<nav class="app-tabbar" aria-label="{{ __('app.nav.menu') }}">
    <a href="{{ route($admin->homeRoute()) }}" class="tab-item {{ request()->routeIs($admin->homeRoute()) ? 'active' : '' }}"><x-icon name="house" /><em>{{ __('app.nav.home') }}</em></a>
    @if ($can('admin.messages.index') && $admin->homeRoute() !== 'admin.messages.index')
        <a href="{{ route('admin.messages.index') }}" class="tab-item {{ request()->routeIs('admin.messages.*') ? 'active' : '' }}"><x-icon name="envelope" /><em>{{ __('Messages') }}</em></a>
    @endif
    @if ($can('admin.office.index') && $admin->homeRoute() !== 'admin.office.index')
        <a href="{{ route('admin.office.index') }}" class="tab-item {{ request()->routeIs('admin.office.*') ? 'active' : '' }}"><x-icon name="receipt" /><em>{{ __('Office') }}</em></a>
    @endif
    <button type="button" class="tab-item" id="tabMenu"><x-icon name="grip" /><em>{{ __('app.nav.menu') }}</em></button>
</nav>

<form id="logoutForm" method="POST" action="{{ route('admin.logout') }}" hidden
      data-confirm="{{ __('app.nav.sign_out_question') }}" data-confirm-text="{{ __('app.nav.sign_out_text') }}"
      data-confirm-ok="{{ __('app.nav.sign_out') }}" data-confirm-style="primary">@csrf</form>

<form id="adminNoticesRead" method="POST" action="{{ route('admin.notices.read') }}" hidden>@csrf</form>
@include('partials.flash')
@include('partials.scripts')
</body>
</html>

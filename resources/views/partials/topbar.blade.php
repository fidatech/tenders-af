@php
    $user = auth()->user();
    $unread = \App\Models\Notice::where('user_id', $user->id)->whereNull('read_at')->count();
    $latest = \App\Models\Notice::where('user_id', $user->id)->orderByDesc('id')->limit(6)->get();
@endphp
<header class="app-topbar" id="appTopbar">
    <button class="btn-icon btn-ghost topbar-toggle" id="sidebarToggle" type="button" aria-label="{{ __('app.nav.toggle_sidebar') }}"><x-icon name="bars-staggered" /></button>
    <button class="appbar-back" type="button" id="appBack" aria-label="{{ __('app.nav.back') }}"><x-icon name="chevron-left" /></button>
    <div class="appbar-title" id="appbarTitle">{{ $title ?? config('app.name') }}</div>

    <button class="topbar-search" type="button" data-bs-toggle="modal" data-bs-target="#searchModal">
        <x-icon name="magnifying-glass" /><span>{{ __('app.nav.search_placeholder') }}</span><span class="kbd-group"><kbd>Ctrl</kbd><kbd>K</kbd></span>
    </button>


    <div class="topbar-right">
        <div class="dropdown">
            <button class="topbar-icon position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" type="button" aria-expanded="false" title="{{ __('Notifications') }}" aria-label="{{ __('Notifications') }}">
                <x-icon name="bell" />
                @if ($unread)<span class="badge bg-danger rounded-pill position-absolute" style="top:2px;inset-inline-end:0;font-size:10px;padding:3px 5px">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0" style="width:340px;max-width:92vw">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <strong>{{ __('Notifications') }}</strong>
                    @if ($unread)<button type="submit" form="noticesReadForm" class="btn btn-sm btn-link p-0 text-decoration-none">{{ __('Mark all as read') }}</button>@endif
                </div>
                <div style="max-height:360px;overflow-y:auto">
                    @forelse ($latest as $notice)
                        <a href="{{ route('notices.open', ['id' => $notice->id]) }}" class="d-flex gap-2 align-items-start px-3 py-2 border-bottom text-decoration-none text-reset">
                            <span class="rounded-circle flex-shrink-0 mt-2" style="width:8px;height:8px;background:{{ $notice->read_at ? 'transparent' : 'var(--bs-primary)' }}"></span>
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

        <button class="topbar-icon" type="button" data-theme-toggle title="{{ __('app.nav.toggle_theme') }}"><x-icon name="moon" /></button>

        <div class="dropdown">
            <button class="topbar-user" data-bs-toggle="dropdown" type="button" aria-expanded="false">
                <span class="avatar avatar-sm avatar-soft-primary">{{ $user->initials() }}</span>
                <span class="topbar-user-meta"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end user-menu">
                <li class="user-menu-head"><span class="avatar avatar-md avatar-soft-primary">{{ $user->initials() }}</span><div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><x-icon name="user" /> {{ __('My Profile') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('profile.password') }}"><x-icon name="key" /> {{ __('Change Password') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item text-danger" type="submit" form="logoutForm"><x-icon name="right-from-bracket" /> {{ __('app.nav.sign_out') }}</button></li>
            </ul>
        </div>
    </div>
</header>

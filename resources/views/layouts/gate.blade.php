{{--
    Sign in, register and the password pages, in the look of the website: a light page with
    one card in the middle. A page gives $title, the sections "heading" and "content",
    and $narrow for the small card of sign in and the password pages.
--}}
@php
    $name = config('app.name');
    $signup = \App\Support\Platform::bool('registration');
    $onRegister = request()->routeIs('register', 'register.*');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }} | {{ $name }}</title>
<meta name="robots" content="noindex, follow">
<meta name="theme-color" content="#F6F8F5">
<link rel="icon" href="{{ asset_v('img/favicon.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset_v('vendor/select2/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/site.css') }}">
<script>document.documentElement.className += ' js';</script>
</head>
<body>
<div class="acc">
    <header class="wrap acc-top">
        <a class="brand" href="{{ route('site.home') }}" aria-label="{{ $name }}, home"><i aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"><path d="M4 17 9 11l4 3 7-8"/><path d="M15 6h5v5"/></svg></i><span>Tenders<b>.af</b></span></a>
        <div class="switch">
            @if ($onRegister)
                <span>Already registered?</span><a href="{{ route('login') }}">Sign in</a>
            @elseif ($signup)
                <span>New here?</span><a href="{{ route('register') }}">Register free</a>
            @endif
        </div>
    </header>

    <main class="acc-main {{ ! empty($narrow) ? 'narrow' : '' }}" id="content">
        <div class="acc-head">@yield('heading')</div>
        <div class="acc-card">
            <div class="acc-card-in">
                @foreach (['success' => 'ok', 'status' => 'ok', 'info' => 'ok', 'warning' => 'no', 'danger' => 'no'] as $key => $tone)
                    @if (session($key))
                        <p class="acc-note {{ $tone }}" role="status">{{ session($key) }}</p>
                    @endif
                @endforeach
                @yield('content')
            </div>
        </div>
        @yield('below')
    </main>

    <footer class="acc-foot">
        <span>© {{ date('Y') }} {{ $name }}</span>
        <a href="{{ route('site.terms') }}">Terms</a>
        <a href="{{ route('site.privacy') }}">Privacy</a>
        <a href="{{ route('site.contact') }}">Help</a>
    </footer>
</div>

<script src="{{ asset_v('vendor/jquery/jquery.min.js') }}" defer></script>
<script src="{{ asset_v('vendor/select2/select2.min.js') }}" defer></script>
<script src="{{ asset_v('js/select2-init.js') }}" defer></script>
<script>
// "Show" beside a password box, and one press only on the big button
document.querySelectorAll('.pw button').forEach(function (button) {
    button.addEventListener('click', function () {
        var input = button.parentNode.querySelector('input');
        var hidden = input.type === 'password';
        input.type = hidden ? 'text' : 'password';
        button.textContent = hidden ? 'Hide' : 'Show';
    });
});
document.querySelectorAll('.acc form').forEach(function (form) {
    form.addEventListener('submit', function () {
        var go = form.querySelector('.go');
        if (go) setTimeout(function () { go.disabled = true; }, 0);
    });
});
</script>
@stack('scripts')
</body>
</html>

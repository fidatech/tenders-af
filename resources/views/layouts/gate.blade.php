{{--
    Sign in, register and the password pages, in the look of the website (not of the panel):
    a dark page with a short story on the left and the form on a white sheet on the right.
    A page gives: $title, the sections "story" and "content", and $wide for a broader sheet.
--}}
@php
    $name = config('app.name');
    // real numbers for the story; before the database is there, none are shown
    try {
        $figures = array_filter([
            'open tenders' => \App\Models\Tender::open()->count(),
            'closing this week' => \App\Models\Tender::closingWithin(7)->count(),
            'verified companies' => \App\Models\Company::listed()->where('verify_status', 'verified')->count(),
        ]);
    } catch (\Throwable) {
        $figures = [];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }} | {{ $name }}</title>
<meta name="robots" content="noindex, follow">
<meta name="theme-color" content="#0A2A1A">
<link rel="icon" href="{{ asset_v('img/favicon.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset_v('vendor/select2/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/site.css') }}">
</head>
<body>
<div class="dark gate">
    <header class="wrap gate-top">
        <a class="brand" href="{{ route('site.home') }}" aria-label="{{ $name }}, home"><i aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"><path d="M4 17 9 11l4 3 7-8"/><path d="M15 6h5v5"/></svg></i><span>Tenders<b>.af</b></span></a>
        <a class="back" href="{{ route('site.tenders') }}">Browse tenders →</a>
    </header>

    <main class="wrap gate-main" id="content">
        <section class="gate-story">
            @yield('story')
            @if ($figures)
                <div class="gate-figures">
                    @foreach ($figures as $label => $number)
                        <div><b>{{ number_format($number) }}</b><span>{{ $label }}</span></div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="sheet {{ ! empty($wide) ? 'wide' : '' }}">
            <div class="sheet-in">
                @foreach (['success' => 'ok', 'status' => 'ok', 'info' => 'ok', 'warning' => 'no', 'danger' => 'no'] as $key => $tone)
                    @if (session($key))
                        <p class="note {{ $tone }}" role="status" style="margin-bottom:16px">{{ session($key) }}</p>
                    @endif
                @endforeach
                @yield('content')
            </div>
        </section>
    </main>

    <footer class="wrap gate-foot">
        <span>© {{ date('Y') }} {{ $name }} · A <a href="https://fida.af" rel="noopener">Fida Technologies</a> product</span>
        <span><a href="{{ route('site.terms') }}">Terms</a> · <a href="{{ route('site.privacy') }}">Privacy</a> · <a href="{{ route('site.contact') }}">Help</a></span>
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
document.querySelectorAll('.sheet form').forEach(function (form) {
    form.addEventListener('submit', function () {
        var go = form.querySelector('.go');
        if (go) { go.disabled = true; go.style.opacity = '.7'; }
    });
});
</script>
@stack('scripts')
</body>
</html>

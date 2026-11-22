{{-- Favicon and phone app icons, from Admin > Branding & Logo (or the built-in mark). --}}
<link rel="icon" href="{{ \App\Support\Branding::faviconUrl() }}" type="{{ \App\Support\Branding::faviconType() }}">
<link rel="apple-touch-icon" href="{{ \App\Support\Branding::appIconUrl(192) ?? \App\Support\Branding::faviconUrl() }}">
<link rel="manifest" href="{{ route('site.manifest') }}">
<script>if ('serviceWorker' in navigator) { addEventListener('load', function () { navigator.serviceWorker.register('{{ route('site.sw', [], false) }}').catch(function () {}); }); }</script>

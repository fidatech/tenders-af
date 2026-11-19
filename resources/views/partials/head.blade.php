<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0B6B3A">
<meta name="robots" content="noindex, nofollow">
{{-- "Add to Home Screen" on a phone opens the system full screen, like an installed app --}}
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<title>{{ $title ?? '' }}{{ isset($title) ? ' | ' : '' }}{{ config('app.name') }}</title>
<link rel="icon" href="{{ asset_v('img/favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset_v('img/favicon.svg') }}">
<script>(function(){var s={};try{s=JSON.parse(localStorage.getItem('erp.ui')||'{}')}catch(e){}var h=document.documentElement;h.setAttribute('data-bs-theme',s.theme==='dark'?'dark':'light');h.setAttribute('data-layout',s.layout==='collapsed'?'collapsed':'vertical')})();</script>
<link rel="stylesheet" href="{{ asset_v(text_dir() === 'rtl' ? 'css/app.rtl.css' : 'css/app.css') }}">

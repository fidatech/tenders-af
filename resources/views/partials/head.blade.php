<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0B6B3A">
<meta name="robots" content="noindex, nofollow">
{{-- "Add to Home Screen" on a phone opens the system full screen, like an installed app --}}
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ \App\Support\Branding::name() }}">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title>{{ $title ?? '' }}{{ isset($title) ? ' | ' : '' }}{{ \App\Support\Branding::name() }}</title>
@include('partials.brand-head')
<script>(function(){var s={};try{s=JSON.parse(localStorage.getItem('erp.ui')||'{}')}catch(e){}var h=document.documentElement;h.setAttribute('data-bs-theme',s.theme==='dark'?'dark':'light');h.setAttribute('data-layout',s.layout==='collapsed'?'collapsed':'vertical')})();</script>
<link rel="stylesheet" href="{{ asset_v(text_dir() === 'rtl' ? 'css/app.rtl.css' : 'css/app.css') }}">
<link rel="stylesheet" href="{{ asset_v('vendor/select2/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/select2-fida.css') }}">

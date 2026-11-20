{{-- The heading font. The build puts it in public/fonts, so it comes from this site with the page:
     preloaded, and shown only if it is there in time, so the page never jumps when it arrives. --}}
@if (is_file(public_path('fonts/jakarta-latin.woff2')))
<link rel="preload" href="{{ asset_v('fonts/jakarta-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<style>@font-face{font-family:'Plus Jakarta Sans';font-style:normal;font-weight:600 800;font-display:optional;src:url({{ asset_v('fonts/jakarta-latin.woff2') }}) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}</style>
@else
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=optional" media="print" onload="this.media='all'">
@endif

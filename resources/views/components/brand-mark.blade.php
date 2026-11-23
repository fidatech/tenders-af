{{--
    The brand inside a link: the uploaded logo, or the text brand while none is uploaded.
    tone: "dark" (white text on the dark header and footer) or "light".
    The picture keeps its proportions; its height comes from Admin > Branding & Logo.
--}}
@props(['tone' => 'dark', 'size' => 22])
@php
    $logo = \App\Support\Branding::logoFor($tone === 'dark' ? 'dark' : 'light');
    $mobile = \App\Support\Branding::has('logo_mobile') ? \App\Support\Branding::logoFor($tone === 'dark' ? 'mobile-dark' : 'mobile') : null;
    $icon = \App\Support\Branding::url('icon');
    [$first, $last] = \App\Support\Branding::nameParts();
    $withName = \App\Support\Branding::display() === 'logo_name';
@endphp
@if ($logo)
    <span class="brand-img" style="--brand-h: {{ \App\Support\Branding::height() }}px; --brand-h-m: {{ \App\Support\Branding::height('mobile') }}px">
        <picture>
            @if ($mobile)<source media="(max-width: 640px)" srcset="{{ $mobile }}">@endif
            <img src="{{ $logo }}" alt="{{ \App\Support\Branding::alt() }}" decoding="async">
        </picture>
        @if ($withName)<span class="brand-name">{{ $first }}<b>{{ $last }}</b></span>@endif
    </span>
@else
    @if ($icon)
        <i aria-hidden="true" class="brand-icon-img"><img src="{{ $icon }}" alt="" width="{{ $size + 16 }}" height="{{ $size + 16 }}"></i>
    @else
        <i aria-hidden="true"><svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"><path d="M4 17 9 11l4 3 7-8"/><path d="M15 6h5v5"/></svg></i>
    @endif
    <span>{{ $first }}<b>{{ $last }}</b></span>
@endif

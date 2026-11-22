{{--
    The bottom bar and menu sheet of phones (App\Support\MobileNav). Hidden above 767px, where the
    normal header and sidebar are used. $nav = ['tabs' => [...], 'sheet' => [heading => entries]].
--}}
@php
    $paths = [
        'home' => '<path d="M3 11 12 3l9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'tenders' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'companies' => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M16 9h2a2 2 0 0 1 2 2v10M3 21h18M8 7h4M8 11h4M8 15h4"/>',
        'products' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'map' => '<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17h.01"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'bell' => '<path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7M10 20a2 2 0 0 0 4 0"/>',
        'bookmark' => '<path d="M6 3h12v18l-6-4-6 4z"/>',
        'megaphone' => '<path d="M3 11v2a1 1 0 0 0 1 1h3l6 5V5L7 10H4a1 1 0 0 0-1 1zM17 8a5 5 0 0 1 0 8"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'out' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l-5-5 5-5M5 12h11"/>',
        'send' => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>',
        'team' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0M16 4a3.5 3.5 0 0 1 0 7M18 13.5a7 7 0 0 1 4 6.5"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
        'dot' => '<circle cx="12" cy="12" r="3"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
    ];
    $svg = fn (string $icon) => '<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.($paths[$icon] ?? $paths['dot']).'</svg>';
    $badge = fn (int $n) => $n > 0 ? '<b class="mnav-badge">'.($n > 99 ? '99+' : $n).'</b>' : '';
@endphp
<nav class="mnav" aria-label="{{ __('Main') }}" data-mnav>
    @foreach ($nav['tabs'] as $tab)
        @if ($tab['url'])
            <a class="mnav-tab {{ $tab['active'] ? 'on' : '' }}" href="{{ $tab['url'] }}" @if ($tab['active']) aria-current="page" @endif>{!! $svg($tab['icon']) !!}{!! $badge($tab['badge'] ?? 0) !!}<span>{{ __($tab['label']) }}</span></a>
        @else
            <button type="button" class="mnav-tab" data-mnav-open aria-haspopup="dialog" aria-controls="mnavSheet" aria-expanded="false">{!! $svg($tab['icon']) !!}{!! $badge($tab['badge'] ?? 0) !!}<span>{{ __($tab['label']) }}</span></button>
        @endif
    @endforeach
</nav>

<div class="mnav-backdrop" data-mnav-close hidden></div>
<div class="mnav-sheet" id="mnavSheet" role="dialog" aria-modal="true" aria-labelledby="mnavTitle" hidden>
    <div class="mnav-grip" aria-hidden="true"></div>
    <div class="mnav-sheet-head">
        <strong id="mnavTitle">{{ __('Menu') }}</strong>
        <button type="button" class="mnav-x" data-mnav-close aria-label="{{ __('Close the menu') }}">{!! $svg('close') !!}</button>
    </div>
    <div class="mnav-sheet-body">
        @foreach ($nav['sheet'] as $heading => $entries)
            @continue(! $entries)
            <section>
                <h2>{{ __($heading) }}</h2>
                <ul>
                    @foreach ($entries as $entry)
                        <li>
                            @if (! empty($entry['form']))
                                <button type="submit" form="{{ $entry['form'] }}" class="mnav-link">{!! $svg($entry['icon']) !!}<span>{{ __($entry['label']) }}</span></button>
                            @else
                                <a class="mnav-link {{ ! empty($entry['primary']) ? 'primary' : '' }} {{ ! empty($entry['active']) ? 'on' : '' }}" href="{{ $entry['url'] }}" @if (! empty($entry['active'])) aria-current="page" @endif>{!! $svg($entry['icon']) !!}<span>{{ __($entry['label']) }}</span>{!! $badge($entry['badge'] ?? 0) !!}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</div>
@once
    @push('scripts')
        <script src="{{ asset_v('js/mobile-nav.js') }}" defer></script>
    @endpush
@endonce

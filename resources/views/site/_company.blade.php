{{-- one company as a card of the directory: its main sector on the cover, then who it is and what it does --}}
@php
    $covers = ['cv-green', 'cv-ink', 'cv-red', 'cv-deep'];
    $sectors = $company->industries;
    $main = $sectors->first();
    $place = $company->province?->name ?: $company->country;
    $roles = array_filter([$company->is_supplier ? 'Supplies' : null, $company->is_buyer ? 'Posts tenders' : null]);
@endphp
<a class="company" href="{{ route('site.companies.show', ['slug' => $company->slug]) }}">
    <div class="cover {{ $covers[($main?->id ?? $company->id) % 4] }}">
        @if ($main)
            <svg class="mark" aria-hidden="true"><use href="{{ asset_v('icons.svg') }}#s-{{ $main->icon }}"/></svg>
            <span class="sector-tag"><svg aria-hidden="true"><use href="{{ asset_v('icons.svg') }}#s-{{ $main->icon }}"/></svg>{{ $main->name }}</span>
        @else
            <span class="sector-tag">{{ $company->kindLabel() }}</span>
        @endif
        @if ($company->isVerified())<span class="verified on-cover" title="We checked the business licence of this company"><svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>Verified</span>@endif
    </div>
    <div class="in">
        <div class="logo">@if ($company->logo_path)<img src="{{ route('site.companies.logo', ['slug' => $company->slug]) }}" alt="" loading="lazy" width="60" height="60">@else{{ \App\Models\User::initialsOf($company->name) }}@endif</div>
        <h3>{{ $company->name }}</h3>
        <p class="kind">{{ $company->kindLabel() }}</p>
        @if ($company->about)
            <p class="about">{{ \Illuminate\Support\Str::limit(strip_tags($company->about), 110) }}</p>
        @endif
        <ul class="facts-row">
            @if ($place)<li><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>{{ $place }}</li>@endif
            @if ($company->founded_year)<li><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>Since {{ $company->founded_year }}</li>@endif
            @if ($company->size)<li><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4-6"/></svg>{{ $company->size }} people</li>@endif
        </ul>
        @if ($sectors->count() > 1)
            <div class="sectors">
                @foreach ($sectors->slice(1, 2) as $sector)<span>{{ $sector->name }}</span>@endforeach
                @if ($sectors->count() > 3)<span class="more">+{{ $sectors->count() - 3 }}</span>@endif
            </div>
        @endif
        <div class="cfoot">
            <span>{{ $roles ? implode(' · ', $roles) : 'Company profile' }}</span>
            <b>View profile <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></b>
        </div>
    </div>
</a>

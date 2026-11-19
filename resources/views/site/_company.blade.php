{{-- one company as a card of the directory --}}
@php
    $covers = ['linear-gradient(120deg, #0B6B3A 0%, #18A05A 100%)', 'linear-gradient(120deg, #B10F1C 0%, #E8323F 100%)', 'linear-gradient(120deg, #0E1512 0%, #0B6B3A 100%)', 'linear-gradient(120deg, #0E1512 0%, #C1121F 100%)'];
@endphp
<a class="company" href="{{ route('site.companies.show', ['slug' => $company->slug]) }}">
    <div class="cover" style="background: {{ $covers[$company->id % 4] }}"></div>
    <div class="in">
        <div class="head">
            <div class="logo">@if ($company->logo_path)<img src="{{ route('site.companies.logo', ['slug' => $company->slug]) }}" alt="" loading="lazy" width="56" height="56">@else{{ \App\Models\User::initialsOf($company->name) }}@endif</div>
            @if ($company->isVerified())<span class="verified">✓ Verified</span>@endif
        </div>
        <h3>{{ $company->name }}</h3>
        <p>{{ collect([$company->industries->first()?->name, $company->province?->name ?: $company->country])->filter()->join(' · ') ?: $company->kindLabel() }}</p>
    </div>
</a>

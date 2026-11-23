{{-- one project of a company's portfolio, as a card --}}
@php $picture = $project->images->first(); @endphp
<a class="co-project" href="{{ route('site.projects.show', ['slug' => $company->slug, 'project' => $project->slug]) }}">
    <span class="co-project-pic">
        @if ($picture)<img src="{{ route('site.projects.image', ['id' => $picture->id]) }}" alt="" loading="lazy" decoding="async">@else<i aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6"/></svg></i>@endif
        @if ($project->is_featured)<em>Featured</em>@endif
    </span>
    <span class="co-project-in">
        <b dir="auto">{{ $project->title }}</b>
        <small>{{ collect([$project->location, $project->period()])->filter()->join(' · ') }}</small>
        <span class="co-review {{ $project->isReviewed() ? 'ok' : '' }}">{{ $project->reviewLabel() }}</span>
    </span>
</a>

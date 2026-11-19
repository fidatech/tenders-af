{{-- the dark top of an inner page: where you are, the heading, one or two sentences --}}
<section class="wrap pagehead">
    <nav class="crumbs" aria-label="You are here"><a href="{{ route('site.home') }}">Home</a>@foreach ($crumbs as [$crumbName, $crumbUrl]) / @if ($crumbUrl)<a href="{{ $crumbUrl }}">{{ $crumbName }}</a>@else<span>{{ $crumbName }}</span>@endif @endforeach</nav>
    <h1>{{ $heading }}</h1>
    @if (! empty($lead))<p class="lead">{{ $lead }}</p>@endif
</section>

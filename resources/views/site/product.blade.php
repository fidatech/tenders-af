@php
    $company = $product->company;
    $facts = array_filter([
        'Price' => $product->priceText(),
        'Smallest order' => $product->min_order,
        'Sold by the' => $product->unit,
        'Capacity' => $product->capacity,
        'Sector' => $product->industry?->name,
        'Made or stocked in' => $place,
        'Origin' => $product->made_in_afghanistan ? 'Made in Afghanistan' : null,
    ]);
    $pictures = $product->images->map(fn ($image) => route('site.products.image', ['id' => $image->id]))->all();
    $phone = $company->phone ?: $company->whatsapp;
    $schema = [array_filter([
        '@type' => 'Product', '@id' => url()->current().'#product', 'name' => $product->name, 'description' => $product->summary, 'image' => $pictures,
        'category' => $product->industry?->name,
        'brand' => ['@type' => 'Organization', 'name' => $company->name],
        'countryOfOrigin' => $product->made_in_afghanistan ? ['@type' => 'Country', 'name' => 'Afghanistan'] : null,
        'offers' => $product->price_min !== null ? array_filter([
            '@type' => $product->price_max !== null && (float) $product->price_max > (float) $product->price_min ? 'AggregateOffer' : 'Offer',
            'priceCurrency' => $product->price_currency,
            'price' => $product->price_max !== null && (float) $product->price_max > (float) $product->price_min ? null : (float) $product->price_min,
            'lowPrice' => $product->price_max !== null && (float) $product->price_max > (float) $product->price_min ? (float) $product->price_min : null,
            'highPrice' => $product->price_max !== null && (float) $product->price_max > (float) $product->price_min ? (float) $product->price_max : null,
            'seller' => ['@type' => 'Organization', 'name' => $company->name],
        ]) : null,
    ])];
@endphp
@extends('layouts.site', ['schema' => $schema])

@section('hero')
<section class="wrap pagehead">
    <nav class="crumbs" aria-label="You are here"><a href="{{ route('site.home') }}">Home</a>@foreach ($crumbs as [$crumbName, $crumbUrl]) / @if ($crumbUrl)<a href="{{ $crumbUrl }}">{{ $crumbName }}</a>@else<span>{{ $crumbName }}</span>@endif @endforeach</nav>
    <div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:8px;align-items:center"><span class="tag" style="background:rgba(255,255,255,.16);color:#fff">Wholesale only</span>@if ($product->isSponsored())<span class="tag" style="background:#fff;color:#0E1512">Sponsored</span>@endif</div>
    <h1>{{ $product->name }}</h1>
    <p class="lead">{{ $company->name }} · {{ $place }}</p>
</section>
@endsection

@section('content')
<div class="wrap" style="margin-top:-30px;position:relative">
    <div class="cols">
        <article class="main">
            <div class="gallery">
                @if ($product->images->isNotEmpty())
                    <img class="big" id="galleryBig" src="{{ $pictures[0] }}" alt="{{ $product->name }}" width="900" height="600">
                @endif
                @if (count($pictures) > 1)
                    <div class="thumbs">
                        @foreach ($pictures as $index => $picture)
                            <button type="button" data-src="{{ $picture }}" @if ($index === 0) aria-current="true" @endif aria-label="Picture {{ $index + 1 }}"><img src="{{ $picture }}" alt="" loading="lazy" width="120" height="90"></button>
                        @endforeach
                    </div>
                @endif
            </div>

            <dl class="facts" style="margin-top:24px">
                @foreach ($facts as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>

            <section class="block">
                <h2>About this product</h2>
                <p class="text">{{ $product->summary }}</p>
                @if ($product->description)<p class="text">{{ $product->description }}</p>@endif
            </section>

            @if ($product->video_path)
                <section class="block">
                    <h2>Video</h2>
                    <video class="clip" controls preload="metadata" playsinline @if ($pictures) poster="{{ $pictures[0] }}" @endif>
                        <source src="{{ route('site.products.video', ['id' => $product->id]) }}">
                    </video>
                </section>
            @endif

            @if ($more->isNotEmpty())
                <section class="block">
                    <h2>More from {{ $company->name }}</h2>
                    <div class="grid-products">
                        @foreach ($more as $other)
                            @include('site._product', ['product' => $other])
                        @endforeach
                    </div>
                </section>
            @endif
        </article>

        <aside class="side">
            <div class="sidecard">
                <h2>Contact the supplier</h2>
                <p class="muted" style="font-size:14px">Ask for a wholesale offer. You deal with the company directly; {{ config('app.name') }} takes no commission.</p>
                @if ($company->whatsapp || $company->phone)
                    <a class="btn btn-green" style="width:100%;margin-top:12px" href="{{ route('site.products.contact', ['slug' => $product->slug]) }}" target="_blank" rel="noopener nofollow">WhatsApp the supplier</a>
                @endif
                @if ($phone)
                    <a class="btn btn-line" style="width:100%;margin-top:8px" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"><span dir="ltr">Call {{ $phone }}</span></a>
                @endif
                @unless ($company->whatsapp || $company->phone)<p class="muted" style="font-size:14px;margin-top:12px">This company has not added a phone number yet.</p>@endunless
            </div>
            <div class="sidecard">
                <h2>{{ $company->name }}</h2>
                @if ($company->isVerified())<p style="margin:6px 0 10px"><span class="verified">✓ Verified company</span></p>@endif
                <p class="muted" style="font-size:14px">{{ $company->kindLabel() }}@if ($company->province) · {{ $company->province->name }}@endif</p>
                @if ($company->is_listed)<a class="line" href="{{ route('site.companies.show', ['slug' => $company->slug]) }}"><b>See the company profile</b></a>@endif
            </div>
        </aside>
    </div>
</div>
<script>
(function () {
    var big = document.getElementById('galleryBig');
    if (!big) return;
    document.querySelectorAll('.gallery .thumbs button').forEach(function (button) {
        button.addEventListener('click', function () {
            big.src = button.getAttribute('data-src');
            document.querySelectorAll('.gallery .thumbs button').forEach(function (other) { other.removeAttribute('aria-current'); });
            button.setAttribute('aria-current', 'true');
        });
    });
})();
</script>
@endsection

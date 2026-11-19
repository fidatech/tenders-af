{{-- one product as a card: the picture, the name, the smallest order and who makes it --}}
@php $cover = $product->images->first(); @endphp
<a class="product" href="{{ route('site.products.show', ['slug' => $product->slug]) }}">
    <div class="shot">
        @if ($cover)<img src="{{ route('site.products.image', ['id' => $cover->id]) }}" alt="{{ $product->name }}" loading="lazy" width="400" height="300">@endif
        @if ($product->isSponsored())<span class="sponsored">Sponsored</span>@endif
        @if ($product->video_path)<span class="hasvideo" aria-label="Has a video">▶</span>@endif
    </div>
    <div class="in">
        <h3>{{ $product->name }}</h3>
        <p class="price">{{ $product->priceText() }}</p>
        <p class="moq">Smallest order: {{ $product->min_order }}</p>
        <p class="by">{{ $product->company->name }}@if ($product->company->isVerified()) <span class="verified">✓ Verified</span>@endif</p>
        <p class="where">{{ $product->province?->name ?: 'Afghanistan' }}</p>
    </div>
</a>

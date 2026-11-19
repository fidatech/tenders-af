@extends('layouts.site', ['crumbs' => [['Products', null]]])

@section('hero')
@include('site._pagehead', ['heading' => 'Wholesale products from Afghanistan', 'lead' => 'What factories and producers in Afghanistan make and supply, for wholesale only. Each product shows its unit and smallest order, and you contact the company directly.', 'crumbs' => [['Products', null]]])
@endsection

@section('content')
<div class="wrap">
    <form class="filters" method="GET" role="search">
        <input type="search" name="q" value="{{ $term }}" placeholder="Search products…" aria-label="Search products">
        <select name="industry" aria-label="Sector" onchange="this.form.submit()">
            <option value="">All sectors</option>
            @foreach ($industries as $id => $industryName)
                <option value="{{ $id }}" @selected((string) request('industry') === (string) $id)>{{ $industryName }}</option>
            @endforeach
        </select>
        <select name="province" aria-label="Province" onchange="this.form.submit()">
            <option value="">All provinces</option>
            @foreach ($provinceOptions as $id => $provinceName)
                <option value="{{ $id }}" @selected((string) request('province') === (string) $id)>{{ $provinceName }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-green btn-sm">Search</button>
    </form>

    <p class="count">{{ trans_choice('{0} No products found|{1} 1 product|[2,*] :count products', $rows->total(), ['count' => number_format($rows->total())]) }}</p>

    @if ($rows->isEmpty())
        <div class="empty">
            <h2>No products here yet</h2>
            <p>Do you make or supply something in bulk? Register your company for free and list your products.</p>
            @if ($signup)<p style="margin-top:16px"><a class="btn btn-green" href="{{ route('register') }}">Register your company</a></p>@endif
        </div>
    @else
        <div class="grid-products">
            @foreach ($rows as $product)
                @include('site._product', ['product' => $product])
            @endforeach
        </div>
        @if ($rows->hasPages())
            <nav class="pager" aria-label="Pages">
                @if ($rows->onFirstPage())<span>Previous</span>@else<a href="{{ $rows->previousPageUrl() }}" rel="prev">Previous</a>@endif
                <span>Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</span>
                @if ($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}" rel="next">Next</a>@else<span>Next</span>@endif
            </nav>
        @endif
    @endif

    <div class="cta">
        <div>
            <h2>Show your products to buyers.</h2>
            <p>Listing is free for registered companies. Sponsored products are shown first.</p>
        </div>
        <div class="btns">
            @if (auth('web')->check())<a class="btn btn-white" href="{{ route('products.index') }}">My products</a>@elseif ($signup)<a class="btn btn-white" href="{{ route('register') }}">Register your company</a>@endif
        </div>
    </div>
</div>
@endsection

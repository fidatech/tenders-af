@extends('layouts.site')

@section('hero')
@include('site._pagehead', ['heading' => $heading, 'lead' => $lead])
@endsection

@section('content')
<div class="wrap">
    <form class="filters" method="GET" role="search">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by title, buyer or number…" aria-label="Search tenders">
        @unless (isset($fixed['industry']))
            <select name="industry" aria-label="Sector" onchange="this.form.submit()">
                <option value="">All sectors</option>
                @foreach ($industries as $id => $industryName)
                    <option value="{{ $id }}" @selected((string) ($filters['industry'] ?? '') === (string) $id)>{{ $industryName }}</option>
                @endforeach
            </select>
        @endunless
        @unless (isset($fixed['province']))
            <select name="province" aria-label="Province" onchange="this.form.submit()">
                <option value="">All provinces</option>
                @foreach ($provinceOptions as $id => $provinceName)
                    <option value="{{ $id }}" @selected((string) ($filters['province'] ?? '') === (string) $id)>{{ $provinceName }}</option>
                @endforeach
            </select>
        @endunless
        <select name="type" aria-label="Kind of tender" onchange="this.form.submit()">
            <option value="">Any kind</option>
            @foreach (config('tenders.types') as $key => [$short, $long])
                <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $short }} · {{ $long }}</option>
            @endforeach
        </select>
        <select name="buyer" aria-label="Buyer" onchange="this.form.submit()">
            <option value="">Any buyer</option>
            @foreach (config('tenders.buyer_types') as $key => $label)
                <option value="{{ $key }}" @selected(($filters['buyer'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="check"><input type="checkbox" name="closed" value="1" @checked($all) onchange="this.form.submit()"> Also closed</label>
        <button type="submit" class="btn btn-green btn-sm">Search</button>
    </form>

    @if ($categories)
        <div class="chips" aria-label="Categories in this sector">@foreach ($categories as $category)<span>{{ $category }}</span>@endforeach</div>
    @endif

    <p class="count">{{ trans_choice('{0} No tenders found|{1} 1 tender|[2,*] :count tenders', $rows->total(), ['count' => number_format($rows->total())]) }}{{ $all ? '' : ', open now' }}</p>

    @if ($rows->isEmpty())
        <div class="empty">
            <h2>No open tenders here today</h2>
            <p>New tenders are added every day. Register your company for free and we send you the ones that match.</p>
            @if ($signup)<p style="margin-top:16px"><a class="btn btn-green" href="{{ route('register') }}">Register your company</a></p>@endif
        </div>
    @else
        <div class="grid-tenders" style="margin-top:16px">
            @foreach ($rows as $tender)
                @include('site._tender', ['tender' => $tender])
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

    @include('site._cta')
</div>
@endsection

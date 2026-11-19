@extends('layouts.site', ['crumbs' => [['Companies', null]]])

@section('hero')
@include('site._pagehead', ['heading' => 'Companies in Afghanistan', 'lead' => 'A free directory of companies, by sector and province. Verified companies sent us their business licence and we checked it.', 'crumbs' => [['Companies', null]]])
@endsection

@section('content')
<div class="wrap">
    <form class="filters" method="GET" role="search">
        <input type="search" name="q" value="{{ $term }}" placeholder="Search by name or by what they do…" aria-label="Search companies">
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
        <label class="check"><input type="checkbox" name="verified" value="1" @checked(request()->boolean('verified')) onchange="this.form.submit()"> Verified only</label>
        <button type="submit" class="btn btn-green btn-sm">Search</button>
    </form>

    <p class="count">{{ trans_choice('{0} No companies found|{1} 1 company|[2,*] :count companies', $rows->total(), ['count' => number_format($rows->total())]) }}</p>

    @if ($rows->isEmpty())
        <div class="empty">
            <h2>No companies here yet</h2>
            <p>Be the first: registering your company is free.</p>
            @if ($signup)<p style="margin-top:16px"><a class="btn btn-green" href="{{ route('register') }}">Register your company</a></p>@endif
        </div>
    @else
        <div class="grid-companies" style="margin-top:16px">
            @foreach ($rows as $company)
                @include('site._company', ['company' => $company])
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

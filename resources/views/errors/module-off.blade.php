{{-- A page of a module that is switched off (App\Http\Middleware\EnsureModule). Status 404 and noindex. --}}
@extends('layouts.site', [
    'title' => 'Not available at the moment',
    'description' => 'This part of '.\App\Support\Branding::name().' is not available at the moment.',
    'crumbs' => [['Not available', null]],
    'noindex' => true,
    'signup' => \App\Support\Platform::bool('registration'),
])

@section('hero')
    @include('site._pagehead', ['heading' => 'Not available at the moment', 'lead' => 'This part of the site ('.$module['name'].') is switched off for now. Nothing has been deleted; it may come back later.', 'crumbs' => [['Not available', null]]])
@endsection

@section('content')
<div class="wrap" style="padding-bottom:48px">
    <div class="empty">
        <h2>What you can do instead</h2>
        <p>Browse the open tenders, or go back to the home page.</p>
        <p style="margin-top:18px;display:flex;flex-wrap:wrap;gap:10px;justify-content:center">
            <a class="btn btn-green btn-sm" href="{{ route('site.tenders') }}">Open tenders</a>
            <a class="btn btn-line btn-sm" href="{{ route('site.home') }}">Home page</a>
        </p>
    </div>
</div>
@endsection

@extends('layouts.site', ['crumbs' => [['Questions', null]]])

@section('hero')
@include('site._pagehead', ['heading' => 'Questions and answers', 'lead' => 'What Tenders.af is, what is free, what a paid plan opens, and how the alerts and the payments work.', 'crumbs' => [['Questions', null]]])
@endsection

@section('content')
<div class="wrap" style="padding-top:8px">
    @foreach ($faqs as [$question, $answer])
        <details class="qa" @if ($loop->first) open @endif><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>
    @endforeach
    <p style="margin-top:24px">Something else? <a href="{{ route('site.contact') }}" style="font-weight:600">Write to us.</a></p>
    @include('site._cta')
</div>
@endsection

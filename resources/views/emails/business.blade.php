@extends('emails.layout', ['heading' => $heading, 'sender' => config('app.name')])

@section('body')
<p style="margin:0 0 14px;">Dear {{ $company->owner()?->name ?: $company->name }},</p>
@foreach ($lines as $line)
    <p style="margin:0 0 14px;">{{ $line }}</p>
@endforeach
<p style="margin:20px 0 0;"><a href="{{ $url }}" style="display:inline-block;background:#0B6B3A;color:#ffffff;text-decoration:none;font-weight:600;padding:11px 20px;border-radius:8px;">{{ $button }}</a></p>
@endsection

@section('footer')
You get this email because you are the owner of {{ $company->name }} in {{ config('app.name') }}.@if (platform('support_email')) Questions: {{ platform('support_email') }}@endif
@endsection

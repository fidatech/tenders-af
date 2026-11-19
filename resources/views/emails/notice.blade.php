@extends('emails.layout', ['heading' => $heading, 'sender' => config('app.name')])

@section('body')
@foreach ($lines as $line)
    <p style="margin:0 0 14px;">{{ $line }}</p>
@endforeach
<p style="margin:20px 0 0;"><a href="{{ $url }}" style="display:inline-block;background:#0B6B3A;color:#ffffff;text-decoration:none;font-weight:600;padding:11px 20px;border-radius:8px;">{{ $button }}</a></p>
@endsection

@section('footer')
You get this email because you are an administrator of {{ config('app.name') }}.
@endsection

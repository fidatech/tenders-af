@extends('emails.layout', ['heading' => 'Message from '.$name, 'sender' => config('app.name').' website'])

@section('body')
<p style="margin:0 0 14px;white-space:pre-line;">{{ $body }}</p>
<p style="margin:18px 0 0;color:#7b8498;font-size:13px;">{{ $name }} · {{ $email }}</p>
@endsection

@section('footer')
Sent from the contact page. Reply to this email to answer.
@endsection

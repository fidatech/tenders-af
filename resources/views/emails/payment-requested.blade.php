@extends('emails.layout', ['heading' => $subscription->company?->name.' says it has paid', 'sender' => config('app.name')])

@php
    $td = 'padding:8px 0;border-bottom:1px solid #eef1f6;font-size:14px;';
    $owner = $subscription->company?->owner();
@endphp

@section('body')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr><td style="{{ $td }}color:#7b8498;">Company</td><td style="{{ $td }}text-align:right;"><strong>{{ $subscription->company?->name }}</strong></td></tr>
    <tr><td style="{{ $td }}color:#7b8498;">Owner</td><td style="{{ $td }}text-align:right;">{{ $owner?->name }} · {{ $owner?->email }}</td></tr>
    <tr><td style="{{ $td }}color:#7b8498;">Amount</td><td style="{{ $td }}text-align:right;"><strong>{{ money($subscription->amount, $subscription->currency) }}</strong></td></tr>
    <tr><td style="{{ $td }}color:#7b8498;">Plan</td><td style="{{ $td }}text-align:right;">{{ $subscription->plan?->name }}</td></tr>
    <tr><td style="{{ $td }}color:#7b8498;">Paid on</td><td style="{{ $td }}text-align:right;">{{ fmt_date($subscription->sent_on) }}</td></tr>
    <tr><td style="{{ $td }}color:#7b8498;">Paid to</td><td style="{{ $td }}text-align:right;">{{ $subscription->account?->name ?: '—' }}</td></tr>
    <tr><td style="{{ $td }}color:#7b8498;">Transfer number</td><td style="{{ $td }}text-align:right;">{{ $subscription->reference ?: '—' }}</td></tr>
    <tr><td style="{{ $td }}color:#7b8498;">Covers</td><td style="{{ $td }}text-align:right;">{{ fmt_date($subscription->starts_on) }} – {{ $subscription->ends_on ? fmt_date($subscription->ends_on) : 'no end' }}</td></tr>
</table>
<p style="margin:18px 0 0;">{{ $subscription->receipt_path ? 'The screenshot of the payment is attached.' : 'No screenshot was sent.' }} Check that the money has arrived, then confirm it: the company becomes active at once.</p>
<p style="margin:20px 0 0;"><a href="{{ route('admin.subscriptions.edit', ['id' => $subscription->id, 'confirm' => 1]) }}" style="display:inline-block;background:#0B6B3A;color:#ffffff;text-decoration:none;font-weight:600;padding:11px 20px;border-radius:8px;">Check and confirm</a>
&nbsp; <a href="{{ route('admin.subscriptions.reject', ['id' => $subscription->id]) }}" style="color:#c53030;font-weight:600;">Reject</a></p>
@endsection

@section('footer')
Sent to the administrators of {{ config('app.name') }}.
@endsection

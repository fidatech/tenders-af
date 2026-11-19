@extends('emails.layout', ['heading' => 'Your payment was not accepted', 'sender' => config('app.name')])

@section('body')
<p style="margin:0 0 14px;">Dear {{ $subscription->company?->owner()?->name ?: $subscription->company?->name }},</p>
<p style="margin:0 0 14px;">We checked the payment of <strong>{{ money($subscription->amount, $subscription->currency) }}</strong> you sent us on {{ fmt_date($subscription->sent_on ?? $subscription->created_at) }} for {{ $subscription->company?->name }}, and we could not accept it.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;background:#fdf2f2;border-radius:10px;">
    <tr><td style="padding:14px 18px;font-size:15px;color:#9b1c1c;"><strong>Reason:</strong> {{ $subscription->rejectText() }}@if ($subscription->reject_note)<br>{{ $subscription->reject_note }}@endif</td></tr>
</table>
<p style="margin:0 0 14px;">{{ $subscription->rejectAdvice() }}</p>
<p style="margin:20px 0 0;"><a href="{{ route('billing.show') }}" style="display:inline-block;background:#0B6B3A;color:#ffffff;text-decoration:none;font-weight:600;padding:11px 20px;border-radius:8px;">Open the Subscription page</a></p>
@endsection

@section('footer')
Your records in {{ config('app.name') }} are safe. Reply to this email if you think this is a mistake.
@endsection

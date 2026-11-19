@extends('emails.layout', ['heading' => $digest ? 'New tenders this week' : 'New tender for your company', 'sender' => config('app.name')])

@section('body')
<p style="margin:0 0 14px;">Dear {{ $company->owner()?->name ?: $company->name }},</p>
<p style="margin:0 0 14px;">{{ $digest ? 'These tenders were published this week in the sectors of '.$company->name.':' : 'A tender was published that matches the sectors of '.$company->name.':' }}</p>
@foreach ($tenders as $tender)
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px;border:1px solid #e6eaf1;border-radius:10px;">
    <tr><td style="padding:14px 16px;">
        <div style="font-size:12px;color:#7b8498;font-weight:600;">{{ $tender->typeLabel() }} · {{ $tender->number() }} · {{ $tender->industry?->name }}</div>
        <div style="margin:4px 0 0;font-size:16px;font-weight:700;color:#1f2637;line-height:1.35;"><a href="{{ route('tenders.show', ['id' => $tender->id]) }}" style="color:#1f2637;text-decoration:none;">{{ $tender->title }}</a></div>
        @unless ($digest)
            <div style="margin:6px 0 0;font-size:14px;">{{ $tender->buyer_name }} · {{ $tender->placeText() }}</div>
            @if ($tender->deadline)
                <div style="margin:4px 0 0;font-size:14px;color:#C1121F;font-weight:600;">Deadline: {{ fmt_datetime($tender->deadline) }} (Kabul time)</div>
            @endif
        @endunless
    </td></tr>
    </table>
@endforeach
<p style="margin:20px 0 0;"><a href="{{ $tenders->count() === 1 ? route('tenders.show', ['id' => $tenders->first()->id]) : route('tenders.matching') }}" style="display:inline-block;background:#0B6B3A;color:#ffffff;text-decoration:none;font-weight:600;padding:11px 20px;border-radius:8px;">{{ $tenders->count() === 1 ? 'Open the tender' : 'See your tenders' }}</a></p>
@if ($digest)
    <p style="margin:16px 0 0;font-size:14px;color:#7b8498;">On a paid plan you get every tender the moment it is published, with its full details and documents.</p>
@endif
@endsection

@section('footer')
You get this email because {{ $company->name }} follows these sectors on {{ config('app.name') }}. Change or stop the alerts under Tender alerts in your account: {{ route('alerts.edit') }}
@endsection

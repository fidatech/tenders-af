@php
    $company = $subscription->company;
    $company = platform('company_name') ?: config('app.name');
    $number = $subscription->receiptNumber();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('Receipt') }} {{ $number }}</title>
<style>
    *{box-sizing:border-box}
    body{margin:0;background:#eef1f6;color:#2b3345;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.5}
    .bar{max-width:760px;margin:20px auto 0;padding:0 16px;display:flex;gap:10px;justify-content:flex-end}
    .bar button,.bar a{border:0;background:#3a7bf0;color:#fff;font-weight:600;font-size:14px;padding:10px 18px;border-radius:9px;text-decoration:none;cursor:pointer}
    .bar a{background:#fff;color:#2b3345;border:1px solid #dfe4ee}
    .paper{max-width:760px;margin:16px auto 40px;background:#fff;border:1px solid #e3e8f0;border-radius:14px;padding:40px}
    .head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;border-bottom:2px solid #2b3345;padding-bottom:20px}
    .head h1{margin:0;font-size:22px}
    .head .muted{white-space:pre-line}
    .title{text-align:right}
    .title strong{display:block;font-size:26px;letter-spacing:.04em}
    .muted{color:#7b8498;font-size:13px}
    .cols{display:flex;gap:30px;margin:26px 0}
    .cols>div{flex:1}
    .label{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#7b8498;margin-bottom:4px}
    table{width:100%;border-collapse:collapse;margin-top:6px}
    th{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#7b8498;text-align:left;padding:10px 8px;border-bottom:1px solid #dfe4ee}
    td{padding:14px 8px;border-bottom:1px solid #eef1f6;vertical-align:top}
    .num{text-align:right;white-space:nowrap}
    .total td{border-bottom:0;font-size:18px;font-weight:700}
    .paid{display:inline-block;margin-top:22px;border:2px solid #15803d;color:#15803d;font-weight:800;letter-spacing:.12em;padding:6px 16px;border-radius:8px;transform:rotate(-4deg)}
    .foot{margin-top:34px;padding-top:16px;border-top:1px solid #e3e8f0;text-align:center}
    @media (max-width:560px){.paper{padding:22px;border-radius:0;margin:12px 0 30px}.cols{flex-direction:column;gap:16px}.head{flex-direction:column}.title{text-align:left} }
    @media print{body{background:#fff}.bar{display:none}.paper{border:0;margin:0;max-width:none;padding:0} }
</style>
</head>
<body>
<div class="bar">
    <a href="javascript:history.back()">{{ __('Back') }}</a>
    <button type="button" onclick="window.print()">{{ __('Print or save as PDF') }}</button>
</div>
<div class="paper">
    <div class="head">
        <div>
            <h1>{{ $company }}</h1>
            @if (platform('company_address'))<div class="muted">{{ platform('company_address') }}</div>@endif
            @if (platform('support_email'))<div class="muted">{{ platform('support_email') }}</div>@endif
        </div>
        <div class="title">
            <strong>{{ __('RECEIPT') }}</strong>
            <div>{{ $number }}</div>
            <div class="muted">{{ fmt_date($subscription->paid_on) }}</div>
        </div>
    </div>

    <div class="cols">
        <div>
            <div class="label">{{ __('Received from') }}</div>
            <strong>{{ $company?->name }}</strong>
            @if ($company?->email)<div class="muted">{{ $company->email }}</div>@endif
            @if ($company?->phone)<div class="muted">{{ $company->phone }}</div>@endif
            @if ($company?->country)<div class="muted">{{ $company->country }}</div>@endif
        </div>
        <div>
            <div class="label">{{ __('Payment') }}</div>
            <div>{{ $subscription->account?->name ?: '—' }}</div>
            @if ($subscription->reference)<div class="muted">{{ __('Transfer number') }}: {{ $subscription->reference }}</div>@endif
            <div class="muted">{{ __('Paid on') }} {{ fmt_date($subscription->paid_on) }}</div>
        </div>
    </div>

    <table>
        <thead><tr><th>{{ __('Description') }}</th><th>{{ __('Period') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
        <tbody>
            <tr>
                <td><strong>{{ config('app.name') }} · {{ $subscription->plan?->name ?: __('Subscription') }}</strong></td>
                <td>{{ fmt_date($subscription->starts_on) }} – {{ $subscription->ends_on ? fmt_date($subscription->ends_on) : __('no end') }}</td>
                <td class="num">{{ money($subscription->amount, $subscription->currency) }}</td>
            </tr>
            <tr class="total"><td colspan="2" class="num">{{ __('Total paid') }}</td><td class="num">{{ money($subscription->amount, $subscription->currency) }}</td></tr>
        </tbody>
    </table>

    <div class="paid">{{ __('PAID') }}</div>

    <div class="foot muted">{{ __('Thank you. This receipt was made by the system and is valid without a signature.') }}</div>
</div>
</body>
</html>

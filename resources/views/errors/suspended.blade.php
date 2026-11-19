<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $company->name }}</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f5f9;color:#3b4254;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif}main{max-width:460px;margin:24px;padding:32px;background:#fff;border:1px solid #edf0f5;border-radius:16px;text-align:center}h1{margin:0 0 8px;font-size:22px;color:#232a3a}p{margin:0 0 20px;line-height:1.6}a.pay{display:inline-block;background:#0B6B3A;color:#fff;font-weight:600;font-size:15px;padding:11px 22px;border-radius:10px;text-decoration:none}button.out{border:0;background:none;color:#7b8498;font-size:14px;text-decoration:underline;cursor:pointer}</style>
</head>
<body>
<main>
    <h1>{{ $company->name }}</h1>
    @if ($company->status === \App\Models\Company::SUSPENDED)
        <p>{{ __('This account is suspended. Please contact :name support.', ['name' => config('app.name')]) }}</p>
    @elseif ($company->status === \App\Models\Company::TRIAL)
        <p>{{ __('Your free trial ended on :date. Your records are safe. Subscribe to continue.', ['date' => fmt_date($company->trial_ends_at), 'name' => config('app.name')]) }}</p>
    @else
        <p>{{ __('Your subscription ended on :date. Your records are safe. Renew it to continue.', ['date' => fmt_date($company->subscription_ends_at), 'name' => config('app.name')]) }}</p>
    @endif
    @if ($company->status !== \App\Models\Company::SUSPENDED)
        <p><a class="pay" href="{{ route('billing.show') }}">{{ __('See the plan and pay') }}</a></p>
    @endif
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="out">{{ __('Sign out') }}</button></form>
</main>
</body>
</html>

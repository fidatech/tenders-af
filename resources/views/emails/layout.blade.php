<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $heading ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f5f9;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#3b4254;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f9;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e6eaf1;border-radius:12px;">
<tr><td style="padding:22px 24px 6px;">
    <div style="font-size:13px;color:#7b8498;font-weight:600;">{{ $sender ?? config('app.name') }}</div>
    <h1 style="margin:6px 0 0;font-size:20px;line-height:1.3;color:#1f2637;">{{ $heading ?? '' }}</h1>
</td></tr>
<tr><td style="padding:10px 24px 24px;font-size:15px;line-height:1.6;">
@yield('body')
</td></tr>
</table>
<div style="max-width:600px;padding:14px 8px;font-size:12px;color:#8a93a6;">@yield('footer')</div>
</td></tr>
</table>
</body>
</html>

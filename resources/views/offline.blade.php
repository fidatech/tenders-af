@php([$first, $last] = \App\Support\Branding::nameParts())
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#0A2A1A">
<title>You are offline | {{ \App\Support\Branding::name() }}</title>
<style>
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px calc(24px + env(safe-area-inset-right)) calc(24px + env(safe-area-inset-bottom)) calc(24px + env(safe-area-inset-left)); box-sizing: border-box; font: 16px/1.55 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #fff; background: linear-gradient(135deg, #060B08 0%, #0A2A1A 52%, #0B5A33 100%); text-align: center; }
    main { max-width: 420px; }
    .b { font: 800 26px/1 system-ui; letter-spacing: -.5px; } .b b { color: #5FE0A0; }
    h1 { margin: 28px 0 8px; font-size: 22px; }
    p { margin: 0; color: #D5E4DB; }
    button { margin-top: 24px; font: 600 15px system-ui; padding: 13px 26px; border: 0; border-radius: 999px; background: linear-gradient(135deg, #18A05A 0%, #0B6B3A 100%); color: #fff; cursor: pointer; }
</style>
</head>
<body>
<main>
    <div class="b">{{ $first }}<b>{{ $last }}</b></div>
    <h1>You are offline</h1>
    <p>This page needs the internet. Tenders, deadlines, payments and messages are always loaded live, so nothing is shown from an old copy. Check your connection and try again.</p>
    <button type="button" onclick="location.reload()">Try again</button>
</main>
</body>
</html>

{{--
    The company profile as an A4 document (App\Http\Controllers\CompanyPdfController).
    Print-ready: a cover page, sections that do not split badly, page numbers. "Save as PDF" in the
    print window of any browser makes the file. Only facts the company entered are used.
--}}
@php
    $accent = $company->accent();
    $place = collect([$company->address, $company->province?->name, $company->country])->filter()->unique()->join(', ');
    $types = $company->businessTypeLabels();
    $labels = ['phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'website' => 'Website'];
    $brand = \App\Support\Branding::name();
    $facts = array_filter([
        'Registered as' => $company->kindLabel(),
        'Business' => $types ? implode(', ', $types) : null,
        'Founded' => $company->founded_year,
        'Size' => $company->size ? (\App\Models\Company::SIZES[$company->size] ?? null) : null,
        'Licence number' => $company->license_no,
        'Tax number' => $variant === 'detailed' ? $company->tin : null,
        'Location' => $place,
    ]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $company->name }} · {{ $variantLabel }}</title>
<style>
    @font-face { font-family: 'Vazirmatn'; src: url('{{ asset('fonts/vazirmatn.woff2') }}') format('woff2'); font-display: swap; }
    @page { size: A4 portrait; margin: 16mm 15mm 18mm; @bottom-right { content: counter(page) " / " counter(pages); font: 9pt system-ui, sans-serif; color: #6b7280; } @bottom-left { content: "{{ addslashes($company->name) }}"; font: 9pt system-ui, sans-serif; color: #6b7280; } }
    @page :first { margin: 0; @bottom-right { content: none; } @bottom-left { content: none; } }
    :root { --accent: {{ $accent }}; }
    * { box-sizing: border-box; }
    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { margin: 0; background: #e5e7eb; color: #111827; font: 10.5pt/1.55 system-ui, -apple-system, 'Segoe UI', Roboto, 'Vazirmatn', 'Noto Sans Arabic', sans-serif; }
    [dir=rtl], :lang(fa), :lang(ps) { font-family: 'Vazirmatn', 'Noto Sans Arabic', system-ui, sans-serif; }
    .sheet { width: 210mm; min-height: 297mm; margin: 12mm auto; background: #fff; box-shadow: 0 10px 40px rgba(0,0,0,.18); }
    .cover { min-height: 297mm; display: flex; flex-direction: column; justify-content: space-between; padding: 22mm 18mm; color: #fff; background: linear-gradient(150deg, #0b1220 0%, var(--accent) 140%); page-break-after: always; break-after: page; }
    .cover .logo { width: 34mm; height: 34mm; border-radius: 6mm; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; color: var(--accent); font: 800 26pt system-ui; }
    .cover .logo img { width: 100%; height: 100%; object-fit: contain; }
    .cover h1 { margin: 10mm 0 3mm; font-size: 30pt; line-height: 1.1; letter-spacing: -.5pt; }
    .cover .tag { font-size: 14pt; opacity: .9; max-width: 150mm; }
    .cover .kind { margin-top: 6mm; display: flex; flex-wrap: wrap; gap: 2mm; }
    .cover .kind span { border: 1px solid rgba(255,255,255,.45); border-radius: 99px; padding: 1mm 3.5mm; font-size: 9.5pt; }
    .cover .meta { font-size: 9.5pt; opacity: .85; display: flex; justify-content: space-between; gap: 6mm; align-items: flex-end; }
    .body { padding: 16mm 15mm; }
    h2 { font-size: 14pt; margin: 0 0 3mm; padding-left: 3mm; border-left: 1.2mm solid var(--accent); break-after: avoid; }
    section { margin-bottom: 9mm; break-inside: avoid-page; }
    section.flow { break-inside: auto; }
    p { margin: 0 0 2mm; white-space: pre-line; }
    .facts { display: grid; grid-template-columns: 1fr 1fr; gap: 2mm 8mm; margin: 0; }
    .facts div { border-bottom: 1px solid #e5e7eb; padding: 1.5mm 0; }
    .facts dt { font-size: 8pt; text-transform: uppercase; letter-spacing: .6pt; color: #6b7280; }
    .facts dd { margin: 0; font-weight: 600; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
    .box { border: 1px solid #e5e7eb; border-top: .8mm solid var(--accent); border-radius: 2mm; padding: 3mm 4mm; break-inside: avoid; }
    .box b { display: block; }
    .box small { color: #4b5563; }
    .project { display: grid; grid-template-columns: 48mm 1fr; gap: 4mm; border: 1px solid #e5e7eb; border-radius: 2mm; padding: 3mm; margin-bottom: 4mm; break-inside: avoid; }
    .project img { width: 48mm; height: 34mm; object-fit: cover; border-radius: 1.5mm; background: #f3f4f6; }
    .project .nopic { width: 48mm; height: 34mm; border-radius: 1.5mm; background: #f3f4f6; }
    .project b { font-size: 11.5pt; }
    .badge { display: inline-block; font-size: 8pt; font-weight: 600; border-radius: 99px; padding: .4mm 2.4mm; background: #f3f4f6; color: #374151; margin-top: 1mm; }
    .badge.ok { background: #dcfce7; color: #166534; }
    .contact a { color: inherit; text-decoration: none; }
    .note { font-size: 8.5pt; color: #6b7280; }
    .toolbar { position: sticky; top: 0; z-index: 2; display: flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap; padding: 10px; background: #111827; color: #fff; font-size: 14px; }
    .toolbar button, .toolbar a { font: 600 14px system-ui; border: 0; border-radius: 999px; padding: 10px 18px; background: var(--accent); color: #fff; cursor: pointer; text-decoration: none; }
    .toolbar a.ghost { background: transparent; box-shadow: inset 0 0 0 1px rgba(255,255,255,.5); }
    @media print { body { background: #fff; } .sheet { margin: 0; box-shadow: none; width: auto; min-height: 0; } .toolbar { display: none; } }
    @media screen and (max-width: 820px) { .sheet { width: 100%; margin: 0; } .cover { min-height: 0; padding: 12mm 8mm; } .body { padding: 10mm 6mm; } .grid, .facts { grid-template-columns: 1fr; } .project { grid-template-columns: 1fr; } .project img, .project .nopic { width: 100%; height: auto; aspect-ratio: 4/3; } }
</style>
</head>
<body>
<div class="toolbar" role="toolbar" aria-label="Document">
    <span>{{ $variantLabel }}</span>
    <button type="button" onclick="window.print()">Save as PDF or print</button>
    @if ($pageUrl)<a class="ghost" href="{{ $pageUrl }}">Back to the company page</a>@endif
    @if ($own)<a class="ghost" href="{{ route('pdf.show') }}">Back to my panel</a>@endif
</div>

<div class="sheet">
    <div class="cover">
        <div>
            <div class="logo">@if ($company->logo_path && $company->is_listed)<img src="{{ route('site.companies.logo', ['slug' => $company->slug]) }}" alt="Logo of {{ $company->name }}">@else{{ \App\Models\User::initialsOf($company->name) }}@endif</div>
            <h1 dir="auto">{{ $company->name }}</h1>
            @if ($company->tagline)<div class="tag" dir="auto">{{ $company->tagline }}</div>@endif
            @if ($types)<div class="kind">@foreach ($types as $label)<span>{{ $label }}</span>@endforeach</div>@endif
        </div>
        <div class="meta">
            <div>{{ $variantLabel }}<br>{{ $place }}</div>
            <div style="text-align:right">Made {{ now()->format('j F Y') }}@if ($pageUrl)<br>{{ preg_replace('#^https?://#', '', $pageUrl) }}@endif</div>
        </div>
    </div>

    <div class="body">
        <section>
            <h2>About the company</h2>
            <p dir="auto">{{ $company->about ?: 'The company has not written about itself yet.' }}</p>
        </section>

        <section>
            <h2>Company facts</h2>
            <dl class="facts">@foreach ($facts as $label => $value)<div><dt>{{ $label }}</dt><dd dir="auto">{{ $value }}</dd></div>@endforeach</dl>
            <p class="note" style="margin-top:3mm">Business licence: {{ $company->isVerified() ? 'checked by '.$brand.' on '.fmt_date($company->verified_at).'.' : 'not checked by '.$brand.'.' }}</p>
        </section>

        @if ($services && $variant !== 'short')
            <section class="flow">
                <h2>Services</h2>
                <div class="grid">@foreach ($services as $service)<div class="box"><b dir="auto">{{ $service['name'] }}</b>@if ($service['text'])<small dir="auto">{{ $service['text'] }}</small>@endif</div>@endforeach</div>
            </section>
        @endif

        @if ($company->industries->isNotEmpty() && $variant !== 'short')
            <section>
                <h2>Sectors</h2>
                <p>{{ $company->industries->pluck('name')->join(' · ') }}</p>
            </section>
        @endif

        @if ($projects->isNotEmpty())
            <section class="flow">
                <h2>{{ $variant === 'standard' ? 'Selected projects' : 'Projects' }}</h2>
                @foreach ($projects as $project)
                    @php $picture = $project->images->first(); @endphp
                    <div class="project">
                        @if ($picture && $company->is_listed)<img src="{{ route('site.projects.image', ['id' => $picture->id]) }}" alt="">@else<span class="nopic"></span>@endif
                        <div>
                            <b dir="auto">{{ $project->title }}</b>
                            <div class="note">{{ collect([$project->location, $project->period(), $project->publicClient() ? 'Client: '.$project->publicClient() : null, $project->publicValue()])->filter()->join(' · ') }}</div>
                            @if ($project->description)<p dir="auto" style="margin-top:1.5mm">{{ \Illuminate\Support\Str::limit($project->description, $variant === 'detailed' ? 900 : 320) }}</p>@endif
                            <span class="badge {{ $project->isReviewed() ? 'ok' : '' }}">{{ $project->reviewLabel() }}</span>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($products->isNotEmpty())
            <section class="flow">
                <h2>Products</h2>
                <div class="grid">@foreach ($products as $product)<div class="box"><b dir="auto">{{ $product->name }}</b><small>{{ collect([$product->min_order ? 'From '.$product->min_order : null, $product->priceText()])->filter()->join(' · ') }}</small></div>@endforeach</div>
            </section>
        @endif

        <section class="contact">
            <h2>Contact</h2>
            @forelse ($fields as $field => $value)
                <p><strong>{{ $labels[$field] ?? $field }}:</strong> <span dir="ltr">{{ $field === 'website' ? preg_replace('#^https?://#', '', rtrim($value, '/')) : $value }}</span></p>
            @empty
                <p>Contact {{ $company->name }} through {{ $brand }}@if ($pageUrl): {{ preg_replace('#^https?://#', '', $pageUrl) }}@endif.</p>
            @endforelse
            @if ($place)<p><strong>Address:</strong> <span dir="auto">{{ $place }}</span></p>@endif
            @if ($contactsNote)<p class="note">{{ $contactsNote }}</p>@endif
        </section>

        <p class="note">Made with {{ $brand }} from what {{ $company->name }} entered. {{ $brand }} checks only what is marked as checked above.</p>
    </div>
</div>
</body>
</html>

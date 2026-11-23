@extends('layouts.app', ['title' => __('Profile PDF')])

@section('content')
<x-page-header :title="__('Profile PDF')" icon="file-pdf" :subtitle="__('Your company profile as an A4 document, made from what you entered. Open a version, then choose “Save as PDF” to keep it or send it with a bid.')" />

@if ($profile['missing'])
    <div class="alert alert-light border small d-flex gap-2 align-items-start">
        <x-icon name="circle-info" class="mt-1" />
        <span>{{ __('The document only shows what you filled in. Still missing:') }} <strong>{{ collect($profile['missing'])->map(fn ($m) => __($m))->join(', ') }}</strong>. <a href="{{ route('settings.edit') }}">{{ __('Complete the profile') }}</a></span>
    </div>
@endif

<div class="row g-3">
    @foreach ($variants as $key => [$label, $text])
        <div class="col-12 col-md-4">
            <div class="card h-100 mb-0">
                <div class="card-body d-flex flex-column gap-2">
                    <h5 class="mb-0">{{ __($label) }}</h5>
                    <p class="text-muted small mb-2">{{ __($text) }}</p>
                    <a class="btn btn-primary mt-auto" href="{{ route('pdf.print', ['variant' => $key]) }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('Open the public version') }}</a>
                    <a class="btn btn-outline-secondary" href="{{ route('pdf.print', ['variant' => $key, 'contacts' => 'full']) }}" target="_blank" rel="noopener">{{ __('With all my approved contacts') }}</a>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mt-3">
    <div class="card-body small text-muted">
        <p class="mb-1"><strong>{{ __('Public version') }}:</strong> {{ __('the contact details you allowed for everyone. This is also what visitors get from your company page.') }}</p>
        <p class="mb-1"><strong>{{ __('With all my approved contacts') }}:</strong> {{ __('every detail you ticked under “Contact details on your page”, even if your page shows them only to subscribers. For your own use, for example in a bid.') }}</p>
        <p class="mb-0">{{ __('Text in Dari or Pashto is laid out right to left. Pictures appear when your company is shown in the directory.') }}</p>
    </div>
</div>
@endsection

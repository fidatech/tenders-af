@extends('layouts.admin', ['title' => __('Settings')])

@php
    // one box of the form: [name, label, help, width on a 12-column row, type]
    $text = fn (string $name, string $label, ?string $help = null, int $col = 6, string $type = 'text') => compact('name', 'label', 'help', 'col', 'type');
    $groups = [
        [__('Trial and sign-up'), __('What a new company gets.'), [
            $text('trial_days', 'Free trial, in days', 'Used for companies that sign up from now on. 0 means no free trial.', 4, 'number'),
            $text('renew_open_days', 'Renewal opens, days before the end', 'A paid period is not changed or paid back. In its last days the company may renew or choose another plan, which starts when the period ends.', 12, 'number'),
        ]],
        [__('Payments'), __('Shown on the Subscription page of companies and on receipts.'), [
            $text('billing_email', 'Tell these addresses when a company says it has paid', 'Commas between several. Empty: every administrator who may sign in.'),
            $text('receipt_prefix', 'Receipt numbers start with', 'TA-R gives TA-R00001, TA-R00002 ...', 4),
            $text('company_name', 'Name on receipts', 'Empty: the name of the system.', 8),
            $text('company_address', 'Address on receipts', null, 12, 'textarea'),
        ]],
        [__('Emails to companies'), __('Sent by themselves, once each. A company whose payment is being checked is left alone.'), [
            $text('trial_warn_days', 'Warn before the trial ends, days before', 'For example 2. An email also goes on the last day and the day after.'),
            $text('renew_warn_days', 'Warn before a subscription ends, days before', 'For example 7, 1. An email also goes on the last day and the day after.'),
        ]],
        [__('Products for wholesale'), __('Companies show their products with pictures or a short video. A listing is free; being sponsored is paid. A price of 0 means that period is not offered.'), [
            $text('products_free_limit', 'Products a company may list', '0 means no limit.', 4, 'number'),
            $text('sponsor_week_afn', 'Sponsored for a week, AFN', null, 4),
            $text('sponsor_month_afn', 'Sponsored for a month, AFN', null, 4),
            $text('sponsor_week_usd', 'Sponsored for a week, USD', null, 4),
            $text('sponsor_month_usd', 'Sponsored for a month, USD', null, 4),
        ]],
        [__('Public statistics'), __('The Statistics page of the site shows real numbers only.'), [
            $text('stats_min', 'Smallest number that is shown', 'A figure below this stays hidden until it grows. 0 shows everything.', 6, 'number'),
        ]],
        [__('WhatsApp alerts'), __('Tender alerts by WhatsApp go through your own sender service. Each alert is one POST with "to" and "message". Leave the address empty to switch WhatsApp alerts off.'), [
            $text('whatsapp_url', 'Address of the sender service', 'For example https://sender.example.af/api/send', 8, 'url'),
            $text('whatsapp_token', 'Access token', 'Sent as "Authorization: Bearer ...".', 4),
        ]],
        [__('Support'), __('How companies reach you. Shown in emails and on receipts.'), [
            $text('support_email', 'Support email', null, 6, 'email'),
            $text('support_whatsapp', 'Support WhatsApp or phone'),
        ]],
    ];
@endphp

@section('content')
<x-page-header :title="__('Settings')" icon="gear" :subtitle="__('What is saved here is used instead of the values in the .env file.')" />

<form method="POST" action="{{ route('admin.settings.update') }}" class="card h-auto" style="max-width:920px">
    @csrf
    @method('PUT')
    <div class="card-body">
        <div class="row g-3">
            @foreach ($groups as $index => [$title, $intro, $fields])
                <div class="col-12"><div class="{{ $index ? 'border-top pt-3 mt-2' : '' }}"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ $title }}</h6><div class="form-text">{{ $intro }}</div></div></div>
                @if ($index === 0)
                    <div class="col-12 col-md-8">
                        <div class="form-check form-switch mt-md-4 pt-md-2">
                            <input type="hidden" name="registration" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="s-registration" name="registration" value="1" @checked(old('registration', $values['registration'] ? '1' : '0') == '1')>
                            <label class="form-check-label" for="s-registration">{{ __('New companies may sign up by themselves') }}</label>
                            <div class="form-text">{{ __('Switched off: only an administrator opens a company.') }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="form-check form-switch">
                            <input type="hidden" name="posting_free" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="s-posting-free" name="posting_free" value="1" @checked(old('posting_free', $values['posting_free'] ? '1' : '0') == '1')>
                            <label class="form-check-label" for="s-posting-free">{{ __('Posting tenders is free for every buyer') }}</label>
                            <div class="form-text">{{ __('Switched off: a buyer needs a plan with "May post tenders" to post new tenders. Registration stays free, and tenders already published stay online.') }}</div>
                        </div>
                    </div>
                @endif
                @if ($title === __('Products for wholesale'))
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="products_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="s-products" name="products_enabled" value="1" @checked(old('products_enabled', $values['products_enabled'] ? '1' : '0') == '1')>
                            <label class="form-check-label" for="s-products">{{ __('The products module is switched on') }}</label>
                            <div class="form-text">{{ __('Switched off: there is no Products page on the site and companies do not see "My products". Nothing is deleted.') }}</div>
                        </div>
                    </div>
                @endif
                @foreach ($fields as $field)
                    <div class="col-12 col-md-{{ $field['col'] }}">
                        <label class="form-label" for="s-{{ $field['name'] }}">{{ __($field['label']) }}</label>
                        @if ($field['type'] === 'textarea')
                            <textarea class="form-control @error($field['name']) is-invalid @enderror" id="s-{{ $field['name'] }}" name="{{ $field['name'] }}" rows="2">{{ old($field['name'], $values[$field['name']]) }}</textarea>
                        @else
                            <input type="{{ $field['type'] }}" class="form-control @error($field['name']) is-invalid @enderror" id="s-{{ $field['name'] }}" name="{{ $field['name'] }}" value="{{ old($field['name'], $values[$field['name']]) }}" @if ($field['type'] === 'number') min="0" max="365" @endif>
                        @endif
                        @error($field['name'])<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if ($field['help'])<div class="form-text">{{ __($field['help']) }}</div>@endif
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
    <div class="card-footer text-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
</form>
@endsection

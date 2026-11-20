@extends('layouts.app', ['title' => __('Tender alerts')])

@php
    $picked = old('alert_types', $mine);
    $instant = $company->hasInstantAlerts();
    $whatsappPlan = $company->isUsable() && (bool) $company->plan?->whatsapp_alerts;
@endphp

@section('content')
<x-page-header :title="__('Tender alerts')" icon="paper-plane" :subtitle="__('New tenders that match your company are sent to you. Choose how, and for which kinds of tender.')" />

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <form method="POST" action="{{ route('alerts.update') }}" class="d-flex flex-column gap-3">
            @csrf
            @method('PUT')

            <div class="card mb-0">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft primary"><x-icon name="envelope" /></span><div><h5>{{ __('Email') }}</h5><p>{{ $instant ? __('An email the moment a matching tender is published.') : __('On the free plan: one email a week with the titles of matching tenders.') }}</p></div></div>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="alert_email" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="a-email" name="alert_email" value="1" @checked(old('alert_email', $company->alert_email))>
                        <label class="form-check-label" for="a-email">{{ __('Send me tender alerts by email') }}</label>
                    </div>
                    <label class="form-label" for="a-notify">{{ __('Send them to') }}</label>
                    <input type="email" class="form-control @error('notify_email') is-invalid @enderror" id="a-notify" name="notify_email" value="{{ old('notify_email', $company->notify_email) }}" maxlength="255" dir="ltr" placeholder="{{ $company->alertEmail() }}">
                    @error('notify_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('Empty: the email of the company.') }}</div>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft success"><x-icon name="message" /></span><div><h5>{{ __('WhatsApp') }}</h5><p>{{ __('A short message with the title, the buyer, the deadline and a link.') }}</p></div></div>
                </div>
                <div class="card-body">
                    @unless ($whatsappPlan)
                        <div class="alert alert-info py-2">{{ __('WhatsApp alerts are part of the Pro and Business plans.') }} <a class="fw-semibold" href="{{ route('billing.show') }}">{{ __('See plans') }}</a></div>
                    @endunless
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="alert_whatsapp" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="a-whatsapp" name="alert_whatsapp" value="1" @checked($whatsappPlan && old('alert_whatsapp', $company->alert_whatsapp)) @disabled(! $whatsappPlan)>
                        <label class="form-check-label" for="a-whatsapp">{{ __('Yes, send tender alerts to my WhatsApp') }}</label>
                    </div>
                    <label class="form-label" for="a-number">{{ __('WhatsApp number') }}</label>
                    <input type="text" class="form-control @error('whatsapp') is-invalid @enderror" id="a-number" name="whatsapp" value="{{ old('whatsapp', $company->whatsapp) }}" maxlength="50" dir="ltr" placeholder="+93 70 000 0000" @disabled(! $whatsappPlan)>
                    @error('whatsapp')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('With the country code. You can switch the messages off here at any time.') }}</div>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft warning"><x-icon name="filter" /></span><div><h5>{{ __('Kinds of tender') }}</h5><p>{{ __('Tick the kinds you want to hear about. Nothing ticked means all of them.') }}</p></div></div>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach ($types as $key => [$short, $long])
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="t-{{ $key }}" name="alert_types[]" value="{{ $key }}" @checked(in_array($key, $picked, true))>
                                    <label class="form-check-label" for="t-{{ $key }}"><span class="fw-semibold">{{ $short }}</span> · {{ __($long) }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('alert_types')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="save-bar">
                <span class="small text-muted">{{ __('Changes are kept when you press Save.') }}</span>
                <button type="submit" class="btn btn-primary px-4"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
            </div>
        </form>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card h-auto">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Your sectors') }}</h5></div>
            <div class="card-body">
                @if ($industries)
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @foreach ($industries as $name)
                            <span class="badge bg-primary-subtle text-primary-emphasis">{{ $name }}</span>
                        @endforeach
                    </div>
                    @if ($categories)
                        <div class="small text-muted mb-2">{{ implode(' · ', $categories) }}</div>
                    @endif
                @else
                    <p class="text-muted">{{ __('You have not chosen any sector yet, so no tender can be matched.') }}</p>
                @endif
                <a class="btn btn-soft-primary w-100" href="{{ route('settings.edit') }}#sectors"><x-icon name="tags" class="me-1" /> {{ __('Change sectors and categories') }}</a>
            </div>
        </div>
    </div>
</div>
@endsection

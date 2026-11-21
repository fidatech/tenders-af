@extends('layouts.admin', ['title' => $company ? $company->name : __('New company')])

@php
    $value = fn (string $key, $default = null) => old($key, $company?->{$key} ?? $default);
@endphp

@section('content')
<x-page-header :title="$company ? $company->name : __('New company')" icon="building" :crumbs="[[__('Companies'), route('admin.companies.index')], [$company ? __('Edit') : __('Add')]]">
    @if ($company)
        @if ($owner = $company->owner())
            <a class="btn btn-outline-soft" href="{{ route('admin.messages.show', $owner) }}"><x-icon name="envelope" class="me-1" /> {{ __('Message company') }}</a>
        @endif
        <a class="btn btn-primary" href="{{ route('admin.subscriptions.create', ['company' => $company->id]) }}"><x-icon name="receipt" class="me-1" /> {{ __('New subscription') }}</a>
    @endif
</x-page-header>

<div class="row g-3">
    <div class="col-12 {{ $company ? 'col-xl-7' : '' }}">
        <form method="POST" action="{{ $company ? route('admin.companies.update', ['id' => $company->id]) : route('admin.companies.store') }}" class="card mb-0">
            @csrf
            @if ($company)
                @method('PUT')
            @endif
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="t-name">{{ __('Name of the company') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="t-name" name="name" value="{{ $value('name') }}" maxlength="150" required>
                        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    @if ($company)
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="t-email">{{ __('Email address') }}</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="t-email" name="email" value="{{ $value('email') }}" maxlength="255" dir="ltr">
                            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @endif
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="t-phone">{{ __('Phone') }}</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror" id="t-phone" name="phone" value="{{ $value('phone') }}" maxlength="50" dir="ltr">
                        @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="t-country">{{ __('Country') }}</label>
                        <x-country-select id="t-country" :value="old('country', $company?->country)" :class="$errors->has('country') ? 'form-select is-invalid' : 'form-select'" />
                        @error('country')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="t-kind">{{ __('Kind') }} <span class="text-danger">*</span></label>
                        <select class="form-select @error('kind') is-invalid @enderror" id="t-kind" name="kind" required data-plain>
                            @foreach (\App\Models\Company::KINDS as $kindKey => [$kindLabel, $kindCurrency])
                                <option value="{{ $kindKey }}" @selected($value('kind', 'company') === $kindKey)>{{ __($kindLabel) }} ({{ $kindCurrency }})</option>
                            @endforeach
                        </select>
                        @error('kind')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-5 col-md-3">
                        <label class="form-label" for="t-currency">{{ __('Pays in') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase @error('currency') is-invalid @enderror" id="t-currency" name="currency" value="{{ $value('currency', 'AFN') }}" maxlength="3" required dir="ltr">
                        @error('currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-7 col-md-9">
                        <label class="form-label" for="t-timezone">{{ __('Time zone') }} <span class="text-danger">*</span></label>
                        <select class="form-select @error('timezone') is-invalid @enderror" id="t-timezone" name="timezone" required data-search>
                            @foreach ($timezones as $zone)
                                <option value="{{ $zone }}" @selected($value('timezone', 'Asia/Kabul') === $zone)>{{ $zone }}</option>
                            @endforeach
                        </select>
                        @error('timezone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    @unless ($company)
                        <div class="col-12"><div class="border-top pt-3 mt-2"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ __('Owner') }}</h6></div></div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="t-owner-name">{{ __('Full name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('owner_name') is-invalid @enderror" id="t-owner-name" name="owner_name" value="{{ old('owner_name') }}" maxlength="150" required>
                            @error('owner_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="t-owner-email">{{ __('Email address') }} <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('owner_email') is-invalid @enderror" id="t-owner-email" name="owner_email" value="{{ old('owner_email') }}" maxlength="255" required dir="ltr">
                            @error('owner_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="t-owner-password">{{ __('Password') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('owner_password') is-invalid @enderror" id="t-owner-password" name="owner_password" value="{{ old('owner_password') }}" minlength="8" required dir="ltr" autocomplete="off">
                            @error('owner_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('At least 8 characters. Give it to the owner; they can change it.') }}</div>
                        </div>
                    @endunless

                    <div class="col-12"><div class="border-top pt-3 mt-2"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ __('Plan and access') }}</h6></div></div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="t-plan">{{ __('Plan') }}</label>
                        <select class="form-select @error('plan_id') is-invalid @enderror" id="t-plan" name="plan_id" data-plain {{ quick_add(__('New plan'), 'admin.plans.create') }}>
                            <option value="">{{ __('No plan') }}</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected((string) $value('plan_id') === (string) $plan->id)>{{ $plan->label() }}</option>
                            @endforeach
                        </select>
                        @error('plan_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="t-status">{{ __('Status') }} <span class="text-danger">*</span></label>
                        <select class="form-select @error('status') is-invalid @enderror" id="t-status" name="status" required data-plain>
                            @foreach (['trial' => 'Trial: free until the trial date', 'active' => 'Active: works until the paid date', 'suspended' => 'Suspended: nobody can use it'] as $key => $text)
                                <option value="{{ $key }}" @selected($value('status', 'trial') === $key)>{{ __($text) }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="t-trial">{{ __('Trial ends') }}</label>
                        <x-date name="trial_ends_at" id="t-trial" :value="$value('trial_ends_at')" class="{{ $errors->has('trial_ends_at') ? 'is-invalid' : '' }}" />
                        @error('trial_ends_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @unless ($company)<div class="form-text">{{ __('Empty: :days days from today.', ['days' => (int) platform('trial_days')]) }}</div>@endunless
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="t-paid">{{ __('Paid until') }}</label>
                        <x-date name="subscription_ends_at" id="t-paid" :value="$value('subscription_ends_at')" class="{{ $errors->has('subscription_ends_at') ? 'is-invalid' : '' }}" />
                        @error('subscription_ends_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('Empty on an active company: no end (your own company, for example).') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="t-notes">{{ __('Notes (only administrators see them)') }}</label>
                        <textarea class="form-control" id="t-notes" name="notes" rows="2" maxlength="2000">{{ $value('notes') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2 justify-content-end">
                <a class="btn btn-light-secondary" href="{{ route('admin.companies.index') }}">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
            </div>
        </form>
    </div>

    @if ($company)
        <div class="col-12 col-xl-5">
            <div class="card h-auto">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Use') }}</h5></div>
                <div class="card-body">
                    <ul class="list-clean">
                        @foreach ($usage as $label => $count)
                            <li><span class="text-muted small flex-grow-1">{{ __($label) }}</span><span class="fw-semibold">{{ number_format($count) }}</span></li>
                        @endforeach
                        @foreach ($users as $user)
                            <li><span class="min-w-0 flex-grow-1"><span class="d-block fw-semibold text-truncate">{{ $user->name }}</span><span class="d-block small text-muted text-truncate">{{ $user->email }}</span></span><span class="small text-muted">{{ $user->isOwner() ? __('Owner') : __('Member') }}{{ $user->is_active ? '' : ' · '.__('off') }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="card h-auto">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Subscriptions') }}</h5></div>
                <div class="card-body">
                    @if ($subscriptions->isEmpty())
                        <p class="text-muted mb-0">{{ __('No subscription yet.') }}</p>
                    @else
                        <ul class="list-clean">
                            @foreach ($subscriptions as $subscription)
                                <li>
                                    <a class="min-w-0 flex-grow-1 text-decoration-none text-reset" href="{{ route('admin.subscriptions.edit', ['id' => $subscription->id]) }}">
                                        <span class="d-block fw-semibold">{{ $subscription->plan?->name }}</span>
                                        <span class="d-block small text-muted">{{ fmt_date($subscription->starts_on) }} – {{ $subscription->ends_on ? fmt_date($subscription->ends_on) : __('no end') }} · {{ $subscription->paid_on ? __('paid :date', ['date' => fmt_date($subscription->paid_on)]) : __('Not paid yet') }}</span>
                                    </a>
                                    <span class="fw-semibold text-nowrap" dir="ltr">{{ money($subscription->amount, $subscription->currency) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('admin.companies.destroy', ['id' => $company->id]) }}" class="card h-auto border-danger" data-confirm="{{ __('Delete this company and everything it recorded?') }}" data-confirm-ok="{{ __('Delete') }}">
                @csrf
                @method('DELETE')
                <div class="card-body">
                    <h6 class="text-danger">{{ __('Delete this company') }}</h6>
                    <p class="small text-muted">{{ __('Its users, its profile and its payment records are deleted for good. To only stop it, set the status to Suspended. To delete, type its name: :name', ['name' => $company->name]) }}</p>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control @error('confirm') is-invalid @enderror" name="confirm" autocomplete="off" aria-label="{{ __('Name of the company') }}" required>
                        <button type="submit" class="btn btn-soft-danger text-nowrap">{{ __('Delete') }}</button>
                    </div>
                    @error('confirm')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </form>
        </div>
    @endif
</div>
@endsection

@extends('layouts.admin', ['title' => __(\App\Models\OfficeEntry::KINDS[$kind])])

@php
    $kinds = \App\Models\OfficeEntry::KINDS;
    $accountType = fn ($account) => __($account->type === 'cash' ? 'Cash' : (\App\Http\Controllers\Admin\AccountController::TYPES[$account->type] ?? $account->type));
    $intro = [
        'subscription' => __('Money a company paid you for its plan, in cash or by HesabPay. The plan starts once the owner approves it.'),
        'income' => __('Money the office received that is not a subscription.'),
        'expense' => __('Money paid for the office: rent, salaries, internet and the like.'),
        'handover' => __('Money you hand to the owner or the office from what you collected.'),
    ][$kind];
    $field = fn (string $name) => $errors->has($name) ? 'is-invalid' : '';
@endphp

@section('content')
<x-page-header :title="__($kinds[$kind])" icon="receipt" :subtitle="$intro"
    :crumbs="[[__('Office register'), route('admin.office.index')], [__($kinds[$kind])]]" />

<nav class="nav-segment of-kinds mb-3" aria-label="{{ __('Type of entry') }}">
    @foreach ($kinds as $kindKey => $kindLabel)
        <a class="nav-link {{ $kind === $kindKey ? 'active' : '' }}" href="{{ route('admin.office.create', $kindKey === 'subscription' ? [] : ['kind' => $kindKey]) }}" @if ($kind === $kindKey) aria-current="page" @endif>
            <x-icon :name="\App\Models\OfficeEntry::ICONS[$kindKey]" /> {{ __($kindLabel) }}
        </a>
    @endforeach
</nav>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <form method="POST" action="{{ route('admin.office.store') }}" enctype="multipart/form-data" class="card mb-0" data-office-form>
            @csrf
            <input type="hidden" name="kind" value="{{ $kind }}">
            <div class="card-body">
                @if ($accounts->isEmpty())
                    <div class="alert alert-warning d-flex gap-2" role="alert">
                        <x-icon name="triangle-exclamation" class="mt-1" />
                        <span>{{ __('No matching payment account is available. The owner can configure one under Payment methods.') }}</span>
                    </div>
                @elseif ($errors->any())
                    <div class="alert alert-danger d-flex gap-2" role="alert">
                        <x-icon name="triangle-exclamation" class="mt-1" />
                        <span>{{ __('Some details need a second look. They are marked below.') }}</span>
                    </div>
                @endif

                <div class="row g-3">
                    @if ($kind === 'subscription')
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="office-company">{{ __('Company or bidder') }} <span class="text-danger">*</span></label>
                            <select id="office-company" name="company_id" class="form-select {{ $field('company_id') }}" required data-search>
                                <option value="">{{ __('Choose a company') }}</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" data-currency="{{ $company->currency }}" @selected((int) old('company_id') === $company->id)>{{ $company->name }} ({{ $company->currency }})</option>
                                @endforeach
                            </select>
                            @error('company_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="office-plan">{{ __('Subscription plan') }} <span class="text-danger">*</span></label>
                            <select id="office-plan" name="plan_id" class="form-select {{ $field('plan_id') }}" required>
                                <option value="">{{ __('Choose a plan') }}</option>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}" data-currency="{{ $plan->currency }}" data-price="{{ $plan->price }}" @selected((int) old('plan_id') === $plan->id)>{{ $plan->label() }}</option>
                                @endforeach
                            </select>
                            @error('plan_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="office-account">{{ $kind === 'handover' ? __('Account the money comes from') : __('Payment account') }} <span class="text-danger">*</span></label>
                        <select id="office-account" name="platform_account_id" class="form-select {{ $field('platform_account_id') }}" required @disabled($accounts->isEmpty())>
                            <option value="">{{ __('Choose an account') }}</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" data-currency="{{ $account->currency }}" @selected((int) old('platform_account_id') === $account->id)>
                                    {{ $account->name }} · {{ $accountType($account) }} · {{ $account->currency }}
                                </option>
                            @endforeach
                        </select>
                        @error('platform_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="office-amount">{{ __('Amount') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control {{ $field('amount') }}" name="amount" id="office-amount" value="{{ old('amount') }}" step="0.01" min="0.01" max="9999999999.99" inputmode="decimal" dir="ltr" required>
                            <span class="input-group-text" data-office-currency>{{ __('Currency') }}</span>
                        </div>
                        @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if ($kind === 'subscription')
                            <div class="form-text">{{ __('Filled in from the plan. It must be the price of the plan.') }}</div>
                        @endif
                        <div class="form-text text-danger" data-office-mismatch hidden>{{ __('The account, the company and the plan must use the same currency.') }}</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="office-date">{{ $kind === 'handover' ? __('Handover date') : __('Transaction date') }} <span class="text-danger">*</span></label>
                        <input type="date" class="form-control {{ $field('date') }}" name="date" id="office-date" max="{{ now()->toDateString() }}" value="{{ old('date', now()->toDateString()) }}" required>
                        @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    @if ($kind === 'handover')
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="office-period">{{ __('Collected since') }} <span class="text-muted fw-normal">({{ __('optional') }})</span></label>
                            <input type="date" class="form-control {{ $field('period_from') }}" name="period_from" id="office-period" value="{{ old('period_from') }}" max="{{ now()->toDateString() }}">
                            @error('period_from')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('The first day of the period this handover covers.') }}</div>
                        </div>
                    @endif

                    @if ($kind === 'expense')
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="office-category">{{ __('Expense category') }} <span class="text-danger">*</span></label>
                            <select id="office-category" name="category" class="form-select {{ $field('category') }}" required>
                                <option value="">{{ __('Choose a category') }}</option>
                                @foreach (\App\Models\PlatformExpense::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category') === $key)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                            @error('category')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    @if (in_array($kind, ['income', 'expense'], true))
                        <div class="col-12 col-md-6">
                            <span class="form-label d-block" id="office-funds-label">{{ $kind === 'expense' ? __('Paid from') : __('Received into') }}</span>
                            <div class="nav-segment w-100" role="radiogroup" aria-labelledby="office-funds-label">
                                <input type="radio" class="btn-check" name="employee_funds" id="office-funds-0" value="0" @checked(! old('employee_funds'))>
                                <label class="nav-link flex-fill justify-content-center mb-0" for="office-funds-0"><x-icon name="building" /> {{ __('Office funds') }}</label>
                                <input type="radio" class="btn-check" name="employee_funds" id="office-funds-1" value="1" @checked(old('employee_funds'))>
                                <label class="nav-link flex-fill justify-content-center mb-0" for="office-funds-1"><x-icon name="wallet" /> {{ __('My collected funds') }}</label>
                            </div>
                        </div>
                    @endif

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="office-reference">{{ __('Receipt or transfer reference') }}@if ($kind !== 'subscription') <span class="text-muted fw-normal">({{ __('optional') }})</span>@endif</label>
                        <input class="form-control {{ $field('reference') }}" id="office-reference" name="reference" maxlength="150" value="{{ old('reference') }}" dir="ltr">
                        @error('reference')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if ($kind === 'subscription')<div class="form-text">{{ __('Required for HesabPay and other mobile payments.') }}</div>@endif
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="office-proof">{{ __('Receipt or supporting document') }} <span class="text-muted fw-normal">({{ __('optional') }})</span></label>
                        <input class="form-control {{ $field('proof') }}" type="file" name="proof" id="office-proof" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        @error('proof')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('PDF or a picture, up to 10 MB.') }}</div>
                    </div>

                    <div class="col-12">
                        @php($descriptionRequired = in_array($kind, ['income', 'expense'], true))
                        <label class="form-label" for="office-description">{{ $kind === 'expense' ? __('Payee and purpose') : __('Description') }} @if ($descriptionRequired)<span class="text-danger">*</span>@else<span class="text-muted fw-normal">({{ __('optional') }})</span>@endif</label>
                        <textarea class="form-control {{ $field('description') }}" rows="3" id="office-description" name="description" maxlength="250" dir="auto" @required($descriptionRequired)>{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2 justify-content-end">
                <a class="btn btn-light-secondary" href="{{ route('admin.office.index') }}">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary" @disabled($accounts->isEmpty())><x-icon name="paper-plane" class="me-1" /> {{ __('Submit for approval') }}</button>
            </div>
        </form>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card mb-0">
            <div class="card-header"><h5 class="card-title">{{ __('What happens next') }}</h5></div>
            <div class="card-body">
                <ol class="of-steps">
                    <li><div><b>{{ __('You submit the entry') }}</b>{{ __('It is saved as pending, with the receipt you attach.') }}</div></li>
                    <li><div><b>{{ __('The owner reviews it') }}</b>{{ __('Under Office approvals, the owner approves it or rejects it with a reason.') }}</div></li>
                    <li><div><b>{{ __('It counts once approved') }}</b>{{ $kind === 'subscription'
                        ? __('The company\'s plan starts and the money is added to the funds you hold.')
                        : ($kind === 'handover' ? __('The amount leaves the funds you hold.') : __('The amount is added to the account and the reports.')) }}</div></li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// the currency of the chosen account beside the amount; for a collection, the plan's price fills the amount
(function () {
    var form = document.querySelector('[data-office-form]');
    if (!form) return;
    var account = form.querySelector('#office-account'), plan = form.querySelector('#office-plan'), company = form.querySelector('#office-company');
    var amount = form.querySelector('#office-amount'), unit = form.querySelector('[data-office-currency]'), mismatch = form.querySelector('[data-office-mismatch]');
    var pick = function (select) { return select && select.selectedOptions[0] && select.selectedOptions[0].value ? select.selectedOptions[0] : null; };
    var filled = '';
    var sync = function () {
        var chosen = [pick(account), pick(company), pick(plan)].filter(Boolean).map(function (option) { return option.dataset.currency; });
        unit.textContent = chosen[0] || unit.textContent;
        mismatch.hidden = chosen.every(function (currency) { return currency === chosen[0]; });
    };
    if (plan) plan.addEventListener('change', function () {
        var option = pick(plan);
        if (option && (amount.value === '' || amount.value === filled)) amount.value = filled = String(Number(option.dataset.price));
        sync();
    });
    [account, company].forEach(function (select) { if (select) select.addEventListener('change', sync); });
    sync();
})();
</script>
@endpush

@extends('layouts.admin', ['title' => __(\App\Models\OfficeEntry::KINDS[$kind])])

@section('content')
<x-page-header :title="__(\App\Models\OfficeEntry::KINDS[$kind])" icon="receipt">
    <a class="btn btn-outline-soft" href="{{ route('admin.office.index') }}">{{ __('Office register') }}</a>
</x-page-header>

<form method="POST" action="{{ route('admin.office.store') }}" enctype="multipart/form-data" class="card">
    @csrf
    <input type="hidden" name="kind" value="{{ $kind }}">
    <div class="card-body">
        @if ($errors->any())
            <ul class="text-danger mb-3" role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
        @if ($accounts->isEmpty())
            <p class="alert alert-warning">{{ __('No matching payment account is available. The owner can configure one under Payment methods.') }}</p>
        @endif
        <div class="row g-3">
            @if ($kind === 'subscription')
                <div class="col-md-6">
                    <label class="form-label" for="office-company">{{ __('Company or bidder') }}</label>
                    <select id="office-company" name="company_id" class="form-select" required data-search>
                        <option value="">{{ __('Choose a company') }}</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected((int) old('company_id') === $company->id)>{{ $company->name }} ({{ $company->currency }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="office-plan">{{ __('Subscription plan') }}</label>
                    <select id="office-plan" name="plan_id" class="form-select" required>
                        <option value="">{{ __('Choose a plan') }}</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((int) old('plan_id') === $plan->id)>{{ $plan->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-6">
                <label class="form-label" for="office-account">{{ __('Payment account') }}</label>
                <select id="office-account" name="platform_account_id" class="form-select" required>
                    <option value="">{{ __('Choose an account') }}</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('platform_account_id') === $account->id)>
                            {{ $account->name }} · {{ __($account->type === 'cash' ? 'Cash' : (\App\Http\Controllers\Admin\AccountController::TYPES[$account->type] ?? $account->type)) }} · {{ $account->currency }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="office-amount">{{ __('Amount') }}</label>
                <input type="number" class="form-control" name="amount" id="office-amount" value="{{ old('amount') }}" step="0.01" min="0.01" max="9999999999.99" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="office-date">{{ $kind === 'handover' ? __('Handover date') : __('Transaction date') }}</label>
                <input type="date" class="form-control" name="date" id="office-date" max="{{ now()->toDateString() }}" value="{{ old('date', now()->toDateString()) }}" required>
            </div>
            @if ($kind === 'handover')
                <div class="col-md-6">
                    <label class="form-label" for="office-period">{{ __('Period start (optional)') }}</label>
                    <input type="date" class="form-control" name="period_from" id="office-period" value="{{ old('period_from') }}">
                </div>
            @endif
            @if ($kind === 'expense')
                <div class="col-md-6">
                    <label class="form-label" for="office-category">{{ __('Expense category') }}</label>
                    <select id="office-category" name="category" class="form-select" required>
                        <option value="">{{ __('Choose a category') }}</option>
                        @foreach (\App\Models\PlatformExpense::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if (in_array($kind, ['income', 'expense'], true))
                <div class="col-md-6">
                    <label class="form-label" for="office-funds">{{ $kind === 'expense' ? __('Paid from') : __('Received into') }}</label>
                    <select id="office-funds" name="employee_funds" class="form-select">
                        <option value="0" @selected(! old('employee_funds'))>{{ __('Office funds') }}</option>
                        <option value="1" @selected(old('employee_funds'))>{{ __('My collected funds') }}</option>
                    </select>
                </div>
            @endif
            <div class="col-md-6">
                <label class="form-label" for="office-reference">{{ __('Receipt or transfer reference') }}</label>
                <input class="form-control" id="office-reference" name="reference" maxlength="150" value="{{ old('reference') }}">
                @if ($kind === 'subscription')<div class="form-text">{{ __('Required for HesabPay and other mobile payments.') }}</div>@endif
            </div>
            <div class="col-md-6">
                <label class="form-label" for="office-proof">{{ __('Receipt or supporting document') }}</label>
                <input class="form-control" type="file" name="proof" id="office-proof" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </div>
            <div class="col-12">
                <label class="form-label" for="office-description">{{ $kind === 'expense' ? __('Payee and purpose') : __('Description') }}</label>
                <textarea class="form-control" rows="2" id="office-description" name="description" maxlength="250" @required(in_array($kind, ['income', 'expense'], true))>{{ old('description') }}</textarea>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end">
        <button class="btn btn-primary" @disabled($accounts->isEmpty())>{{ __('Submit for approval') }}</button>
    </div>
</form>
@endsection

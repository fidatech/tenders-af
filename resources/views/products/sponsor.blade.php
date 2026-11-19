@extends('layouts.app', ['title' => __('Sponsor a product')])

@php $kinds = ['bank' => 'Bank transfer', 'cash' => 'Cash at our office', 'mobile' => 'Mobile money', 'online' => 'Online payment', 'crypto' => 'Crypto']; @endphp

@section('content')
<x-page-header :title="__('Sponsor :name', ['name' => $product->name])" icon="bullhorn" :crumbs="[[__('My products'), route('products.index')], [__('Sponsor')]]" />

<div class="row g-3">
    <div class="col-12 col-xl-7 d-flex flex-column gap-3">
        <div class="card mb-0 h-auto">
            <div class="card-body">
                <div class="fw-bold mb-1">{{ __('What sponsoring gives') }}</div>
                <ul class="small text-muted mb-0 ps-3">
                    <li>{{ __('Your product is shown before the others on the Products page, with a "Sponsored" label.') }}</li>
                    <li>{{ __('It is shown on the first page of the site.') }}</li>
                    <li>{{ __('You see how many people opened it and how many pressed a contact button.') }}</li>
                </ul>
                @if ($product->isSponsored())
                    <div class="alert alert-success small py-2 mt-3 mb-0">{{ __('Sponsored until :date. A new payment adds its days after that date.', ['date' => fmt_date($product->sponsored_until)]) }}</div>
                @endif
            </div>
        </div>

        @if ($product->status !== 'published')
            <div class="alert alert-warning mb-0">{{ __('Only a published product can be sponsored. Send it for approval first.') }}</div>
        @elseif ($waiting)
            <div class="alert alert-warning mb-0 d-flex align-items-start gap-3"><x-icon name="hourglass-half" class="fs-4 mt-1" /><div><div class="fw-bold">{{ __('Your payment is being checked') }}</div><div class="small">{{ __(':amount :currency for :period, sent on :date.', ['amount' => number_format((float) $waiting->amount), 'currency' => $waiting->currency, 'period' => __(\App\Models\ProductPromotion::PERIODS[$waiting->period][0]), 'date' => fmt_date($waiting->created_at)]) }}</div></div></div>
        @elseif (empty($prices))
            <div class="alert alert-secondary mb-0">{{ __('Sponsoring is not offered at the moment.') }}</div>
        @else
            <form class="card mb-0 h-auto" method="POST" action="{{ route('products.sponsor.store', ['id' => $product->id]) }}" enctype="multipart/form-data">
                @csrf
                <div class="card-header"><h5 class="card-title mb-0">{{ __('1. Choose how long') }}</h5></div>
                <div class="card-body">
                    <div class="role-pick">
                        @foreach ($prices as $period => $price)
                            <label class="role-option"><input type="radio" name="period" value="{{ $period }}" @checked(old('period', array_key_first($prices)) === $period) required><span><strong>{{ __(\App\Models\ProductPromotion::PERIODS[$period][0]) }}</strong><small>{{ number_format($price) }} {{ $company->currency }}</small></span></label>
                        @endforeach
                    </div>
                    @error('period')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="card-header border-top"><h5 class="card-title mb-0">{{ __('2. Pay') }}</h5></div>
                <div class="card-body">
                    @forelse ($accounts as $account)
                        <div class="border rounded p-3 mb-2">
                            <div class="fw-semibold">{{ $account->name }} <span class="small text-muted fw-normal">· {{ __($kinds[$account->type] ?? $account->type) }}</span></div>
                            @if ($account->number)<div class="d-flex align-items-center gap-2 mt-1"><span class="fs-6 fw-bold user-select-all" dir="ltr">{{ $account->number }}</span><button type="button" class="btn btn-sm btn-soft-primary" data-copy="{{ $account->number }}" data-copied="{{ __('Copied') }}"><x-icon name="copy" class="me-1" /><span>{{ __('Copy') }}</span></button></div>@endif
                            @if ($account->details)<p class="small text-muted mt-2 mb-0" style="white-space:pre-line" dir="auto">{{ $account->details }}</p>@endif
                        </div>
                    @empty
                        <div class="alert alert-secondary mb-0">{{ __('Please contact support to pay.') }}</div>
                    @endforelse
                </div>
                <div class="card-header border-top"><h5 class="card-title mb-0">{{ __('3. Send us the proof') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        @if ($accounts->count() > 1)
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="sp-account">{{ __('Paid to') }}</label>
                                <select class="form-select" id="sp-account" name="platform_account_id" data-plain>
                                    <option value="">{{ __('Choose…') }}</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" @selected((string) old('platform_account_id') === (string) $account->id)>{{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif ($accounts->count() === 1)
                            <input type="hidden" name="platform_account_id" value="{{ $accounts->first()->id }}">
                        @endif
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="sp-reference">{{ __('Transaction number') }}</label>
                            <input type="text" class="form-control" id="sp-reference" name="reference" value="{{ old('reference') }}" maxlength="255" dir="ltr">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="sp-receipt">{{ __('Screenshot or photo of the payment') }} <span class="text-danger">*</span></label>
                            <input type="file" class="form-control @error('receipt') is-invalid @enderror" id="sp-receipt" name="receipt" accept="image/*,.pdf" required>
                            @error('receipt')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3"><x-icon name="paper-plane" class="me-1" /> {{ __('I have paid') }}</button>
                </div>
            </form>
        @endif
    </div>

    <div class="col-12 col-xl-5">
        <div class="card mb-0 h-auto">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Earlier payments') }}</h5></div>
            <div class="card-body">
                @forelse ($history as $payment)
                    <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                        <div class="min-w-0"><div class="fw-semibold">{{ __(\App\Models\ProductPromotion::PERIODS[$payment->period][0]) }} · {{ number_format((float) $payment->amount) }} {{ $payment->currency }}</div><div class="small text-muted">@if ($payment->status === 'approved'){{ fmt_date($payment->starts_on) }} – {{ fmt_date($payment->ends_on) }}@else{{ $payment->note }}@endif</div></div>
                        <span class="badge align-self-start {{ $payment->status === 'approved' ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' }}">{{ $payment->status === 'approved' ? __('Confirmed') : __('Not confirmed') }}</span>
                    </div>
                @empty
                    <p class="small text-muted mb-0">{{ __('No payments for this product yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

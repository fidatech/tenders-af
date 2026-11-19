@extends('layouts.admin', ['title' => __('Dashboard')])

@php
    $cards = [
        [__('Companies'), 'building', 'primary', $companies, route('admin.companies.index'), trans_choice('{0} none new this month|{1} 1 new this month|[2,*] :count new this month', $newThisMonth, ['count' => $newThisMonth])],
        [__('Paying'), 'circle-check', 'success', $active, route('admin.companies.index', ['status' => 'active']), null],
        [__('On trial'), 'hourglass-half', 'warning', $trial, route('admin.companies.index', ['status' => 'trial']), null],
        [__('Ended or suspended'), 'ban', 'danger', $endedCount + $suspended, route('admin.companies.index', ['status' => 'ended']), trans_choice('{0} none suspended|[1,*] :count suspended', $suspended, ['count' => $suspended])],
    ];
    $lists = [
        [__('Ending in the next 30 days'), $expiring, 'warning', __('Nobody ends in the next 30 days.')],
        [__('Ended: trial or subscription'), $expired, 'danger', __('Nobody has ended.')],
        [__('Newest companies'), $latest, 'primary', __('No company yet.')],
    ];
@endphp

@section('content')
<x-page-header :title="__('Dashboard')" icon="gauge-high" :subtitle="fmt_date(now())">
    <a class="btn btn-outline-soft" href="{{ route('admin.subscriptions.create') }}"><x-icon name="receipt" class="me-1" /> {{ __('New subscription') }}</a>
    <a class="btn btn-primary" href="{{ route('admin.companies.create') }}"><x-icon name="plus" class="me-1" /> {{ __('New company') }}</a>
</x-page-header>

{{-- payments that wait for a decision come first: this is the work of the day --}}
@if ($waiting->isNotEmpty())
    <div class="card h-auto border-warning">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><x-icon name="triangle-exclamation" class="text-warning me-1" /> {{ trans_choice('{1} 1 company says it has paid|[2,*] :count companies say they have paid', $waiting->count(), ['count' => $waiting->count()]) }}</h5>
            <a class="small text-decoration-none" href="{{ route('admin.subscriptions.index', ['paid' => 'no']) }}">{{ __('See all') }}</a>
        </div>
        <div class="card-body">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Company') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Sent') }}</th><th>{{ __('Paid to') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($waiting as $request)
                        <tr>
                            <td><a class="fw-semibold text-decoration-none" href="{{ route('admin.companies.edit', ['id' => $request->company_id]) }}">{{ $request->company?->name }}</a>@if ($request->reference)<span class="d-block small text-muted" dir="ltr">{{ $request->reference }}</span>@endif</td>
                            <td class="fw-semibold text-nowrap" dir="ltr">{{ money($request->amount, $request->currency) }}</td>
                            <td class="text-nowrap">{{ fmt_date($request->sent_on ?? $request->created_at) }}</td>
                            <td>{{ $request->account?->name ?: '—' }}</td>
                            <td class="text-end text-nowrap">
                                <div class="act-group">
                                    @if ($request->receipt_path)
                                        <a class="btn btn-sm btn-light-secondary" target="_blank" rel="noopener" href="{{ route('admin.subscriptions.receipt', ['id' => $request->id]) }}"><x-icon name="paperclip" class="me-1" />{{ __('Screenshot') }}</a>
                                    @endif
                                    <a class="btn btn-sm btn-soft-success" href="{{ route('admin.subscriptions.edit', ['id' => $request->id, 'confirm' => 1]) }}"><x-icon name="check" class="me-1" />{{ __('Confirm') }}</a>
                                    <a class="btn btn-sm btn-soft-danger" href="{{ route('admin.subscriptions.reject', ['id' => $request->id]) }}"><x-icon name="ban" class="me-1" />{{ __('Reject') }}</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="row g-3 mb-3">
    @foreach ($cards as [$label, $icon, $tone, $count, $url, $foot])
        <div class="col-6 col-xl-3">
            <a href="{{ $url }}" class="card card-hover h-100 mb-0 text-decoration-none text-reset">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2"><span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:38px;height:38px"><x-icon :name="$icon" /></span><span class="small fw-semibold text-muted">{{ $label }}</span></div>
                    <div class="fs-3 fw-bold lh-1">{{ number_format($count) }}</div>
                    @if ($foot)<div class="small text-muted mt-1">{{ $foot }}</div>@endif
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    {{-- money --}}
    <div class="col-12 col-xl-5">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ __('Money received') }}</h5>
                <a class="small text-decoration-none" href="{{ route('admin.accounts.index') }}">{{ __('By account') }}</a>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-6"><div class="metric-box"><span>{{ __('This month') }}</span><strong dir="ltr" style="font-size:18px">{{ sums($monthRevenue) }}</strong></div></div>
                    <div class="col-6"><div class="metric-box"><span>{{ __('This year') }}</span><strong dir="ltr" style="font-size:18px">{{ sums($yearRevenue) }}</strong></div></div>
                </div>
                <ul class="list-clean">
                    @foreach ($months as $month => $totals)
                        <li><span class="flex-grow-1 text-muted">{{ date('F Y', strtotime($month.'-01')) }}</span><span class="fw-semibold" dir="ltr">{{ $totals ? sums($totals) : '—' }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-7">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ __('Latest payments') }}</h5>
                <a class="small text-decoration-none" href="{{ route('admin.subscriptions.index', ['paid' => 'yes']) }}">{{ __('See all') }}</a>
            </div>
            <div class="card-body">
                @if ($payments->isEmpty())
                    <p class="text-muted mb-0">{{ __('No payment has been confirmed yet.') }}</p>
                @else
                    <ul class="list-clean">
                        @foreach ($payments as $payment)
                            <li>
                                <span class="sc-icon icon-soft success flex-shrink-0" style="width:36px;height:36px"><x-icon name="arrow-down" /></span>
                                <a class="min-w-0 flex-grow-1 text-decoration-none text-reset" href="{{ route('admin.companies.edit', ['id' => $payment->company_id]) }}">
                                    <span class="d-block fw-semibold text-truncate">{{ $payment->company?->name }}</span>
                                    <span class="d-block small text-muted text-truncate">{{ fmt_date($payment->paid_on) }} · {{ $payment->account?->name ?: __('no account chosen') }}</span>
                                </a>
                                <span class="fw-semibold text-nowrap bal-due" dir="ltr">+{{ money($payment->amount, $payment->currency) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    @foreach ($lists as [$heading, $rows, $tone, $empty])
        <div class="col-12 col-xl-4">
            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ $heading }}</h5></div>
                <div class="card-body">
                    @if ($rows->isEmpty())
                        <p class="text-muted mb-0">{{ $empty }}</p>
                    @else
                        <ul class="list-clean">
                            @foreach ($rows as $company)
                                <li>
                                    <span class="avatar avatar-sm avatar-soft-{{ $tone }} flex-shrink-0">{{ \App\Models\User::initialsOf($company->name) }}</span>
                                    <a class="min-w-0 flex-grow-1 text-decoration-none text-reset" href="{{ route('admin.companies.edit', ['id' => $company->id]) }}">
                                        <span class="d-block fw-semibold text-truncate">{{ $company->name }}</span>
                                        <span class="d-block small text-muted">{{ $company->plan?->name ?: __('No plan') }} · {{ __($company->statusLabel()) }}</span>
                                    </a>
                                    <span class="small text-muted text-nowrap">{{ $company->endsOn() ? fmt_date($company->endsOn()) : '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection

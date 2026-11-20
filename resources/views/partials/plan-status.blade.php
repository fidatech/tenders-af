{{--
    Where the company stands with its plan: status, plan, the period that is running with a bar,
    and the days left. Shown on the dashboard (with a button) and on the Subscription page.
    @include('partials.plan-status', ['company' => $company, 'link' => true])
--}}
@php
    $info = $company->period();
    $planNow = $company->plan;
    $ok = $company->isUsable();
    $onTrial = $company->status === 'trial';
    $onFree = $company->onFreePlan();
    $noEnd = $company->status === 'active' && $info['to'] === null;
    $daysLeft = $info['left'];
    $tone = $company->status === 'suspended' || ! $ok ? 'danger' : ($onTrial || ($daysLeft !== null && $daysLeft <= 30) ? 'warning' : 'success');
    $bar = ['danger' => 'bg-danger', 'warning' => 'bg-warning', 'success' => 'bg-success'][$tone];
    $icons = ['danger' => 'triangle-exclamation', 'warning' => 'hourglass-half', 'success' => 'circle-check'];
    $per = ['monthly' => __('a month'), 'yearly' => __('a year'), 'lifetime' => __('once')];
    [$title, $sub] = match (true) {
        $company->status === 'suspended' => [__('Suspended'), __('Please contact support.')],
        $onFree => [__('You are on the :plan plan', ['plan' => $planNow?->name ?? __('Free')]), __('Nothing to pay and no end date. Upgrade when you need more.')],
        $noEnd => [__('Your subscription is active'), __('No end date')],
        ! $ok && $onTrial => [__('Your free trial has ended'), __('It ended on :date. Your company profile stays online. Pay to open the full tenders and the alerts again.', ['date' => fmt_date($info['to'])])],
        ! $ok => [__('Your subscription has ended'), __('It ended on :date. Your company profile stays online. Pay to open the full tenders and the alerts again.', ['date' => fmt_date($info['to'])])],
        $onTrial => [$daysLeft > 0 ? trans_choice('{1} 1 day of free trial left|[2,*] :count days of free trial left', $daysLeft, ['count' => $daysLeft]) : __('Your free trial ends today'), __('Free until :date', ['date' => fmt_date($info['to'])])],
        default => [__('Your subscription is active'), __('Paid until :date', ['date' => fmt_date($info['to'])])],
    };
    $badge = match (true) {
        $company->status === 'suspended' => [__('Suspended'), 'bg-danger-subtle text-danger-emphasis'],
        ! $ok => [__('Ended'), 'bg-danger-subtle text-danger-emphasis'],
        $onTrial => [__('Free trial'), 'bg-warning-subtle text-warning-emphasis'],
        $onFree => [__('Free'), 'bg-success-subtle text-success-emphasis'],
        default => [__('Active'), 'bg-success-subtle text-success-emphasis'],
    };
    $action = ! $ok ? __('Pay to continue') : ($onTrial ? __('Choose a plan') : ($onFree ? __('Upgrade') : __('Subscription')));
@endphp
<div class="card h-auto">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:52px;height:52px;font-size:21px"><x-icon :name="$icons[$tone]" /></span>
            <div class="min-w-0 flex-grow-1">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="fw-bold fs-5">{{ $title }}</span>
                    <span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span>
                </div>
                <div class="small text-muted">
                    @if ($planNow && ! $onFree)<span class="fw-semibold text-body">{{ $planNow->name }}</span> · <span dir="ltr">{{ $planNow->priceText() }}</span> / {{ $planNow->isFree() ? __('forever') : ($per[$planNow->interval] ?? $planNow->interval) }} · @endif{{ $sub }}
                </div>
            </div>
            @if ($ok && $daysLeft !== null && $company->status !== 'suspended')
                <div class="text-center flex-shrink-0 px-2">
                    <div class="fw-bold lh-1" style="font-size:34px">{{ number_format($daysLeft) }}</div>
                    <div class="small text-muted">{{ trans_choice('{1} day left|[0,*] days left', $daysLeft) }}</div>
                </div>
            @endif
            @if (! empty($link))
                <a class="btn {{ $ok && ! $onTrial ? 'btn-outline-soft' : 'btn-primary' }} flex-shrink-0" href="{{ route('billing.show') }}"><x-icon name="credit-card" class="me-1" /> {{ $action }}</a>
            @endif
        </div>

        @if ($info['from'] && $info['to'] && $company->status !== 'suspended')
            <div class="progress mt-3" style="height:8px" role="progressbar" aria-label="{{ __('Time used') }}" aria-valuenow="{{ $info['percent'] }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar {{ $bar }}" style="width:{{ max(2, $info['percent']) }}%"></div></div>
            <div class="d-flex justify-content-between gap-2 small text-muted mt-2">
                <span><span class="d-block text-uppercase" style="font-size:10px;letter-spacing:.06em">{{ $onTrial ? __('Trial started') : __('Period started') }}</span><span class="fw-semibold text-body">{{ fmt_date($info['from']) }}</span></span>
                <span class="text-center align-self-end">{{ trans_choice('{1} 1 day of :total used|[0,*] :count days of :total used', $info['used'], ['count' => number_format($info['used']), 'total' => number_format($info['total'])]) }}</span>
                <span class="text-end"><span class="d-block text-uppercase" style="font-size:10px;letter-spacing:.06em">{{ $ok ? __('Ends') : __('Ended') }}</span><span class="fw-semibold text-body">{{ fmt_date($info['to']) }}</span></span>
            </div>
        @endif
    </div>
</div>

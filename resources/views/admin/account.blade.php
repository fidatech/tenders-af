@extends('layouts.admin', ['title' => $account->name])

@php
    $kinds = \App\Http\Controllers\Admin\AccountController::TYPES;
    $self = fn (array $query = []) => route('admin.accounts.show', ['id' => $account->id] + $query);
    $top = max($chart) ?: 1;
    $searching = $term !== '' || $period !== 'all';
    $tiles = [
        [__('This month'), sums($month), 'success', 'calendar-day'],
        [__('This year'), sums($year), 'primary', 'calendar'],
        [__('All time'), sums($all), 'purple', 'sack-dollar'],
    ];
@endphp

@section('content')
<x-page-header :title="$account->name" icon="building-columns" :crumbs="[[__('Financial accounts'), route('admin.accounts.index')], [$account->name]]">
    <a class="btn btn-outline-soft" href="{{ $self(request()->only('period', 'from', 'to', 'q') + ['export' => 'csv']) }}"><x-icon name="file-excel" class="me-1" /> {{ __('Excel') }}</a>
    @if (auth('admin')->user()->isSuper())
    <a class="btn btn-outline-soft" href="{{ route('admin.accounts.edit', ['id' => $account->id]) }}"><x-icon name="pen" class="me-1" /> {{ __('Edit') }}</a>
    <a class="btn btn-primary" href="{{ route('admin.subscriptions.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Record a payment') }}</a>
    @endif
</x-page-header>

{{-- the account itself --}}
<div class="card h-auto">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="sc-icon icon-soft primary flex-shrink-0" style="width:56px;height:56px;font-size:22px"><x-icon :name="$account->type === 'bank' ? 'building-columns' : 'wallet'" /></span>
            <div class="min-w-0 flex-grow-1">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="fs-5 fw-bold">{{ $account->name }}</span>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $account->currency }}</span>
                    @if ($account->is_active)
                        <span class="badge bg-success-subtle text-success-emphasis">{{ __('In use: companies see it') }}</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger-emphasis">{{ __('Switched off: hidden from companies') }}</span>
                    @endif
                </div>
                <div class="small text-muted mt-1">{{ __($kinds[$account->type] ?? $account->type) }}</div>
                @if ($account->number)
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <span class="fw-bold user-select-all" dir="ltr">{{ $account->number }}</span>
                        <button type="button" class="btn btn-sm btn-light-secondary" data-copy="{{ $account->number }}" data-copied="{{ __('Copied') }}"><x-icon name="copy" class="me-1" /><span>{{ __('Copy') }}</span></button>
                    </div>
                @endif
            </div>
            @if ($account->qr_path)
                <a href="{{ route('admin.accounts.qr', ['id' => $account->id]) }}" target="_blank" rel="noopener" class="flex-shrink-0" title="{{ __('QR code') }}"><img src="{{ route('admin.accounts.qr', ['id' => $account->id]) }}" alt="{{ __('QR code') }}" class="border rounded" style="width:84px;height:auto;background:#fff"></a>
            @endif
            <div class="text-end flex-shrink-0">
                <div class="small text-muted">{{ __('Last payment') }}</div>
                @if ($last)
                    <div class="fw-semibold" dir="ltr">{{ money($last->amount, $last->currency) }}</div>
                    <div class="small text-muted">{{ fmt_date($last->paid_on) }} · {{ $last->company?->name }}</div>
                @else
                    <div class="fw-semibold">—</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- totals --}}
<div class="row g-3 mb-3">
    @foreach ($tiles as [$label, $value, $tone, $icon])
        <div class="col-6 col-xl-3">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2"><span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:36px;height:36px"><x-icon :name="$icon" /></span><span class="small fw-semibold text-muted">{{ $label }}</span></div>
                    <div class="fs-5 fw-bold" dir="ltr">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
    <div class="col-6 col-xl-3">
        <div class="card h-100 mb-0">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2"><span class="sc-icon icon-soft warning flex-shrink-0" style="width:36px;height:36px"><x-icon name="scale-balanced" /></span><span class="small fw-semibold text-muted">{{ __('Balance') }}</span></div>
                <div class="fs-5 fw-bold" dir="ltr">{{ $balance ? collect($balance)->map(fn ($amount, $currency) => money($amount, $currency))->implode(' · ') : '0' }}</div>
                <div class="small text-muted mt-1">{{ trans_choice('{0} no payments|{1} 1 payment|[2,*] :count payments', $count, ['count' => $count]) }} · {{ __('spent') }} <span dir="ltr">{{ sums($spent) }}</span></div>
                @if ($officeIncome)<div class="small">{{ __('Other income') }} · {{ sums($officeIncome) }}</div>@endif
                @if ($held)
                    <div class="small">{{ __('Held by employees') }} · {{ sums($held) }}</div>
                    <div class="small">{{ __('Office balance') }} · {{ sums($officeBalance) }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="d-flex flex-column gap-3">
            {{-- the last twelve months --}}
            <div class="card mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">{{ __('Received each month') }}</h5>
                    <span class="small text-muted">{{ $account->currency }}</span>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-end gap-1" style="height:150px" role="img" aria-label="{{ __('Received each month') }}">
                        @foreach ($chart as $key => $amount)
                            <div class="flex-fill d-flex flex-column justify-content-end align-items-center h-100" title="{{ date('F Y', strtotime($key.'-01')) }}: {{ money($amount, $account->currency) }}">
                                @if ($amount > 0)<span class="text-muted mb-1 d-none d-md-block" style="font-size:10px" dir="ltr">{{ number_format($amount) }}</span>@endif
                                <div class="w-100 rounded-top" style="height:{{ $amount > 0 ? max(4, round(100 * $amount / $top)) : 1 }}%;min-height:2px;background:{{ $amount > 0 ? 'var(--bs-primary)' : 'var(--bs-border-color)' }};opacity:{{ $loop->last ? '1' : '.75' }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex gap-1 mt-1">
                        @foreach ($chart as $key => $amount)
                            <div class="flex-fill text-center text-muted" style="font-size:10px">{{ date('M', strtotime($key.'-01')) }}</div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- the payments --}}
            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Where the money came from') }}</h5></div>
                <div class="card-body">
                    <div class="d-flex flex-nowrap gap-2 mb-3 overflow-auto pb-1">
                        @foreach ($periods as $key => $label)
                            <a href="{{ $self(['period' => $key] + ($term !== '' ? ['q' => $term] : [])) }}" class="btn btn-sm text-nowrap {{ $period === $key ? 'btn-primary' : 'btn-light-secondary' }}">{{ __($label) }}</a>
                        @endforeach
                        <button type="button" class="btn btn-sm text-nowrap {{ $period === 'custom' ? 'btn-primary' : 'btn-light-secondary' }}" data-bs-toggle="collapse" data-bs-target="#customDates" aria-expanded="{{ $period === 'custom' ? 'true' : 'false' }}">{{ __('Other dates') }}</button>
                    </div>

                    <form method="GET" class="row g-2 mb-3">
                        @if ($period !== 'custom' && $period !== 'all')<input type="hidden" name="period" value="{{ $period }}">@endif
                        <div class="col-12 collapse {{ $period === 'custom' ? 'show' : '' }}" id="customDates">
                            <div class="row g-2">
                                <div class="col-6"><label class="form-label small mb-1" for="st-from">{{ __('From') }}</label><input type="date" class="form-control" id="st-from" name="from" value="{{ $period === 'custom' ? $from : '' }}"></div>
                                <div class="col-6"><label class="form-label small mb-1" for="st-to">{{ __('To') }}</label><input type="date" class="form-control" id="st-to" name="to" value="{{ $period === 'custom' ? $to : '' }}"></div>
                            </div>
                        </div>
                        <div class="col">
                            <input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search a company or a transfer number…') }}" aria-label="{{ __('Search') }}">
                        </div>
                        <div class="col-auto"><button class="btn btn-light-secondary" type="submit"><x-icon name="magnifying-glass" /></button></div>
                    </form>

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge bg-primary-subtle text-primary-emphasis fw-semibold p-2">{{ trans_choice('{0} No payments|{1} 1 payment|[2,*] :count payments', $filteredCount, ['count' => $filteredCount]) }} · <span dir="ltr">{{ sums($filtered) }}</span></span>
                        @if ($period === 'custom')
                            <span class="small text-muted">{{ $from ? fmt_date($from) : '…' }} – {{ $to ? fmt_date($to) : __('today') }}</span>
                        @endif
                        @if ($searching)
                            <a class="small text-decoration-none" href="{{ $self() }}">{{ __('Show everything') }}</a>
                        @endif
                    </div>

                    @if ($rows->isEmpty())
                        <div class="empty-state">
                            <div class="es-icon"><x-icon name="building-columns" /></div>
                            <h3>{{ $searching ? __('Nothing found') : __('No money received here yet') }}</h3>
                            <p>{{ $searching ? __('Try another period or another word.') : __('A payment appears here when you confirm it and choose this account under "Received in".') }}</p>
                        </div>
                    @else
                        <table class="table table-hover w-100">
                            <thead><tr><th>{{ __('From') }}</th><th>{{ __('Paid on') }}</th><th>{{ __('For') }}</th><th>{{ __('Transfer number') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="avatar avatar-sm avatar-soft-primary flex-shrink-0">{{ \App\Models\User::initialsOf($row->company?->name) }}</span>
                                                <span class="min-w-0"><a class="fw-semibold text-decoration-none" href="{{ route('admin.companies.edit', ['id' => $row->company_id]) }}">{{ $row->company?->name }}</a><span class="d-block small text-muted">{{ $row->company?->email }}</span></span>
                                            </div>
                                        </td>
                                        <td class="text-nowrap">{{ fmt_date($row->paid_on) }}</td>
                                        <td>{{ $row->plan?->name }}<span class="d-block small text-muted text-nowrap">{{ fmt_date($row->starts_on) }} – {{ $row->ends_on ? fmt_date($row->ends_on) : __('no end') }}</span></td>
                                        <td dir="ltr">{{ $row->reference ?: '—' }}</td>
                                        <td class="text-end fw-semibold text-nowrap bal-due" dir="ltr">+{{ money($row->amount, $row->currency) }}</td>
                                        <td class="text-end text-nowrap">
                                            <div class="act-group">
                                                @if ($row->receipt_path)
                                                    <a class="btn btn-sm btn-light-secondary" target="_blank" rel="noopener" href="{{ route('admin.subscriptions.receipt', ['id' => $row->id]) }}"><x-icon name="paperclip" class="me-1" />{{ __('Screenshot') }}</a>
                                                @endif
                                                <x-act kind="edit" :href="route('admin.subscriptions.edit', ['id' => $row->id])" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        {{ $rows->links('partials.pagination') }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="d-flex flex-column gap-3">
            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Who paid the most') }}</h5></div>
                <div class="card-body">
                    @if ($payers->isEmpty())
                        <p class="text-muted mb-0">{{ __('Nobody yet.') }}</p>
                    @else
                        <ul class="list-clean">
                            @foreach ($payers as $payer)
                                <li>
                                    <span class="avatar avatar-sm avatar-soft-primary flex-shrink-0">{{ \App\Models\User::initialsOf($payer->company?->name) }}</span>
                                    <a class="min-w-0 flex-grow-1 text-decoration-none text-reset" href="{{ $self(['q' => $payer->company?->name]) }}">
                                        <span class="d-block fw-semibold text-truncate">{{ $payer->company?->name }}</span>
                                        <span class="d-block small text-muted">{{ trans_choice('{1} 1 payment|[2,*] :count payments', $payer->payments, ['count' => $payer->payments]) }}</span>
                                    </a>
                                    <span class="fw-semibold text-nowrap" dir="ltr">{{ money($payer->total, $payer->currency) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">{{ __('Paid out of this account') }}</h5>
                    <a class="small text-decoration-none" href="{{ route('admin.expenses.index', ['account' => $account->id]) }}">{{ __('See all') }}</a>
                </div>
                <div class="card-body">
                    @if ($expenses->isEmpty())
                        <p class="text-muted mb-0">{{ __('No expense was paid from this account.') }}</p>
                    @else
                        <ul class="list-clean">
                            @foreach ($expenses as $expense)
                                <li>
                                    <span class="min-w-0 flex-grow-1"><span class="d-block fw-semibold text-truncate">{{ $expense->description ?: $expense->categoryLabel() }}</span><span class="d-block small text-muted">{{ fmt_date($expense->date) }} · {{ $expense->categoryLabel() }}</span></span>
                                    <span class="fw-semibold text-nowrap bal-owe" dir="ltr">−{{ money($expense->amount, $expense->currency) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">{{ __('What companies see') }}</h5>
                    <a class="small text-decoration-none" href="{{ route('admin.accounts.edit', ['id' => $account->id]) }}">{{ __('Edit') }}</a>
                </div>
                <div class="card-body">
                    @if (! $account->is_active)
                        <p class="text-muted">{{ __('Nothing: this account is switched off.') }}</p>
                    @endif
                    @if ($account->qr_path)
                        <div class="text-center mb-3"><img src="{{ route('admin.accounts.qr', ['id' => $account->id]) }}" alt="{{ __('QR code') }}" class="border rounded" style="width:180px;max-width:100%;height:auto;background:#fff" loading="lazy"></div>
                    @endif
                    @if ($account->number)
                        <div class="fw-bold text-center mb-2" dir="ltr">{{ $account->number }}</div>
                    @endif
                    @if ($account->details)
                        <p class="small text-muted mb-0" style="white-space:pre-line" dir="auto">{{ $account->details }}</p>
                    @elseif (! $account->qr_path && ! $account->number)
                        <p class="text-muted mb-0">{{ __('No number, QR code or instructions yet. Add them so companies know how to pay.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    var button = e.target.closest('[data-copy]');
    if (!button || !navigator.clipboard) return;
    navigator.clipboard.writeText(button.getAttribute('data-copy')).then(function () {
        var label = button.querySelector('span') || button, text = label.textContent;
        label.textContent = button.getAttribute('data-copied');
        setTimeout(function () { label.textContent = text; }, 1500);
    });
});
</script>
@endpush

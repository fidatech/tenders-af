@extends('layouts.admin', ['title' => __('Reports')])

@php
    $monthName = fn (int $m) => date('F', mktime(0, 0, 0, $m, 1));
    $categories = \App\Models\PlatformExpense::CATEGORIES;
@endphp

@section('content')
<x-page-header :title="__('Reports')" icon="chart-line" :subtitle="__('Money received, money spent and profit.')">
    <form method="GET">
        <select class="form-select" name="year" onchange="this.form.submit()" aria-label="{{ __('Year') }}" data-plain>
            @foreach ($years as $option)
                <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
            @endforeach
        </select>
    </form>
    <a class="btn btn-primary" href="{{ route('admin.expenses.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add an expense') }}</a>
</x-page-header>

<div class="row g-3 mb-3">
    @foreach ([[__('Signed up in :year', ['year' => $year]), number_format($signed), 'primary', 'building'], [__('Of them, paid'), number_format($paying), 'success', 'circle-check'], [__('Trial to paid'), $conversion === null ? '—' : $conversion.'%', 'purple', 'chart-line']] as [$label, $value, $tone, $icon])
        <div class="col-12 col-md-4">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2"><span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:36px;height:36px"><x-icon :name="$icon" /></span><span class="small fw-semibold text-muted">{{ $label }}</span></div>
                    <div class="fs-4 fw-bold">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@forelse ($months as $currency => $byMonth)
    @php
        $in = 0;
        $out = 0;
    @endphp
    <div class="card h-auto">
        <div class="card-header"><h5 class="card-title mb-0">{{ __('Profit of :year', ['year' => $year]) }} · {{ $currency }}</h5></div>
        <div class="card-body">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end">{{ __('Spent') }}</th><th class="text-end">{{ __('Profit') }}</th></tr></thead>
                <tbody>
                    @for ($m = 1; $m <= 12; $m++)
                        @php
                            $received = $byMonth[$m]['in'] ?? 0;
                            $spent = $byMonth[$m]['out'] ?? 0;
                            $in += $received;
                            $out += $spent;
                        @endphp
                        @continue(! $received && ! $spent)
                        <tr>
                            <td>{{ $monthName($m) }}</td>
                            <td class="text-end" dir="ltr">{{ money($received) }}</td>
                            <td class="text-end" dir="ltr">{{ money($spent) }}</td>
                            <td class="text-end fw-semibold {{ $received - $spent < 0 ? 'bal-owe' : 'bal-due' }}" dir="ltr">{{ money($received - $spent) }}</td>
                        </tr>
                    @endfor
                    <tr class="fw-bold">
                        <td>{{ __('Whole year') }}</td>
                        <td class="text-end" dir="ltr">{{ money($in) }}</td>
                        <td class="text-end" dir="ltr">{{ money($out) }}</td>
                        <td class="text-end {{ $in - $out < 0 ? 'bal-owe' : 'bal-due' }}" dir="ltr">{{ money($in - $out) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="card h-auto"><div class="card-body"><p class="text-muted mb-0">{{ __('No money received or spent in :year.', ['year' => $year]) }}</p></div></div>
@endforelse

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Received by plan') }} · {{ $year }}</h5></div>
            <div class="card-body">
                @if ($byPlan->isEmpty())
                    <p class="text-muted mb-0">{{ __('Nothing yet.') }}</p>
                @else
                    <table class="table table-hover w-100">
                        <thead><tr><th>{{ __('Plan') }}</th><th class="text-end">{{ __('Payments') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                            @foreach ($byPlan as $row)
                                <tr><td>{{ $row->plan ?: __('No plan') }}</td><td class="text-end">{{ $row->payments }}</td><td class="text-end fw-semibold" dir="ltr">{{ money($row->total, $row->currency) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ __('Spent by category') }} · {{ $year }}</h5>
                <a class="small text-decoration-none" href="{{ route('admin.expenses.index') }}">{{ __('See all') }}</a>
            </div>
            <div class="card-body">
                @if ($byCategory->isEmpty())
                    <p class="text-muted mb-0">{{ __('No expense recorded yet. Add your server, domain and other costs to see the real profit.') }}</p>
                @else
                    <table class="table table-hover w-100">
                        <thead><tr><th>{{ __('Category') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                            @foreach ($byCategory as $row)
                                <tr><td>{{ __($categories[$row->category] ?? $row->category) }}</td><td class="text-end fw-semibold" dir="ltr">{{ money($row->total, $row->currency) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

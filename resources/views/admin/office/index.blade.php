@extends('layouts.admin', ['title' => $review ? __('Office approvals') : __('Office register')])

@section('content')
<x-page-header :title="$review ? __('Office approvals') : __('Office register')" icon="receipt">
    @if ($review)
        <a class="btn btn-outline-soft" href="{{ route('admin.office.index') }}">{{ __('Office register') }}</a>
    @else
        <a class="btn btn-primary" href="{{ route('admin.office.create') }}">{{ __('Record collection') }}</a>
        <a class="btn btn-outline-soft" href="{{ route('admin.office.create', ['kind' => 'expense']) }}">{{ __('Record expense') }}</a>
        <a class="btn btn-outline-soft" href="{{ route('admin.office.create', ['kind' => 'income']) }}">{{ __('Record income') }}</a>
        <a class="btn btn-outline-soft" href="{{ route('admin.office.create', ['kind' => 'handover']) }}">{{ __('Record handover') }}</a>
    @endif
</x-page-header>

@if ($errors->any())
    <ul class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<li class="ms-3">{{ $error }}</li>@endforeach</ul>
@endif

<div class="card mb-3">
    <div class="card-header"><h2 class="h6 mb-0">{{ __('Approved funds held by employees') }}</h2></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('Funds held') }}</th></tr></thead>
            <tbody>
                @forelse ($balances as $balance)
                    <tr><td>{{ $balance->employee->name }}</td><td>{{ $balance->account->name }}</td><td class="text-end text-nowrap">{{ money($balance->held, $balance->currency) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">{{ __('No approved employee funds have been recorded.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-body pb-0">
        @php
            $filterUrl = route($review ? 'admin.office.approvals' : 'admin.office.index');
            $hasDates = request()->filled('from') || request()->filled('to');
            $hasFilters = $term !== '' || $hasDates || request()->filled('kind') || $employeeId || $status !== ($review ? 'pending' : null);
            $choices = [
                ['kind', __('All types'), array_map('__', \App\Models\OfficeEntry::KINDS), request('kind')],
                ['status', __('All statuses'), array_map('__', \App\Models\OfficeEntry::STATUSES), $status],
            ];
            if ($employee->isSuper()) {
                $choices[] = ['employee', __('All employees'), $employees, $employeeId];
            }
        @endphp
        <x-filter-bar :action="$filterUrl" :term="$term" :placeholder="__('Search company, reference or description…')"
            :limit="150" :reset="$hasFilters ? $filterUrl : null">
            @foreach ($choices as [$name, $label, $options, $selected])
                <div class="col-6 col-xl-auto">
                    <select class="form-select" name="{{ $name }}" aria-label="{{ $label }}" onchange="this.form.requestSubmit()" data-plain>
                        <option value="">{{ $label }}</option>
                        @foreach ($options as $value => $text)
                            <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-auto">
                <button type="button" class="btn {{ $hasDates ? 'btn-primary' : 'btn-light-secondary' }}" data-bs-toggle="collapse"
                    data-bs-target="#office-filter-dates" aria-controls="office-filter-dates" aria-expanded="{{ $hasDates ? 'true' : 'false' }}">
                    <x-icon name="calendar" class="me-1" />{{ __('Date range') }}
                </button>
            </div>
            <x-slot:extra>
                <div class="col-12 collapse {{ $hasDates ? 'show' : '' }}" id="office-filter-dates">
                    <div class="row g-2">
                        <div class="col-6 col-md-auto">
                            <label class="form-label small mb-1" for="office-from">{{ __('From') }}</label>
                            <input class="form-control" type="date" id="office-from" name="from" value="{{ request('from') }}">
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="form-label small mb-1" for="office-to">{{ __('To') }}</label>
                            <input class="form-control" type="date" id="office-to" name="to" value="{{ request('to') }}">
                        </div>
                    </div>
                </div>
            </x-slot:extra>
        </x-filter-bar>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th>{{ __('Entry') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th>@if ($review)<th>{{ __('Review') }}</th>@endif</tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>
                            <strong class="d-block">{{ __(\App\Models\OfficeEntry::KINDS[$entry->kind]) }} #{{ $entry->id }}</strong>
                            <span class="d-block small text-muted">{{ $entry->period_from ? fmt_date($entry->period_from).' – ' : '' }}{{ fmt_date($entry->date) }}</span>
                            @if ($entry->company)<span class="d-block">{{ $entry->company->name }} · {{ $entry->plan?->name }}</span>@endif
                            @if ($entry->category)<span class="d-block">{{ __(\App\Models\PlatformExpense::CATEGORIES[$entry->category] ?? $entry->category) }}</span>@endif
                            @if ($entry->description)<span class="d-block">{{ $entry->description }}</span>@endif
                            @if ($entry->reference)<span class="d-block small">{{ $entry->reference }}</span>@endif
                            @if ($employee->isSuper() && $entry->subscription?->receipt_path)<a class="d-block small" href="{{ route('admin.subscriptions.receipt', $entry->subscription_id) }}">{{ __('Customer payment proof') }}</a>@endif
                            @if ($entry->proof_path)<a class="d-block small" href="{{ route('admin.office.proof', $entry->id) }}">{{ __('Download proof') }}</a>@endif
                        </td>
                        <td>{{ $entry->employee->name }}</td>
                        <td>
                            {{ $entry->account->name }}
                            <span class="d-block small text-muted">{{ $entry->employee_funds ? __('Employee funds') : __('Office funds') }}</span>
                        </td>
                        <td class="text-end text-nowrap">{{ money($entry->amount, $entry->currency) }}</td>
                        <td>
                            <span class="badge {{ match($entry->status) { 'approved' => 'bg-success', 'rejected' => 'bg-danger', default => 'bg-warning text-dark' } }}">{{ __(\App\Models\OfficeEntry::STATUSES[$entry->status]) }}</span>
                            @if ($entry->reviewer)<div class="small text-muted">{{ $entry->reviewer->name }} · {{ fmt_date($entry->reviewed_at) }}</div>@endif
                            @if ($entry->review_note)<div class="small">{{ $entry->review_note }}</div>@endif
                        </td>
                        @if ($review)
                            <td>
                                @if ($entry->status === 'pending')
                                    <form method="POST" action="{{ route('admin.office.review', $entry->id) }}" style="min-width:13rem">
                                        @csrf
                                        <input class="form-control form-control-sm mb-2" name="review_note" aria-label="{{ __('Review note') }}" placeholder="{{ __('Reason required to reject') }}" maxlength="250">
                                        <button name="decision" value="approved" class="btn btn-sm btn-primary">{{ $entry->kind === 'handover' ? __('Confirm receipt') : __('Approve') }}</button>
                                        <button name="decision" value="rejected" class="btn btn-sm btn-outline-danger">{{ __('Reject') }}</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $review ? 6 : 5 }}" class="text-muted">{{ __('No entries match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $entries->links('partials.pagination') }}
@endsection

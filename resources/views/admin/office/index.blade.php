@extends('layouts.admin', ['title' => $review ? __('Office approvals') : __('Office register')])

@php
    $isOwner = $employee->isSuper();
    $here = $review ? 'admin.office.approvals' : 'admin.office.index';
    $filterUrl = route($here);
    $hasDates = request()->filled('from') || request()->filled('to');
    $hasFilters = $term !== '' || $hasDates || request()->filled('kind') || $employeeId;
    $resetUrl = route($here, request()->has('status') ? ['status' => $status ?? ''] : []);
    // the status tabs keep the other filters; "All" sends an empty status so the approvals page does not fall back to "Pending"
    $tabUrl = fn (?string $key) => route($here, array_filter(request()->except('status', 'page'), fn ($value) => $value !== null && $value !== '') + ['status' => $key ?? '']);
    $tabs = [null => __('All')] + array_map('__', \App\Models\OfficeEntry::STATUSES);
    $waiting = \App\Models\OfficeEntry::where('status', 'pending')->when(! $isOwner, fn ($query) => $query->where('employee_id', $employee->id))->count();
    $statusBadge = ['approved' => 'success', 'rejected' => 'danger', 'pending' => 'warning'];
    $choices = [['kind', __('All types'), array_map('__', \App\Models\OfficeEntry::KINDS), request('kind')]];
    if ($isOwner) {
        $choices[] = ['employee', __('All employees'), $employees, $employeeId];
    }
@endphp

@section('content')
<x-page-header :title="$review ? __('Office approvals') : __('Office register')" icon="receipt"
    :subtitle="$waiting ? trans_choice('{1} 1 entry waits for approval|[2,*] :count entries wait for approval', $waiting, ['count' => $waiting]) : __('Nothing waits for approval')">
    @if ($review)
        <a class="btn btn-outline-soft" href="{{ route('admin.office.index') }}"><x-icon name="receipt" class="me-1" /> {{ __('Office register') }}</a>
    @else
        @if ($isOwner && $waiting)
            <a class="btn btn-outline-soft" href="{{ route('admin.office.approvals') }}"><x-icon name="check-double" class="me-1" /> {{ __('Office approvals') }} <span class="badge bg-danger rounded-pill ms-1">{{ $waiting }}</span></a>
        @endif
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><x-icon name="plus" class="me-1" /> {{ __('New entry') }}</button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach (\App\Models\OfficeEntry::KINDS as $kindKey => $kindLabel)
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.office.create', $kindKey === 'subscription' ? [] : ['kind' => $kindKey]) }}"><span class="of-ico of-ico-sm of-{{ $kindKey }}"><x-icon :name="\App\Models\OfficeEntry::ICONS[$kindKey]" /></span>{{ __($kindLabel) }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif
</x-page-header>

@if ($errors->any())
    <div class="alert alert-danger d-flex gap-2" role="alert">
        <x-icon name="triangle-exclamation" class="mt-1" />
        <ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

{{-- what each employee still holds from approved collections, until it is handed over --}}
<div class="card h-auto">
    <div class="card-header">
        <div>
            <h5 class="card-title">{{ $isOwner ? __('Funds held by employees') : __('Funds you hold') }}</h5>
            <p class="card-subtitle">{{ __('Approved collections and income, less approved expenses and handovers.') }}</p>
        </div>
        @if (! $review)
            <a class="btn btn-sm btn-soft-warning" href="{{ route('admin.office.create', ['kind' => 'handover']) }}"><x-icon name="hand-holding-dollar" class="me-1" />{{ __('Record handover') }}</a>
        @endif
    </div>
    <div class="card-body">
        @if ($balances->isEmpty())
            <p class="text-muted small mb-0">{{ __('No approved employee funds have been recorded.') }}</p>
        @else
            <div class="row g-2">
                @foreach ($balances as $balance)
                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="of-held">
                            @if ($isOwner)
                                <span class="avatar avatar-sm avatar-soft-primary">{{ $balance->employee->initials() }}</span>
                            @else
                                <span class="of-ico"><x-icon name="wallet" /></span>
                            @endif
                            <div class="min-w-0 flex-grow-1">
                                <div class="small fw-semibold text-truncate">{{ $isOwner ? $balance->employee->name : $balance->account->name }}</div>
                                @if ($isOwner)<div class="small text-muted text-truncate">{{ $balance->account->name }}</div>@endif
                            </div>
                            <div class="of-amount text-nowrap" dir="ltr">{{ money($balance->held, $balance->currency) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <nav class="seg" aria-label="{{ __('Status') }}">
                @foreach ($tabs as $key => $label)
                    @php($key = $key === '' ? null : $key)
                    <a href="{{ $tabUrl($key) }}" class="{{ $status === $key ? 'active' : '' }}" @if ($status === $key) aria-current="page" @endif>{{ $label }}@if ($key === 'pending' && $waiting) <span class="badge bg-danger rounded-pill ms-1">{{ $waiting }}</span>@endif</a>
                @endforeach
            </nav>
            <span class="small text-muted">{{ trans_choice('{0} No entries|{1} 1 entry|[2,*] :count entries', $entries->total(), ['count' => number_format($entries->total())]) }}</span>
        </div>

        <x-filter-bar :action="$filterUrl" :term="$term" :placeholder="__('Search company, reference or description…')"
            :limit="150" :reset="$hasFilters ? $resetUrl : null">
            @if (request()->has('status'))<input type="hidden" name="status" value="{{ $status }}">@endif
            @foreach ($choices as [$name, $label, $options, $selected])
                <div class="col-6 col-md-auto">
                    <select class="form-select" name="{{ $name }}" aria-label="{{ $label }}" onchange="this.form.requestSubmit()" data-plain>
                        <option value="">{{ $label }}</option>
                        @foreach ($options as $value => $text)
                            <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-auto">
                <button type="button" class="btn {{ $hasDates ? 'btn-soft-primary' : 'btn-light-secondary' }}" data-bs-toggle="collapse"
                    data-bs-target="#office-filter-dates" aria-controls="office-filter-dates" aria-expanded="{{ $hasDates ? 'true' : 'false' }}">
                    <x-icon name="calendar" class="me-1" />{{ __('Date range') }}
                </button>
            </div>
            <x-slot:extra>
                <div class="col-12 collapse {{ $hasDates ? 'show' : '' }}" id="office-filter-dates">
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-auto">
                            <label class="form-label small mb-1" for="office-from">{{ __('From') }}</label>
                            <input class="form-control" type="date" id="office-from" name="from" value="{{ request('from') }}">
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="form-label small mb-1" for="office-to">{{ __('To') }}</label>
                            <input class="form-control" type="date" id="office-to" name="to" value="{{ request('to') }}">
                        </div>
                        <div class="col-12 col-md-auto">
                            <button class="btn btn-soft-primary w-100" type="submit">{{ __('Apply dates') }}</button>
                        </div>
                    </div>
                </div>
            </x-slot:extra>
        </x-filter-bar>

        @if ($entries->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="{{ $review ? 'check-double' : 'receipt' }}" /></div>
                @if ($hasFilters)
                    <h3>{{ __('No entries match these filters.') }}</h3>
                    <p>{{ __('Change the search or the filters, or clear them to see every entry.') }}</p>
                @elseif ($review && $status === 'pending')
                    <h3>{{ __('Nothing waits for approval') }}</h3>
                    <p>{{ __('New collections, income, expenses and handovers from employees appear here.') }}</p>
                @else
                    <h3>{{ __('No entries yet') }}</h3>
                    @unless ($review)<p>{{ __('Press "New entry" to record a collection, an expense, other income or a handover.') }}</p>@endunless
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Entry') }}</th>
                            @if ($isOwner)<th scope="col">{{ __('Employee') }}</th>@endif
                            <th scope="col">{{ __('Account') }}</th>
                            <th scope="col" class="text-end">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col" class="text-end no-print">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            @php($kindLabel = __(\App\Models\OfficeEntry::KINDS[$entry->kind]))
                            <tr>
                                <td>
                                    <div class="of-entry">
                                        <span class="of-ico of-{{ $entry->kind }}"><x-icon :name="\App\Models\OfficeEntry::ICONS[$entry->kind]" /></span>
                                        <div class="min-w-0">
                                            <div class="fw-semibold">{{ $kindLabel }} <span class="text-muted fw-normal">#{{ $entry->id }}</span></div>
                                            <div class="small text-muted">
                                                {{ $entry->period_from ? fmt_date($entry->period_from).' – ' : '' }}{{ fmt_date($entry->date) }}
                                                @if ($entry->reference) · <span dir="ltr">{{ $entry->reference }}</span>@endif
                                            </div>
                                            @if ($entry->company)<div class="small" dir="auto">{{ $entry->company->name }}@if ($entry->plan) · {{ $entry->plan->name }}@endif</div>@endif
                                            @if ($entry->category)<div class="small">{{ __(\App\Models\PlatformExpense::CATEGORIES[$entry->category] ?? $entry->category) }}</div>@endif
                                            @if ($entry->description)<div class="of-desc" dir="auto" title="{{ $entry->description }}">{{ \Illuminate\Support\Str::limit($entry->description, 120) }}</div>@endif
                                        </div>
                                    </div>
                                </td>
                                @if ($isOwner)
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar avatar-xs avatar-soft-primary">{{ $entry->employee->initials() }}</span>
                                            <span>{{ $entry->employee->name }}</span>
                                        </div>
                                    </td>
                                @endif
                                <td>
                                    {{ $entry->account->name }}
                                    <span class="d-block small text-muted">{{ $entry->employee_funds ? __('Employee funds') : __('Office funds') }}</span>
                                </td>
                                <td class="text-end text-nowrap fw-semibold {{ $entry->kind === 'expense' ? 'text-danger' : ($entry->kind === 'handover' ? '' : 'text-success') }}" dir="ltr">{{ money($entry->amount, $entry->currency) }}</td>
                                <td>
                                    <span class="badge-soft {{ $statusBadge[$entry->status] }}"><span class="dot"></span>{{ __(\App\Models\OfficeEntry::STATUSES[$entry->status]) }}</span>
                                    @if ($entry->reviewer)<div class="small text-muted mt-1">{{ $entry->reviewer->name }} · {{ fmt_date($entry->reviewed_at) }}</div>@endif
                                    @if ($entry->review_note)<div class="of-note small mt-1 {{ $entry->status === 'rejected' ? 'text-danger' : '' }}" dir="auto">{{ $entry->review_note }}</div>@endif
                                </td>
                                <td class="text-end text-nowrap no-print">
                                    <div class="act-group">
                                        @if ($isOwner && $entry->subscription?->receipt_path)
                                            <a class="btn btn-sm btn-light-secondary" href="{{ route('admin.subscriptions.receipt', $entry->subscription_id) }}" target="_blank" rel="noopener" title="{{ __('Customer payment proof') }}" aria-label="{{ __('Customer payment proof') }}"><x-icon name="image" class="me-1" />{{ __('Customer') }}</a>
                                        @endif
                                        @if ($entry->proof_path)
                                            <a class="btn btn-sm btn-light-secondary" href="{{ route('admin.office.proof', $entry->id) }}" title="{{ __('Download proof') }}" aria-label="{{ __('Download proof') }}"><x-icon name="paperclip" class="me-1" />{{ __('Proof') }}</a>
                                        @endif
                                        @if ($review && $entry->status === 'pending')
                                            <form method="POST" action="{{ route('admin.office.review', $entry->id) }}" class="d-inline"
                                                data-confirm="{{ $entry->kind === 'handover' ? __('Confirm that you received this handover?') : __('Approve this entry?') }}"
                                                data-confirm-text="{{ $kindLabel }} #{{ $entry->id }} · {{ money($entry->amount, $entry->currency) }} · {{ $entry->employee->name }}"
                                                data-confirm-ok="{{ $entry->kind === 'handover' ? __('Confirm receipt') : __('Approve') }}" data-confirm-style="primary">
                                                @csrf
                                                <input type="hidden" name="decision" value="approved">
                                                <button type="submit" class="btn btn-sm btn-soft-success"><x-icon name="check" class="me-1" />{{ $entry->kind === 'handover' ? __('Confirm receipt') : __('Approve') }}</button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-soft-danger" data-bs-toggle="modal" data-bs-target="#office-reject-{{ $entry->id }}"><x-icon name="ban" class="me-1" />{{ __('Reject') }}</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $entries->links('partials.pagination') }}
        @endif
    </div>
</div>

{{-- one window per waiting entry: a rejection always needs a reason, which the employee reads --}}
@if ($review)
    @foreach ($entries->where('status', 'pending') as $entry)
        <div class="modal fade" id="office-reject-{{ $entry->id }}" tabindex="-1" aria-labelledby="office-reject-title-{{ $entry->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('admin.office.review', $entry->id) }}" class="modal-content">
                    @csrf
                    <input type="hidden" name="decision" value="rejected">
                    <div class="modal-header">
                        <h5 class="modal-title" id="office-reject-title-{{ $entry->id }}"><span class="mt-icon is-danger"><x-icon name="ban" /></span>{{ __('Reject entry #:id', ['id' => $entry->id]) }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="of-held mb-3">
                            <span class="of-ico of-{{ $entry->kind }}"><x-icon :name="\App\Models\OfficeEntry::ICONS[$entry->kind]" /></span>
                            <div class="min-w-0 flex-grow-1">
                                <div class="small fw-semibold">{{ __(\App\Models\OfficeEntry::KINDS[$entry->kind]) }}</div>
                                <div class="small text-muted text-truncate">{{ $entry->employee->name }} · {{ $entry->account->name }}</div>
                            </div>
                            <div class="of-amount text-nowrap" dir="ltr">{{ money($entry->amount, $entry->currency) }}</div>
                        </div>
                        <label class="form-label" for="office-note-{{ $entry->id }}">{{ __('Reason') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="office-note-{{ $entry->id }}" name="review_note" rows="3" maxlength="250" dir="auto" required
                            placeholder="{{ __('For example: the amount does not match the receipt.') }}"></textarea>
                        <div class="form-text">{{ __('The employee sees this reason in the register.') }}</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-danger"><x-icon name="ban" class="me-1" />{{ __('Reject') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endif
@endsection

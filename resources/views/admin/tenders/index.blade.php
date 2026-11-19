@extends('layouts.admin', ['title' => __('Tenders')])

@php
    $pill = ['soon' => 'bg-danger-subtle text-danger-emphasis', 'open' => 'bg-success-subtle text-success-emphasis', 'closed' => 'bg-secondary-subtle text-secondary-emphasis', 'awarded' => 'bg-primary-subtle text-primary-emphasis', 'cancelled' => 'bg-secondary-subtle text-secondary-emphasis', 'draft' => 'bg-warning-subtle text-warning-emphasis'];
@endphp

@section('content')
<x-page-header :title="__('Tenders')" icon="gavel" :subtitle="__(':published published, :draft drafts', ['published' => number_format($counts['published'] ?? 0), 'draft' => number_format($counts['draft'] ?? 0)])">
    <a class="btn btn-primary" href="{{ route('admin.tenders.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add tender') }}</a>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md">
                <input type="search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search by title, buyer or number…') }}" aria-label="{{ __('Search') }}">
            </div>
            <div class="col-6 col-md-auto">
                <select class="form-select" name="status" aria-label="{{ __('Status') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Any status') }}</option>
                    @foreach (\App\Models\Tender::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ __($label) }} ({{ number_format($counts[$key] ?? 0) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <select class="form-select" name="industry" aria-label="{{ __('Sector') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('All sectors') }}</option>
                    @foreach ($industries as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['industry'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="gavel" /></div>
                <h3>{{ __('No tenders yet') }}</h3>
                <p>{{ __('Press "Add tender" to enter the first one.') }}</p>
            </div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Tender') }}</th><th>{{ __('Sector') }}</th><th>{{ __('Province') }}</th><th>{{ __('Deadline') }}</th><th>{{ __('Status') }}</th><th>{{ __('Views') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $tender)
                        <tr>
                            <td><a class="fw-semibold text-decoration-none" href="{{ route('admin.tenders.edit', ['id' => $tender->id]) }}">{{ $tender->title }}</a><span class="d-block small text-muted"><span dir="ltr">{{ $tender->number() }}</span> · {{ $tender->typeLabel() }} · {{ $tender->buyer_name }}@if ($tender->documents_count) · {{ trans_choice('{1} 1 document|[2,*] :count documents', $tender->documents_count, ['count' => $tender->documents_count]) }}@endif</span></td>
                            <td>{{ $tender->industry?->name }}</td>
                            <td>{{ $tender->placeText() }}</td>
                            <td class="text-nowrap">{{ $tender->deadline ? fmt_datetime($tender->deadline) : '—' }}</td>
                            <td><span class="badge {{ $pill[$tender->state()] }}">{{ $tender->stateLabel() }}</span>@if ($tender->alerted_at)<span class="d-block small text-muted">{{ __('Alerts sent') }}</span>@endif</td>
                            <td>{{ number_format($tender->views) }}</td>
                            <td class="text-end text-nowrap">
                                <div class="act-group">
                                    @if ($tender->status !== 'draft')
                                        <a class="btn btn-sm btn-light-secondary" href="{{ route('site.tenders.show', ['slug' => $tender->slug]) }}" target="_blank" rel="noopener"><x-icon name="eye" /></a>
                                    @endif
                                    <x-act kind="edit" :href="route('admin.tenders.edit', ['id' => $tender->id])" />
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
@endsection

@extends('layouts.app', ['title' => __('Posted tenders')])

@php
    $pill = ['soon' => 'bg-danger-subtle text-danger-emphasis', 'open' => 'bg-success-subtle text-success-emphasis', 'closed' => 'bg-secondary-subtle text-secondary-emphasis', 'awarded' => 'bg-primary-subtle text-primary-emphasis', 'cancelled' => 'bg-secondary-subtle text-secondary-emphasis', 'draft' => 'bg-secondary-subtle text-secondary-emphasis', 'pending' => 'bg-warning-subtle text-warning-emphasis', 'rejected' => 'bg-danger-subtle text-danger-emphasis'];
    $total = array_sum($counts);
@endphp

@section('content')
<x-page-header :title="__('Posted tenders')" icon="bullhorn" :subtitle="__('The tenders of :name. We check each one, publish it and tell the companies of its sector.', ['name' => $company->name])">
    @if ($canPost)
        <a class="btn btn-primary" href="{{ route('posted.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Post a tender') }}</a>
    @endif
</x-page-header>

@if (! $canPost)
    <div class="alert alert-warning d-flex align-items-center gap-3">
        <x-icon name="credit-card" class="fs-4" />
        <div class="flex-grow-1 min-w-0"><div class="fw-bold">{{ __('Posting new tenders needs a plan') }}</div><div class="small">{{ __('Your published tenders stay online. To post new ones, choose a plan that includes posting.') }}</div></div>
        <a class="btn btn-warning flex-shrink-0" href="{{ route('billing.show') }}">{{ __('See the plans') }}</a>
    </div>
@elseif (\App\Support\Platform::bool('posting_free'))
    <div class="alert alert-success small py-2"><x-icon name="gift" class="me-1" /> {{ __('Posting tenders is free at the moment.') }}</div>
@endif

<div class="card">
    <div class="card-body">
        @if ($total)
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a class="btn btn-sm {{ $status === null ? 'btn-primary' : 'btn-outline-soft' }}" href="{{ route('posted.index') }}">{{ __('All') }} ({{ number_format($total) }})</a>
                @foreach (\App\Models\Tender::STATUSES as $key => $label)
                    @if (! empty($counts[$key]))
                        <a class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-soft' }}" href="{{ route('posted.index', ['status' => $key]) }}">{{ __($label) }} ({{ number_format($counts[$key]) }})</a>
                    @endif
                @endforeach
            </div>
        @endif

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="bullhorn" /></div>
                <h3>{{ __('No tenders posted yet') }}</h3>
                <p>{{ __('Post your tender and reach the registered companies of its sector, in every province.') }}</p>
                @if ($canPost)
                    <a class="btn btn-primary" href="{{ route('posted.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Post a tender') }}</a>
                @endif
            </div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Tender') }}</th><th>{{ __('Sector') }}</th><th>{{ __('Deadline') }}</th><th>{{ __('Status') }}</th><th>{{ __('Views') }}</th><th>{{ __('Saved by') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $tender)
                        <tr>
                            <td><a class="fw-semibold text-decoration-none" href="{{ route('posted.edit', ['id' => $tender->id]) }}">{{ $tender->title }}</a><span class="d-block small text-muted"><span dir="ltr">{{ $tender->number() }}</span> · {{ $tender->typeLabel() }} · {{ $tender->placeText() }}@if ($tender->documents_count) · {{ trans_choice('{1} 1 document|[2,*] :count documents', $tender->documents_count, ['count' => $tender->documents_count]) }}@endif</span>@if ($tender->status === 'rejected')<span class="d-block small text-danger">{{ __($tender->reject_reason) }}</span>@endif</td>
                            <td>{{ $tender->industry?->name }}</td>
                            <td class="text-nowrap">{{ $tender->deadline ? fmt_datetime($tender->deadline) : '—' }}</td>
                            <td><span class="badge {{ $pill[$tender->state()] }}">{{ $tender->stateLabel() }}</span></td>
                            <td>{{ number_format($tender->views) }}</td>
                            <td>{{ number_format($tender->saves) }}</td>
                            <td class="text-end text-nowrap">
                                <div class="act-group">
                                    @if (! $tender->isEditableByBuyer())
                                        <a class="btn btn-sm btn-light-secondary" href="{{ route('site.tenders.show', ['slug' => $tender->slug]) }}" target="_blank" rel="noopener" aria-label="{{ __('See on the site') }}"><x-icon name="eye" /></a>
                                    @endif
                                    <x-act kind="edit" :href="route('posted.edit', ['id' => $tender->id])" />
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

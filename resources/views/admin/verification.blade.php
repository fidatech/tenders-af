@extends('layouts.admin', ['title' => __('Verification')])

@php
    $tabs = ['pending' => 'Waiting', 'verified' => 'Verified', 'rejected' => 'Refused'];
@endphp

@section('content')
<x-page-header :title="__('Verification')" icon="shield-halved" :subtitle="trans_choice('{0} No company is waiting|{1} 1 company is waiting|[2,*] :count companies are waiting', $waiting, ['count' => $waiting])" />

<div class="card">
    <div class="card-body">
        <div class="btn-group mb-3" role="group" aria-label="{{ __('Status') }}">
            @foreach ($tabs as $key => $label)
                <a class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.verification.index', ['status' => $key]) }}">{{ __($label) }}</a>
            @endforeach
        </div>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="shield-halved" /></div>
                <h3>{{ __('Nothing here') }}</h3>
            </div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Company') }}</th><th>{{ __('Licence number') }}</th><th>{{ __('Province') }}</th><th>{{ __('Sent on') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $company)
                        <tr>
                            <td><a class="fw-semibold text-decoration-none" href="{{ route('admin.companies.edit', ['id' => $company->id]) }}">{{ $company->name }}</a><span class="d-block small text-muted">{{ $company->kindLabel() }} · {{ $company->email }}</span>@if ($status === 'rejected' && $company->verify_note)<span class="d-block small text-danger">{{ $company->verify_note }}</span>@endif</td>
                            <td dir="ltr">{{ $company->license_no ?: '—' }}@if ($company->tin)<span class="d-block small text-muted">TIN {{ $company->tin }}</span>@endif</td>
                            <td>{{ $company->province?->name ?: ($company->country ?: '—') }}</td>
                            <td class="text-nowrap">{{ fmt_date($company->verify_requested_at) }}</td>
                            <td class="text-end">
                                <div class="act-group flex-wrap justify-content-end">
                                    @if ($company->verify_doc_path)
                                        <a class="btn btn-sm btn-light-secondary" href="{{ route('admin.verification.document', ['id' => $company->id]) }}" target="_blank" rel="noopener"><x-icon name="file-lines" class="me-1" />{{ __('Licence') }}</a>
                                    @endif
                                    @if ($status === 'pending')
                                        <form method="POST" action="{{ route('admin.verification.approve', ['id' => $company->id]) }}" class="d-inline" data-confirm="{{ __('Verify :name?', ['name' => $company->name]) }}" data-confirm-ok="{{ __('Verify') }}" data-confirm-style="primary">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-soft-success"><x-icon name="circle-check" class="me-1" />{{ __('Verify') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.verification.reject', ['id' => $company->id]) }}" class="d-inline-flex gap-1">
                                            @csrf
                                            <select class="form-select form-select-sm" name="reason" aria-label="{{ __('Reason') }}" required data-plain style="max-width:220px">
                                                <option value="">{{ __('Refuse because…') }}</option>
                                                @foreach ($reasons as $key => $text)
                                                    <option value="{{ $key }}">{{ __($text) }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-soft-danger">{{ __('Refuse') }}</button>
                                        </form>
                                    @endif
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

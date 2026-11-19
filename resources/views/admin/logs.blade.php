@extends('layouts.admin', ['title' => __('Activity log')])

@section('content')
<x-page-header :title="__('Activity log')" icon="clock-rotate-left" :subtitle="__('What each administrator did, newest first.')" />

<div class="card h-auto">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md">
                <input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search a company, an action…') }}" aria-label="{{ __('Search') }}">
            </div>
            <div class="col-12 col-md-auto">
                <select class="form-select" name="admin" aria-label="{{ __('Administrator') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Administrator') }}</option>
                    @foreach ($admins as $id => $name)
                        <option value="{{ $id }}" @selected((string) $adminId === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto"><input type="date" class="form-control" name="from" value="{{ $from }}" title="{{ __('From') }}" aria-label="{{ __('From') }}" onchange="this.form.submit()"></div>
            <div class="col-6 col-md-auto"><input type="date" class="form-control" name="to" value="{{ $to }}" title="{{ __('To') }}" aria-label="{{ __('To') }}" onchange="this.form.submit()"></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit"><x-icon name="magnifying-glass" /></button></div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="clock-rotate-left" /></div>
                <h3>{{ $term !== '' || $adminId || $from || $to ? __('Nothing found') : __('Nothing here yet') }}</h3>
                <p>{{ __('Sign-ins, companies opened or changed, payments confirmed or rejected, and settings are written here.') }}</p>
            </div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('What') }}</th><th>{{ __('About') }}</th><th>{{ __('Who') }}</th><th>{{ __('When') }}</th><th>{{ __('From address') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="fw-semibold">{{ ucfirst($row->action) }}</td>
                            <td>{{ $row->subject ?: '—' }}@if ($row->details)<span class="d-block small text-muted">{{ $row->details }}</span>@endif</td>
                            <td>{{ $row->admin_name ?: '—' }}</td>
                            <td class="text-nowrap">{{ fmt_date($row->created_at) }} <span class="text-muted small">{{ $row->created_at?->format('H:i') }}</span></td>
                            <td class="small text-muted" dir="ltr">{{ $row->ip }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection

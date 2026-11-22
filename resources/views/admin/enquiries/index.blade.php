@extends('layouts.admin', ['title' => __('Reported enquiries')])

@section('content')
<x-page-header :title="__('Reported enquiries')" icon="flag" :subtitle="__('Threads between companies are private. You see one only after a company reports it; opening it is recorded in the activity log.')" />

<div class="d-flex gap-2 mb-3">
    <a class="btn btn-sm {{ $open ? 'btn-primary' : 'btn-light-secondary' }}" href="{{ route('admin.enquiries.index') }}">{{ __('To look at') }}</a>
    <a class="btn btn-sm {{ $open ? 'btn-light-secondary' : 'btn-primary' }}" href="{{ route('admin.enquiries.index', ['state' => 'done']) }}">{{ __('Closed') }}</a>
</div>
<div class="card"><div class="card-body">
    @if ($rows->isEmpty())
        <div class="empty-state"><div class="es-icon"><x-icon name="flag" /></div><h3>{{ __('Nothing reported') }}</h3></div>
    @else
        <div class="table-responsive">
            <table class="table table-hover w-100 align-middle">
                <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Reported') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $enquiry)
                        <tr>
                            <td class="fw-semibold">{{ $enquiry->subject }}</td>
                            <td>{{ $enquiry->sender?->name }}</td>
                            <td>{{ $enquiry->receiver?->name }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($enquiry->report_reason, 80) }}</td>
                            <td class="small text-nowrap">{{ fmt_date($enquiry->reported_at) }}</td>
                            <td class="text-end"><x-act kind="view" :href="route('admin.enquiries.show', ['id' => $enquiry->id])" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $rows->links('partials.pagination') }}
    @endif
</div></div>
@endsection

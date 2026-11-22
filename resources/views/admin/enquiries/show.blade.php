@extends('layouts.admin', ['title' => $enquiry->subject])

@section('content')
<x-page-header :title="$enquiry->subject" icon="flag" :crumbs="[[__('Reported enquiries'), route('admin.enquiries.index')], [$enquiry->subject]]" />

<div class="alert alert-warning small"><strong>{{ __('Reported by :company', ['company' => $enquiry->reported_by === $enquiry->from_company_id ? $enquiry->sender?->name : $enquiry->receiver?->name]) }}:</strong> {{ $enquiry->report_reason }}</div>
<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card mb-0"><div class="card-body d-flex flex-column gap-3">
            @foreach ($enquiry->messages as $message)
                <div class="p-3 rounded-3 border {{ $message->company_id === $enquiry->from_company_id ? 'bg-light' : '' }}">
                    <div class="small fw-semibold mb-1">{{ $message->sender_name }} · {{ $message->company_id === $enquiry->from_company_id ? $enquiry->sender?->name : $enquiry->receiver?->name }} <span class="text-muted fw-normal">· {{ fmt_datetime($message->created_at) }}</span></div>
                    <div style="white-space:pre-line" dir="auto">{{ $message->body }}</div>
                    @if ($message->attachment_name)<div class="small text-muted mt-1"><x-icon name="paperclip" /> {{ $message->attachment_name }}</div>@endif
                </div>
            @endforeach
        </div></div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card mb-0"><div class="card-body d-flex flex-column gap-2">
            @foreach ([$enquiry->sender, $enquiry->receiver] as $company)
                @if ($company)<a class="btn btn-light-secondary" href="{{ route('admin.companies.edit', ['id' => $company->id]) }}">{{ $company->name }}</a>@endif
            @endforeach
            @if ($enquiry->status === 'open' && \App\Support\AdminAccess::allows(auth('admin')->user(), 'admin.enquiries.close', false))
                <form method="POST" action="{{ route('admin.enquiries.close', ['id' => $enquiry->id]) }}" data-confirm="{{ __('Close this thread?') }}" data-confirm-text="{{ __('Neither company can write in it any more.') }}" data-confirm-ok="{{ __('Close') }}">@csrf<button class="btn btn-danger w-100" type="submit">{{ __('Close the thread') }}</button></form>
            @endif
            <p class="small text-muted mb-0">{{ __('To stop a company for good, suspend it from its page.') }}</p>
        </div></div>
    </div>
</div>
@endsection

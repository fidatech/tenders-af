@extends('layouts.app', ['title' => __('Enquiries')])

@section('content')
<x-page-header :title="__('Enquiries')" icon="comments" :subtitle="__('Private threads with other companies. You can always answer the enquiries you receive, on any plan.')" />

<ul class="nav nav-pills mb-3 gap-2">
    <li class="nav-item"><a class="nav-link {{ $box === 'inbox' ? 'active' : '' }}" href="{{ route('enquiries.index') }}">{{ __('Received') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ $box === 'sent' ? 'active' : '' }}" href="{{ route('enquiries.index', ['box' => 'sent']) }}">{{ __('Sent') }}</a></li>
</ul>

<div class="card">
    <div class="card-body">
        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="comments" /></div>
                <h3>{{ $box === 'sent' ? __('You have not sent an enquiry yet') : __('No enquiries yet') }}</h3>
                <p>{{ $box === 'sent' ? __('Open a company in the directory and choose “Send an enquiry”.') : __('Enquiries from buyers and other companies appear here. A complete profile helps them find you.') }}</p>
                @if ($box === 'sent' && module_on('directory'))<a class="btn btn-primary" href="{{ route('site.companies') }}">{{ __('Open the directory') }}</a>@endif
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach ($rows as $enquiry)
                    @php($other = $enquiry->otherSide($companyId))
                    @php($new = in_array($enquiry->id, $unread, true))
                    <a class="list-group-item list-group-item-action d-flex gap-3 align-items-center py-3" href="{{ route('enquiries.show', ['id' => $enquiry->id]) }}">
                        <span class="avatar-sm rounded-circle bg-primary-subtle text-primary-emphasis d-inline-flex align-items-center justify-content-center fw-semibold flex-shrink-0" style="width:40px;height:40px">{{ \App\Models\User::initialsOf($other?->name ?? '?') }}</span>
                        <span class="min-w-0 flex-grow-1">
                            <span class="d-block text-truncate {{ $new ? 'fw-bold' : 'fw-semibold' }}">{{ $other?->name ?? __('A removed company') }}</span>
                            <span class="d-block small text-truncate {{ $new ? 'text-body' : 'text-muted' }}">{{ $enquiry->subject }}</span>
                        </span>
                        <span class="text-end flex-shrink-0">
                            <span class="d-block small text-muted">{{ $enquiry->last_message_at?->diffForHumans(short: true) }}</span>
                            @if ($new)<span class="badge bg-danger">{{ __('New') }}</span>@elseif ($enquiry->status === 'closed')<span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Closed') }}</span>@endif
                        </span>
                    </a>
                @endforeach
            </div>
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection

@extends('layouts.admin', ['title' => __('Messages')])

@section('content')
<x-page-header :title="__('Messages')" icon="envelope" />

<div class="card">
    <div class="card-body">
        <x-filter-bar :action="route('admin.messages.index')" :term="$search"
            :placeholder="__('Search company, bidder or email…')" :limit="150"
            :reset="$search !== '' ? route('admin.messages.index') : null" />

        @if ($people->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="envelope" /></div>
                <h3>{{ __('No contacts found') }}</h3>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Contact') }}</th>
                            <th scope="col">{{ __('Company') }}</th>
                            <th scope="col">{{ __('Unread') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($people as $person)
                            @php($chatUrl = route('admin.messages.show', $person))
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar avatar-sm avatar-soft-primary flex-shrink-0" aria-hidden="true">{{ $person->initials() }}</span>
                                        <div class="min-w-0">
                                            <a href="{{ $chatUrl }}" class="d-block fw-semibold text-decoration-none text-break" dir="auto">{{ $person->name }}</a>
                                            <span class="d-block small text-muted text-break" dir="ltr">{{ $person->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="text-break" dir="auto">{{ $person->company?->name ?? '—' }}</span></td>
                                <td>
                                    @if ($person->unread_messages)
                                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill">{{ number_format($person->unread_messages) }}</span>
                                    @else
                                        <span class="text-muted" aria-label="{{ __('No unread messages') }}">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ $chatUrl }}" class="btn btn-sm btn-light-secondary text-nowrap" aria-label="{{ __('Open chat with :name', ['name' => $person->name]) }}">
                                        <x-icon name="envelope" class="me-1" />{{ __('Open chat') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $people->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection

@php
    $titles = ['all' => __('All tenders'), 'matching' => __('Matching my alerts'), 'saved' => __('Saved tenders')];
    $icons = ['all' => 'gavel', 'matching' => 'bell', 'saved' => 'bookmark'];
    $routeName = ['all' => 'tenders.index', 'matching' => 'tenders.matching', 'saved' => 'tenders.saved'][$mode];
    $types = config('tenders.types');
    $buyers = config('tenders.buyer_types');
    $filtered = collect($filters)->filter(fn ($value) => (string) $value !== '')->isNotEmpty();
    $pill = ['soon' => 'bg-danger-subtle text-danger-emphasis', 'open' => 'bg-success-subtle text-success-emphasis', 'closed' => 'bg-secondary-subtle text-secondary-emphasis', 'awarded' => 'bg-secondary-subtle text-secondary-emphasis', 'cancelled' => 'bg-secondary-subtle text-secondary-emphasis', 'draft' => 'bg-secondary-subtle text-secondary-emphasis'];
@endphp
@extends('layouts.app', ['title' => $titles[$mode]])

@section('content')
<x-page-header :title="$titles[$mode]" :icon="$icons[$mode]" :subtitle="trans_choice('{0} No tenders|{1} 1 tender|[2,*] :count tenders', $rows->total(), ['count' => number_format($rows->total())])">
    @if ($mode === 'matching' && auth()->user()->isOwner())
        <a class="btn btn-outline-soft" href="{{ route('alerts.edit') }}"><x-icon name="paper-plane" class="me-1" /> {{ __('Tender alerts') }}</a>
    @endif
</x-page-header>

@if ($mode === 'matching' && ! $hasAlerts)
    <div class="alert alert-warning">{{ __('You have not chosen your sectors yet, so no tender can be matched.') }} @if (auth()->user()->isOwner())<a class="fw-semibold" href="{{ route('settings.edit') }}#sectors">{{ __('Choose sectors') }}</a>@endif</div>
@endif

@unless ($company->hasFullAccess())
    <a href="{{ route('billing.show') }}" class="alert alert-info d-flex align-items-center gap-2 text-decoration-none py-2">
        <x-icon name="lock" />
        <span class="flex-grow-1">{{ __('You see the summary of every tender. The full details and the documents open on a paid plan.') }} <strong>{{ __('See plans') }}</strong></span>
        <x-icon name="chevron-right" />
    </a>
@endunless

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-lg">
                <input type="search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search by title, buyer or number…') }}" aria-label="{{ __('Search') }}">
            </div>
            <div class="col-6 col-lg-auto">
                <select class="form-select" name="industry" aria-label="{{ __('Sector') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('All sectors') }}</option>
                    @foreach ($industries as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['industry'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-auto">
                <select class="form-select" name="province" aria-label="{{ __('Province') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('All provinces') }}</option>
                    @foreach ($provinces as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['province'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-auto">
                <select class="form-select" name="type" aria-label="{{ __('Tender type') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Any type') }}</option>
                    @foreach ($types as $key => [$short, $long])
                        <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $short }} · {{ __($long) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-auto">
                <select class="form-select" name="buyer" aria-label="{{ __('Buyer') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Any buyer') }}</option>
                    @foreach ($buyers as $key => $name)
                        <option value="{{ $key }}" @selected(($filters['buyer'] ?? '') === $key)>{{ __($name) }}</option>
                    @endforeach
                </select>
            </div>
            @if ($mode !== 'saved')
                <div class="col-6 col-lg-auto">
                    <select class="form-select" name="show" aria-label="{{ __('Show') }}" onchange="this.form.submit()" data-plain>
                        <option value="open" @selected($show !== 'all')>{{ __('Open tenders') }}</option>
                        <option value="all" @selected($show === 'all')>{{ __('Open and closed') }}</option>
                    </select>
                </div>
            @endif
            <div class="col-auto">
                <button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button>
            </div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon :name="$icons[$mode]" /></div>
                <h3>{{ $filtered ? __('Nothing found') : ($mode === 'saved' ? __('No saved tenders yet') : __('No tenders here yet')) }}</h3>
                @if ($mode === 'saved' && ! $filtered)
                    <p>{{ __('Press "Save" on a tender to keep it here.') }}</p>
                @endif
            </div>
        @else
            <div class="d-flex flex-column gap-2">
                @foreach ($rows as $tender)
                    @php
                        $state = $tender->state();
                    @endphp
                    <div class="border rounded-3 p-3 d-flex flex-wrap gap-3 align-items-start">
                        <a href="{{ route('tenders.show', ['id' => $tender->id]) }}" class="flex-grow-1 min-w-0 text-decoration-none text-reset" style="flex-basis:320px">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="badge bg-secondary-subtle text-secondary-emphasis" title="{{ $tender->typeName() }}">{{ $tender->typeLabel() }}</span>
                                <span class="small text-muted" dir="ltr">{{ $tender->number() }}</span>
                                <span class="badge {{ $pill[$state] }}">{{ $tender->stateLabel() }}</span>
                            </div>
                            <div class="fw-bold fs-6">{{ $tender->title }}</div>
                            <div class="small text-muted mt-1">{{ $tender->buyer_name }} · {{ $tender->buyerTypeLabel() }} · {{ $tender->industry?->name }} · {{ $tender->placeText() }}</div>
                        </a>
                        <div class="text-end flex-shrink-0 ms-auto">
                            <div class="fw-semibold {{ $state === 'soon' ? 'text-danger' : ($state === 'open' ? 'text-success' : 'text-muted') }}">{{ $tender->timeLeft() }}</div>
                            @if ($tender->deadline)
                                <div class="small text-muted">{{ __('Closes') }} {{ fmt_datetime($tender->deadline) }}</div>
                            @endif
                            <form method="POST" action="{{ route('tenders.save', ['id' => $tender->id]) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ in_array($tender->id, $savedIds, true) ? 'btn-soft-primary' : 'btn-light-secondary' }}"><x-icon name="bookmark" class="me-1" />{{ in_array($tender->id, $savedIds, true) ? __('Saved') : __('Save') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3">{{ $rows->links('partials.pagination') }}</div>
        @endif
    </div>
</div>
@endsection

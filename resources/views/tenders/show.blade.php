@php
    $state = $tender->state();
    $pill = ['soon' => 'bg-danger-subtle text-danger-emphasis', 'open' => 'bg-success-subtle text-success-emphasis', 'closed' => 'bg-secondary-subtle text-secondary-emphasis', 'awarded' => 'bg-secondary-subtle text-secondary-emphasis', 'cancelled' => 'bg-secondary-subtle text-secondary-emphasis', 'draft' => 'bg-secondary-subtle text-secondary-emphasis'];
    $facts = array_filter([
        __('Buyer') => $tender->buyer_name.' ('.$tender->buyerTypeLabel().')',
        __('Sector') => $tender->industry?->name.($tender->category ? ' · '.$tender->category->name : ''),
        __('Location') => $tender->placeText().($tender->province && $tender->location ? ', '.$tender->location : ''),
        __('Kind of tender') => $tender->typeLabel().' · '.$tender->typeName(),
        __('Buyer\'s reference') => $tender->reference,
        __('Published') => fmt_date($tender->published_on),
        __('Questions until') => fmt_date($tender->questions_until),
        __('Budget') => $tender->budgetText().($tender->sizeLabel() ? ' · '.$tender->sizeLabel().' ('.__('estimated size').')' : ''),
        __('Submission') => $tender->submissionLabel(),
    ]);
    $locked = [
        [__('Scope of work and full description'), filled($tender->description)],
        [__('Eligibility and required documents'), filled($tender->eligibility)],
        [__('Where and how to submit'), filled($tender->submission_details)],
        [__('Contact person of the buyer'), filled($tender->contact)],
        [trans_choice('{1} 1 tender document|[2,*] :count tender documents', max(1, $tender->documents->count()), ['count' => $tender->documents->count()]), $tender->documents->isNotEmpty()],
    ];
@endphp
@extends('layouts.app', ['title' => $tender->title])

@section('content')
<x-page-header :title="$tender->title" icon="gavel" :crumbs="[[__('All tenders'), route('tenders.index')], [$tender->number()]]">
    <form method="POST" action="{{ route('tenders.save', ['id' => $tender->id]) }}">
        @csrf
        <button type="submit" class="btn {{ $saved ? 'btn-soft-primary' : 'btn-outline-soft' }}"><x-icon name="bookmark" class="me-1" /> {{ $saved ? __('Saved') : __('Save') }}</button>
    </form>
</x-page-header>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $tender->typeLabel() }}</span>
                    <span class="small text-muted" dir="ltr">{{ $tender->number() }}</span>
                    <span class="badge {{ $pill[$state] }}">{{ $tender->stateLabel() }}</span>
                    @if ($tender->status === 'awarded' && $tender->awarded_to)
                        <span class="small">{{ __('Awarded to :name', ['name' => $tender->awarded_to]) }}</span>
                    @endif
                </div>
                <dl class="row mb-0">
                    @foreach ($facts as $label => $value)
                        <dt class="col-sm-4 text-muted fw-normal small">{{ $label }}</dt>
                        <dd class="col-sm-8 fw-semibold" dir="auto">{{ $value }}</dd>
                    @endforeach
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Summary') }}</h5></div>
            <div class="card-body"><p class="mb-0" style="white-space:pre-line" dir="auto">{{ $tender->summary }}</p></div>
        </div>

        @if ($full)
            @foreach ([[__('Scope of work'), $tender->description], [__('Eligibility and required documents'), $tender->eligibility], [__('Where and how to submit'), $tender->submission_details], [__('Contact'), $tender->contact]] as [$heading, $text])
                @continue(blank($text))
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">{{ $heading }}</h5></div>
                    <div class="card-body"><p class="mb-0" style="white-space:pre-line" dir="auto">{{ $text }}</p></div>
                </div>
            @endforeach

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Tender documents') }}</h5></div>
                <div class="card-body">
                    @if ($tender->documents->isEmpty())
                        <p class="text-muted mb-0">{{ __('No documents were added to this tender.') }}</p>
                    @else
                        <ul class="list-clean">
                            @foreach ($tender->documents as $document)
                                <li>
                                    <x-icon name="file-lines" class="text-muted" />
                                    <span class="flex-grow-1 min-w-0 text-truncate">{{ $document->name }}</span>
                                    <span class="small text-muted">{{ $document->sizeText() }}</span>
                                    <a class="btn btn-sm btn-soft-primary" href="{{ route('tenders.document', ['id' => $tender->id, 'document' => $document->id]) }}"><x-icon name="download" class="me-1" />{{ __('Download') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($tender->source_url)
                        <p class="small text-muted mt-3 mb-0">{{ __('Source') }}: <a href="{{ $tender->source_url }}" target="_blank" rel="noopener nofollow">{{ $tender->source_name ?: $tender->source_url }}</a></p>
                    @endif
                </div>
            </div>
        @else
            <div class="card border-primary">
                <div class="card-header"><h5 class="card-title mb-0"><x-icon name="lock" class="me-1" /> {{ __('Full details') }}</h5></div>
                <div class="card-body">
                    <ul class="list-clean mb-3">
                        @foreach ($locked as [$label, $has])
                            @continue(! $has)
                            <li><x-icon name="lock" class="text-muted" /><span class="flex-grow-1">{{ $label }}</span></li>
                        @endforeach
                    </ul>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="min-w-0"><div class="fw-bold">{{ __('Open the full tender and its documents') }}</div><div class="small text-muted">{{ __('Included in every paid plan. You pay in :currency.', ['currency' => $company->currency]) }}</div></div>
                        <a class="btn btn-primary" href="{{ route('billing.show') }}"><x-icon name="credit-card" class="me-1" /> {{ __('See plans') }}</a>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-12 col-xl-4">
        <div class="card h-auto {{ $state === 'soon' ? 'border-danger' : '' }}">
            <div class="card-body">
                <div class="small fw-semibold text-uppercase text-muted">{{ __('Deadline') }}</div>
                <div class="fs-3 fw-bold {{ $state === 'soon' ? 'text-danger' : ($state === 'open' ? 'text-success' : '') }}">{{ $tender->timeLeft() }}</div>
                @if ($tender->deadline)
                    <div class="fw-semibold">{{ fmt_datetime($tender->deadline) }}</div>
                    <div class="small text-muted">{{ __('Kabul time') }}</div>
                @endif
            </div>
        </div>

        @if ($full)
            {{-- the bid price worked out on the page; nothing of it is sent or stored --}}
            <div class="card h-auto" id="bidCalc">
                <div class="card-header"><h5 class="card-title mb-0"><x-icon name="calculator" class="me-1" /> {{ __('Prepare my bid') }}</h5></div>
                <div class="card-body">
                    @foreach ([['goods', __('Cost of goods or work')], ['transport', __('Transport and installation')], ['other', __('Other costs')]] as [$key, $label])
                        <div class="mb-2">
                            <label class="form-label small mb-1" for="bc-{{ $key }}">{{ $label }}</label>
                            <input type="text" inputmode="decimal" class="form-control" id="bc-{{ $key }}" data-bid="{{ $key }}" dir="ltr" autocomplete="off">
                        </div>
                    @endforeach
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small mb-1" for="bc-margin">{{ __('Margin %') }}</label><input type="text" inputmode="decimal" class="form-control" id="bc-margin" data-bid="margin" dir="ltr" value="10" autocomplete="off"></div>
                        <div class="col-6"><label class="form-label small mb-1" for="bc-tax">{{ __('Tax %') }}</label><input type="text" inputmode="decimal" class="form-control" id="bc-tax" data-bid="tax" dir="ltr" value="0" autocomplete="off"></div>
                    </div>
                    <ul class="list-clean mt-3">
                        <li><span class="small text-muted flex-grow-1">{{ __('Total cost') }}</span><span class="fw-semibold" dir="ltr" data-bid-out="cost">0</span></li>
                        <li><span class="small text-muted flex-grow-1">{{ __('Margin') }}</span><span class="fw-semibold" dir="ltr" data-bid-out="margin">0</span></li>
                        <li><span class="small text-muted flex-grow-1">{{ __('Tax') }}</span><span class="fw-semibold" dir="ltr" data-bid-out="tax">0</span></li>
                        <li><span class="fw-bold flex-grow-1">{{ __('Your bid price') }}</span><span class="fs-5 fw-bold text-success" dir="ltr" data-bid-out="total">0</span></li>
                    </ul>
                    <p class="small text-muted mt-2 mb-0">{{ __('An estimate only, kept on this page. Confirm the tax with your accountant.') }}</p>
                </div>
            </div>
        @else
            <div class="card h-auto">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="sc-icon icon-soft primary flex-shrink-0" style="width:38px;height:38px"><x-icon name="calculator" /></span>
                    <div class="min-w-0"><div class="fw-semibold">{{ __('Bid price calculator') }}</div><div class="small text-muted">{{ __('Available on a paid plan.') }}</div></div>
                </div>
            </div>
        @endif

        @if ($similar->isNotEmpty())
            <div class="card h-auto">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Similar tenders') }}</h5></div>
                <div class="card-body">
                    <ul class="list-clean">
                        @foreach ($similar as $other)
                            <li><a class="min-w-0 flex-grow-1 text-decoration-none text-reset" href="{{ route('tenders.show', ['id' => $other->id]) }}"><span class="d-block fw-semibold">{{ $other->title }}</span><span class="d-block small text-muted">{{ $other->typeLabel() }} · {{ $other->placeText() }} · {{ $other->timeLeft() }}</span></a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@if ($full)
@push('scripts')
<script>
(function () {
    var box = document.getElementById('bidCalc');
    if (!box) return;
    var num = function (key) { var el = box.querySelector('[data-bid="' + key + '"]'); var v = parseFloat(String(el.value).replace(/,/g, '')); return isFinite(v) && v > 0 ? v : 0; };
    var put = function (key, value) { box.querySelector('[data-bid-out="' + key + '"]').textContent = value.toLocaleString('en-US', { maximumFractionDigits: 2 }); };
    var work = function () {
        var cost = num('goods') + num('transport') + num('other');
        var margin = cost * num('margin') / 100;
        var tax = (cost + margin) * num('tax') / 100;
        put('cost', cost); put('margin', margin); put('tax', tax); put('total', cost + margin + tax);
    };
    box.addEventListener('input', work);
    work();
})();
</script>
@endpush
@endif

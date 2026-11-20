@extends('layouts.app', ['title' => $tender ? $tender->title : __('Post a tender')])

@php
    $value = fn (string $key, $default = null) => old($key, $tender?->{$key} ?? $default);
    $deadline = old('deadline', $tender?->deadline?->format('Y-m-d\TH:i'));
    $pickedCategory = (string) $value('category_id');
    $editable = ! $tender || $tender->isEditableByBuyer();
    $status = $tender?->status ?? 'draft';
    // one text box: [name, label, help, rows, required]
    $areas = [
        ['summary', 'Summary', 'Two or three sentences everybody can read: what you buy, where, and for whom.', 3, true],
        ['description', 'Scope of work and full description', 'The work, the quantities, the place and the time.', 8, false],
        ['eligibility', 'Eligibility and required documents', 'Who may bid and what they must hand in.', 4, false],
        ['submission_details', 'Where and how to submit', 'The address or email, the forms and the number of copies.', 3, false],
        ['contact', 'Contact for questions', 'Name, phone and email of the person to ask.', 2, false],
    ];
    $pill = ['soon' => 'bg-danger-subtle text-danger-emphasis', 'open' => 'bg-success-subtle text-success-emphasis', 'closed' => 'bg-secondary-subtle text-secondary-emphasis', 'awarded' => 'bg-primary-subtle text-primary-emphasis', 'cancelled' => 'bg-secondary-subtle text-secondary-emphasis', 'draft' => 'bg-secondary-subtle text-secondary-emphasis', 'pending' => 'bg-warning-subtle text-warning-emphasis', 'rejected' => 'bg-danger-subtle text-danger-emphasis'];
@endphp

@section('content')
<x-page-header :title="$tender ? $tender->title : __('Post a tender')" icon="bullhorn" :crumbs="[[__('Posted tenders'), route('posted.index')], [$tender ? $tender->number() : __('New')]]">
    @if ($tender && ! $editable)
        <a class="btn btn-outline-soft" href="{{ route('site.tenders.show', ['slug' => $tender->slug]) }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('See on the site') }}</a>
    @endif
</x-page-header>

@if ($status === 'rejected')
    <div class="alert alert-danger d-flex align-items-start gap-3"><x-icon name="circle-exclamation" class="fs-4 mt-1" /><div><div class="fw-bold">{{ __('This tender was sent back') }}</div><div class="small">{{ __($tender->reject_reason) }} {{ __('Correct it and send it again.') }}</div></div></div>
@elseif ($status === 'pending')
    <div class="alert alert-warning d-flex align-items-start gap-3"><x-icon name="hourglass-half" class="fs-4 mt-1" /><div><div class="fw-bold">{{ __('Waiting for approval') }}</div><div class="small">{{ __('We check every tender before it is published, usually within one working day. You can still correct it.') }}</div></div></div>
@endif

<form method="POST" action="{{ $tender ? route('posted.update', ['id' => $tender->id]) : route('posted.store') }}" enctype="multipart/form-data" id="tenderForm">
    @csrf
    @if ($tender)
        @method('PUT')
    @endif
    <fieldset @disabled(! $editable) class="border-0 p-0 m-0">
    <div class="row g-3">
        <div class="col-12 col-xl-8 d-flex flex-column gap-3">
            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('The tender') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="f-title">{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="f-title" name="title" value="{{ $value('title') }}" maxlength="255" required dir="auto">
                            @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="f-type">{{ __('Kind of tender') }} <span class="text-danger">*</span></label>
                            <select class="form-select @error('type') is-invalid @enderror" id="f-type" name="type" required data-plain>
                                @foreach (config('tenders.types') as $key => [$short, $long])
                                    <option value="{{ $key }}" @selected($value('type', 'rfq') === $key)>{{ $short }} · {{ __($long) }}</option>
                                @endforeach
                            </select>
                            @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="f-reference">{{ __('Your reference number') }}</label>
                            <input type="text" class="form-control @error('reference') is-invalid @enderror" id="f-reference" name="reference" value="{{ $value('reference') }}" maxlength="100" dir="ltr">
                            @error('reference')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="f-industry">{{ __('Sector') }} <span class="text-danger">*</span></label>
                            <select class="form-select @error('industry_id') is-invalid @enderror" id="f-industry" name="industry_id" required data-plain>
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($industries as $id => $name)
                                    <option value="{{ $id }}" @selected((string) $value('industry_id') === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('industry_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="f-category">{{ __('Category') }}</label>
                            <select class="form-select @error('category_id') is-invalid @enderror" id="f-category" name="category_id" data-plain>
                                <option value="">{{ __('The whole sector') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" data-industry="{{ $category->industry_id }}" @selected($pickedCategory === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="f-province">{{ __('Province') }}</label>
                            <select class="form-select @error('province_id') is-invalid @enderror" id="f-province" name="province_id" data-plain>
                                <option value="">{{ __('Several provinces or the whole country') }}</option>
                                @foreach ($provinces as $id => $name)
                                    <option value="{{ $id }}" @selected((string) $value('province_id') === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('province_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="f-location">{{ __('Place') }}</label>
                            <input type="text" class="form-control @error('location') is-invalid @enderror" id="f-location" name="location" value="{{ $value('location') }}" maxlength="255" dir="auto">
                            @error('location')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('Districts, a city, or the list of provinces.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('What it says') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($areas as [$name, $label, $help, $rows, $required])
                            <div class="col-12">
                                <label class="form-label" for="f-{{ $name }}">{{ __($label) }}@if ($required) <span class="text-danger">*</span>@endif</label>
                                <textarea class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" dir="auto" @required($required)>{{ $value($name) }}</textarea>
                                @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text">{{ __($help) }}</div>
                            </div>
                        @endforeach
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="f-method">{{ __('Bids are handed in') }}</label>
                            <select class="form-select @error('submission_method') is-invalid @enderror" id="f-method" name="submission_method" data-plain>
                                <option value="">{{ __('Not said') }}</option>
                                @foreach (config('tenders.submission_methods') as $key => $label)
                                    <option value="{{ $key }}" @selected($value('submission_method') === $key)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                            @error('submission_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Documents') }}</h5></div>
                <div class="card-body">
                    @if ($tender && $tender->documents->isNotEmpty())
                        <ul class="list-clean mb-3">
                            @foreach ($tender->documents as $document)
                                <li>
                                    <x-icon name="file-lines" class="text-muted" />
                                    <a class="flex-grow-1 min-w-0 text-truncate text-decoration-none" href="{{ route('posted.document', ['id' => $tender->id, 'document' => $document->id]) }}">{{ $document->name }}</a>
                                    <span class="small text-muted">{{ $document->sizeText() }}</span>
                                    @if ($editable)<button type="submit" form="doc-delete-{{ $document->id }}" class="btn btn-sm btn-soft-danger" aria-label="{{ __('Remove :name', ['name' => $document->name]) }}"><x-icon name="trash" /></button>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <label class="form-label" for="f-documents">{{ __('Add documents') }}</label>
                    <x-file-drop accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.zip,.rar,image/*" :hint="__('PDF, Word, Excel, PowerPoint, zip, rar or pictures. Up to 20 files at a time, 20 MB each. Only subscribers download them.')" />
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4 d-flex flex-column gap-3">
            <div class="card mb-0 h-auto">
                <div class="card-header d-flex align-items-center justify-content-between"><h5 class="card-title mb-0">{{ __('Publishing') }}</h5>@if ($tender)<span class="badge {{ $pill[$tender->state()] }}">{{ $tender->stateLabel() }}</span>@endif</div>
                <div class="card-body">
                    <div class="small text-muted mb-3">{{ __('Posted as') }} <strong class="text-body">{{ $company->name }}</strong></div>
                    <label class="form-label" for="f-deadline">{{ __('Deadline (Kabul time)') }} <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control mb-3 @error('deadline') is-invalid @enderror" id="f-deadline" name="deadline" value="{{ $deadline }}" required>
                    @error('deadline')<div class="invalid-feedback d-block mb-3">{{ $message }}</div>@enderror
                    <label class="form-label" for="f-questions">{{ __('Questions until') }}</label>
                    <x-date name="questions_until" :value="$value('questions_until')" id="f-questions" />
                    @if ($editable)
                        <button type="submit" name="action" value="send" class="btn btn-primary w-100 mt-3"><x-icon name="paper-plane" class="me-1" /> {{ $status === 'pending' ? __('Save and keep waiting') : __('Send for approval') }}</button>
                        @if ($status !== 'pending')
                            <button type="submit" name="action" value="draft" class="btn btn-outline-soft w-100 mt-2">{{ __('Save as a draft') }}</button>
                        @endif
                        <div class="form-text mt-2">{{ __('We check the tender and publish it. Then the companies of its sector are told by email and WhatsApp.') }}</div>
                    @endif
                </div>
            </div>

            <div class="card mb-0 h-auto">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Budget') }}</h5></div>
                <div class="card-body">
                    <label class="form-label" for="f-budget-type">{{ __('You give') }}</label>
                    <select class="form-select mb-3 @error('budget_type') is-invalid @enderror" id="f-budget-type" name="budget_type" required data-plain>
                        @foreach (['undisclosed' => 'No budget', 'exact' => 'One amount', 'range' => 'A range'] as $key => $label)
                            <option value="{{ $key }}" @selected($value('budget_type', 'undisclosed') === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="f-budget-min">{{ __('Amount, or from') }}</label>
                            <input type="text" inputmode="decimal" class="form-control @error('budget_min') is-invalid @enderror" id="f-budget-min" name="budget_min" value="{{ $value('budget_min') }}" dir="ltr" autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="f-budget-max">{{ __('To') }}</label>
                            <input type="text" inputmode="decimal" class="form-control @error('budget_max') is-invalid @enderror" id="f-budget-max" name="budget_max" value="{{ $value('budget_max') }}" dir="ltr" autocomplete="off">
                        </div>
                        @error('budget_min')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('budget_max')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="col-6">
                            <label class="form-label" for="f-budget-currency">{{ __('Currency') }}</label>
                            <select class="form-select @error('budget_currency') is-invalid @enderror" id="f-budget-currency" name="budget_currency" data-plain>
                                <option value="">—</option>
                                @foreach (config('tenders.currencies') as $code)
                                    <option value="{{ $code }}" @selected($value('budget_currency') === $code)>{{ $code }}</option>
                                @endforeach
                            </select>
                            @error('budget_currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="form-text">{{ __('A budget, even a range, brings more serious bids. You may also leave it out.') }}</div>
                </div>
            </div>
        </div>
    </div>
    </fieldset>
</form>

@if ($tender)
    @foreach ($editable ? $tender->documents : [] as $document)
        <form id="doc-delete-{{ $document->id }}" method="POST" action="{{ route('posted.document.destroy', ['id' => $tender->id, 'document' => $document->id]) }}" hidden data-confirm="{{ __('Remove this document?') }}" data-confirm-ok="{{ __('Remove') }}">@csrf @method('DELETE')</form>
    @endforeach

    <div class="row g-3 mt-0">
        @if ($status === 'published')
            <div class="col-12 col-md-6 col-xl-4">
                <form class="card h-100 mb-0" method="POST" action="{{ route('posted.extend', ['id' => $tender->id]) }}">
                    @csrf
                    <div class="card-body">
                        <div class="fw-semibold mb-1">{{ __('Extend the deadline') }}</div>
                        <div class="small text-muted mb-3">{{ __('Now: :date', ['date' => $tender->deadline ? fmt_datetime($tender->deadline) : '—']) }}</div>
                        <input type="datetime-local" class="form-control mb-2" name="deadline" required aria-label="{{ __('New deadline') }}">
                        <button type="submit" class="btn btn-outline-soft w-100"><x-icon name="clock" class="me-1" /> {{ __('Extend') }}</button>
                    </div>
                </form>
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <form class="card h-100 mb-0" method="POST" action="{{ route('posted.documents', ['id' => $tender->id]) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="fw-semibold mb-1">{{ __('Add documents') }}</div>
                        <div class="small text-muted mb-3">{{ __('A clarification or an amendment for the bidders.') }}</div>
                        <div class="mb-2"><x-file-drop id="f-more-documents" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.zip,.rar,image/*" required :hint="__('Up to 20 files, 20 MB each.')" /></div>
                        <button type="submit" class="btn btn-outline-soft w-100"><x-icon name="plus" class="me-1" /> {{ __('Add') }}</button>
                    </div>
                </form>
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <form class="card h-100 mb-0" method="POST" action="{{ route('posted.award', ['id' => $tender->id]) }}" data-confirm="{{ __('Publish the winner? The tender closes.') }}" data-confirm-ok="{{ __('Publish') }}">
                    @csrf
                    <div class="card-body">
                        <div class="fw-semibold mb-1">{{ __('Name the winner') }}</div>
                        <div class="small text-muted mb-3">{{ __('The tender stays on the site with its result.') }}</div>
                        <input type="text" class="form-control mb-2" name="awarded_to" maxlength="255" required placeholder="{{ __('Name of the company') }}" dir="auto">
                        <button type="submit" class="btn btn-outline-soft w-100"><x-icon name="circle-check" class="me-1" /> {{ __('Publish the winner') }}</button>
                    </div>
                </form>
            </div>
        @endif
        @if (in_array($status, ['published', 'pending'], true))
            <div class="col-12 col-xl-6">
                <div class="card h-auto mb-0"><div class="card-body d-flex align-items-center justify-content-between gap-3">
                    <div class="min-w-0"><div class="fw-semibold">{{ $status === 'pending' ? __('Take this tender back') : __('Cancel this tender') }}</div><div class="small text-muted">{{ $status === 'pending' ? __('It becomes a draft again and is not checked.') : __('It stays on the site marked as cancelled, so bidders know.') }}</div></div>
                    <form method="POST" action="{{ route('posted.cancel', ['id' => $tender->id]) }}" data-confirm="{{ $status === 'pending' ? __('Take this tender back?') : __('Cancel this tender?') }}" data-confirm-ok="{{ __('Yes') }}">@csrf<button type="submit" class="btn btn-soft-danger flex-shrink-0">{{ $status === 'pending' ? __('Take back') : __('Cancel tender') }}</button></form>
                </div></div>
            </div>
        @endif
        @if ($editable && ! $tender->alerted_at)
            <div class="col-12 col-xl-6">
                <div class="card h-auto mb-0"><div class="card-body d-flex align-items-center justify-content-between gap-3">
                    <div class="min-w-0"><div class="fw-semibold">{{ __('Delete this tender') }}</div><div class="small text-muted">{{ __('It was never published, so it can be removed completely.') }}</div></div>
                    <form method="POST" action="{{ route('posted.destroy', ['id' => $tender->id]) }}" data-confirm="{{ __('Delete this tender?') }}" data-confirm-ok="{{ __('Delete') }}">@csrf @method('DELETE')<button type="submit" class="btn btn-soft-danger flex-shrink-0"><x-icon name="trash" class="me-1" /> {{ __('Delete') }}</button></form>
                </div></div>
            </div>
        @endif
    </div>
@endif
@endsection

@push('scripts')
<script>
// the category list only offers the categories of the chosen sector
(function () {
    var industry = document.getElementById('f-industry'), category = document.getElementById('f-category');
    if (!industry || !category) return;
    var all = Array.prototype.slice.call(category.querySelectorAll('option[data-industry]'));
    var sync = function () {
        all.forEach(function (option) {
            var fits = option.getAttribute('data-industry') === industry.value;
            option.hidden = !fits; option.disabled = !fits;
            if (!fits && option.selected) category.value = '';
        });
    };
    industry.addEventListener('change', sync);
    sync();
})();
</script>
@endpush

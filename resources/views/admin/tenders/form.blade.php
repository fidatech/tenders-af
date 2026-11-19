@extends('layouts.admin', ['title' => $tender ? $tender->title : __('Add tender')])

@php
    $value = fn (string $key, $default = null) => old($key, $tender?->{$key} ?? $default);
    $deadline = old('deadline', $tender?->deadline?->format('Y-m-d\TH:i'));
    $pickedCategory = (string) $value('category_id');
    // one text box: [name, label, help, rows, required]
    $areas = [
        ['summary', 'Summary', 'Two or three sentences everybody can read: what is bought, where, and for whom. This is the public teaser.', 3, true],
        ['description', 'Scope of work and full description', 'Only subscribers read this.', 8, false],
        ['eligibility', 'Eligibility and required documents', 'Who may bid and what they must hand in.', 4, false],
        ['submission_details', 'Where and how to submit', 'The address or email, the forms and the number of copies.', 3, false],
        ['contact', 'Contact of the buyer', 'Name, phone and email of the person to ask.', 2, false],
    ];
@endphp

@section('content')
<x-page-header :title="$tender ? $tender->title : __('Add tender')" icon="gavel" :crumbs="[[__('Tenders'), route('admin.tenders.index')], [$tender ? $tender->number() : __('Add')]]">
    @if ($tender && in_array($tender->status, ['published', 'awarded', 'cancelled'], true))
        <a class="btn btn-outline-soft" href="{{ route('site.tenders.show', ['slug' => $tender->slug]) }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('See on the site') }}</a>
    @endif
</x-page-header>

@if ($tender?->company)
    <div class="card h-auto mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="sc-icon icon-soft {{ $tender->status === 'pending' ? 'warning' : 'success' }} flex-shrink-0" style="width:42px;height:42px"><x-icon name="bullhorn" /></span>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-bold">{{ __('Posted by :name', ['name' => $tender->company->name]) }} <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $tender->company->kindLabel() }}</span> @if ($tender->company->isVerified())<span class="badge bg-success-subtle text-success-emphasis">{{ __('Verified') }}</span>@else<span class="badge bg-warning-subtle text-warning-emphasis">{{ __('Not verified') }}</span>@endif</div>
                <div class="small text-muted">@if ($tender->status === 'pending'){{ __('Waiting since :date. Check it, correct small things below if needed, then publish it or send it back.', ['date' => fmt_datetime($tender->submitted_at)]) }}@elseif ($tender->status === 'rejected'){{ __('Sent back:') }} {{ __($tender->reject_reason) }}@else{{ $tender->company->email }}@endif</div>
            </div>
            @if ($tender->status === 'pending')
                <form method="POST" action="{{ route('admin.tenders.reject', ['id' => $tender->id]) }}" class="d-flex gap-2 flex-wrap">
                    @csrf
                    <select class="form-select w-auto" name="reason" required aria-label="{{ __('Reason') }}" data-plain style="max-width:260px">
                        <option value="">{{ __('Reason to send back…') }}</option>
                        @foreach ($reasons as $key => $text)
                            <option value="{{ $key }}">{{ __($text) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-soft-danger">{{ __('Send back') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.tenders.approve', ['id' => $tender->id]) }}">@csrf<button type="submit" class="btn btn-success"><x-icon name="check" class="me-1" /> {{ __('Publish') }}</button></form>
            @endif
        </div>
    </div>
@endif

<form method="POST" action="{{ $tender ? route('admin.tenders.update', ['id' => $tender->id]) : route('admin.tenders.store') }}" enctype="multipart/form-data" id="tenderForm">
    @csrf
    @if ($tender)
        @method('PUT')
    @endif
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
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="f-type">{{ __('Kind of tender') }} <span class="text-danger">*</span></label>
                            <select class="form-select @error('type') is-invalid @enderror" id="f-type" name="type" required data-plain>
                                @foreach (config('tenders.types') as $key => [$short, $long])
                                    <option value="{{ $key }}" @selected($value('type', 'rfq') === $key)>{{ $short }} · {{ __($long) }}</option>
                                @endforeach
                            </select>
                            @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="f-reference">{{ __('Buyer\'s reference number') }}</label>
                            <input type="text" class="form-control @error('reference') is-invalid @enderror" id="f-reference" name="reference" value="{{ $value('reference') }}" maxlength="100" dir="ltr">
                            @error('reference')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="f-buyer-type">{{ __('Kind of buyer') }} <span class="text-danger">*</span></label>
                            <select class="form-select @error('buyer_type') is-invalid @enderror" id="f-buyer-type" name="buyer_type" required data-plain>
                                @foreach (config('tenders.buyer_types') as $key => $label)
                                    <option value="{{ $key }}" @selected($value('buyer_type', 'ngo') === $key)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                            @error('buyer_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="f-buyer">{{ __('Buyer') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('buyer_name') is-invalid @enderror" id="f-buyer" name="buyer_name" value="{{ $value('buyer_name') }}" maxlength="255" required dir="auto">
                            @error('buyer_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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
                                    <a class="flex-grow-1 min-w-0 text-truncate text-decoration-none" href="{{ route('admin.tenders.document', ['id' => $tender->id, 'document' => $document->id]) }}">{{ $document->name }}</a>
                                    <span class="small text-muted">{{ $document->sizeText() }}</span>
                                    <button type="submit" form="doc-delete-{{ $document->id }}" class="btn btn-sm btn-soft-danger" aria-label="{{ __('Remove :name', ['name' => $document->name]) }}"><x-icon name="trash" /></button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <label class="form-label" for="f-documents">{{ __('Add documents') }}</label>
                    <input type="file" class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror" id="f-documents" name="documents[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,image/*">
                    @error('documents')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @error('documents.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('PDF, Word, Excel, zip or pictures, up to 20 MB each. Only subscribers download them.') }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4 d-flex flex-column gap-3">
            <div class="card mb-0 h-auto">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Publishing') }}</h5></div>
                <div class="card-body">
                    <label class="form-label" for="f-status">{{ __('Status') }} <span class="text-danger">*</span></label>
                    <select class="form-select @error('status') is-invalid @enderror" id="f-status" name="status" required data-plain>
                        @foreach (\App\Models\Tender::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected($value('status', 'draft') === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text mb-3">{{ __('A published tender is on the site, and matching companies are alerted once.') }}@if ($tender?->alerted_at) {{ __('Alerts went out on :date.', ['date' => fmt_date($tender->alerted_at)]) }}@endif</div>

                    <label class="form-label" for="f-awarded">{{ __('Awarded to') }}</label>
                    <input type="text" class="form-control mb-3 @error('awarded_to') is-invalid @enderror" id="f-awarded" name="awarded_to" value="{{ $value('awarded_to') }}" maxlength="255" dir="auto">

                    <label class="form-label" for="f-deadline">{{ __('Deadline (Kabul time)') }}</label>
                    <input type="datetime-local" class="form-control mb-3 @error('deadline') is-invalid @enderror" id="f-deadline" name="deadline" value="{{ $deadline }}">
                    @error('deadline')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="f-published">{{ __('Published on') }}</label>
                            <x-date name="published_on" :value="$value('published_on')" id="f-published" />
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="f-questions">{{ __('Questions until') }}</label>
                            <x-date name="questions_until" :value="$value('questions_until')" id="f-questions" />
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
                </div>
            </div>

            <div class="card mb-0 h-auto">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Budget') }}</h5></div>
                <div class="card-body">
                    <label class="form-label" for="f-budget-type">{{ __('The buyer gives') }}</label>
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
                        <div class="col-6">
                            <label class="form-label" for="f-size">{{ __('Estimated size') }}</label>
                            <select class="form-select @error('size_band') is-invalid @enderror" id="f-size" name="size_band" data-plain>
                                <option value="">—</option>
                                @foreach (config('tenders.size_bands') as $key => $label)
                                    <option value="{{ $key }}" @selected($value('size_band') === $key)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-text">{{ __('With no budget, choose the estimated size so companies can still judge the tender.') }}</div>
                </div>
            </div>

            <div class="card mb-0 h-auto">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Source') }}</h5></div>
                <div class="card-body">
                    <label class="form-label" for="f-source">{{ __('Where it was found') }}</label>
                    <input type="text" class="form-control mb-3 @error('source_name') is-invalid @enderror" id="f-source" name="source_name" value="{{ $value('source_name') }}" maxlength="255" placeholder="{{ __('The buyer, or the name of the site') }}">
                    <label class="form-label" for="f-source-url">{{ __('Link to the original notice') }}</label>
                    <input type="url" class="form-control @error('source_url') is-invalid @enderror" id="f-source-url" name="source_url" value="{{ $value('source_url') }}" maxlength="500" dir="ltr" placeholder="https://">
                    @error('source_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
</form>

@if ($tender)
    @foreach ($tender->documents as $document)
        <form id="doc-delete-{{ $document->id }}" method="POST" action="{{ route('admin.tenders.document.destroy', ['id' => $tender->id, 'document' => $document->id]) }}" hidden data-confirm="{{ __('Remove this document?') }}" data-confirm-ok="{{ __('Remove') }}">@csrf @method('DELETE')</form>
    @endforeach
    <div class="card h-auto mt-3" style="max-width:560px">
        <div class="card-body d-flex align-items-center justify-content-between gap-3">
            <div class="min-w-0"><div class="fw-semibold">{{ __('Delete this tender') }}</div><div class="small text-muted">{{ __('Only for a tender entered by mistake. One that was announced is cancelled instead.') }}</div></div>
            <form method="POST" action="{{ route('admin.tenders.destroy', ['id' => $tender->id]) }}" data-confirm="{{ __('Delete this tender?') }}" data-confirm-ok="{{ __('Delete') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-soft-danger flex-shrink-0"><x-icon name="trash" class="me-1" /> {{ __('Delete') }}</button>
            </form>
        </div>
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

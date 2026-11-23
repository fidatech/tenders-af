@extends('layouts.app', ['title' => __($collection->name)])

@php
    $pill = ['researching' => 'bg-secondary-subtle text-secondary-emphasis', 'shortlisted' => 'bg-info-subtle text-info-emphasis', 'contacted' => 'bg-primary-subtle text-primary-emphasis', 'qualified' => 'bg-success-subtle text-success-emphasis', 'preferred' => 'bg-success text-white', 'rejected' => 'bg-danger-subtle text-danger-emphasis'];
    $canEdit = $collection->user_id === auth()->id() || auth()->user()->isOwner();
@endphp

@section('content')
<x-page-header :title="__($collection->name)" icon="folder-open" :crumbs="[[__('Saved suppliers'), route('collections.index')], [__($collection->name)]]" :subtitle="$collection->description">
    @if ($items->total())
        <a class="btn btn-outline-soft" href="{{ route('collections.export', ['id' => $collection->id]) }}"><x-icon name="download" class="me-1" /> {{ __('Export CSV') }}</a>
    @endif
</x-page-header>

<form method="GET" class="d-flex flex-wrap gap-2 mb-3">
    <select class="form-select w-auto" name="status" aria-label="{{ __('Status') }}" onchange="this.form.submit()" data-plain>
        <option value="">{{ __('Every status') }}</option>
        @foreach (\App\Models\SupplierCollectionItem::STATUSES as $key => $label)
            <option value="{{ $key }}" @selected(request('status') === $key)>{{ __($label) }}</option>
        @endforeach
    </select>
    @if ($tags->isNotEmpty())
        <select class="form-select w-auto" name="tag" aria-label="{{ __('Tag') }}" onchange="this.form.submit()" data-plain>
            <option value="">{{ __('Every tag') }}</option>
            @foreach ($tags as $tag)
                <option value="{{ $tag }}" @selected(request('tag') === $tag)>{{ $tag }}</option>
            @endforeach
        </select>
    @endif
    @if (request()->hasAny(['status', 'tag']))<a class="btn btn-link" href="{{ route('collections.show', ['id' => $collection->id]) }}">{{ __('Clear') }}</a>@endif
</form>

@if ($items->isEmpty())
    <div class="card"><div class="card-body">
        <div class="empty-state">
            <div class="es-icon"><x-icon name="folder-open" /></div>
            <h3>{{ request()->hasAny(['status', 'tag']) ? __('Nothing with this filter') : __('No companies here yet') }}</h3>
            <p>{{ __('Open a company in the directory and choose “Save” to add it here.') }}</p>
            @if (module_on('directory'))<a class="btn btn-primary" href="{{ route('site.companies') }}">{{ __('Open the directory') }}</a>@endif
        </div>
    </div></div>
@else
    <form method="GET" action="{{ route('collections.compare', ['id' => $collection->id]) }}" id="compareForm"></form>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <button type="submit" form="compareForm" class="btn btn-sm btn-primary" id="compareBtn" disabled><x-icon name="table-columns" class="me-1" /> {{ __('Compare the chosen ones') }}</button>
        <span class="small text-muted">{{ __('Tick two to four companies.') }}</span>
    </div>
    <div class="d-flex flex-column gap-3">
        @foreach ($items as $item)
            @php $supplier = $item->supplier; @endphp
            <div class="card mb-0">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 align-items-start">
                        <div class="form-check pt-1"><input class="form-check-input compare-pick" type="checkbox" name="c[]" value="{{ $item->id }}" form="compareForm" id="cmp-{{ $item->id }}" aria-label="{{ __('Compare :name', ['name' => $supplier->name]) }}"></div>
                        <div class="min-w-0 flex-grow-1">
                            <a class="fw-semibold fs-6 text-decoration-none" href="{{ route('site.companies.show', ['slug' => $supplier->slug]) }}" target="_blank" rel="noopener">{{ $supplier->name }}</a>
                            <span class="badge {{ $pill[$item->status] ?? '' }} ms-1">{{ __(\App\Models\SupplierCollectionItem::STATUSES[$item->status] ?? $item->status) }}</span>
                            <div class="small text-muted">{{ collect([$supplier->province?->name ?: $supplier->country, implode(', ', $supplier->businessTypeLabels()), $supplier->industries->pluck('name')->take(3)->join(', ')])->filter()->join(' · ') }}</div>
                            @if ($item->tags)<div class="mt-1 d-flex flex-wrap gap-1">@foreach ($item->tags as $tag)<span class="badge bg-light text-dark border">{{ $tag }}</span>@endforeach</div>@endif
                            @if ($item->note)<div class="small mt-2 p-2 rounded-2 bg-light" style="white-space:pre-line" dir="auto">{{ $item->note }}</div>@endif
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-light-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-{{ $item->id }}" aria-expanded="false" aria-controls="edit-{{ $item->id }}"><x-icon name="pen" class="me-1" /> {{ __('Notes') }}</button>
                            <form method="POST" action="{{ route('collections.items.destroy', ['id' => $collection->id, 'item' => $item->id]) }}" data-confirm="{{ __('Remove :name from this collection?', ['name' => $supplier->name]) }}" data-confirm-ok="{{ __('Remove') }}" data-confirm-style="danger">@csrf @method('DELETE')<button class="btn btn-sm btn-light-danger" type="submit" aria-label="{{ __('Remove :name', ['name' => $supplier->name]) }}"><x-icon name="trash" /></button></form>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('collections.items.update', ['id' => $collection->id, 'item' => $item->id]) }}" class="collapse mt-3" id="edit-{{ $item->id }}">
                        @csrf @method('PUT')
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <label class="form-label small" for="st-{{ $item->id }}">{{ __('Status') }}</label>
                                <select class="form-select form-select-sm" id="st-{{ $item->id }}" name="status" data-plain>
                                    @foreach (\App\Models\SupplierCollectionItem::STATUSES as $key => $label)<option value="{{ $key }}" @selected($item->status === $key)>{{ __($label) }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small" for="tg-{{ $item->id }}">{{ __('Tags, separated by commas') }}</label>
                                <input type="text" class="form-control form-control-sm" id="tg-{{ $item->id }}" name="tags" value="{{ implode(', ', (array) $item->tags) }}" maxlength="200">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small" for="mv-{{ $item->id }}">{{ __('Move to') }}</label>
                                <select class="form-select form-select-sm" id="mv-{{ $item->id }}" name="move_to" data-plain>
                                    <option value="">{{ __('Keep it here') }}</option>
                                    @foreach ($others as $other)<option value="{{ $other->id }}">{{ __($other->name) }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small" for="nt-{{ $item->id }}">{{ __('Private note') }}</label>
                                <textarea class="form-control form-control-sm" id="nt-{{ $item->id }}" name="note" rows="2" maxlength="2000" dir="auto">{{ $item->note }}</textarea>
                            </div>
                            <div class="col-12 text-end"><button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button></div>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-3">{{ $items->links('partials.pagination') }}</div>
@endif

@if ($canEdit)
    <div class="card mt-4">
        <div class="card-header"><h6 class="mb-0">{{ __('This collection') }}</h6></div>
        <form method="POST" action="{{ route('collections.update', ['id' => $collection->id]) }}" class="card-body row g-2 align-items-end">
            @csrf @method('PUT')
            <div class="col-12 col-md-4"><label class="form-label small" for="cn">{{ __('Name') }}</label><input class="form-control" id="cn" name="name" value="{{ __($collection->name) }}" maxlength="100" required></div>
            <div class="col-12 col-md-5"><label class="form-label small" for="cd">{{ __('Description') }}</label><input class="form-control" id="cd" name="description" value="{{ $collection->description }}" maxlength="300"></div>
            <div class="col-12 col-md-3 d-flex flex-wrap gap-2 align-items-center">
                @unless ($collection->is_favourites)<div class="form-check"><input class="form-check-input" type="checkbox" id="cp" name="is_private" value="1" @checked($collection->is_private)><label class="form-check-label" for="cp">{{ __('Private') }}</label></div>@endunless
                <button class="btn btn-primary" type="submit">{{ __('Save') }}</button>
                @unless ($collection->is_favourites)<button class="btn btn-link text-danger" type="submit" form="delCollection">{{ __('Delete') }}</button>@endunless
            </div>
        </form>
    </div>
    @unless ($collection->is_favourites)
        <form id="delCollection" method="POST" action="{{ route('collections.destroy', ['id' => $collection->id]) }}" hidden data-confirm="{{ __('Delete this collection?') }}" data-confirm-text="{{ __('The companies stay in the directory; only your list and notes are deleted.') }}" data-confirm-ok="{{ __('Delete') }}" data-confirm-style="danger">@csrf @method('DELETE')</form>
    @endunless
@endif

@push('scripts')
<script>
(function () {
    var picks = document.querySelectorAll('.compare-pick'), btn = document.getElementById('compareBtn');
    if (!btn) return;
    function sync() {
        var n = Array.prototype.filter.call(picks, function (p) { return p.checked; }).length;
        btn.disabled = n < 2 || n > 4;
        picks.forEach(function (p) { p.disabled = !p.checked && n >= 4; });
    }
    picks.forEach(function (p) { p.addEventListener('change', sync); });
})();
</script>
@endpush
@endsection

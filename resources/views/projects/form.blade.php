@extends('layouts.app', ['title' => $project->exists ? $project->title : __('Add a project')])

@php
    $value = fn (string $key, $default = null) => old($key, $project->{$key} ?? $default);
    $date = fn (string $key) => old($key, $project->{$key}?->format('Y-m-d'));
    $images = $project->exists ? $project->files->where('kind', 'image') : collect();
    $evidence = $project->exists ? $project->files->where('kind', 'evidence') : collect();
    $review = ['submitted' => 'secondary', 'pending' => 'warning', 'evidence_reviewed' => 'success', 'client_confirmed' => 'success', 'rejected' => 'danger'][$project->review_status ?? 'submitted'];
    $company = company();
@endphp

@section('content')
<x-page-header :title="$project->exists ? $project->title : __('Add a project')" icon="briefcase" :crumbs="[[__('Projects'), route('projects.index')], [$project->exists ? __('Edit') : __('New')]]">
    @if ($project->exists && $project->status === 'published' && $company->is_listed && module_on('directory'))
        <a class="btn btn-outline-soft" href="{{ route('site.projects.show', ['slug' => $company->slug, 'project' => $project->slug]) }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('See on your page') }}</a>
    @endif
</x-page-header>

@if ($project->exists)
    <div class="alert alert-{{ $review }} d-flex gap-2 align-items-start small">
        <x-icon name="shield-halved" class="mt-1" />
        <div><strong>{{ $project->reviewLabel() }}.</strong> {{ $project->reviewMeaning() }}@if ($project->review_note) <span class="d-block mt-1">{{ __('Note from Tenders.af:') }} {{ $project->review_note }}</span>@endif</div>
    </div>
@endif

<form method="POST" action="{{ $project->exists ? route('projects.update', ['id' => $project->id]) : route('projects.store') }}" enctype="multipart/form-data">
    @csrf
    @if ($project->exists)
        @method('PUT')
    @endif
    <div class="row g-3">
        <div class="col-12 col-xl-8 d-flex flex-column gap-3">
            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('The project') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="p-title">{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="p-title" name="title" value="{{ $value('title') }}" maxlength="200" required dir="auto">
                            @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="p-industry">{{ __('Sector') }}</label>
                            <select class="form-select" id="p-industry" name="industry_id" data-search>
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($industries as $id => $name)
                                    <option value="{{ $id }}" @selected((string) $value('industry_id') === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="p-progress">{{ __('Status') }}</label>
                            <select class="form-select" id="p-progress" name="progress" data-plain>
                                @foreach (\App\Models\CompanyProject::PROGRESS as $key => $label)
                                    <option value="{{ $key }}" @selected($value('progress', 'completed') === $key)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="p-location">{{ __('Location') }}</label>
                            <input type="text" class="form-control" id="p-location" name="location" value="{{ $value('location') }}" maxlength="200" dir="auto" placeholder="{{ __('City or province') }}">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="p-country">{{ __('Country') }}</label>
                            <x-country-select id="p-country" name="country" :value="$value('country')" class="form-select" />
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="p-start">{{ __('Started') }}</label>
                            <input type="date" class="form-control @error('started_on') is-invalid @enderror" id="p-start" name="started_on" value="{{ $date('started_on') }}" max="{{ today_iso() }}">
                            @error('started_on')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="p-end">{{ __('Completed') }}</label>
                            <input type="date" class="form-control @error('completed_on') is-invalid @enderror" id="p-end" name="completed_on" value="{{ $date('completed_on') }}" max="{{ today_iso() }}">
                            @error('completed_on')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="p-description">{{ __('Description') }}</label>
                            <textarea class="form-control" id="p-description" name="description" rows="4" maxlength="5000" dir="auto">{{ $value('description') }}</textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="p-scope">{{ __('Scope of work') }}</label>
                            <textarea class="form-control" id="p-scope" name="scope" rows="3" maxlength="3000" dir="auto">{{ $value('scope') }}</textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="p-resp">{{ __('What your company did') }}</label>
                            <textarea class="form-control" id="p-resp" name="responsibilities" rows="3" maxlength="3000" dir="auto">{{ $value('responsibilities') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Client and value') }}</h5><div class="form-text mt-0">{{ __('Shown only when you allow it.') }}</div></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="p-client">{{ __('Client') }}</label>
                            <input type="text" class="form-control" id="p-client" name="client_name" value="{{ $value('client_name') }}" maxlength="200" dir="auto">
                            <div class="form-check mt-2"><input type="hidden" name="client_public" value="0"><input class="form-check-input" type="checkbox" id="p-client-public" name="client_public" value="1" @checked($value('client_public'))><label class="form-check-label small" for="p-client-public">{{ __('We may name this client publicly') }}</label></div>
                        </div>
                        <div class="col-8 col-md-4">
                            <label class="form-label" for="p-value">{{ __('Contract value') }}</label>
                            <input type="text" inputmode="decimal" class="form-control @error('contract_value') is-invalid @enderror" id="p-value" name="contract_value" value="{{ $value('contract_value') !== null ? rtrim(rtrim((string) $value('contract_value'), '0'), '.') : '' }}" dir="ltr">
                            @error('contract_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-check mt-2"><input type="hidden" name="value_public" value="0"><input class="form-check-input" type="checkbox" id="p-value-public" name="value_public" value="1" @checked($value('value_public'))><label class="form-check-label small" for="p-value-public">{{ __('Show the value') }}</label></div>
                        </div>
                        <div class="col-4 col-md-2">
                            <label class="form-label" for="p-currency">{{ __('Currency') }}</label>
                            <select class="form-select @error('contract_currency') is-invalid @enderror" id="p-currency" name="contract_currency" data-plain>
                                <option value=""></option>
                                @foreach (['AFN', 'USD', 'EUR', 'PKR', 'IRR', 'CNY', 'TRY', 'AED', 'GBP'] as $currency)
                                    <option value="{{ $currency }}" @selected($value('contract_currency', $company->currency) === $currency)>{{ $currency }}</option>
                                @endforeach
                            </select>
                            @error('contract_currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4 d-flex flex-column gap-3">
            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Pictures') }}</h5><div class="form-text mt-0">{{ __('Up to :n. Shown on your page when the project is published.', ['n' => \App\Models\CompanyProject::MAX_IMAGES]) }}</div></div>
                <div class="card-body">
                    @if ($images->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach ($images as $image)
                                <div class="position-relative">
                                    <img src="{{ route('projects.file', ['id' => $project->id, 'file' => $image->id]) }}" alt="{{ $image->caption ?: $project->title }}" width="96" height="72" class="rounded border" style="object-fit:cover">
                                    <button type="submit" form="del-file-{{ $image->id }}" class="btn btn-sm btn-danger position-absolute top-0 end-0 py-0 px-1" aria-label="{{ __('Remove this picture') }}"><x-icon name="xmark" /></button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if ($images->count() < \App\Models\CompanyProject::MAX_IMAGES)
                        <x-file-drop name="images[]" id="p-images" accept="image/jpeg,image/png,image/webp" :max="\App\Models\CompanyProject::MAX_IMAGES - $images->count()" :max-mb="5" :hint="__('JPG, PNG or WebP, up to 5 MB each.')" />
                    @endif
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Evidence for a review') }}</h5><div class="form-text mt-0">{{ __('Private: only you and the reviewers of Tenders.af see these.') }}</div></div>
                <div class="card-body">
                    @foreach ($evidence as $file)
                        <div class="d-flex align-items-center gap-2 small mb-2">
                            <x-icon name="file-lines" class="text-muted" />
                            <a class="text-truncate flex-grow-1" href="{{ route('projects.file', ['id' => $project->id, 'file' => $file->id]) }}">{{ $file->name }}</a>
                            <button type="submit" form="del-file-{{ $file->id }}" class="btn btn-sm btn-link text-danger p-0" aria-label="{{ __('Remove :name', ['name' => $file->name]) }}"><x-icon name="trash" /></button>
                        </div>
                    @endforeach
                    @if ($evidence->count() < \App\Models\CompanyProject::MAX_EVIDENCE)
                        <x-file-drop name="evidence[]" id="p-evidence" accept="application/pdf,image/jpeg,image/png" :max="\App\Models\CompanyProject::MAX_EVIDENCE - $evidence->count()" :max-mb="10" :hint="__('A contract, a completion certificate or a client letter. PDF, JPG or PNG, up to 10 MB.')" />
                    @endif
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-body d-flex flex-column gap-2">
                    <div class="form-check"><input type="hidden" name="is_featured" value="0"><input class="form-check-input" type="checkbox" id="p-featured" name="is_featured" value="1" @checked($value('is_featured'))><label class="form-check-label" for="p-featured">{{ __('Feature it at the top of my page') }}</label></div>
                    <button type="submit" name="action" value="publish" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ $project->status === 'published' ? __('Save') : __('Publish on my page') }}</button>
                    <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">{{ $project->status === 'published' ? __('Save as draft (hide it)') : __('Save as draft') }}</button>
                    @if ($project->exists)
                        <button type="submit" form="del-project" class="btn btn-link text-danger">{{ __('Delete the project') }}</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</form>

@if ($project->exists)
    @foreach ($project->files as $file)
        <form id="del-file-{{ $file->id }}" method="POST" action="{{ route('projects.file.destroy', ['id' => $project->id, 'file' => $file->id]) }}" hidden data-confirm="{{ __('Remove this file?') }}" data-confirm-ok="{{ __('Remove') }}" data-confirm-style="danger">@csrf @method('DELETE')</form>
    @endforeach
    <form id="del-project" method="POST" action="{{ route('projects.destroy', ['id' => $project->id]) }}" hidden data-confirm="{{ __('Delete this project?') }}" data-confirm-text="{{ __('Its pictures and documents are deleted too.') }}" data-confirm-ok="{{ __('Delete') }}" data-confirm-style="danger">@csrf @method('DELETE')</form>
@endif
@endsection

@extends('layouts.admin', ['title' => $project->title])

@php
    $facts = array_filter([
        __('Company') => $project->company?->name,
        __('Sector') => $project->industry?->name,
        __('Location') => collect([$project->location, $project->country])->filter()->join(', '),
        __('Period') => $project->period(),
        __('Client') => $project->client_name ? $project->client_name.($project->client_public ? '' : ' ('.__('not public').')') : null,
        __('Contract value') => $project->contract_value !== null ? money($project->contract_value, $project->contract_currency).($project->value_public ? '' : ' ('.__('not public').')') : null,
        __('On the page') => $project->status === 'published' ? __('Published') : __('Draft'),
    ]);
@endphp

@section('content')
<x-page-header :title="$project->title" icon="briefcase" :crumbs="[[__('Company projects'), route('admin.projects.index')], [$project->company?->name]]">
    @if ($project->status === 'published' && $project->company?->is_listed && module_on('portfolios'))
        <a class="btn btn-outline-soft" href="{{ route('site.projects.show', ['slug' => $project->company->slug, 'project' => $project->slug]) }}" target="_blank" rel="noopener"><x-icon name="eye" class="me-1" /> {{ __('See on the site') }}</a>
    @endif
</x-page-header>

<div class="row g-3">
    <div class="col-12 col-xl-8 d-flex flex-column gap-3">
        <div class="card mb-0">
            <div class="card-body">
                <dl class="row mb-0">
                    @foreach ($facts as $label => $value)
                        <dt class="col-sm-4 text-muted fw-semibold small">{{ $label }}</dt><dd class="col-sm-8">{{ $value }}</dd>
                    @endforeach
                </dl>
                @foreach ([__('Description') => $project->description, __('Scope of work') => $project->scope, __('What the company did') => $project->responsibilities] as $label => $text)
                    @if ($text)<h6 class="mt-3">{{ $label }}</h6><p class="mb-0" style="white-space:pre-line" dir="auto">{{ $text }}</p>@endif
                @endforeach
            </div>
        </div>
        @if ($project->files->where('kind', 'image')->isNotEmpty())
            <div class="card mb-0"><div class="card-header"><h6 class="mb-0">{{ __('Pictures') }}</h6></div><div class="card-body d-flex flex-wrap gap-2">
                @foreach ($project->files->where('kind', 'image') as $image)
                    <a href="{{ route('admin.projects.file', ['id' => $project->id, 'file' => $image->id]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.projects.file', ['id' => $project->id, 'file' => $image->id]) }}" alt="" width="140" height="100" class="rounded border" style="object-fit:cover"></a>
                @endforeach
            </div></div>
        @endif
    </div>

    <div class="col-12 col-xl-4 d-flex flex-column gap-3">
        <div class="card mb-0">
            <div class="card-header"><h6 class="mb-0">{{ __('Evidence') }}</h6><div class="form-text mt-0">{{ __('Private to the company and the reviewers.') }}</div></div>
            <div class="card-body">
                @forelse ($project->files->where('kind', 'evidence') as $file)
                    <div class="d-flex align-items-center gap-2 small mb-2"><x-icon name="file-lines" class="text-muted" /><a class="text-truncate" href="{{ route('admin.projects.file', ['id' => $project->id, 'file' => $file->id]) }}" target="_blank" rel="noopener">{{ $file->name }}</a><span class="text-muted ms-auto text-nowrap">{{ number_format($file->size / 1024) }} KB</span></div>
                @empty
                    <p class="text-muted small mb-0">{{ __('The company has not sent documents for this project.') }}</p>
                @endforelse
            </div>
        </div>

        <form method="POST" action="{{ route('admin.projects.review', ['id' => $project->id]) }}" class="card mb-0">
            @csrf
            <div class="card-header"><h6 class="mb-0">{{ __('Review') }}</h6></div>
            <div class="card-body d-flex flex-column gap-2">
                @foreach (\App\Models\CompanyProject::REVIEW as $key => [$label, $meaning])
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="review_status" id="r-{{ $key }}" value="{{ $key }}" @checked(old('review_status', $project->review_status) === $key) @disabled(! \App\Support\AdminAccess::allows(auth('admin')->user(), 'admin.projects.review', false))>
                        <label class="form-check-label" for="r-{{ $key }}"><strong>{{ __($label) }}</strong><span class="d-block small text-muted">{{ __($meaning) }}</span></label>
                    </div>
                @endforeach
                @error('review_status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <label class="form-label mt-2 mb-0" for="r-note">{{ __('Note to the company') }}</label>
                <textarea class="form-control @error('review_note') is-invalid @enderror" id="r-note" name="review_note" rows="2" maxlength="500">{{ old('review_note', $project->review_note) }}</textarea>
                @error('review_note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @if ($project->reviewed_at)<div class="form-text">{{ __('Last reviewed :date.', ['date' => fmt_datetime($project->reviewed_at)]) }}</div>@endif
            </div>
            @if (\App\Support\AdminAccess::allows(auth('admin')->user(), 'admin.projects.review', false))
                <div class="card-footer text-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save the review') }}</button></div>
            @endif
        </form>
    </div>
</div>
@endsection

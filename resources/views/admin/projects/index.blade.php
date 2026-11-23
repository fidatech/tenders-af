@extends('layouts.admin', ['title' => __('Company projects')])

@php
    $pill = ['submitted' => 'bg-secondary-subtle text-secondary-emphasis', 'pending' => 'bg-warning-subtle text-warning-emphasis', 'evidence_reviewed' => 'bg-success-subtle text-success-emphasis', 'client_confirmed' => 'bg-success-subtle text-success-emphasis', 'rejected' => 'bg-danger-subtle text-danger-emphasis'];
@endphp

@section('content')
<x-page-header :title="__('Company projects')" icon="briefcase" :subtitle="__('Projects companies show on their pages. Review the evidence they send; record only what you actually checked.')" />

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-sm {{ request('review') ? 'btn-light-secondary' : 'btn-primary' }}" href="{{ route('admin.projects.index') }}">{{ __('All') }} <span class="badge bg-white text-dark ms-1">{{ $counts->sum() }}</span></a>
    @foreach (\App\Models\CompanyProject::REVIEW as $key => [$label])
        <a class="btn btn-sm {{ request('review') === $key ? 'btn-primary' : 'btn-light-secondary' }}" href="{{ route('admin.projects.index', ['review' => $key]) }}">{{ __($label) }} <span class="badge bg-white text-dark ms-1">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            @if (request('review'))<input type="hidden" name="review" value="{{ request('review') }}">@endif
            <div class="col-12 col-md"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search a project or a company…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col col-md-auto">
                <select class="form-select" name="status" aria-label="{{ __('On the page') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Published and drafts') }}</option>
                    <option value="published" @selected(request('status') === 'published')>{{ __('Published') }}</option>
                    <option value="draft" @selected(request('status') === 'draft')>{{ __('Drafts') }}</option>
                </select>
            </div>
            <div class="col col-md-auto">
                <select class="form-select" name="sort" aria-label="{{ __('Sort') }}" onchange="this.form.submit()" data-plain>
                    <option value="newest" @selected($sort === 'newest')>{{ __('Recently changed') }}</option>
                    <option value="oldest" @selected($sort === 'oldest')>{{ __('Waiting longest') }}</option>
                    <option value="title" @selected($sort === 'title')>{{ __('Title A–Z') }}</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="briefcase" /></div><h3>{{ __('Nothing found') }}</h3><p>{{ __('Projects appear here when companies add them.') }}</p></div>
        @else
            <div class="table-responsive">
                <table class="table table-hover w-100 align-middle">
                    <thead><tr><th>{{ __('Project') }}</th><th>{{ __('Company') }}</th><th>{{ __('Review') }}</th><th>{{ __('Documents') }}</th><th>{{ __('Changed') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $project)
                            <tr>
                                <td><a class="fw-semibold text-decoration-none" href="{{ route('admin.projects.show', ['id' => $project->id]) }}">{{ $project->title }}</a><span class="d-block small text-muted">{{ $project->status === 'published' ? __('Published') : __('Draft') }}</span></td>
                                <td>{{ $project->company?->name }}</td>
                                <td><span class="badge {{ $pill[$project->review_status] ?? '' }}">{{ $project->reviewLabel() }}</span></td>
                                <td>{{ $project->evidence_count }}</td>
                                <td class="text-nowrap small">{{ fmt_date($project->updated_at) }}</td>
                                <td class="text-end"><x-act kind="view" :href="route('admin.projects.show', ['id' => $project->id])" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection

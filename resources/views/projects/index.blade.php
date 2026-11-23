@extends('layouts.app', ['title' => __('Projects')])

@php
    $review = ['submitted' => 'bg-secondary-subtle text-secondary-emphasis', 'pending' => 'bg-warning-subtle text-warning-emphasis', 'evidence_reviewed' => 'bg-success-subtle text-success-emphasis', 'client_confirmed' => 'bg-success-subtle text-success-emphasis', 'rejected' => 'bg-danger-subtle text-danger-emphasis'];
@endphp

@section('content')
<x-page-header :title="__('Projects')" icon="briefcase" :subtitle="__('Work your company has done. Published projects are shown on your public page; drafts only here.')">
    <a class="btn btn-primary" href="{{ route('projects.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add a project') }}</a>
</x-page-header>

<div class="alert alert-light border small d-flex gap-2 align-items-start">
    <x-icon name="circle-info" class="mt-1" />
    <span>{{ __('A project shows "Company submitted" until Tenders.af checks it. Send documents (a contract, a completion certificate or a client letter) to ask for a review. Uploading documents alone does not mark a project as checked.') }}</span>
</div>

<div class="card">
    <div class="card-body">
        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="briefcase" /></div>
                <h3>{{ __('No projects yet') }}</h3>
                <p>{{ __('Add the projects you are proud of, with pictures. Buyers look for experience like yours.') }}</p>
                <a class="btn btn-primary" href="{{ route('projects.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add a project') }}</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover w-100 align-middle">
                    <thead><tr><th>{{ __('Project') }}</th><th>{{ __('On your page') }}</th><th>{{ __('Checked by Tenders.af') }}</th><th>{{ __('Views') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $project)
                            <tr>
                                <td>
                                    <a class="text-decoration-none text-reset" href="{{ route('projects.edit', ['id' => $project->id]) }}">
                                        <span class="d-block fw-semibold">@if ($project->is_featured)<x-icon name="star" class="text-warning me-1" />@endif{{ $project->title }}</span>
                                        <span class="d-block small text-muted">{{ collect([$project->location, $project->period(), trans_choice('{0} no pictures|{1} 1 picture|[2,*] :count pictures', $project->images_count)])->filter()->join(' · ') }}</span>
                                    </a>
                                </td>
                                <td>@if ($project->status === 'published')<span class="badge bg-success-subtle text-success-emphasis">{{ __('Published') }}</span>@else<span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Draft') }}</span>@endif</td>
                                <td><span class="badge {{ $review[$project->review_status] ?? '' }}">{{ $project->reviewLabel() }}</span>@if ($project->review_status === 'rejected' && $project->review_note)<span class="d-block small text-danger mt-1">{{ $project->review_note }}</span>@endif</td>
                                <td>{{ number_format($project->views) }}</td>
                                <td class="text-end text-nowrap">
                                    <div class="act-group">
                                        @if ($project->status === 'published' && $company->is_listed && module_on('directory'))
                                            <x-act kind="view" :href="route('site.projects.show', ['slug' => $company->slug, 'project' => $project->slug])" blank />
                                        @endif
                                        <x-act kind="edit" :href="route('projects.edit', ['id' => $project->id])" />
                                    </div>
                                </td>
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

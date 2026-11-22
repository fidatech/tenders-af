@extends('layouts.app', ['title' => __('Saved suppliers')])

@section('content')
<x-page-header :title="__('Saved suppliers')" icon="folder-open" :subtitle="__('Keep the companies you are looking at in collections, with your own statuses, notes and tags. Only your company sees them; the suppliers never do.')" />

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card mb-0">
            <div class="card-body">
                <div class="list-group list-group-flush">
                    @foreach ($collections as $collection)
                        <a class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3" href="{{ route('collections.show', ['id' => $collection->id]) }}">
                            <span class="icon-soft {{ $collection->is_favourites ? 'warning' : 'primary' }} rounded-3 d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:42px;height:42px"><x-icon :name="$collection->is_favourites ? 'star' : 'folder-open'" /></span>
                            <span class="min-w-0 flex-grow-1">
                                <span class="d-block fw-semibold">{{ __($collection->name) }} @if ($collection->is_private)<span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">{{ __('Private') }}</span>@endif</span>
                                <span class="d-block small text-muted text-truncate">{{ $collection->description ?: ($collection->is_private ? __('Only you see this collection.') : __('Shared with your team.').($collection->user ? ' '.__('Made by :name.', ['name' => $collection->user->name]) : '')) }}</span>
                            </span>
                            <span class="badge bg-light text-dark border">{{ trans_choice('{0} empty|{1} 1 company|[2,*] :count companies', $collection->items_count) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <form method="POST" action="{{ route('collections.store') }}" class="card mb-0">
            @csrf
            <div class="card-header"><h5 class="card-title mb-0">{{ __('New collection') }}</h5></div>
            <div class="card-body d-flex flex-column gap-2">
                <label class="form-label mb-0" for="c-name">{{ __('Name') }}</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="c-name" name="name" maxlength="100" required placeholder="{{ __('For example: Cement suppliers in Herat') }}">
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <label class="form-label mb-0 mt-1" for="c-desc">{{ __('Description (optional)') }}</label>
                <input type="text" class="form-control" id="c-desc" name="description" maxlength="300">
                <div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="c-private" name="is_private" value="1"><label class="form-check-label" for="c-private">{{ __('Private: only I see it') }}</label></div>
            </div>
            <div class="card-footer text-end"><button type="submit" class="btn btn-primary"><x-icon name="plus" class="me-1" /> {{ __('Make it') }}</button></div>
        </form>
        <p class="small text-muted mt-3">{{ __('To save a company, open its page in the company directory and choose “Save”.') }} @if (module_on('directory'))<a href="{{ route('site.companies') }}">{{ __('Open the directory') }}</a>@endif</p>
    </div>
</div>
@endsection

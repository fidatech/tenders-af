@props(['action' => null, 'term' => null, 'placeholder' => null, 'reset' => null, 'limit' => null])

<form method="GET" @if ($action) action="{{ $action }}" @endif {{ $attributes->class(['row g-2 mb-3']) }}>
    @if ($term !== null)
        <div class="col-12 col-md">
            <input type="search" class="form-control" name="q" value="{{ $term }}"
                placeholder="{{ $placeholder ?? __('Search…') }}" aria-label="{{ __('Search') }}"
                @if ($limit) maxlength="{{ $limit }}" @endif>
        </div>
    @endif

    {{ $slot }}

    <div class="col-auto">
        <button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Apply filters') }}" title="{{ __('Apply filters') }}">
            <x-icon name="magnifying-glass" />
        </button>
        @if ($reset)
            <a class="btn btn-light-secondary" href="{{ $reset }}" aria-label="{{ __('Clear filters') }}" title="{{ __('Clear filters') }}">
                <x-icon name="xmark" />
            </a>
        @endif
    </div>

    {{ $extra ?? '' }}
</form>

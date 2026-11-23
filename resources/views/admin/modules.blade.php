@extends('layouts.admin', ['title' => __('Module Management')])

@php
    use App\Support\Modules;
    $names = collect($modules)->map(fn ($m) => __($m['name']));
    $labels = ['enabled' => __('Switched on'), 'public_nav' => __('In the website menus'), 'dashboard_nav' => __('In the company panel menu'), 'notifications' => __('Notifications'), 'access' => __('Who may use it')];
    $show = fn ($value) => is_bool($value) ? ($value ? __('yes') : __('no')) : ($value === 'paid' ? __('paid plans') : __('everyone'));
    $built = array_filter($modules, fn ($m) => $m['built']);
    $planned = array_filter($modules, fn ($m) => ! $m['built']);
@endphp

@section('content')
<x-page-header :title="__('Module Management')" icon="puzzle-piece" :subtitle="__('Switch parts of the platform on and off. Switching a module off hides it and blocks its pages; nothing saved in it is deleted.')" />

@unless ($canEdit)
    <div class="alert alert-info d-flex gap-2 align-items-center"><x-icon name="circle-info" /> {{ __('Only a super administrator may change modules. You can see their state and history.') }}</div>
@endunless

<div class="row g-3">
    @foreach ($built as $key => $module)
        @php
            $on = Modules::enabled($key);
            $own = Modules::switchedOn($key);
            $blocked = Modules::blockedBy($key);
            $dependents = array_values(array_filter(Modules::dependents($key), fn ($d) => $modules[$d]['built']));
            $state = Modules::state($key);
        @endphp
        <div class="col-12 col-xl-6" id="module-{{ $key }}">
            <form method="POST" action="{{ route('admin.modules.update', ['module' => $key]) }}" class="card h-100 mb-0"
                  @if ($dependents && $own && ! $module['locked']) data-module-dependents="{{ $dependents ? collect($dependents)->map(fn ($d) => $names[$d])->join(', ') : '' }}" @endif>
                @csrf
                @method('PUT')
                <div class="card-body d-flex flex-column gap-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="min-w-0 flex-grow-1">
                            <h5 class="mb-1 d-flex flex-wrap align-items-center gap-2">
                                {{ __($module['name']) }}
                                @if ($module['locked'])
                                    <span class="badge bg-primary-subtle text-primary-emphasis">{{ __('Always on') }}</span>
                                @elseif ($on)
                                    <span class="badge bg-success-subtle text-success-emphasis">{{ __('On') }}</span>
                                @elseif ($own && $blocked)
                                    <span class="badge bg-warning-subtle text-warning-emphasis">{{ __('Off: needs :modules', ['modules' => collect($blocked)->map(fn ($b) => $names[$b])->join(', ')]) }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Off') }}</span>
                                @endif
                            </h5>
                            <p class="text-muted small mb-0">{{ __($module['description']) }}</p>
                        </div>
                        @unless ($module['locked'])
                            <div class="form-check form-switch flex-shrink-0 m-0" title="{{ __('Switched on') }}">
                                <input type="hidden" name="enabled" value="0">
                                <input class="form-check-input" style="width:2.6em;height:1.4em" type="checkbox" role="switch" name="enabled" value="1" id="m-{{ $key }}-on" @checked($own) @disabled(! $canEdit) aria-label="{{ __(':module switched on', ['module' => __($module['name'])]) }}">
                            </div>
                        @endunless
                    </div>

                    @if ($module['depends'] || $dependents)
                        <div class="small d-flex flex-column gap-1">
                            @if ($module['depends'])<div><x-icon name="link" class="text-muted me-1" />{{ __('Needs') }}: {{ collect($module['depends'])->map(fn ($d) => $names[$d])->join(', ') }}</div>@endif
                            @if ($dependents)<div><x-icon name="sitemap" class="text-muted me-1" />{{ __('Switching it off also stops') }}: {{ collect($dependents)->map(fn ($d) => $names[$d])->join(', ') }}</div>@endif
                        </div>
                    @endif

                    <div class="row g-2 small">
                        @foreach (['public_nav' => __('Show in the website menus'), 'dashboard_nav' => __('Show in the company panel menu'), 'notifications' => __('Send its notifications')] as $option => $label)
                            <div class="col-12 col-sm-6">
                                <div class="form-check">
                                    <input type="hidden" name="{{ $option }}" value="0">
                                    <input class="form-check-input" type="checkbox" name="{{ $option }}" value="1" id="m-{{ $key }}-{{ $option }}" @checked($state[$option]) @disabled(! $canEdit)>
                                    <label class="form-check-label" for="m-{{ $key }}-{{ $option }}">{{ $label }}</label>
                                </div>
                            </div>
                        @endforeach
                        <div class="col-12 col-sm-6">
                            <label class="visually-hidden" for="m-{{ $key }}-access">{{ __('Who may use it') }}</label>
                            <select class="form-select form-select-sm" name="access" id="m-{{ $key }}-access" data-native @disabled(! $canEdit)>
                                <option value="all" @selected($state['access'] === 'all')>{{ __('Every company may use it') }}</option>
                                <option value="paid" @selected($state['access'] === 'paid')>{{ __('Only companies on a paid plan') }}</option>
                            </select>
                        </div>
                    </div>

                    @if ($canEdit)
                        <div class="d-flex flex-wrap gap-2 align-items-center mt-auto">
                            <input type="text" name="note" class="form-control form-control-sm flex-grow-1" style="min-width:180px;width:auto" maxlength="500" placeholder="{{ __('Why (optional, kept in the history)') }}" aria-label="{{ __('Why (optional, kept in the history)') }}">
                            <button type="submit" class="btn btn-sm btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
                        </div>
                    @endif
                </div>
            </form>
        </div>
    @endforeach
</div>

@if ($planned)
    <div class="card mt-3">
        <div class="card-header"><h6 class="mb-0">{{ __('Planned') }}</h6><div class="form-text mt-0">{{ __('Not built yet. They are listed so the plan is visible; there is nothing to switch on until they are delivered.') }}</div></div>
        <div class="list-group list-group-flush">
            @foreach ($planned as $key => $module)
                <div class="list-group-item d-flex flex-wrap gap-2 align-items-center">
                    <strong class="me-2">{{ __($module['name']) }}</strong>
                    <span class="badge bg-light text-muted border">{{ __('Planned') }}</span>
                    <span class="text-muted small w-100">{{ __($module['description']) }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">{{ __('History') }}</h6></div>
    @if ($history->isEmpty())
        <div class="card-body text-center text-muted py-4">{{ __('No module has been changed yet.') }}</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Module') }}</th><th>{{ __('Change') }}</th><th>{{ __('By') }}</th></tr></thead>
                <tbody>
                    @foreach ($history as $change)
                        <tr>
                            <td class="text-nowrap small">{{ $change->created_at?->format('j M Y, H:i') }}</td>
                            <td>{{ $names[$change->module] ?? $change->module }}</td>
                            <td class="small">
                                @foreach ($change->changes as $option => [$before, $after])
                                    <div>{{ $labels[$option] ?? $option }}: <span class="text-muted">{{ $show($before) }}</span> → <strong>{{ $show($after) }}</strong></div>
                                @endforeach
                                @if ($change->note)<div class="text-muted fst-italic">“{{ $change->note }}”</div>@endif
                            </td>
                            <td class="small">{{ $change->admin_name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@push('scripts')
<script>
// switching off a module that others need: the app's confirmation says which ones stop as well
document.querySelectorAll('form[data-module-dependents]').forEach(function (form) {
    var toggle = form.querySelector('input[type=checkbox][name=enabled]');
    if (!toggle) return;
    var sync = function () {
        if (toggle.checked) {
            form.removeAttribute('data-confirm');
        } else {
            form.setAttribute('data-confirm', @json(__('Switch this module off?')));
            form.setAttribute('data-confirm-text', @json(__('These modules will stop working too:')) + ' ' + form.dataset.moduleDependents + '. ' + @json(__('Nothing is deleted.')));
            form.setAttribute('data-confirm-ok', @json(__('Switch off')));
            form.setAttribute('data-confirm-style', 'danger');
        }
    };
    toggle.addEventListener('change', sync);
})
</script>
@endpush
@endsection

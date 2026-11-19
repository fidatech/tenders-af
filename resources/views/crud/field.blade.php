@php
    $name = $field['name'];
    $type = $field['type'] ?? 'text';
    $stored = ! empty($field['meta']) ? ($record?->meta[$name] ?? null) : ($record?->{$name} ?? null);
    if ($type === 'password' || $type === 'file' || ! empty($field['virtual'])) {
        $stored = null;
    }
    $fallback = array_key_exists('value', $field) ? $field['value'] : ($record ? null : ($field['default'] ?? null));
    $value = old($name, $stored ?? $fallback);
    $required = in_array('required', $field['rules'] ?? [], true);
    $readonly = ! empty($field['readonly']);
@endphp
@if ($type === 'heading')
    <div class="col-12"><div class="border-top pt-3 mt-2"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ __($field['label']) }}</h6>@if (! empty($field['help']))<div class="form-text">{{ __($field['help']) }}</div>@endif</div></div>
    @php
        return;
    @endphp
@endif
@if (! empty($field['hidden']))
    @php
        return;
    @endphp
@endif
<div class="col-12 col-md-{{ $field['col'] ?? 6 }}">
    @if ($type === 'checkbox')
        <div class="form-check form-switch mt-md-4 pt-md-2">
            <input type="hidden" name="{{ $name }}" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="f-{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $stored ?? ($field['default'] ?? false)))>
            <label class="form-check-label" for="f-{{ $name }}">{{ __($field['label']) }}</label>
            @if (! empty($field['help']))
                <div class="form-text">{{ __($field['help']) }}</div>
            @endif
        </div>
    @else
        <label class="form-label" for="f-{{ $name }}">{{ __($field['label']) }}@if ($required) <span class="text-danger">*</span>@endif</label>
        @if ($type === 'select')
            <select class="form-select @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" @disabled($readonly) @required($required) @if (! empty($field['search'])) data-search @endif @foreach ($field['attrs'] ?? [] as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach @if (! empty($field['quick'])) {{ quick_add(__($field['quick'][0]), $field['quick'][1], $field['quick'][2] ?? []) }} @endif>
                @unless ($required && $value !== null && $value !== '')
                    <option value="">{{ __('Choose…') }}</option>
                @endunless
                @foreach ($field['options'] ?? [] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @elseif ($type === 'file')
            <input type="file" class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" @if (! empty($field['accept'])) accept="{{ $field['accept'] }}" @endif>
        @elseif ($type === 'textarea')
            <textarea class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" rows="2">{{ $value }}</textarea>
        @elseif ($type === 'date')
            <x-date :name="$name" :value="$value" :required="$required" id="f-{{ $name }}" class="{{ $errors->has($name) ? 'is-invalid' : '' }}" />
        @elseif ($type === 'decimal')
            <input type="text" inputmode="decimal" class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" value="{{ $value }}" autocomplete="off" dir="ltr" @readonly($readonly) @required($required)>
        @else
            <input type="{{ $type }}" class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : $value }}" @if ($type === 'password') autocomplete="new-password" @endif @readonly($readonly) @required($required && $type !== 'password')>
        @endif
        @error($name)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @if (! empty($field['help']))
            <div class="form-text">{{ __($field['help']) }}</div>
        @endif
    @endif
</div>

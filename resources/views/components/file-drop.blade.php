@props(['name' => 'documents[]', 'id' => 'f-documents', 'accept' => null, 'max' => 20, 'maxMb' => 20, 'required' => false, 'hint' => null])
{{--
    A box to add several files: drop them on it or choose them, in as many rounds as needed.
    Every file chosen is listed with its size and can be taken out again before saving.
--}}
@php $field = rtrim($name, '[]'); @endphp
<div class="file-drop @if ($errors->has($field) || $errors->has($field.'.*')) is-invalid @endif" data-file-drop data-max="{{ $max }}" data-max-mb="{{ $maxMb }}"
     data-text-many="{{ __('You can add up to :count files at a time.', ['count' => $max]) }}"
     data-text-big="{{ __(':name is larger than :size MB.', ['name' => '%name%', 'size' => $maxMb]) }}"
     data-text-type="{{ __(':name is not a file type we accept.', ['name' => '%name%']) }}"
     data-text-chosen="{{ __(':count files chosen, :size', ['count' => '%count%', 'size' => '%size%']) }}"
     data-text-remove="{{ __('Remove') }}">
    <input type="file" class="fd-input" id="{{ $id }}" name="{{ $name }}" multiple @if ($accept) accept="{{ $accept }}" @endif @required($required)>
    <label class="fd-zone" for="{{ $id }}">
        <span class="fd-icon"><x-icon name="cloud-arrow-up" /></span>
        <span class="fd-text"><b>{{ __('Drop files here or click to choose') }}</b><small>{{ $hint ?? __('Several files at once. You can add more before saving.') }}</small></span>
    </label>
    <div class="fd-warn" role="alert" hidden></div>
    <ul class="fd-list" aria-live="polite"></ul>
    <div class="fd-total small text-muted" hidden></div>
</div>
@error($field)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
@foreach ($errors->get($field.'.*') as $messages)
    @foreach ($messages as $message)<div class="invalid-feedback d-block">{{ $message }}</div>@endforeach
@endforeach

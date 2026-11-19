{{--
    One small action button of a list row: a coloured icon with its name as a tooltip.
    kind: view (blue), receipt (teal), edit (amber), delete or cancel (red).
    With "href" it is a link; without, a submit button for the form around it.
--}}
@props(['kind', 'href' => null, 'label' => null, 'blank' => false])
@php
    [$icon, $color, $text] = [
        'view' => ['eye', 'is-blue', 'View'],
        'receipt' => ['receipt', 'is-teal', 'Receipt'],
        'edit' => ['pen', 'is-amber', 'Edit'],
        'delete' => ['trash', 'is-red', 'Delete'],
        'cancel' => ['ban', 'is-red', 'Cancel'],
    ][$kind];
    $label = $label ?: __($text);
@endphp
@if ($href)
    <a {{ $attributes->class(['btn-act', $color]) }} href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" @if ($blank) target="_blank" rel="noopener" @endif><x-icon :name="$icon" /></a>
@else
    <button type="submit" {{ $attributes->class(['btn-act', $color]) }} title="{{ $label }}" aria-label="{{ $label }}"><x-icon :name="$icon" /></button>
@endif

{{-- A small "?" beside a label: the explanation shows when the pointer is on it, or when it is tapped or reached with the keyboard. --}}
@props(['text'])
<span class="help-tip" tabindex="0" role="note" aria-label="{{ $text }}" data-tip="{{ $text }}"><x-icon name="circle-question" /></span>

{{-- Date box. Gives and receives the date as 2026-10-27. --}}
@props(['name', 'value' => null, 'required' => false])
<input type="date" name="{{ $name }}" value="{{ input_date($value) }}" {{ $required ? 'required' : '' }} {{ $attributes->class('form-control') }}>

@props(['name' => 'country', 'id' => 'country', 'value' => null])
{{-- Every country, searchable (Select2). Afghanistan is chosen when nothing else is. --}}
@php $chosen = $value ?: 'Afghanistan'; @endphp
<select name="{{ $name }}" id="{{ $id }}" {{ $attributes }} data-placeholder="{{ __('Choose a country') }}">
    @foreach (config('countries') as $code => $country)
        <option value="{{ $country }}" @selected($chosen === $country)>{{ $country }}</option>
    @endforeach
</select>

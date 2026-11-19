@php
    $erpText = [
        'cancel' => __('app.actions.cancel'),
        'confirmOk' => __('app.actions.yes'),
        'confirmTitle' => __('app.actions.confirm_title'),
        'noResults' => __('app.nav.no_results'),
        'addNew' => __('New'),
        'failed' => __('Something went wrong. Please try again.'),
    ];
@endphp
<script>window.ERP_TEXT = {{ Illuminate\Support\Js::from($erpText) }};</script>
{{-- every pick list is a Select2 list (public/js/select2-init.js) --}}
<script src="{{ asset_v('vendor/jquery/jquery.min.js') }}" defer></script>
<script src="{{ asset_v('vendor/select2/select2.min.js') }}" defer></script>
<script src="{{ asset_v('js/select2-init.js') }}" defer></script>
<script src="{{ asset_v('js/app.js') }}" defer></script>
@stack('scripts')

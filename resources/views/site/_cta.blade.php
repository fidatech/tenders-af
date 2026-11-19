<div class="cta">
    <div>
        <h2>Registration is free for every company.</h2>
        <p>Afghan companies pay plans in AFN. NGOs and international clients pay in USD.</p>
    </div>
    <div class="btns">
        @if ($signup)<a class="btn btn-white" href="{{ route('register') }}">Register your company</a>@endif
        @if ($signup)<a class="btn btn-ghost" href="{{ route('register', ['as' => 'buyer']) }}">Post a tender</a>@endif
        <a class="btn btn-ghost" href="{{ route('site.pricing') }}">See plans</a>
    </div>
</div>

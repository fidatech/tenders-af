@extends('layouts.gate', ['title' => 'Register for free', 'wide' => true])

@php
    $roles = (array) old('roles', request('as') === 'buyer' ? ['buyer'] : ['supplier']);
    $kind = old('kind', request('as') === 'buyer' ? 'ngo' : 'company');
@endphp

@section('story')
<span class="chip">Free. No card needed.</span>
<h1>One account for <em>every tender</em> in Afghanistan.</h1>
<p class="lead">Find tenders to bid on, post your own, or do both from the same account.</p>
<ul class="gate-points">
    <li><i>01</i><div><b>Tenders that fit you, sent to you</b><span>Choose your sectors and get new tenders by email and WhatsApp.</span></div></li>
    <li><i>02</i><div><b>A public page for your organisation</b><span>Buyers find you in the company directory. It stays free.</span></div></li>
    <li><i>03</i><div><b>Post a tender in minutes</b><span>Ministries, NGOs and companies reach suppliers in all 34 provinces.</span></div></li>
</ul>
@endsection

@section('content')
<div class="eyebrow">Register</div>
<h2>Create your account</h2>
<p class="sub">Three short steps. It takes about a minute.</p>

<form method="POST" action="{{ route('register.store') }}">
    @csrf

    <div class="step"><i>1</i> What do you want to do?</div>
    <div class="roles">
        <label class="role">
            <input type="checkbox" name="roles[]" value="supplier" @checked(in_array('supplier', $roles, true))>
            <span><u aria-hidden="true"></u><svg aria-hidden="true" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><b>Find tenders</b><small>Get alerts and bid on tenders</small></span>
        </label>
        <label class="role">
            <input type="checkbox" name="roles[]" value="buyer" @checked(in_array('buyer', $roles, true))>
            <span><u aria-hidden="true"></u><svg aria-hidden="true" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h3l6 4V6L7 10H4a1 1 0 0 0-1 1Z"/><path d="M17 9a4 4 0 0 1 0 6"/><path d="M19.5 6.5a8 8 0 0 1 0 11"/></svg><b>Post tenders</b><small>Publish tenders and reach suppliers</small></span>
        </label>
    </div>
    @error('roles')<span class="err">{{ $message }}</span>@else<span class="hint">You can tick both, and change it later.</span>@enderror

    <div class="step"><i>2</i> Your organisation</div>
    <div class="fld">
        <label for="company">Name of your company or organisation</label>
        <input type="text" id="company" name="company" value="{{ old('company') }}" class="@error('company') bad @enderror" maxlength="150" required autocomplete="organization">
        @error('company')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="fld">
        <label for="kind">You are</label>
        <select id="kind" name="kind" class="@error('kind') bad @enderror" required>
            @foreach ($kinds as $key => [$label, $currency])
                <option value="{{ $key }}" @selected($kind === $key)>{{ $label }} · plans in {{ $currency }}</option>
            @endforeach
        </select>
        @error('kind')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="two">
        <div class="fld" data-for-kind="company government ngo">
            <label for="province_id">Province</label>
            <select id="province_id" name="province_id" class="@error('province_id') bad @enderror" data-placeholder="Choose a province">
                <option value=""></option>
                @foreach ($provinces as $id => $province)
                    <option value="{{ $id }}" @selected((string) old('province_id') === (string) $id)>{{ $province }}</option>
                @endforeach
            </select>
            @error('province_id')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="fld" data-for-kind="ngo international">
            <label for="country">Country</label>
            <input type="text" id="country" name="country" value="{{ old('country', 'Afghanistan') }}" class="@error('country') bad @enderror" maxlength="100" autocomplete="country-name">
            @error('country')<span class="err">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="step"><i>3</i> Your account</div>
    <div class="fld">
        <label for="name">Your name</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" class="@error('name') bad @enderror" maxlength="150" autocomplete="name" required>
        @error('name')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="two">
        <div class="fld">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" class="@error('email') bad @enderror" autocomplete="username" required>
            @error('email')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="fld">
            <label for="phone">Phone or WhatsApp</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" class="@error('phone') bad @enderror" maxlength="50" dir="ltr" placeholder="+93 70 000 0000" autocomplete="tel">
            @error('phone')<span class="err">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="two">
        <div class="fld">
            <label for="password">Password</label>
            <div class="pw"><input type="password" id="password" name="password" class="@error('password') bad @enderror" autocomplete="new-password" minlength="8" required><button type="button" aria-label="Show the password">Show</button></div>
            @error('password')<span class="err">{{ $message }}</span>@else<span class="hint">At least 8 characters.</span>@enderror
        </div>
        <div class="fld">
            <label for="password_confirmation">Password again</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="8" required>
        </div>
    </div>

    <label class="tick"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required><span>I accept the <a href="{{ route('site.terms') }}" target="_blank" rel="noopener">terms</a> and the <a href="{{ route('site.privacy') }}" target="_blank" rel="noopener">privacy policy</a>.</span></label>
    @error('terms')<span class="err">{{ $message }}</span>@enderror

    <button type="submit" class="btn btn-red go">Create my free account</button>
</form>

<p class="foot">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
@endsection

@push('scripts')
<script>
// the province is asked from organisations in Afghanistan, the country from the others
(function () {
    var kind = document.getElementById('kind');
    var sync = function () {
        document.querySelectorAll('[data-for-kind]').forEach(function (box) {
            box.hidden = box.getAttribute('data-for-kind').split(' ').indexOf(kind.value) < 0;
        });
    };
    kind.addEventListener('change', sync);
    sync();
})();
</script>
@endpush

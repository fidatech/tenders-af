@extends('layouts.gate', ['title' => 'Create your free account'])

@php
    $roles = (array) old('roles', request('as') === 'buyer' ? ['buyer'] : ['supplier']);
    $kind = old('kind', request('as') === 'buyer' ? 'ngo' : 'company');
    // after a mistake the form opens on the first step that has one
    $start = $errors->has('roles') ? 1 : ($errors->hasAny(['company', 'kind', 'province_id', 'country']) ? 2 : ($errors->any() ? 3 : 1));
    $icons = [
        'company' => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01"/>',
        'government' => '<path d="M3 21h18M4 10h16M12 3 3 8h18zM6 10v8M10 10v8M14 10v8M18 10v8"/>',
        'ngo' => '<path d="M12 21s-7-4.5-9.3-9A5 5 0 0 1 12 6a5 5 0 0 1 9.3 6c-2.3 4.5-9.3 9-9.3 9Z"/>',
        'international' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
    ];
    $hints = ['company' => 'Registered in Afghanistan', 'government' => 'Ministries and public offices', 'ngo' => 'National or international NGO', 'international' => 'UN agencies, embassies, foreign firms'];
@endphp

@section('heading')
<h1>Create your free account</h1>
<p>Three short steps, about a minute. No card needed.</p>
@endsection

@section('content')
<form method="POST" action="{{ route('register.store') }}" id="signup" data-start="{{ $start }}" novalidate>
    @csrf
    <ol class="steps" aria-label="Steps">
        <li data-step="1"><span><b>1</b><em>Your goal</em></span></li>
        <li data-step="2"><span><b>2</b><em>Organisation</em></span></li>
        <li data-step="3"><span><b>3</b><em>Your account</em></span></li>
    </ol>

    {{-- step 1 --}}
    <section class="pane" data-pane="1" aria-labelledby="p1">
        <h2 id="p1">What will you use {{ config('app.name') }} for?</h2>
        <p>Choose one or both. You can change it later.</p>
        <div class="choice">
            <label class="opt">
                <input type="checkbox" name="roles[]" value="supplier" @checked(in_array('supplier', $roles, true))>
                <span><span class="pic"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></span><span><b>Find tenders to bid on</b><small>Get matching tenders by email and WhatsApp, save them and download the documents.</small></span><u aria-hidden="true"></u></span>
            </label>
            <label class="opt">
                <input type="checkbox" name="roles[]" value="buyer" @checked(in_array('buyer', $roles, true))>
                <span><span class="pic"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h3l6 4V6L7 10H4a1 1 0 0 0-1 1Z"/><path d="M17 9a4 4 0 0 1 0 6"/><path d="M19.5 6.5a8 8 0 0 1 0 11"/></svg></span><span><b>Post our own tenders</b><small>Publish tenders and reach suppliers in all 34 provinces.</small></span><u aria-hidden="true"></u></span>
            </label>
        </div>
        @error('roles')<span class="err">{{ $message }}</span>@enderror
        <span class="err" data-msg="roles" hidden>Please choose at least one.</span>
        <div class="nav-btns"><button type="button" class="btn btn-green" data-next>Continue</button></div>
    </section>

    {{-- step 2 --}}
    <section class="pane" data-pane="2" aria-labelledby="p2">
        <h2 id="p2">Your organisation</h2>
        <p>This is the name buyers and suppliers will see.</p>
        <div class="fld">
            <label for="company">Name of the company or organisation</label>
            <input type="text" id="company" name="company" value="{{ old('company') }}" class="@error('company') bad @enderror" maxlength="150" required autocomplete="organization" placeholder="For example: Kabul Solar Ltd">
            @error('company')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="fld">
            <span class="lbl" id="kind-label">You are</span>
            <div class="choice two-col" role="radiogroup" aria-labelledby="kind-label">
                @foreach ($kinds as $key => [$label, $currency])
                    <label class="opt">
                        <input type="radio" name="kind" value="{{ $key }}" @checked($kind === $key) required>
                        <span><span class="pic"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$key] ?? $icons['company'] !!}</svg></span><span><b>{{ $label }}</b><small>{{ $hints[$key] ?? '' }}</small><span class="cur">Plans in {{ $currency }}</span></span><u aria-hidden="true"></u></span>
                    </label>
                @endforeach
            </div>
            @error('kind')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="two">
            <div class="fld" data-for-kind="company government ngo">
                <label for="province_id">Province <span class="optional">(optional)</span></label>
                <select id="province_id" name="province_id" data-placeholder="Choose a province">
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
        <div class="nav-btns"><button type="button" class="btn back" data-back>Back</button><button type="button" class="btn btn-green" data-next>Continue</button></div>
    </section>

    {{-- step 3 --}}
    <section class="pane" data-pane="3" aria-labelledby="p3">
        <h2 id="p3">Your account</h2>
        <p>You sign in with this email. We send tender alerts to it.</p>
        <div class="summary" data-summary hidden></div>
        <div class="two">
            <div class="fld">
                <label for="name">Your name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="@error('name') bad @enderror" maxlength="150" autocomplete="name" required>
                @error('name')<span class="err">{{ $message }}</span>@enderror
            </div>
            <div class="fld">
                <label for="phone">WhatsApp number <span class="optional">(optional)</span></label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" class="@error('phone') bad @enderror" maxlength="50" dir="ltr" placeholder="+93 70 000 0000" autocomplete="tel">
                @error('phone')<span class="err">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="fld">
            <label for="email">Work email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" class="@error('email') bad @enderror" autocomplete="username" required placeholder="you@company.af">
            @error('email')<span class="err">{{ $message }}</span>@enderror
        </div>
        {{-- the password: two boxes side by side, then one strength bar and the rules for both --}}
        <div class="pwbox">
            <div class="two">
                <div class="fld">
                    <label for="password">Password</label>
                    <div class="pw"><input type="password" id="password" name="password" class="@error('password') bad @enderror" autocomplete="new-password" minlength="8" required aria-describedby="pw-rules"><button type="button" data-toggle-pw aria-label="Show the passwords">Show</button></div>
                </div>
                <div class="fld">
                    <label for="password_confirmation">Password again</label>
                    <div class="pw"><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="8" required aria-describedby="pw-match"><span class="state" data-match-icon aria-hidden="true"></span></div>
                </div>
            </div>
            <div class="pwmeta" id="pw-rules">
                <div class="strength"><div class="meter" aria-hidden="true"><i></i><i></i><i></i><i></i></div><span data-meter-text>Choose a password</span></div>
                <ul class="rules">
                    <li data-rule="length">8 characters or more</li>
                    <li data-rule="case">Upper and lower case</li>
                    <li data-rule="number">A number</li>
                    <li data-rule="symbol">A sign like ! or #</li>
                </ul>
                <p class="match" id="pw-match" data-match aria-live="polite" hidden></p>
                @error('password')<span class="err">{{ $message }}</span>@enderror
            </div>
        </div>
        <label class="tick"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required><span>I accept the <a href="{{ route('site.terms') }}" target="_blank" rel="noopener">terms</a> and the <a href="{{ route('site.privacy') }}" target="_blank" rel="noopener">privacy policy</a>.</span></label>
        @error('terms')<span class="err">{{ $message }}</span>@enderror
        <div class="nav-btns"><button type="button" class="btn back" data-back>Back</button><button type="submit" class="btn btn-red go">Create my free account</button></div>
    </section>
</form>
@endsection

@section('below')
<div class="acc-trust">
    <span><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg> Free company page</span>
    <span><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg> Tenders from all 34 provinces</span>
    <span><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg> Alerts by email and WhatsApp</span>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('signup');
    var panes = form.querySelectorAll('[data-pane]');
    var marks = form.querySelectorAll('.steps li');
    var kinds = form.querySelectorAll('input[name="kind"]');
    var at = +form.getAttribute('data-start') || 1;

    // the province is asked from organisations in Afghanistan, the country from the others
    var kind = function () { var on = form.querySelector('input[name="kind"]:checked'); return on ? on.value : ''; };
    var syncKind = function () {
        form.querySelectorAll('[data-for-kind]').forEach(function (box) {
            box.hidden = box.getAttribute('data-for-kind').split(' ').indexOf(kind()) < 0;
        });
    };
    kinds.forEach(function (radio) { radio.addEventListener('change', syncKind); });
    syncKind();

    var show = function (step, focus) {
        at = step;
        panes.forEach(function (pane) { pane.classList.toggle('on', +pane.getAttribute('data-pane') === step); });
        marks.forEach(function (li) {
            var n = +li.getAttribute('data-step');
            li.classList.toggle('now', n === step);
            li.classList.toggle('done', n < step);
            if (n === step) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
        });
        if (step === 3) summary();
        if (focus) {
            var first = form.querySelector('[data-pane="' + step + '"] input:not([type=hidden]):not([type=checkbox]):not([type=radio])');
            if (first) first.focus({ preventScroll: true });
            form.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
    };

    // what was chosen, shown on the last step
    var summary = function () {
        var box = form.querySelector('[data-summary]');
        var bits = [];
        form.querySelectorAll('input[name="roles[]"]:checked').forEach(function (r) { bits.push(r.value === 'buyer' ? 'Posts tenders' : 'Finds tenders'); });
        var name = form.querySelector('#company').value.trim();
        if (name) bits.unshift(name);
        var k = form.querySelector('input[name="kind"]:checked');
        if (k) bits.push(k.closest('.opt').querySelector('b').textContent);
        box.innerHTML = '';
        bits.forEach(function (text) { var s = document.createElement('span'); s.textContent = text; box.appendChild(s); });
        box.hidden = !bits.length;
    };

    // a step is checked before the next one opens
    var valid = function (step) {
        var pane = form.querySelector('[data-pane="' + step + '"]');
        if (step === 1) {
            var any = form.querySelectorAll('input[name="roles[]"]:checked').length > 0;
            form.querySelector('[data-msg="roles"]').hidden = any;
            return any;
        }
        var fields = pane.querySelectorAll('input, select');
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (field.closest('[hidden]') || field.type === 'hidden') continue;
            if (!field.checkValidity()) { field.reportValidity(); return false; }
        }
        if (step === 3 && !checkMatch()) { again.focus(); return false; }
        return true;
    };

    form.querySelectorAll('[data-next]').forEach(function (b) { b.addEventListener('click', function () { if (valid(at)) show(at + 1, true); }); });
    form.querySelectorAll('[data-back]').forEach(function (b) { b.addEventListener('click', function () { show(at - 1, true); }); });
    form.querySelectorAll('input[name="roles[]"]').forEach(function (c) { c.addEventListener('change', function () { form.querySelector('[data-msg="roles"]').hidden = true; }); });
    // Enter in a box moves on instead of sending the form early
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && at < 3 && e.target.tagName === 'INPUT') { e.preventDefault(); if (valid(at)) show(at + 1, true); }
    });
    form.addEventListener('submit', function (e) {
        for (var s = 1; s <= 3; s++) { if (!valid(s)) { e.preventDefault(); show(s, false); valid(s); return; } }
    });

    // how strong the password is, which rules it meets, and whether the second box matches
    var pass = form.querySelector('#password'), again = form.querySelector('#password_confirmation');
    var meter = form.querySelector('.meter'), meterText = form.querySelector('[data-meter-text]');
    var match = form.querySelector('[data-match]'), matchIcon = form.querySelector('[data-match-icon]');
    var rules = {
        length: function (v) { return v.length >= 8; },
        'case': function (v) { return /[a-z]/.test(v) && /[A-Z]/.test(v); },
        number: function (v) { return /\d/.test(v); },
        symbol: function (v) { return /[^A-Za-z0-9]/.test(v); }
    };
    var checkMatch = function () {
        var touched = again.value.length > 0;
        var same = pass.value === again.value;
        match.hidden = !touched;
        match.className = 'match ' + (same ? 'ok' : 'no');
        match.textContent = same ? 'The passwords match.' : 'The passwords are not the same yet.';
        matchIcon.className = 'state' + (touched ? (same ? ' ok' : ' no') : '');
        again.classList.toggle('bad', touched && !same);
        again.classList.toggle('good', touched && same && pass.value.length >= 8);
        return same;
    };
    pass.addEventListener('input', function () {
        var v = pass.value, met = 0;
        Object.keys(rules).forEach(function (key) {
            var ok = rules[key](v);
            if (ok) met++;
            form.querySelector('[data-rule="' + key + '"]').classList.toggle('ok', ok);
        });
        var level = !v.length ? 0 : v.length < 8 ? 1 : Math.max(1, met);
        meter.setAttribute('data-level', level);
        meterText.textContent = !v.length ? 'Choose a password' : v.length < 8 ? 'Too short' : ['', 'Weak', 'Fair', 'Good', 'Strong'][level];
        meterText.className = 'lvl-' + level;
        pass.classList.remove('bad');
        if (again.value.length) checkMatch();
    });
    again.addEventListener('input', checkMatch);
    // one "Show" shows both boxes
    form.querySelector('[data-toggle-pw]').addEventListener('click', function (e) {
        e.stopImmediatePropagation();
        var hidden = pass.type === 'password';
        pass.type = again.type = hidden ? 'text' : 'password';
        this.textContent = hidden ? 'Hide' : 'Show';
        this.setAttribute('aria-label', hidden ? 'Hide the passwords' : 'Show the passwords');
    });

    show(at, false);
})();
</script>
@endpush

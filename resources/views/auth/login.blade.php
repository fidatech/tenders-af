@extends('layouts.gate', ['title' => 'Sign in'])

@section('story')
<span class="chip">Welcome back</span>
<h1>Your next tender is <em>already waiting.</em></h1>
<p class="lead">Sign in to see the tenders that match your sectors, the ones you saved, and the tenders you posted.</p>
@endsection

@section('content')
<div class="eyebrow">Sign in</div>
<h2>Good to see you again</h2>
<p class="sub">Use the email address of your account.</p>

<form method="POST" action="{{ route('login.store') }}">
    @csrf
    <div class="fld">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" class="@error('email') bad @enderror" autocomplete="username" required autofocus placeholder="you@company.af">
        @error('email')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="fld">
        <label for="password">Password</label>
        <div class="pw"><input type="password" id="password" name="password" class="@error('password') bad @enderror" autocomplete="current-password" required><button type="button" aria-label="Show the password">Show</button></div>
        @error('password')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="row">
        <label class="tick"><input type="checkbox" name="remember" value="1" checked> Keep me signed in</label>
        <a href="{{ route('password.request') }}" style="font-weight:600;text-decoration:none">Forgot your password?</a>
    </div>
    <button type="submit" class="btn btn-green go">Sign in</button>
</form>

@if (\App\Support\Platform::bool('registration'))
    <p class="foot">New to {{ config('app.name') }}? <a href="{{ route('register') }}">Register for free</a></p>
@endif
@endsection

@extends('layouts.gate', ['title' => 'Forgot your password?'])

@section('story')
<span class="chip">It happens</span>
<h1>Back in your account <em>in a minute.</em></h1>
<p class="lead">We send a link to your email address. Open it and choose a new password.</p>
@endsection

@section('content')
<div class="eyebrow">Password</div>
<h2>Forgot your password?</h2>
<p class="sub">Write the email address of your account.</p>

<form method="POST" action="{{ route('password.email') }}">
    @csrf
    <div class="fld">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" class="@error('email') bad @enderror" autocomplete="username" required autofocus>
        @error('email')<span class="err">{{ $message }}</span>@enderror
    </div>
    <button type="submit" class="btn btn-green go">Send the link</button>
</form>
<p class="foot"><a href="{{ route('login') }}">← Back to sign in</a></p>
@endsection

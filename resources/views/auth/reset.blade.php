@extends('layouts.gate', ['title' => 'Choose a new password'])

@section('story')
<span class="chip">Almost there</span>
<h1>Choose a password <em>only you know.</em></h1>
<p class="lead">At least 8 characters. After saving you sign in with the new password.</p>
@endsection

@section('content')
<div class="eyebrow">Password</div>
<h2>Choose a new password</h2>
<p class="sub">At least 8 characters.</p>

<form method="POST" action="{{ route('password.store') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="fld">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $email) }}" class="@error('email') bad @enderror" autocomplete="username" required>
        @error('email')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="fld">
        <label for="password">New password</label>
        <div class="pw"><input type="password" id="password" name="password" class="@error('password') bad @enderror" autocomplete="new-password" minlength="8" required autofocus><button type="button" aria-label="Show the password">Show</button></div>
        @error('password')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="fld">
        <label for="password_confirmation">New password again</label>
        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="8" required>
    </div>
    <button type="submit" class="btn btn-green go">Save the new password</button>
</form>
@endsection

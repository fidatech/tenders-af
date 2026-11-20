@extends('layouts.gate', ['title' => 'Choose a new password', 'narrow' => true])

@section('heading')
<h1>Choose a new password</h1>
<p>At least 8 characters. Then you sign in with it.</p>
@endsection

@section('content')
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
        <div class="pw"><input type="password" id="password" name="password" class="@error('password') bad @enderror" autocomplete="new-password" minlength="8" required autofocus><button type="button">Show</button></div>
        @error('password')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="fld">
        <label for="password_confirmation">New password again</label>
        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="8" required>
    </div>
    <button type="submit" class="btn btn-green go">Save the new password</button>
</form>
@endsection

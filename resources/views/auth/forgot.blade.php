@extends('layouts.gate', ['title' => 'Forgot your password?', 'narrow' => true])

@section('heading')
<h1>Forgot your password?</h1>
<p>We send a link to your email. Open it and choose a new one.</p>
@endsection

@section('content')
<form method="POST" action="{{ route('password.email') }}">
    @csrf
    <div class="fld">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" class="@error('email') bad @enderror" autocomplete="username" required autofocus placeholder="you@company.af">
        @error('email')<span class="err">{{ $message }}</span>@enderror
    </div>
    <button type="submit" class="btn btn-green go">Send the link</button>
    <p class="alt"><a href="{{ route('login') }}">← Back to sign in</a></p>
</form>
@endsection

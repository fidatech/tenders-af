@extends('layouts.gate', ['title' => 'Sign in', 'narrow' => true])

@section('heading')
<h1>Welcome back</h1>
<p>Sign in to your tenders, alerts and company page.</p>
@endsection

@section('content')
<form method="POST" action="{{ route('login.store') }}">
    @csrf
    <div class="fld">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" class="@error('email') bad @enderror" autocomplete="username" required autofocus placeholder="you@company.af">
        @error('email')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="fld">
        <label for="password">Password</label>
        <div class="pw"><input type="password" id="password" name="password" class="@error('password') bad @enderror" autocomplete="current-password" required><button type="button">Show</button></div>
        @error('password')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="row">
        <label class="tick"><input type="checkbox" name="remember" value="1" checked> Keep me signed in</label>
        <a href="{{ route('password.request') }}">Forgot password?</a>
    </div>
    <button type="submit" class="btn btn-green go">Sign in</button>
</form>
@endsection

@extends('layouts.site', ['crumbs' => [['Contact', null]]])

@section('hero')
@include('site._pagehead', ['heading' => 'Contact us, or post a tender', 'lead' => 'Ask a question, report a tender, or send us a tender to publish. Posting a tender is free.', 'crumbs' => [['Contact', null]]])
@endsection

@section('content')
<div class="wrap" style="padding-top:8px">
    <div class="cols">
        <div class="main">
            @if (session('sent'))
                <p class="note" role="status">Thank you. Your message is sent, and we will answer by email.</p>
            @endif
            <form class="form" method="POST" action="{{ route('site.contact.send') }}" style="margin-top:16px">
                @csrf
                <label>Your name
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="150" autocomplete="name" required>
                    @error('name')<span class="err">{{ $message }}</span>@enderror
                </label>
                <label>Your email
                    <input type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
                    @error('email')<span class="err">{{ $message }}</span>@enderror
                </label>
                <label>Your message
                    <textarea name="message" rows="7" maxlength="5000" required placeholder="For a tender: the buyer, the title, the deadline, and where the documents are.">{{ old('message') }}</textarea>
                    @error('message')<span class="err">{{ $message }}</span>@enderror
                </label>
                {{-- a box people never see; only programs fill it in --}}
                <div class="sr" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div><button type="submit" class="btn btn-green">Send the message</button></div>
            </form>
        </div>
        <aside class="side">
            <div class="sidecard">
                <h2>Other ways to reach us</h2>
                @if (platform('support_whatsapp'))<div style="padding:12px 0;border-top:1px solid var(--line)"><span class="muted" style="font-size:12px">WhatsApp or phone</span><b style="display:block" dir="ltr">{{ platform('support_whatsapp') }}</b></div>@endif
                @if (platform('support_email'))<a class="line" href="mailto:{{ platform('support_email') }}"><span>Email</span><b>{{ platform('support_email') }}</b></a>@endif
                <div style="padding:12px 0;border-top:1px solid var(--line)"><span class="muted" style="font-size:12px">Office</span><b style="display:block">Kabul, Afghanistan</b></div>
            </div>
        </aside>
    </div>
</div>
@endsection

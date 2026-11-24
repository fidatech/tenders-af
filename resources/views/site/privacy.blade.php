@extends('layouts.site', ['crumbs' => [['Privacy', null]]])

@section('hero')
@include('site._pagehead', ['heading' => 'Privacy', 'lead' => 'What '.config('app.name').' keeps about you and your company, and what it does with it.', 'crumbs' => [['Privacy', null]]])
@endsection

@section('content')
<div class="wrap prose" style="padding-top:8px">
    <h2>What we keep</h2>
    <ul>
        <li>Your account: your name, email address and password (stored only as a one-way hash).</li>
        <li>Your company profile: what you write on it, your logo, your sectors and your contact details.</li>
        <li>Your verification document: the business licence you send to be verified.</li>
        <li>Your alert choices: the email address and the WhatsApp number you give for tender alerts.</li>
        <li>Your payments: the plan, the amount, the date and the screenshot of the payment you upload.</li>
    </ul>
    <h2>What is public</h2>
    <p>Your company profile is shown in the public directory: the name, the description, the sectors, the province, the address, the phone number and the website you entered. You can take your company out of the directory at any time on your company profile.</p>
    <p>Your verification document, your payment screenshots, your alert choices and the email addresses of your users are never shown publicly.</p>
    <h2>What we use it for</h2>
    <p>To show your company to buyers, to send you the tenders that match your sectors, to check your verification and your payments, and to answer you when you write to us. We do not sell your data.</p>
    <h2>Email and WhatsApp</h2>
    <p>We send tender alerts by email, and by WhatsApp only if you switched it on. You can switch both off under Tender alerts in your account.</p>
    <h2>Cookies</h2>
    <p>The site uses one cookie to keep you signed in and one to protect its forms. It uses no advertising cookies.</p>

    <h2>Visitor figures</h2>
    <p>To count visitors, the site notes each page opened, the day, the kind of device, the website you came from, and the country and city found from your internet address. The address itself is not kept: it is turned into a code that changes every day and cannot be turned back. The place is looked up on our own server; nothing is sent to another company, and no cookie is used for it. A small script in the page tells the server that a real browser opened it, so that robots and scripts are not counted as people; it stores nothing on your device. Known robots, such as search engines, are counted separately for our own records. Browsers that send "Do Not Track" or "Global Privacy Control" are not counted. These records are deleted after 13 months. The totals by country and larger city appear on the <a href="{{ route('site.statistics') }}">statistics page</a>.</p>
    <h2>Deleting your account</h2>
    <p>Write to us from the <a href="{{ route('site.contact') }}">contact page</a> and we delete your company and its users. Records of payments are kept as long as the law requires.</p>
</div>
@endsection

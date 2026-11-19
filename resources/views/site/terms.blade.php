@extends('layouts.site', ['crumbs' => [['Terms', null]]])

@section('hero')
@include('site._pagehead', ['heading' => 'Terms of use', 'lead' => 'The rules for using '.config('app.name').'.', 'crumbs' => [['Terms', null]]])
@endsection

@section('content')
<div class="wrap prose" style="padding-top:8px">
    <h2>What the site is</h2>
    <p>{{ config('app.name') }} lists tenders and keeps a directory of companies. It is run by Fida Technologies in Kabul. It is not a buyer and not a party to any tender: bids are handed in to the buyer, in the way the tender says.</p>
    <h2>Tenders</h2>
    <p>Tenders are sent to us by buyers or collected by our team from what buyers have published. We check them with care, but the buyer alone is responsible for what a tender says, and its terms can change. Always confirm the deadline and the requirements with the buyer or in the tender documents before you bid.</p>
    <p>A real tender does not ask you to pay us or anybody else in order to take part through this site. If a tender looks false, please report it from the contact page.</p>
    <h2>Company profiles</h2>
    <p>What you write on your company profile must be true and must be yours to publish. "Verified" means that we checked the business licence a company sent us. It is not a guarantee of that company's work.</p>
    <p>We may suspend an account that gives false information, misuses the site, or copies its content in bulk.</p>
    <h2>Plans and payments</h2>
    <p>Registering a company is free. Paid plans are priced in AFN for Afghan companies and in USD for NGOs and international clients. A plan is switched on after we confirm your payment.</p>
    <p>Payments are not refunded. You can move to a higher plan at any time: it is paid in full and starts that day. Any other change of plan is made when your paid period ends.</p>
    <h2>Use of the content</h2>
    <p>The full details and the documents of tenders are for your own bidding. Do not republish or resell them.</p>
    <h2>Changes</h2>
    <p>We may change these terms. The current version is always on this page.</p>
</div>
@endsection

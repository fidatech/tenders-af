<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To a company: its payment was not accepted, why, and what to do next. */
class PaymentRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').': your payment was not accepted');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-rejected');
    }
}

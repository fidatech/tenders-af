<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To the platform owner: a company says it has paid. The screenshot is attached; the button opens the confirm form. */
class PaymentRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').': '.$this->subscription->company?->name.' says it paid '.money($this->subscription->amount, $this->subscription->currency));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-requested');
    }

    public function attachments(): array
    {
        return $this->subscription->receipt_path ? [Attachment::fromStorageDisk('local', $this->subscription->receipt_path)] : [];
    }
}

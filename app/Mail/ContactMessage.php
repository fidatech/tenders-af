<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A message written on the contact page of the website. Replying to it answers the person who wrote. */
class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $email, public string $body)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' website: message from '.$this->name, replyTo: [new Address($this->email, $this->name)]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact');
    }
}

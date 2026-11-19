<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A short email to the system administrators: something is waiting for them. */
class PlatformNotice extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  list<string>  $lines */
    public function __construct(public string $heading, public array $lines, public string $button, public string $url)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').': '.$this->heading);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notice');
    }
}

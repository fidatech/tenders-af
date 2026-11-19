<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * New tenders that match a company. On a paid plan it goes out as soon as a tender is
 * published, with the deadline and the buyer; on the free plan once a week, as a list of titles.
 */
class TenderAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  Collection<int, \App\Models\Tender>  $tenders */
    public function __construct(public Company $company, public Collection $tenders, public bool $digest = false)
    {
    }

    public function envelope(): Envelope
    {
        $count = $this->tenders->count();
        $subject = $this->digest
            ? trans_choice('{1} 1 new tender for your company this week|[2,*] :count new tenders for your company this week', $count, ['count' => $count])
            : ($count === 1 ? 'New tender: '.$this->tenders->first()->title : $count.' new tenders for your company');

        return new Envelope(subject: config('app.name').': '.$subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tender-alert');
    }
}

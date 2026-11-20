<?php

namespace App\Console\Commands;

use App\Mail\TenderAlertMail;
use App\Models\Company;
use App\Models\Notice;
use App\Models\Tender;
use App\Models\TenderAlert;
use App\Models\User;
use App\Support\WhatsApp;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells companies about newly published tenders that match their sectors, categories and
 * tender types: an email, and a WhatsApp message for companies that asked for it. Only
 * companies on a plan with instant alerts are told here; the others get the weekly digest.
 * Run every ten minutes by the scheduler. Every tender is announced once.
 */
class SendTenderAlerts extends Command
{
    protected $signature = 'tenders:alerts {--tender= : Only this tender (id)}';

    protected $description = 'Send email and WhatsApp alerts for newly published tenders';

    public function handle(): int
    {
        $tenders = Tender::open()->whereNull('alerted_at')
            ->when($this->option('tender'), fn ($query, $id) => $query->whereKey($id))
            ->with('industry', 'province')->orderBy('id')->limit(50)->get();

        foreach ($tenders as $tender) {
            // marked first, so a second run that starts meanwhile never announces it again
            $tender->update(['alerted_at' => now()]);
            $sent = 0;

            // every matching company on instant alerts: the bell on the site is always told, email and WhatsApp when switched on
            Company::query()->where('status', '!=', Company::SUSPENDED)
                ->whereHas('plan', fn ($q) => $q->where('instant_alerts', true))
                ->where(fn ($q) => $q->whereHas('industries', fn ($i) => $i->whereKey($tender->industry_id))
                    ->when($tender->category_id, fn ($q2) => $q2->orWhereHas('categories', fn ($c) => $c->whereKey($tender->category_id))))
                ->with('plan')->chunkById(100, function ($companies) use ($tender, &$sent) {
                    foreach ($companies as $company) {
                        $sent += $this->tell($company, $tender);
                    }
                });

            $this->info($tender->number().': '.$sent.' alerts');
        }

        return self::SUCCESS;
    }

    /** One company about one tender. Returns how many messages went out. */
    private function tell(Company $company, Tender $tender): int
    {
        if (! $company->hasInstantAlerts()) {
            return 0;
        }
        // the kinds of tender the company wants; none chosen means all of them
        $types = array_values(array_filter($company->alert_types ?? []));
        if ($types && ! in_array($tender->type, $types, true)) {
            return 0;
        }
        // a company that ticked categories under this sector hears only about those categories
        $ticked = $company->categories()->where('industry_id', $tender->industry_id)->pluck('categories.id')->all();
        if ($ticked && $company->industries()->whereKey($tender->industry_id)->exists() && $tender->category_id && ! in_array($tender->category_id, $ticked, true)) {
            return 0;
        }

        $sent = 0;
        // the bell in the panel and on the website, for every user of the company
        if ($this->claim($company, $tender, 'site')) {
            $this->notify($company, $tender, 'New tender matches your alerts', $tender->title.' · '.$tender->buyer_name);
            $sent++;
        }
        if ($company->alert_email && ($to = $company->alertEmail()) && $this->claim($company, $tender, 'email')) {
            try {
                Mail::to($to)->send(new TenderAlertMail($company, collect([$tender])));
                $sent++;
            } catch (Throwable $e) {
                report($e);
                TenderAlert::where(['company_id' => $company->id, 'tender_id' => $tender->id, 'channel' => 'email'])->update(['status' => 'failed']);
            }
        }
        if (WhatsApp::ready() && $company->hasWhatsappAlerts() && $this->claim($company, $tender, 'whatsapp')) {
            $ok = WhatsApp::send($company->whatsapp, $this->message($tender));
            if ($ok) {
                $sent++;
            } else {
                TenderAlert::where(['company_id' => $company->id, 'tender_id' => $tender->id, 'channel' => 'whatsapp'])->update(['status' => 'failed']);
            }
        }

        return $sent;
    }

    /** A notification under the bell of every active user of the company. */
    private function notify(Company $company, Tender $tender, string $title, string $body): void
    {
        $url = route('tenders.show', ['id' => $tender->id]);
        foreach (User::where('company_id', $company->id)->where('is_active', true)->pluck('id') as $userId) {
            Notice::create(['company_id' => $company->id, 'user_id' => $userId, 'title' => $title, 'body' => mb_substr($body, 0, 250), 'url' => $url]);
        }
    }

    /** Write the row of this alert. False when it was already there: the company is never told twice. */
    private function claim(Company $company, Tender $tender, string $channel): bool
    {
        try {
            TenderAlert::create(['company_id' => $company->id, 'tender_id' => $tender->id, 'channel' => $channel]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function message(Tender $tender): string
    {
        return implode("\n", array_filter([
            'New tender on '.config('app.name'),
            $tender->title,
            $tender->buyer_name.' · '.$tender->placeText(),
            $tender->deadline ? 'Deadline: '.fmt_datetime($tender->deadline).' (Kabul time)' : null,
            route('site.tenders.show', ['slug' => $tender->slug]),
            'To stop these messages, switch WhatsApp off under Tender alerts in your account.',
        ]));
    }
}

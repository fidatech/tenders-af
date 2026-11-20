<?php

namespace App\Console\Commands;

use App\Mail\TenderAlertMail;
use App\Models\Company;
use App\Models\Notice;
use App\Models\Tender;
use App\Models\TenderAlert;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Once a week: companies without instant alerts (the free plan) get one email with the titles
 * of the tenders published in the last seven days that match their sectors.
 */
class SendTenderDigest extends Command
{
    protected $signature = 'tenders:digest {--company= : Only this company (id)}';

    protected $description = 'Send the weekly digest of matching tenders to companies on the free plan';

    public function handle(): int
    {
        $since = Tender::now()->subDays(7)->toDateString();
        $count = 0;

        // free companies: once a week, a notification under the bell and, when switched on, an email
        Company::query()->where('status', '!=', Company::SUSPENDED)
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->whereHas('industries')->with('plan')
            ->chunkById(100, function ($companies) use ($since, &$count) {
                foreach ($companies as $company) {
                    if ($company->hasInstantAlerts()) {
                        continue;
                    }
                    $to = $company->alert_email ? $company->alertEmail() : null;
                    $already = TenderAlert::where('company_id', $company->id)->where('channel', 'digest')->pluck('tender_id');
                    $tenders = Tender::open()->matching($company)->where('published_on', '>=', $since)
                        ->whereNotIn('id', $already)->with('industry')->orderByDesc('published_on')->limit(15)->get();
                    if ($tenders->isEmpty()) {
                        continue;
                    }
                    $n = $tenders->count();
                    foreach (User::where('company_id', $company->id)->where('is_active', true)->pluck('id') as $userId) {
                        Notice::create(['company_id' => $company->id, 'user_id' => $userId, 'url' => route('tenders.matching'),
                            'title' => $n === 1 ? '1 new tender matches your sectors this week' : $n.' new tenders match your sectors this week',
                            'body' => mb_substr($tenders->pluck('title')->take(3)->implode(' · '), 0, 250)]);
                    }
                    try {
                        if ($to) {
                            Mail::to($to)->send(new TenderAlertMail($company, $tenders, true));
                        }
                        foreach ($tenders as $tender) {
                            TenderAlert::firstOrCreate(['company_id' => $company->id, 'tender_id' => $tender->id, 'channel' => 'digest']);
                        }
                        $count++;
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        $this->info($count.' digests sent');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Mail\TenderAlertMail;
use App\Models\Company;
use App\Models\Tender;
use App\Models\TenderAlert;
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

        Company::query()->where('status', '!=', Company::SUSPENDED)->where('alert_email', true)
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->whereHas('industries')->with('plan')
            ->chunkById(100, function ($companies) use ($since, &$count) {
                foreach ($companies as $company) {
                    if ($company->hasInstantAlerts() || ! ($to = $company->alertEmail())) {
                        continue;
                    }
                    $already = TenderAlert::where('company_id', $company->id)->where('channel', 'digest')->pluck('tender_id');
                    $tenders = Tender::open()->matching($company)->where('published_on', '>=', $since)
                        ->whereNotIn('id', $already)->with('industry')->orderByDesc('published_on')->limit(15)->get();
                    if ($tenders->isEmpty()) {
                        continue;
                    }
                    try {
                        Mail::to($to)->send(new TenderAlertMail($company, $tenders, true));
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

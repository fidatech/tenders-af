<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Company;
use App\Support\Lifecycle as Emails;
use Illuminate\Console\Command;
use Throwable;

/**
 * Emails from the platform to its companies: the trial is ending, the subscription is
 * ending, it has ended. Run every hour by the scheduler; a company is looked at from
 * nine in the morning of its own time zone, and each email goes once.
 */
class Lifecycle extends Command
{
    protected $signature = 'tenders:lifecycle {--company= : Only this company (id)} {--force : Whatever the hour}';

    protected $description = 'Email companies whose trial or subscription is ending or has ended';

    public function handle(): int
    {
        $companies = Company::query()
            ->where('status', '!=', Company::SUSPENDED)
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        foreach ($companies as $company) {
            if (! $this->option('force') && now($company->timezone ?: 'UTC')->hour < 9) {
                continue;
            }
            try {
                // a plan bought for the next period takes over on the day that period starts
                $running = Subscription::runningFor($company);
                if ($running && $running->plan_id && $running->plan_id !== $company->plan_id
                    && $running->starts_on->toDateString() === now($company->timezone ?: 'UTC')->toDateString()) {
                    $company->update(['plan_id' => $running->plan_id]);
                    $this->info($company->name.': plan changed');
                }
                if ($kind = Emails::check($company)) {
                    $this->info($company->name.': '.$kind);
                }
            } catch (Throwable $e) {
                report($e);
                $this->error($company->name.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}

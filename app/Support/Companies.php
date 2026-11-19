<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Registers a new company with its owner. It starts on the free plan: a profile in the directory and tender summaries. */
class Companies
{
    /**
     * @param  array{name: string, kind?: string, owner_name: string, owner_email: string, owner_password: string, is_supplier?: bool, is_buyer?: bool, country?: ?string, phone?: ?string, province_id?: ?int, plan_id?: ?int, status?: string, subscription_ends_at?: ?string, timezone?: string}  $data
     */
    public static function create(array $data): Company
    {
        return DB::transaction(function () use ($data) {
            $kind = isset(Company::KINDS[$data['kind'] ?? '']) ? $data['kind'] : 'company';
            $currency = Company::currencyFor($kind);
            $plan = isset($data['plan_id']) ? Plan::find($data['plan_id']) : (Plan::free() ?? Plan::offeredIn($currency)->first());
            // on the free plan there is nothing to pay and no end date; otherwise the company starts with a trial
            $free = $plan === null || $plan->isFree();
            $status = $data['status'] ?? ($free ? Company::ACTIVE : Company::TRIAL);

            $company = Company::create([
                'name' => $data['name'],
                'slug' => Company::freeSlug($data['name']),
                'kind' => $kind,
                'email' => strtolower($data['owner_email']),
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['phone'] ?? null,
                'country' => $data['country'] ?? ($kind === 'company' ? 'Afghanistan' : null),
                'province_id' => $data['province_id'] ?? null,
                'timezone' => $data['timezone'] ?? 'Asia/Kabul',
                // looking for tenders unless said otherwise; an account with no role would be useless
                'is_buyer' => (bool) ($data['is_buyer'] ?? false),
                'is_supplier' => (bool) ($data['is_supplier'] ?? true) || ! ($data['is_buyer'] ?? false),
                'currency' => $currency,
                'plan_id' => $plan?->id,
                'status' => $status,
                'trial_ends_at' => $status === Company::TRIAL ? now()->addDays((int) platform('trial_days'))->toDateString() : null,
                'subscription_ends_at' => $data['subscription_ends_at'] ?? null,
            ]);

            User::create([
                'company_id' => $company->id,
                'name' => $data['owner_name'],
                'email' => strtolower($data['owner_email']),
                'password' => $data['owner_password'],
                'role' => User::OWNER,
            ]);

            return $company;
        });
    }
}

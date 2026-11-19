<?php

namespace Tests;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Industry;
use App\Models\Plan;
use App\Models\Province;
use App\Models\Tender;
use App\Models\User;
use App\Support\Companies;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // a fixed day, so tests do not depend on the clock
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));
    }

    /** A new company with its owner: an Afghan company (pays in AFN) unless another kind is given. */
    protected function business(string $name = 'Acme', string $email = 'owner@acme.test', string $kind = 'company'): Company
    {
        return Companies::create([
            'name' => $name,
            'kind' => $kind,
            'owner_name' => 'Owner of '.$name,
            'owner_email' => $email,
            'owner_password' => 'password123',
        ]);
    }

    /** A sector, a province and one published tender in them. */
    protected function tender(array $attributes = []): Tender
    {
        $industry = Industry::firstOrCreate(['slug' => 'construction'], ['name' => 'Construction', 'icon' => 'helmet-safety']);
        $province = Province::firstOrCreate(['slug' => 'herat'], ['name' => 'Herat', 'capital' => 'Herat']);
        $title = $attributes['title'] ?? 'Construction of a school building';

        return Tender::create($attributes + [
            'title' => $title,
            'slug' => Tender::freeSlug($title),
            'type' => 'rfq',
            'buyer_name' => 'Sample NGO',
            'buyer_type' => 'ngo',
            'industry_id' => $industry->id,
            'province_id' => $province->id,
            'summary' => 'A school with six classrooms is to be built.',
            'description' => 'SECRET-SCOPE: the full bill of quantities.',
            'status' => Tender::PUBLISHED,
            'published_on' => '2026-11-14',
            'deadline' => '2026-11-30 16:00:00',
        ]);
    }

    /** A paid plan in one currency, with full tenders and instant alerts. */
    protected function paidPlan(string $currency = 'AFN', float $price = 2000): Plan
    {
        return Plan::create(['name' => 'Pro', 'price' => $price, 'currency' => $currency, 'interval' => 'monthly', 'full_access' => true, 'instant_alerts' => true, 'whatsapp_alerts' => true]);
    }

    /** Sign in as the owner of a company. */
    protected function signIn(Company $company): User
    {
        $user = $company->owner();
        $this->actingAs($user, 'web');

        return $user;
    }

    protected function systemAdmin(): Admin
    {
        $admin = Admin::firstOrCreate(['email' => 'admin@tenders.test'], ['name' => 'System Admin', 'password' => 'password123']);
        $this->actingAs($admin, 'admin');

        return $admin;
    }
}

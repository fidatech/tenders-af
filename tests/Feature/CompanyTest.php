<?php

namespace Tests\Feature;

use App\Mail\TenderAlertMail;
use App\Models\Admin;
use App\Models\Industry;
use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Models\Tender;
use App\Models\TenderAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_free_and_the_kind_of_company_decides_the_currency(): void
    {
        Plan::create(['name' => 'Free', 'price' => 0, 'currency' => 'AFN', 'interval' => 'lifetime']);

        $this->post('/register', [
            'company' => 'Kabul Supplies', 'kind' => 'company', 'name' => 'Ahmad', 'email' => 'ahmad@supplies.test',
            'password' => 'password123', 'password_confirmation' => 'password123', 'terms' => '1',
        ])->assertRedirect('/company');

        $afghan = \App\Models\Company::where('name', 'Kabul Supplies')->firstOrFail();
        $this->assertSame('AFN', $afghan->currency);
        $this->assertSame('active', $afghan->status);
        $this->assertTrue($afghan->onFreePlan());
        $this->assertFalse($afghan->hasFullAccess());

        $ngo = $this->business('Relief Org', 'owner@relief.test', 'ngo');
        $this->assertSame('USD', $ngo->currency);
        $international = $this->business('Global Trade', 'owner@global.test', 'international');
        $this->assertSame('USD', $international->currency);
    }

    public function test_a_free_company_sees_the_summary_and_a_paid_one_the_full_tender(): void
    {
        $free = Plan::create(['name' => 'Free', 'price' => 0, 'currency' => 'AFN', 'interval' => 'lifetime']);
        $pro = $this->paidPlan();
        $tender = $this->tender();
        $company = $this->business();
        $this->signIn($company);

        $this->get('/my/tenders')->assertOk()->assertSee($tender->title);
        $this->get('/my/tenders/'.$tender->id)->assertOk()->assertSee('A school with six classrooms')->assertDontSee('SECRET-SCOPE')->assertSee('See plans');

        $company->update(['plan_id' => $pro->id, 'subscription_ends_at' => '2026-12-14']);
        $this->signIn($company); // a fresh sign-in, so the new plan is read
        $this->get('/my/tenders/'.$tender->id)->assertOk()->assertSee('SECRET-SCOPE')->assertSee('Prepare my bid');

        // when the paid time is over, the company keeps its panel but reads summaries again
        $company->update(['subscription_ends_at' => '2026-11-01']);
        $this->signIn($company);
        $this->get('/my/tenders/'.$tender->id)->assertOk()->assertDontSee('SECRET-SCOPE');
        $this->assertNotNull($free);
    }

    public function test_the_subscription_page_offers_plans_and_payment_methods_in_the_currency_of_the_company(): void
    {
        Plan::create(['name' => 'Free', 'price' => 0, 'currency' => 'AFN', 'interval' => 'lifetime']);
        $this->paidPlan('AFN', 2000);
        $this->paidPlan('USD', 29);
        PlatformAccount::create(['name' => 'Azizi Bank AFN', 'type' => 'bank', 'currency' => 'AFN', 'number' => '0001']);
        PlatformAccount::create(['name' => 'Dollar account', 'type' => 'bank', 'currency' => 'USD', 'number' => '0002']);

        $this->signIn($this->business());
        $this->get('/subscription')->assertOk()->assertSee('2,000 AFN')->assertDontSee('29 USD')->assertSee('Azizi Bank AFN')->assertDontSee('Dollar account');

        $this->signIn($this->business('Relief Org', 'owner@relief.test', 'ngo'));
        $this->get('/subscription')->assertOk()->assertSee('29 USD')->assertDontSee('2,000 AFN')->assertSee('Dollar account')->assertDontSee('Azizi Bank AFN');
    }

    public function test_a_company_saves_a_tender_and_finds_it_under_saved_tenders(): void
    {
        $tender = $this->tender();
        $company = $this->business();
        $this->signIn($company);

        $this->post('/my/tenders/'.$tender->id.'/save')->assertRedirect();
        $this->assertSame(1, $company->savedTenders()->count());
        $this->get('/my/tenders/saved')->assertOk()->assertSee($tender->title);

        $this->post('/my/tenders/'.$tender->id.'/save')->assertRedirect();
        $this->assertSame(0, $company->savedTenders()->count());
    }

    public function test_a_matching_tender_is_emailed_once_to_a_company_with_instant_alerts(): void
    {
        Mail::fake();
        Plan::create(['name' => 'Free', 'price' => 0, 'currency' => 'AFN', 'interval' => 'lifetime']);
        $pro = $this->paidPlan();
        $tender = $this->tender();
        $other = Industry::create(['name' => 'Health', 'slug' => 'health', 'icon' => 'kit-medical']);

        $match = $this->business('Builders', 'owner@builders.test');
        $match->update(['plan_id' => $pro->id, 'subscription_ends_at' => '2026-12-14']);
        $match->industries()->sync([$tender->industry_id]);

        $elsewhere = $this->business('Pharma', 'owner@pharma.test');
        $elsewhere->update(['plan_id' => $pro->id, 'subscription_ends_at' => '2026-12-14']);
        $elsewhere->industries()->sync([$other->id]);

        $freeOne = $this->business('Small', 'owner@small.test');
        $freeOne->industries()->sync([$tender->industry_id]);

        $this->artisan('tenders:alerts')->assertSuccessful();
        $this->artisan('tenders:alerts')->assertSuccessful();

        Mail::assertSent(TenderAlertMail::class, 1);
        Mail::assertSent(TenderAlertMail::class, fn (TenderAlertMail $mail) => $mail->hasTo('owner@builders.test'));
        $this->assertSame(1, TenderAlert::where('channel', 'email')->count());
        $this->assertNotNull($tender->fresh()->alerted_at);

        // the company on the free plan gets the weekly digest instead
        $this->artisan('tenders:digest')->assertSuccessful();
        Mail::assertSent(TenderAlertMail::class, fn (TenderAlertMail $mail) => $mail->hasTo('owner@small.test') && $mail->digest);
    }

    public function test_a_company_sends_its_licence_and_an_administrator_verifies_it(): void
    {
        Storage::fake('local');
        Mail::fake();
        $company = $this->business();
        $this->signIn($company);

        $this->post('/verification', ['license_no' => 'D-12345', 'document' => UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf')])->assertRedirect();
        $this->assertSame('pending', $company->fresh()->verify_status);

        $this->systemAdmin();
        $this->get('/admin/verification')->assertOk()->assertSee('Acme');
        $this->post('/admin/verification/'.$company->id.'/approve')->assertRedirect();

        $this->assertTrue($company->fresh()->isVerified());
        $this->get('/companies/'.$company->slug)->assertOk()->assertSee('Verified company');
    }

    public function test_an_administrator_enters_a_tender_and_publishes_it(): void
    {
        $this->seed();
        $admin = $this->systemAdmin();
        $this->assertInstanceOf(Admin::class, $admin);
        $industry = Industry::where('slug', 'construction')->firstOrFail();

        $this->post('/admin/tenders', [
            'title' => 'Supply of cement', 'type' => 'rfq', 'buyer_name' => 'Sample NGO', 'buyer_type' => 'ngo', 'industry_id' => $industry->id,
            'summary' => 'Cement for a clinic.', 'budget_type' => 'range', 'budget_min' => '500,000', 'budget_max' => '800,000', 'budget_currency' => 'AFN',
            'status' => 'published', 'deadline' => '2026-12-01T16:00',
        ])->assertRedirect();

        $tender = Tender::where('title', 'Supply of cement')->firstOrFail();
        $this->assertSame('supply-of-cement', $tender->slug);
        $this->assertSame('2026-11-14', $tender->published_on->toDateString());
        $this->assertSame('500,000 to 800,000 AFN', $tender->budgetText());
        $this->get('/tender/supply-of-cement')->assertOk()->assertSee('Supply of cement');
        $this->get('/admin/tenders')->assertOk()->assertSee('Supply of cement');
    }
}

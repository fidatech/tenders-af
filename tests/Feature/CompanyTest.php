<?php

namespace Tests\Feature;

use App\Mail\TenderAlertMail;
use App\Models\Admin;
use App\Models\AdminNotice;
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
            'company' => 'Kabul Supplies', 'kind' => 'company', 'roles' => ['supplier'], 'name' => 'Ahmad', 'email' => 'ahmad@supplies.test',
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
        // the bell of the matching company is told once; the other two hear nothing yet
        $this->assertSame(1, \App\Models\Notice::where('company_id', $match->id)->where('title', 'New tender matches your alerts')->count());
        $this->assertSame(0, \App\Models\Notice::whereIn('company_id', [$elsewhere->id, $freeOne->id])->count());

        // the company on the free plan gets the weekly digest instead
        $this->artisan('tenders:digest')->assertSuccessful();
        Mail::assertSent(TenderAlertMail::class, fn (TenderAlertMail $mail) => $mail->hasTo('owner@small.test') && $mail->digest);
        $this->assertSame(1, \App\Models\Notice::where('company_id', $freeOne->id)->where('title', '1 new tender matches your sectors this week')->count());
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
        $this->get('/companies/'.$company->slug)->assertOk()->assertSee('Business licence checked');
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

    public function test_the_website_is_saved_with_https_and_the_country_comes_from_the_list(): void
    {
        $company = $this->business();
        $this->signIn($company);

        $this->put('/company', ['name' => $company->name, 'website' => 'HTTPS://www.Fida.af/', 'country' => 'Afghanistan'])->assertRedirect();
        $this->assertSame('https://fida.af', $company->refresh()->website);
        $this->get('/company')->assertOk()->assertSee('value="fida.af"', false);

        $this->put('/company', ['name' => $company->name, 'website' => 'fida', 'country' => 'Afghanistan'])->assertSessionHasErrors('website');
        $this->put('/company', ['name' => $company->name, 'country' => 'Narnia'])->assertSessionHasErrors('country');
    }

    public function test_whatsapp_alerts_are_locked_on_a_plan_without_them(): void
    {
        $company = $this->business();
        $company->update(['whatsapp' => '+93 70 111 2222', 'alert_whatsapp' => false]);
        $this->signIn($company);

        $this->get('/alerts')->assertOk()->assertSee('WhatsApp alerts are part of')->assertSee('id="a-number"', false);
        $this->assertMatchesRegularExpression('/id="a-number"[^>]*disabled/', $this->get('/alerts')->getContent());

        // a form sent around the locked boxes changes neither the number nor the switch
        $this->put('/alerts', ['alert_email' => '1', 'alert_whatsapp' => '1', 'whatsapp' => '+93 79 999 9999'])->assertRedirect();
        $company->refresh();
        $this->assertSame('+93 70 111 2222', $company->whatsapp);
        $this->assertFalse((bool) $company->alert_whatsapp);
    }

    public function test_administrators_are_told_about_new_companies_and_licences(): void
    {
        $this->seed();
        $admin = $this->systemAdmin();
        auth('admin')->logout();

        $this->post('/register', ['company' => 'Kabul Solar', 'kind' => 'company', 'roles' => ['supplier'], 'name' => 'Ahmad', 'email' => 'ahmad@solar.test',
            'password' => 'password123', 'password_confirmation' => 'password123', 'terms' => '1'])->assertRedirect();
        $this->assertSame(1, AdminNotice::where('admin_id', $admin->id)->where('kind', 'company')->count());

        auth('web')->logout();
        $this->actingAs($admin, 'admin');
        $this->get('/admin/notifications')->assertOk()->assertSee('New company: Kabul Solar');
        $notice = AdminNotice::where('admin_id', $admin->id)->firstOrFail();
        $this->get('/admin/notifications/'.$notice->id)->assertOk()
            ->assertSee('New company: Kabul Solar')->assertSee('Open related page');
        $this->assertNotNull($notice->fresh()->read_at);
    }

    public function test_admin_sidebar_links_to_notifications_and_mark_all_covers_every_page(): void
    {
        $admin = $this->systemAdmin();
        $notice = AdminNotice::create(['admin_id' => $admin->id, 'kind' => 'info', 'title' => 'Older unread notice']);

        foreach (range(1, 30) as $number) {
            AdminNotice::create([
                'admin_id' => $admin->id, 'kind' => 'info', 'title' => 'Read notice '.$number, 'read_at' => now(),
            ]);
        }

        $url = route('admin.notices.index');
        $link = '<a class="menu-link" href="'.$url.'">';
        $button = 'form="adminNoticesRead" class="btn btn-outline-soft"';

        $this->get(route('admin.dashboard'))->assertOk()->assertSee($link, false);
        $this->get($url)->assertOk()->assertSee($button, false)->assertDontSee('Older unread notice');
        $this->get($url.'?page=2')->assertOk()->assertSee('Older unread notice');
        $this->from($url)->post(route('admin.notices.read'))->assertRedirect($url);
        $this->assertNotNull($notice->fresh()->read_at);
        $this->get($url)->assertOk()->assertDontSee($button, false);
    }

    public function test_a_company_can_send_a_full_message_to_the_administrators(): void
    {
        $admin = $this->systemAdmin();
        $otherAdmin = Admin::create(['name' => 'Second admin', 'email' => 'second@tenders.test', 'password' => 'password123']);
        Admin::create(['name' => 'Inactive admin', 'email' => 'inactive@tenders.test', 'password' => 'password123', 'is_active' => false]);
        auth('admin')->logout();

        $form = route('company-message.create');
        $send = route('company-message.store');
        $this->post($send)->assertRedirect(route('login'));

        $company = $this->business();
        $sender = $this->signIn($company);
        $body = str_repeat('پیام شرکت ', 60)."\n".'<script>End of message</script>';

        $this->get($form)->assertRedirect(route('messages.show'));
        $this->from($form)->post($send, ['subject' => 'Pricing question'])->assertSessionHasErrors('message');
        $this->post($send, ['subject' => 'Pricing question', 'message' => $body, 'email' => 'other@example.test'])
            ->assertRedirect($form)->assertSessionHas('success');

        $notice = AdminNotice::where('admin_id', $admin->id)->firstOrFail();
        $otherNotice = AdminNotice::where('admin_id', $otherAdmin->id)->firstOrFail();
        $this->assertSame(2, AdminNotice::count());
        $this->assertSame('mailto:'.$sender->email, $notice->url);

        auth('web')->logout();
        $this->actingAs($admin, 'admin');
        $this->get(route('admin.notices.open', $otherNotice->id))->assertNotFound();
        $this->get(route('admin.notices.open', $notice->id))->assertOk()
            ->assertSee('Pricing question')->assertSee($company->name)->assertSee($sender->name)
            ->assertSee($body)->assertDontSee('<script>End of message</script>', false)->assertSee('Reply by email');
        $this->assertNotNull($notice->fresh()->read_at);
        $this->assertNull($otherNotice->fresh()->read_at);
    }
}

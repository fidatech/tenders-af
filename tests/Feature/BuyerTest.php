<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Notice;
use App\Models\Plan;
use App\Models\Tender;
use App\Support\Companies;
use App\Support\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Buyers post their own tenders; an administrator approves each one before it is published. */
class BuyerTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(string $name = 'Ministry of Public Works', string $email = 'buyer@mopw.test', string $kind = 'government'): Company
    {
        return Companies::create(['name' => $name, 'kind' => $kind, 'is_buyer' => true, 'is_supplier' => false, 'owner_name' => 'Procurement', 'owner_email' => $email, 'owner_password' => 'password123']);
    }

    private function form(array $more = []): array
    {
        $industry = Industry::firstOrCreate(['slug' => 'construction'], ['name' => 'Construction', 'icon' => 'helmet-safety']);

        return $more + [
            'title' => 'Repair of the Salang road', 'type' => 'itb', 'industry_id' => $industry->id,
            'summary' => 'Twelve kilometres of road are to be repaired.', 'budget_type' => 'undisclosed',
            'deadline' => '2026-12-20T16:00', 'action' => 'send',
        ];
    }

    public function test_an_organisation_registers_as_a_buyer_and_only_sees_the_buyer_menu(): void
    {
        $this->seed();
        $this->post('/register', [
            'company' => 'Relief Organisation', 'kind' => 'ngo', 'roles' => ['buyer'], 'name' => 'Sara', 'email' => 'sara@relief.test',
            'password' => 'password123', 'password_confirmation' => 'password123', 'terms' => '1',
        ])->assertRedirect('/my/posted');

        $company = Company::where('name', 'Relief Organisation')->firstOrFail();
        $this->assertTrue($company->is_buyer);
        $this->assertFalse($company->is_supplier);
        $this->assertSame('USD', $company->currency);
        $this->get('/my/posted')->assertOk()->assertSee('Post a tender')->assertDontSee('Matching my alerts');

        // without a role the form is not accepted
        auth('web')->logout();
        $this->post('/register', ['company' => 'Nobody', 'kind' => 'company', 'name' => 'N', 'email' => 'n@n.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'terms' => '1'])
            ->assertSessionHasErrors('roles');
    }

    public function test_a_posted_tender_waits_for_an_administrator_and_is_published_after_approval(): void
    {
        Mail::fake();
        $buyer = $this->buyer();
        $this->signIn($buyer);

        $this->post('/my/posted', $this->form())->assertRedirect();
        $tender = Tender::where('title', 'Repair of the Salang road')->firstOrFail();
        $this->assertSame(Tender::PENDING, $tender->status);
        $this->assertSame($buyer->id, $tender->company_id);
        $this->assertSame('Ministry of Public Works', $tender->buyer_name);
        $this->assertSame('government', $tender->buyer_type);
        // nobody sees it on the site yet
        $this->get('/tender/'.$tender->slug)->assertNotFound();
        $this->get('/tenders')->assertOk()->assertDontSee('Repair of the Salang road');

        $this->systemAdmin();
        $this->get('/admin/tenders?status=pending')->assertOk()->assertSee('Repair of the Salang road');
        $this->post('/admin/tenders/'.$tender->id.'/approve')->assertRedirect();

        $tender->refresh();
        $this->assertSame(Tender::PUBLISHED, $tender->status);
        $this->assertSame('2026-11-14', $tender->published_on->toDateString());
        $this->get('/tender/'.$tender->slug)->assertOk()->assertSee('Posted by the buyer');
        $this->get('/companies/'.$buyer->slug)->assertOk()->assertSee('Repair of the Salang road');
        $this->assertSame(1, Notice::where('company_id', $buyer->id)->where('title', 'Your tender is published')->count());
    }

    public function test_a_buyer_adds_several_documents_at_once(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->signIn($this->buyer());

        $this->post('/my/posted', $this->form(['documents' => [
            \Illuminate\Http\UploadedFile::fake()->create('terms of reference.pdf', 900, 'application/pdf'),
            \Illuminate\Http\UploadedFile::fake()->create('bill of quantities.xlsx', 120, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            \Illuminate\Http\UploadedFile::fake()->create('drawings.zip', 3000, 'application/zip'),
        ]]))->assertRedirect();
        $tender = Tender::firstOrFail();
        $this->assertSame(['bill of quantities.xlsx', 'drawings.zip', 'terms of reference.pdf'], $tender->documents()->orderBy('name')->pluck('name')->all());

        // later more are added to the same tender
        $this->put('/my/posted/'.$tender->id, $this->form(['documents' => [
            \Illuminate\Http\UploadedFile::fake()->create('site photos.pdf', 400, 'application/pdf'),
            \Illuminate\Http\UploadedFile::fake()->create('annex.docx', 80, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]]))->assertRedirect();
        $this->assertSame(5, $tender->documents()->count());
    }

    public function test_a_tender_is_sent_back_with_a_reason_and_the_buyer_sends_it_again(): void
    {
        Mail::fake();
        $buyer = $this->buyer();
        $this->signIn($buyer);
        $this->post('/my/posted', $this->form())->assertRedirect();
        $tender = Tender::firstOrFail();

        $this->systemAdmin();
        $this->post('/admin/tenders/'.$tender->id.'/reject', ['reason' => 'incomplete'])->assertRedirect();
        $this->assertSame(Tender::REJECTED, $tender->refresh()->status);

        $this->signIn($buyer);
        $this->get('/my/posted/'.$tender->id.'/edit')->assertOk()->assertSee('This tender was sent back')->assertSee('Important details are missing');
        $this->put('/my/posted/'.$tender->id, $this->form(['description' => 'The full scope of the work.']))->assertRedirect();
        $tender->refresh();
        $this->assertSame(Tender::PENDING, $tender->status);
        $this->assertNull($tender->reject_reason);
    }

    public function test_a_buyer_extends_cancels_and_awards_only_its_own_published_tenders(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer('Other NGO', 'other@ngo.test', 'ngo');
        $tender = $this->tender(['company_id' => $buyer->id, 'title' => 'Supply of generators']);

        // another buyer cannot open or change it
        $this->signIn($other);
        $this->get('/my/posted/'.$tender->id.'/edit')->assertNotFound();
        $this->post('/my/posted/'.$tender->id.'/cancel')->assertNotFound();

        $this->signIn($buyer);
        // a published tender is not rewritten through the form
        $this->put('/my/posted/'.$tender->id, $this->form(['title' => 'Something else']));
        $this->assertSame('Supply of generators', $tender->refresh()->title);

        $this->post('/my/posted/'.$tender->id.'/extend', ['deadline' => '2026-12-15T16:00'])->assertRedirect();
        $this->assertSame('2026-12-15', $tender->refresh()->deadline->toDateString());

        $this->post('/my/posted/'.$tender->id.'/award', ['awarded_to' => 'Kabul Power Ltd'])->assertRedirect();
        $tender->refresh();
        $this->assertSame(Tender::AWARDED, $tender->status);
        $this->assertSame('Kabul Power Ltd', $tender->awarded_to);
        // it was published, so it cannot be deleted any more
        $this->delete('/my/posted/'.$tender->id);
        $this->assertNotNull(Tender::find($tender->id));
    }

    public function test_a_company_that_only_looks_for_tenders_has_no_posting_pages(): void
    {
        $company = $this->business();
        $this->signIn($company);
        $this->assertTrue($company->is_supplier);
        $this->get('/my/posted')->assertNotFound();
        $this->get('/dashboard')->assertOk()->assertDontSee('Your posted tenders');

        // the owner switches posting on in the company profile: one account, both roles
        $this->put('/company', ['name' => $company->name, 'is_supplier' => '1', 'is_buyer' => '1'])->assertRedirect();
        $this->assertTrue($company->refresh()->is_buyer);
        $this->assertTrue($company->is_supplier);
        $this->get('/my/posted')->assertOk();
    }

    public function test_when_posting_is_no_longer_free_a_buyer_needs_a_plan_that_includes_it(): void
    {
        $buyer = $this->buyer();
        $this->assertTrue($buyer->canPostTenders());

        Platform::set(['posting_free' => '0']);
        $this->assertFalse($buyer->refresh()->canPostTenders());
        $this->signIn($buyer);
        $this->get('/my/posted')->assertOk()->assertSee('Posting new tenders needs a plan');
        $this->post('/my/posted', $this->form());
        $this->assertSame(0, Tender::count());

        $plan = Plan::create(['name' => 'Buyer', 'price' => 1500, 'currency' => 'AFN', 'interval' => 'monthly', 'can_post' => true]);
        $buyer->update(['plan_id' => $plan->id, 'status' => Company::ACTIVE, 'subscription_ends_at' => '2026-12-14']);
        $this->assertTrue($buyer->refresh()->canPostTenders());
    }
}

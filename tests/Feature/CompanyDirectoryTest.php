<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\CompanyProject;
use App\Support\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function profile(Company $company, array $values = []): void
    {
        $company->update($values + ['about' => 'We build roads and bridges.', 'phone' => '+93 700 111 222', 'email' => 'sales@acme.test', 'website' => 'https://acme.af']);
    }

    /** The settings form as the panel sends it. */
    private function settings(array $values = []): array
    {
        return $values + ['name' => 'Acme', 'contact_shown' => '1', 'business_types_shown' => '1', 'page_shown' => '1', 'is_listed' => '1'];
    }

    public function test_existing_companies_keep_showing_their_phone_and_website(): void
    {
        $company = $this->business();
        $this->profile($company);

        $page = $this->get('/companies/'.$company->slug)->assertOk();
        $page->assertSee('+93 700 111 222');
        $page->assertSee('acme.af');
        $page->assertDontSee('sales@acme.test'); // the email was never shown, and is not approved
    }

    public function test_premium_contacts_are_left_out_of_the_page_structured_data_and_document_for_visitors(): void
    {
        $company = $this->business();
        $this->profile($company, ['contact_visibility' => 'premium', 'contact_fields' => ['phone', 'email']]);

        $page = $this->get('/companies/'.$company->slug)->assertOk();
        $page->assertDontSee('700 111 222');
        $page->assertDontSee('sales@acme.test');
        $page->assertSee('shows its contact details to subscribers');
        $this->assertStringNotContainsString('telephone', $page->getContent());

        $pdf = $this->get(route('site.companies.pdf', ['slug' => $company->slug]))->assertOk();
        $pdf->assertDontSee('700 111 222');
        $pdf->assertDontSee('sales@acme.test');
        $this->assertStringContainsString('private', (string) $pdf->headers->get('Cache-Control'));

        // a free company does not see them either
        $this->signIn($this->business('Free Co', 'free@co.test'));
        $this->get('/companies/'.$company->slug)->assertDontSee('700 111 222');

        // a paid subscriber does
        $paid = $this->business('Paid Co', 'paid@co.test');
        $paid->update(['plan_id' => $this->paidPlan()->id, 'status' => Company::ACTIVE, 'subscription_ends_at' => '2027-11-14']);
        $this->signIn($paid);
        $this->get('/companies/'.$company->slug)->assertSee('+93 700 111 222')->assertSee('sales@acme.test');
    }

    public function test_private_contacts_are_never_shown_and_offer_an_enquiry(): void
    {
        $company = $this->business();
        $this->profile($company, ['contact_visibility' => 'private', 'contact_fields' => ['phone', 'website']]);

        $paid = $this->business('Paid Co', 'paid@co.test');
        $paid->update(['plan_id' => $this->paidPlan()->id, 'status' => Company::ACTIVE, 'subscription_ends_at' => '2027-11-14']);
        $this->signIn($paid);

        $this->get('/companies/'.$company->slug)->assertOk()->assertDontSee('700 111 222')->assertSee('Send an enquiry');
    }

    public function test_the_owner_chooses_what_is_shared_and_sees_the_full_document(): void
    {
        $company = $this->business();
        $this->profile($company);
        $this->signIn($company);

        $this->put(route('settings.update'), $this->settings([
            'phone' => '+93 700 111 222', 'email' => 'sales@acme.test', 'website' => 'acme.af',
            'contact_visibility' => 'premium', 'contact_fields' => ['email'],
            'business_types' => ['manufacturer', 'exporter'], 'tagline' => 'Steel since 1999',
            'services' => [['name' => 'Steel beams', 'text' => 'Cut to size'], ['name' => '', 'text' => 'ignored']],
            'page_template' => 'industrial', 'page_accent' => '#0E5A8A',
        ]))->assertSessionHasNoErrors();

        $company->refresh();
        $this->assertSame('premium', $company->contact_visibility);
        $this->assertSame(['email'], $company->contact_fields);
        $this->assertSame(['manufacturer', 'exporter'], $company->business_types);
        $this->assertSame([['name' => 'Steel beams', 'text' => 'Cut to size']], $company->serviceList());

        // the company sees its own details on its own page, and in its full document
        $this->get('/companies/'.$company->slug)->assertSee('sales@acme.test')->assertSee('Steel since 1999')->assertSee('Steel beams');
        $this->get(route('pdf.print', ['contacts' => 'full']))->assertOk()->assertSee('sales@acme.test');
        $this->get(route('pdf.print'))->assertOk()->assertDontSee('sales@acme.test');
    }

    public function test_arbitrary_colours_and_templates_are_refused(): void
    {
        $company = $this->business();
        $this->signIn($company);
        $this->put(route('settings.update'), $this->settings(['page_accent' => '#ff00ff', 'page_template' => '<script>']))
            ->assertSessionHasErrors(['page_accent', 'page_template']);
    }

    public function test_the_directory_filters_by_business_type_only_for_modules_that_are_on(): void
    {
        $maker = $this->business('Herat Steel', 'steel@herat.test');
        $maker->update(['business_types' => ['manufacturer']]);
        $trader = $this->business('Kabul Traders', 'trade@kabul.test');
        $trader->update(['business_types' => ['importer']]);

        // the Manufacturer Directory is off: the choice is not offered and the filter is ignored
        $this->get('/companies')->assertDontSee('<option value="manufacturer"', false);
        $this->get('/companies?type=manufacturer')->assertSee('Kabul Traders');

        Platform::set(['module_manufacturers' => '1']);
        $this->get('/companies')->assertSee('<option value="manufacturer"', false);
        $this->get('/companies?type=manufacturer')->assertSee('Herat Steel')->assertDontSee('Kabul Traders');
    }

    public function test_a_company_publishes_a_project_with_pictures_and_private_evidence(): void
    {
        Storage::fake('local');
        $company = $this->business();
        $this->profile($company);
        $this->signIn($company);

        $this->post(route('projects.store'), [
            'title' => 'Bridge over the Kabul river', 'description' => 'A 40 m steel bridge.', 'progress' => 'completed',
            'started_on' => '2024-03-01', 'completed_on' => '2025-06-30', 'client_name' => 'Secret Ministry', 'client_public' => '0',
            'contract_value' => '250000', 'contract_currency' => 'USD', 'value_public' => '0', 'action' => 'publish',
            'images' => [UploadedFile::fake()->image('bridge.jpg', 800, 600)],
            'evidence' => [UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $project = CompanyProject::sole();
        $this->assertSame('pending', $project->review_status); // asked for a review, not reviewed
        $this->assertFalse($project->isReviewed());

        auth('web')->logout();
        $page = $this->get('/companies/'.$company->slug)->assertOk();
        $page->assertSee('Bridge over the Kabul river')->assertSee('Pending review');
        $show = $this->get(route('site.projects.show', ['slug' => $company->slug, 'project' => $project->slug]))->assertOk();
        $show->assertDontSee('Secret Ministry')->assertDontSee('250,000');

        $image = $project->images()->first();
        $evidence = $project->evidence()->first();
        $this->get(route('site.projects.image', ['id' => $image->id]))->assertOk();
        $this->get(route('site.projects.image', ['id' => $evidence->id]))->assertNotFound(); // evidence is never public
        $this->get(route('projects.file', ['id' => $project->id, 'file' => $evidence->id]))->assertRedirect(route('login'));
    }

    public function test_a_company_cannot_see_or_change_another_companys_projects(): void
    {
        $a = $this->business('Alpha', 'a@alpha.test');
        $b = $this->business('Beta', 'b@beta.test');
        $project = CompanyProject::create(['company_id' => $b->id, 'title' => 'Beta tower', 'slug' => 'beta-tower', 'status' => 'draft']);

        $this->signIn($a);
        $this->get(route('projects.edit', ['id' => $project->id]))->assertNotFound();
        $this->put(route('projects.update', ['id' => $project->id]), ['title' => 'Hacked', 'progress' => 'completed', 'action' => 'publish'])->assertNotFound();
        $this->delete(route('projects.destroy', ['id' => $project->id]))->assertNotFound();
        $this->assertSame('Beta tower', $project->fresh()->title);

        // drafts are not public
        $this->get(route('site.projects.show', ['slug' => $b->slug, 'project' => 'beta-tower']))->assertNotFound();
    }

    public function test_a_reviewer_records_what_was_checked_and_a_changed_project_needs_a_new_review(): void
    {
        $company = $this->business();
        $this->signIn($company);
        $project = CompanyProject::create(['company_id' => $company->id, 'title' => 'School', 'slug' => 'school', 'status' => 'published', 'progress' => 'completed']);

        $this->systemAdmin();
        // nothing to look at: "evidence reviewed" is refused
        $this->post(route('admin.projects.review', ['id' => $project->id]), ['review_status' => 'evidence_reviewed'])->assertSessionHasErrors('review_status');
        // a refusal needs a reason
        $this->post(route('admin.projects.review', ['id' => $project->id]), ['review_status' => 'rejected'])->assertSessionHasErrors('review_note');

        $this->post(route('admin.projects.review', ['id' => $project->id]), ['review_status' => 'client_confirmed', 'review_note' => 'Called the client.'])->assertSessionHasNoErrors();
        $this->assertTrue($project->fresh()->isReviewed());
        $this->assertDatabaseHas('notices', ['company_id' => $company->id]);
        $this->assertDatabaseHas('admin_logs', ['action' => 'reviewed a company project']);

        // the company changes the client: the confirmation no longer applies
        auth('admin')->logout();
        $this->signIn($company);
        $this->put(route('projects.update', ['id' => $project->id]), ['title' => 'School', 'client_name' => 'Another client', 'progress' => 'completed', 'action' => 'publish'])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $project->fresh()->review_status);
    }

    public function test_a_company_verifier_may_review_projects_but_a_focal_point_may_not(): void
    {
        $company = $this->business();
        $project = CompanyProject::create(['company_id' => $company->id, 'title' => 'Clinic', 'slug' => 'clinic', 'status' => 'published']);

        $this->actingAs(Admin::create(['name' => 'Focal', 'email' => 'f@t.test', 'password' => 'password123', 'role' => 'focal']), 'admin');
        $this->get(route('admin.projects.show', ['id' => $project->id]))->assertForbidden();

        $this->actingAs(Admin::create(['name' => 'Verifier', 'email' => 'v@t.test', 'password' => 'password123', 'role' => 'verifier']), 'admin');
        $this->get(route('admin.projects.show', ['id' => $project->id]))->assertOk();
        $this->post(route('admin.projects.review', ['id' => $project->id]), ['review_status' => 'rejected', 'review_note' => 'No proof.'])->assertSessionHasNoErrors();
    }

    public function test_switched_off_modules_hide_projects_pages_and_documents(): void
    {
        $company = $this->business();
        $this->profile($company);
        CompanyProject::create(['company_id' => $company->id, 'title' => 'Hidden road', 'slug' => 'hidden-road', 'status' => 'published']);
        Platform::set(['module_portfolios' => '0', 'module_company_pdf' => '0', 'module_mini_websites' => '0']);
        $company->update(['tagline' => 'Should not show', 'services' => [['name' => 'Hidden service', 'text' => '']]]);

        $page = $this->get('/companies/'.$company->slug)->assertOk();
        $page->assertDontSee('Hidden road')->assertDontSee('Should not show')->assertDontSee('Hidden service')->assertDontSee('Profile PDF');
        $this->get(route('site.projects.show', ['slug' => $company->slug, 'project' => 'hidden-road']))->assertNotFound();
        $this->get(route('site.companies.pdf', ['slug' => $company->slug]))->assertNotFound();

        $this->signIn($company);
        $this->get(route('projects.index'))->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('company_projects', ['title' => 'Hidden road']); // kept
    }

    public function test_an_empty_profile_is_not_offered_to_search_engines(): void
    {
        $empty = $this->business('Empty Co', 'e@co.test');
        $full = $this->business('Full Co', 'f@co.test');
        $this->profile($full);

        $this->get('/companies/'.$empty->slug)->assertSee('noindex, follow', false);
        $this->get('/companies/'.$full->slug)->assertSee('index, follow, max-snippet', false);
        $sitemap = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString('/companies/'.$full->slug, $sitemap);
        $this->assertStringNotContainsString('/companies/'.$empty->slug.'<', $sitemap);
    }
}

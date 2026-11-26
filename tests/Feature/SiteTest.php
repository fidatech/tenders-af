<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_of_the_website_opens_for_a_visitor(): void
    {
        $this->seed();
        $tender = $this->tender();

        $this->get('/')->assertOk()
            ->assertSee('Find and win tenders across')
            ->assertSee($tender->title)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('index, follow', false);

        foreach (['/tenders', '/sectors', '/provinces', '/companies', '/pricing', '/how-it-works', '/questions', '/contact', '/privacy', '/terms'] as $path) {
            $this->get($path)->assertOk()->assertSee('<link rel="canonical" href="'.url($path).'">', false);
        }

        $this->get('/tenders/sector/construction')->assertOk()->assertSee('Construction tenders in Afghanistan')->assertSee($tender->title);
        $this->get('/tenders/province/herat')->assertOk()->assertSee('Tenders in Herat')->assertSee($tender->title);
        $this->get('/sitemap.xml')->assertOk()->assertSee('/tender/'.$tender->slug)->assertSee('/tenders/province/kabul');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /company$', false)->assertSee('Disallow: /company/', false);
        $this->get('/llms.txt')->assertOk()->assertSee('Company registration: free');
    }

    public function test_the_structured_data_is_valid_json_with_a_schema_org_context(): void
    {
        $tender = $this->tender();
        foreach (['/', '/tenders', '/tender/'.$tender->slug, '/questions'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m), $path);
            $data = json_decode($m[1], true);
            $this->assertIsArray($data, $path.': the structured data is not valid JSON');
            $this->assertSame('https://schema.org', $data['@context'] ?? null, $path);
            $this->assertStringNotContainsString('<?php', $m[1], $path);
        }
    }

    public function test_a_visitor_reads_the_summary_of_a_tender_but_not_its_full_details(): void
    {
        $tender = $this->tender();

        $this->get('/tender/'.$tender->slug)->assertOk()
            ->assertSee($tender->title)
            ->assertSee('A school with six classrooms is to be built.')
            ->assertSee('Open the full tender and its documents')
            ->assertDontSee('SECRET-SCOPE');
    }

    public function test_a_draft_tender_is_not_on_the_website(): void
    {
        $tender = $this->tender(['status' => 'draft']);

        $this->get('/tender/'.$tender->slug)->assertNotFound();
        $this->get('/tenders')->assertOk()->assertDontSee($tender->title);
    }

    public function test_the_pricing_page_shows_afn_prices_first_and_usd_prices_on_request(): void
    {
        Plan::create(['name' => 'Free', 'price' => 0, 'currency' => 'AFN', 'interval' => 'lifetime']);
        $this->paidPlan('AFN', 2000);
        $this->paidPlan('USD', 29);

        $this->get('/pricing')->assertOk()->assertSee('2,000 <small>AFN a month</small>', false)->assertDontSee('29 <small>USD a month</small>', false);
        $this->get('/pricing?currency=USD')->assertOk()->assertSee('29 <small>USD a month</small>', false)->assertDontSee('2,000 <small>AFN a month</small>', false);
        $this->get('/pricing')->assertSee('Why companies choose')->assertSee('Plans start at 2,000 AFN a month');
    }

    public function test_a_listed_company_has_a_public_page_and_an_unlisted_one_has_none(): void
    {
        $company = $this->business('Herat Builders', 'owner@builders.test');

        $this->get('/companies')->assertOk()->assertSee('Herat Builders');
        $this->get('/companies/'.$company->slug)->assertOk()->assertSee('Herat Builders');

        $company->update(['is_listed' => false]);
        $this->get('/companies/'.$company->slug)->assertNotFound();
    }

    public function test_the_other_addresses_lead_to_the_main_one(): void
    {
        config(['app.url' => 'https://tenders.af']);
        $this->get('http://tender.af/tenders?q=solar')->assertStatus(301)->assertRedirect('https://tenders.af/tenders?q=solar');
        $this->get('https://www.tenders.af/companies')->assertStatus(301)->assertRedirect('https://tenders.af/companies');
        $this->get('https://tenders.af/')->assertOk();
    }
}

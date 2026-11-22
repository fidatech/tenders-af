<?php

namespace Tests\Feature;

use App\Support\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_bottom_bar_follows_the_modules(): void
    {
        $page = $this->get('/')->assertOk();
        $page->assertSee('class="mnav"', false);
        $page->assertSee('<span>Companies</span>', false);
        $page->assertDontSee('<span>Products</span>', false);
        $page->assertSee('data-mnav-open', false);
        $page->assertSee('Register free');

        Platform::set(['products_enabled' => '1', 'module_directory' => '0']);
        $page = $this->get('/');
        $page->assertSee('<span>Products</span>', false);
        $page->assertDontSee('<span>Companies</span>', false);
    }

    public function test_a_signed_in_company_gets_its_account_in_the_public_sheet(): void
    {
        $this->signIn($this->business());

        $page = $this->get('/');
        $page->assertSee('<span>Account</span>', false);
        $page->assertSee('My dashboard');
        $page->assertSee('id="mnavLogout"', false);
        $page->assertDontSee('<span>Sign in</span>', false);
    }

    public function test_the_panel_bar_and_sheet_show_only_what_the_user_may_open(): void
    {
        $company = $this->business();
        $this->signIn($company);

        $page = $this->get('/dashboard')->assertOk();
        $page->assertSee('class="mnav"', false);
        $page->assertSee('<span>Messages</span>', false);
        $page->assertSee('Company profile'); // the owner
        $page->assertDontSee('<span>Products</span>', false); // module off

        // a team member who is not the owner does not get owner pages in the sheet
        $member = $company->users()->create(['name' => 'Member', 'email' => 'member@acme.test', 'password' => 'password123', 'role' => 'member', 'is_active' => true]);
        $this->actingAs($member, 'web');
        $page = $this->get('/dashboard')->assertOk();
        $sheet = substr($page->getContent(), strpos($page->getContent(), 'id="mnavSheet"'));
        $this->assertStringNotContainsString(route('settings.edit'), $sheet);
        $this->assertStringNotContainsString(route('team.index'), $sheet);
    }
}

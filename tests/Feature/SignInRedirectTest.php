<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The company sign-in and the admin sign-in share one browser session; neither sends a user to the other panel. */
class SignInRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_company_is_not_sent_to_an_admin_page_saved_earlier(): void
    {
        $company = $this->business();
        // a visit to /admin while signed out saves that page to return to after signing in
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $this->post(route('login.store'), ['email' => $company->owner()->email, 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated('web');
    }

    public function test_a_company_still_returns_to_its_own_page_saved_earlier(): void
    {
        $company = $this->business();
        $this->get('/subscription')->assertRedirect(route('login'));

        $this->post(route('login.store'), ['email' => $company->owner()->email, 'password' => 'password123'])
            ->assertRedirect(url('/subscription'));
    }

    public function test_an_administrator_is_not_sent_to_a_company_page_saved_earlier(): void
    {
        Admin::create(['name' => 'Owner', 'email' => 'owner@tenders.test', 'password' => 'password123']);
        $this->get('/subscription')->assertRedirect(route('login'));

        $this->post(route('admin.login.store'), ['email' => 'owner@tenders.test', 'password' => 'password123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');
    }
}

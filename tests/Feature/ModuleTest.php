<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\ModuleChange;
use App\Support\Modules;
use App\Support\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_follow_the_plan_and_keep_what_is_already_in_use(): void
    {
        $this->assertTrue(Modules::enabled('tenders'));
        $this->assertTrue(Modules::enabled('directory'));
        $this->assertFalse(Modules::enabled('products'));
        $this->assertFalse(Modules::enabled('rfqs')); // planned: never on

        // a site that already switched products on keeps it on: the old setting is the switch
        Platform::set(['products_enabled' => '1']);
        $this->assertTrue(Modules::enabled('products'));
    }

    public function test_the_core_module_cannot_be_switched_off(): void
    {
        $this->systemAdmin();
        $this->put(route('admin.modules.update', ['module' => 'tenders']), ['enabled' => '0'])->assertRedirect();

        $this->assertTrue(Modules::enabled('tenders'));
        $this->get('/tenders')->assertOk();
    }

    public function test_switching_the_directory_off_blocks_its_pages_hides_its_links_and_keeps_the_data(): void
    {
        $company = $this->business('Herat Builders', 'b@herat.test');
        $company->update(['about' => 'We build schools.']);

        $this->systemAdmin();
        $this->put(route('admin.modules.update', ['module' => 'directory']), ['enabled' => '0', 'note' => 'Testing'])->assertRedirect();
        auth('admin')->logout();

        $this->assertFalse(Modules::enabled('directory'));
        $this->get('/companies')->assertNotFound()->assertSee('Not available at the moment')->assertSee('noindex', false);
        $this->get('/companies/'.$company->slug)->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('href="'.route('site.companies').'"', false);
        $this->assertStringNotContainsString('/companies', $this->get('/sitemap.xml')->getContent());

        // nothing was deleted
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'about' => 'We build schools.']);

        // and the change is in the history
        $change = ModuleChange::sole();
        $this->assertSame('directory', $change->module);
        $this->assertSame([true, false], $change->changes['enabled']);
        $this->assertSame('Testing', $change->note);
        $this->assertDatabaseHas('admin_logs', ['action' => 'changed a module']);
    }

    public function test_dependent_modules_stop_with_their_parent_and_come_back_with_it(): void
    {
        Platform::set(['module_directory' => '0']);

        $this->assertTrue(Modules::switchedOn('mini_websites'));
        $this->assertFalse(Modules::enabled('mini_websites'));
        $this->assertFalse(Modules::enabled('portfolios'));
        $this->assertSame(['directory'], Modules::blockedBy('portfolios'));
        $this->assertContains('mini_websites', Modules::dependents('directory'));

        Platform::set(['module_directory' => '1']);
        $this->assertTrue(Modules::enabled('mini_websites'));
    }

    public function test_switching_products_on_opens_its_pages_and_menu(): void
    {
        $this->systemAdmin();
        $this->put(route('admin.modules.update', ['module' => 'products']), ['enabled' => '1', 'public_nav' => '1', 'dashboard_nav' => '1', 'notifications' => '1', 'access' => 'all']);

        $this->assertSame('1', Platform::get('products_enabled'));
        $this->get('/products')->assertOk();
        $this->get('/')->assertSee('href="'.route('site.products').'"', false);

        // hidden from the menus but still on
        $this->put(route('admin.modules.update', ['module' => 'products']), ['enabled' => '1', 'public_nav' => '0', 'dashboard_nav' => '0', 'notifications' => '1', 'access' => 'all']);
        $this->get('/products')->assertOk();
        $this->get('/')->assertDontSee('href="'.route('site.products').'"', false);
        $this->signIn($this->business());
        $this->get('/dashboard')->assertDontSee('My products');
    }

    public function test_a_paid_only_module_is_refused_to_free_companies(): void
    {
        Platform::set(['products_enabled' => '1', 'module_products_access' => 'paid']);
        $free = $this->business();
        $this->assertFalse(Modules::allows('products', $free));

        $paid = $this->business('Paid Co', 'paid@co.test');
        $paid->update(['plan_id' => $this->paidPlan()->id, 'status' => Company::ACTIVE, 'subscription_ends_at' => '2027-11-14']);
        $this->assertTrue(Modules::allows('products', $paid->fresh()));
    }

    public function test_only_a_super_administrator_may_change_a_module_and_planned_ones_cannot_be_switched_on(): void
    {
        $manager = Admin::create(['name' => 'Manager', 'email' => 'm@tenders.test', 'password' => 'password123', 'role' => 'manager']);
        $this->actingAs($manager, 'admin');
        $this->get(route('admin.modules.index'))->assertOk()->assertSee('Only a super administrator may change modules');
        $this->put(route('admin.modules.update', ['module' => 'directory']), ['enabled' => '0'])->assertForbidden();
        $this->assertTrue(Modules::enabled('directory'));

        $this->systemAdmin();
        $this->put(route('admin.modules.update', ['module' => 'rfqs']), ['enabled' => '1'])->assertNotFound();
        $this->put(route('admin.modules.update', ['module' => 'nope']), ['enabled' => '1'])->assertNotFound();
        $this->get(route('admin.modules.index'))->assertOk()->assertSee('Planned')->assertSee('Buyer RFQs');
    }

    public function test_companies_and_guests_cannot_reach_module_management(): void
    {
        $this->get(route('admin.modules.index'))->assertRedirect(route('admin.login'));
        $this->signIn($this->business());
        $this->put(route('admin.modules.update', ['module' => 'directory']), ['enabled' => '0'])->assertRedirect(route('admin.login'));
        $this->assertTrue(Modules::enabled('directory'));
    }

    public function test_saving_the_settings_page_does_not_change_a_module(): void
    {
        Platform::set(['products_enabled' => '1']);
        $this->systemAdmin();
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('Module Management');
        $this->put(route('admin.settings.update'), [
            'trial_days' => 7, 'renew_open_days' => 30, 'products_free_limit' => 5, 'sponsor_week_afn' => 1, 'sponsor_month_afn' => 1,
            'sponsor_week_usd' => 1, 'sponsor_month_usd' => 1, 'stats_min' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Modules::enabled('products'));
    }
}

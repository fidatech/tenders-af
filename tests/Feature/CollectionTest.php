<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SupplierCollection;
use App\Models\SupplierCollectionItem;
use App\Support\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Platform::set(['module_collections' => '1']);
    }

    private function paid(string $name = 'Buyer Co', string $email = 'buyer@co.test'): Company
    {
        $company = $this->business($name, $email);
        $company->update(['plan_id' => $this->paidPlan()->id, 'status' => Company::ACTIVE, 'subscription_ends_at' => '2027-11-14']);

        return $company->fresh();
    }

    public function test_a_paid_subscriber_saves_a_supplier_and_manages_it(): void
    {
        $buyer = $this->paid();
        $supplier = $this->business('Herat Cement', 'c@herat.test');
        $this->signIn($buyer);

        $this->get('/companies/'.$supplier->slug)->assertOk()->assertSee('☆ Save');
        $this->post(route('collections.save', ['slug' => $supplier->slug]), [])->assertRedirect()->assertSessionHas('success');

        $favourites = SupplierCollection::where('company_id', $buyer->id)->where('is_favourites', true)->sole();
        $item = $favourites->items()->sole();
        $this->get('/companies/'.$supplier->slug)->assertSee('★ Saved');

        // a new collection from the company page
        $this->post(route('collections.save', ['slug' => $supplier->slug]), ['new_collection' => 'Cement in Herat'])->assertSessionHas('success');
        $this->assertDatabaseHas('supplier_collections', ['company_id' => $buyer->id, 'name' => 'Cement in Herat']);

        $this->put(route('collections.items.update', ['id' => $favourites->id, 'item' => $item->id]), ['status' => 'shortlisted', 'note' => 'Good prices', 'tags' => 'cement, herat, cement'])->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertSame('shortlisted', $item->status);
        $this->assertSame(['cement', 'herat'], $item->tags);

        $this->get(route('collections.show', ['id' => $favourites->id]))->assertOk()->assertSee('Herat Cement')->assertSee('Good prices');
        $this->get(route('collections.show', ['id' => $favourites->id, 'tag' => 'herat']))->assertSee('Herat Cement');
        $this->get(route('collections.show', ['id' => $favourites->id, 'status' => 'rejected']))->assertDontSee('Good prices');
    }

    public function test_free_companies_are_sent_to_the_plans_and_cannot_save(): void
    {
        $free = $this->business();
        $supplier = $this->business('Herat Cement', 'c@herat.test');
        $this->signIn($free);

        $this->get('/companies/'.$supplier->slug)->assertSee(route('billing.show'), false)->assertDontSee('name="new_collection"', false);
        $this->get(route('collections.index'))->assertRedirect(route('billing.show'));
        $this->post(route('collections.save', ['slug' => $supplier->slug]))->assertSessionHas('danger');
        $this->assertSame(0, SupplierCollectionItem::count());

        // an administrator may open it to every company
        Platform::set(['module_collections_access' => 'all']);
        $this->post(route('collections.save', ['slug' => $supplier->slug]))->assertSessionHas('success');
    }

    public function test_notes_stay_private_to_the_saving_company(): void
    {
        $buyer = $this->paid();
        $supplier = $this->business('Herat Cement', 'c@herat.test');
        $list = SupplierCollection::create(['company_id' => $buyer->id, 'name' => 'Cement']);
        $list->items()->create(['company_id' => $supplier->id, 'note' => 'Too expensive, do not tell them']);

        // the supplier sees nothing of it, on its page or by guessing addresses
        $this->signIn($supplier);
        $this->get('/companies/'.$supplier->slug)->assertDontSee('Too expensive');
        $response = $this->get(route('collections.show', ['id' => $list->id]));
        $this->assertContains($response->status(), [302, 404]);
        $this->assertStringNotContainsString('Too expensive', (string) $response->getContent());

        $other = $this->paid('Other Buyer', 'other@co.test');
        $this->signIn($other);
        $this->get(route('collections.show', ['id' => $list->id]))->assertNotFound();
        $this->get(route('collections.export', ['id' => $list->id]))->assertNotFound();
        $this->put(route('collections.items.update', ['id' => $list->id, 'item' => $list->items()->first()->id]), ['status' => 'rejected'])->assertNotFound();
    }

    public function test_private_collections_are_hidden_from_the_rest_of_the_team(): void
    {
        $buyer = $this->paid();
        $owner = $buyer->users()->first();
        $member = $buyer->users()->create(['name' => 'Member', 'email' => 'm@buyer.test', 'password' => 'password123', 'role' => 'member', 'is_active' => true]);
        $mine = SupplierCollection::create(['company_id' => $buyer->id, 'user_id' => $owner->id, 'name' => 'Owner only', 'is_private' => true]);
        $shared = SupplierCollection::create(['company_id' => $buyer->id, 'user_id' => $owner->id, 'name' => 'Team list']);

        $this->actingAs($member, 'web');
        $this->get(route('collections.index'))->assertOk()->assertSee('Team list')->assertDontSee('Owner only');
        $this->get(route('collections.show', ['id' => $mine->id]))->assertNotFound();
        // a member may use a shared list but not delete it
        $this->delete(route('collections.destroy', ['id' => $shared->id]))->assertForbidden();
    }

    public function test_compare_shows_facts_and_the_export_respects_contact_sharing(): void
    {
        $buyer = $this->paid();
        $a = $this->business('Alpha Steel', 'a@steel.test');
        $a->update(['phone' => '+93 700 000 001', 'contact_visibility' => 'public', 'contact_fields' => ['phone']]);
        $b = $this->business('Beta Steel', 'b@steel.test');
        $b->update(['phone' => '+93 700 000 002', 'contact_visibility' => 'private', 'contact_fields' => ['phone']]);
        $list = SupplierCollection::create(['company_id' => $buyer->id, 'name' => 'Steel']);
        $ia = $list->items()->create(['company_id' => $a->id]);
        $ib = $list->items()->create(['company_id' => $b->id, 'note' => '=HYPERLINK("x")']);

        $this->signIn($buyer);
        $this->get(route('collections.compare', ['id' => $list->id, 'c' => [$ia->id, $ib->id]]))->assertOk()
            ->assertSee('Alpha Steel')->assertSee('Beta Steel')->assertSee('+93 700 000 001')->assertDontSee('+93 700 000 002')->assertSee('Enquiries only');
        $this->get(route('collections.compare', ['id' => $list->id, 'c' => [$ia->id]]))->assertSessionHas('danger');

        $csv = $this->get(route('collections.export', ['id' => $list->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString('+93 700 000 001', $csv);
        $this->assertStringNotContainsString('+93 700 000 002', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv); // spreadsheet formulas are neutralised
    }

    public function test_switched_off_the_save_control_and_pages_disappear_and_lists_are_kept(): void
    {
        $buyer = $this->paid();
        $supplier = $this->business('Herat Cement', 'c@herat.test');
        SupplierCollection::create(['company_id' => $buyer->id, 'name' => 'Kept']);
        Platform::set(['module_collections' => '0']);

        $this->signIn($buyer);
        $this->get('/companies/'.$supplier->slug)->assertDontSee('☆ Save');
        $this->get(route('collections.index'))->assertRedirect(route('dashboard'));
        $this->post(route('collections.save', ['slug' => $supplier->slug]))->assertNotFound();
        $this->assertDatabaseHas('supplier_collections', ['name' => 'Kept']);
    }
}

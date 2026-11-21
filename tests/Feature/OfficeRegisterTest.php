<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OfficeEntry;
use App\Models\PlatformAccount;
use App\Models\PlatformExpense;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_reviews_employee_collections_expenses_and_handovers_without_double_counting(): void
    {
        Mail::fake();
        Storage::fake('local');
        $owner = $this->systemAdmin();
        $cashier = $this->employee('cashier');
        $other = $this->employee('cashier', 'other');
        $cash = PlatformAccount::create(['name' => 'Office cash', 'type' => 'cash', 'currency' => 'AFN']);
        $company = $this->business();
        $plan = $this->paidPlan('AFN', 2000);
        $pending = Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'currency' => 'AFN', 'amount' => 2000,
            'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonth()->toDateString(), 'requested' => true,
        ]);

        $this->actingAs($cashier, 'admin');
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.office.index'));
        $this->get(route('admin.office.create'))->assertOk()->assertSee('Company or bidder');
        $this->get(route('admin.office.approvals'))->assertForbidden();
        $this->get(route('admin.subscriptions.edit', $pending))->assertForbidden();
        $this->get(route('admin.verification.index'))->assertForbidden();
        $common = ['platform_account_id' => $cash->id, 'date' => now()->toDateString()];
        $collection = $this->submit($common + [
            'kind' => 'subscription', 'amount' => 2000, 'company_id' => $company->id, 'plan_id' => $plan->id,
            'proof' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
        ]);
        $this->assertFalse($pending->fresh()->isPaid());
        $this->post(route('admin.office.review', $collection), ['decision' => 'approved'])->assertForbidden();

        $this->actingAs($other, 'admin');
        $this->get(route('admin.office.index', ['employee' => $cashier->id]))->assertOk()->assertDontSee($company->name);
        $this->get(route('admin.office.proof', $collection))->assertNotFound();

        $this->actingAs($owner, 'admin');
        $this->get(route('admin.office.approvals'))->assertOk()->assertSee('Download proof');
        $this->get(route('admin.office.proof', $collection))->assertOk();
        $this->approve($collection);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertNotNull($pending->fresh()->receipt_no);
        $this->assertTrue($pending->fresh()->isPaid());
        $this->assertSame($plan->id, $company->fresh()->plan_id);

        $this->actingAs($cashier, 'admin');
        $expense = $this->submit($common + [
            'kind' => 'expense', 'category' => 'food', 'amount' => 300, 'employee_funds' => true, 'description' => 'Office lunch',
        ]);
        $income = $this->submit($common + [
            'kind' => 'income', 'amount' => 50, 'employee_funds' => true, 'description' => 'Other office income',
        ]);
        $this->assertDatabaseCount('platform_expenses', 0);
        $this->actingAs($owner, 'admin');
        $this->approve($expense);
        $this->approve($income);

        $this->actingAs($cashier, 'admin');
        $handover = $this->submit($common + ['kind' => 'handover', 'amount' => 1750]);
        $this->postJson(route('admin.office.store'), $common + ['kind' => 'handover', 'amount' => 1])->assertUnprocessable();
        $this->actingAs($owner, 'admin');
        $this->approve($handover);
        $this->postJson(route('admin.office.review', $handover), ['decision' => 'approved'])->assertUnprocessable();
        $this->assertDatabaseCount('platform_expenses', 1);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertEquals(0, OfficeEntry::custody()->first()->held);
        $this->get(route('admin.accounts.show', $cash))->assertOk()->assertViewHas('officeBalance', ['AFN' => 1750.0]);
        $this->get(route('admin.reports.index'))->assertOk()->assertViewHas('months', ['AFN' => [11 => ['in' => 2050.0, 'out' => 300.0]]]);
        $this->get(route('admin.expenses.edit', PlatformExpense::sole()))->assertForbidden();
    }

    public function test_verifier_and_custom_staff_have_only_their_assigned_capabilities(): void
    {
        $verifier = $this->employee('verifier');
        $this->actingAs($verifier, 'admin');
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.verification.index'));
        $this->get(route('admin.verification.index'))->assertOk()->assertDontSee('Office approvals')->assertDontSee('Employees and roles');
        $this->get(route('admin.office.index'))->assertForbidden();

        $owner = $this->systemAdmin();
        $this->post(route('admin.admins.store'), [
            'name' => 'Custom employee', 'email' => 'custom@example.test', 'password' => 'password123', 'role' => 'staff',
            'is_active' => true, 'permission_office' => true, 'permission_verification' => true,
        ])->assertRedirect(route('admin.admins.index'));
        $employee = Admin::where('email', 'custom@example.test')->sole();
        $this->assertSame(['verification', 'office'], $employee->permissions);
        $this->actingAs($employee, 'admin');
        $this->get(route('admin.office.index'))->assertOk()->assertSee('Verification');
        $this->get(route('admin.messages.index'))->assertForbidden();

        $this->actingAs($owner, 'admin');
        $this->get(route('admin.admins.edit', $employee))->assertOk();
    }

    public function test_hesabpay_requires_a_reference_and_can_only_be_recorded_once(): void
    {
        $owner = $this->systemAdmin();
        $cashier = $this->employee('cashier');
        $wallet = PlatformAccount::create(['name' => 'HesabPay AFN', 'type' => 'mobile', 'currency' => 'AFN']);
        $buyer = $this->business();
        $other = $this->business('Other bidder', 'other-bidder@example.test');
        $plan = $this->paidPlan('AFN', 2000);
        $fields = [
            'kind' => 'subscription', 'amount' => 2000, 'platform_account_id' => $wallet->id,
            'date' => now()->toDateString(), 'plan_id' => $plan->id,
        ];

        $this->actingAs($cashier, 'admin');
        $this->postJson(route('admin.office.store'), $fields + ['company_id' => $buyer->id])->assertUnprocessable();
        $collection = $this->submit($fields + ['company_id' => $buyer->id, 'reference' => 'HP-123']);
        $this->postJson(route('admin.office.store'), $fields + ['company_id' => $other->id, 'reference' => 'HP-123'])->assertUnprocessable();
        $this->actingAs($owner, 'admin');
        $this->post(route('admin.office.review', $collection), ['decision' => 'rejected', 'review_note' => 'Payment reference needs checking.'])->assertRedirect();
        $this->assertSame('rejected', $collection->fresh()->status);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    private function employee(string $role, string $name = 'employee'): Admin
    {
        return Admin::create([
            'name' => ucfirst($name), 'email' => $name.'@example.test', 'password' => 'password123', 'role' => $role, 'is_active' => true,
        ]);
    }

    private function submit(array $fields): OfficeEntry
    {
        $this->post(route('admin.office.store'), $fields)->assertRedirect(route('admin.office.index'))->assertSessionHasNoErrors();

        return OfficeEntry::latest('id')->firstOrFail();
    }

    private function approve(OfficeEntry $entry): void
    {
        $this->post(route('admin.office.review', $entry), ['decision' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $entry->fresh()->status);
    }
}

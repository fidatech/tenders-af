<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Enquiry;
use App\Support\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Platform::set(['module_enquiries' => '1']);
        Storage::fake('local');
    }

    public function test_a_buyer_sends_an_enquiry_and_a_free_supplier_answers(): void
    {
        $buyer = $this->business('Buyer Co', 'buyer@co.test');
        $supplier = $this->business('Herat Cement', 'c@herat.test');

        $this->signIn($buyer);
        $this->get('/companies/'.$supplier->slug)->assertSee('Send an enquiry')->assertSee('name="subject"', false);
        $this->post(route('enquiries.store', ['slug' => $supplier->slug]), [
            'subject' => 'Price for 200 tonnes', 'body' => 'Please send your price for 200 tonnes delivered to Kabul.',
            'attachment' => UploadedFile::fake()->create('spec.pdf', 50, 'application/pdf'),
        ])->assertRedirect();
        $enquiry = Enquiry::sole();
        $this->assertDatabaseHas('notices', ['company_id' => $supplier->id, 'title' => 'New enquiry from Buyer Co']);

        // the supplier (any plan) sees it as new, reads it, answers
        $this->signIn($supplier);
        $this->assertSame(1, Enquiry::unreadFor($supplier->id)->count());
        $this->get(route('enquiries.index'))->assertOk()->assertSee('Buyer Co')->assertSee('New');
        $this->get(route('enquiries.show', ['id' => $enquiry->id]))->assertOk()->assertSee('200 tonnes delivered')->assertSee('spec.pdf');
        $this->assertSame(0, Enquiry::unreadFor($supplier->id)->count());
        $file = $enquiry->messages()->first();
        $this->get(route('enquiries.file', ['id' => $enquiry->id, 'message' => $file->id]))->assertOk();
        $this->post(route('enquiries.reply', ['id' => $enquiry->id]), ['body' => '5,200 AFN per tonne.'])->assertSessionHas('success');

        $this->signIn($buyer);
        $this->assertSame(1, Enquiry::unreadFor($buyer->id)->count());
        $this->get(route('enquiries.show', ['id' => $enquiry->id]))->assertSee('5,200 AFN per tonne.');
    }

    public function test_other_companies_cannot_read_or_write_in_a_thread(): void
    {
        $buyer = $this->business('Buyer Co', 'buyer@co.test');
        $supplier = $this->business('Herat Cement', 'c@herat.test');
        $enquiry = Enquiry::create(['from_company_id' => $buyer->id, 'to_company_id' => $supplier->id, 'subject' => 'Secret deal', 'last_message_at' => now()]);
        $message = $enquiry->messages()->create(['company_id' => $buyer->id, 'sender_name' => 'B', 'body' => 'Private terms', 'attachment_path' => 'enquiries/x.pdf', 'attachment_name' => 'x.pdf']);

        $this->signIn($this->business('Nosy Co', 'nosy@co.test'));
        $this->get(route('enquiries.show', ['id' => $enquiry->id]))->assertNotFound();
        $this->post(route('enquiries.reply', ['id' => $enquiry->id]), ['body' => 'hi'])->assertNotFound();
        $this->get(route('enquiries.file', ['id' => $enquiry->id, 'message' => $message->id]))->assertNotFound();
        $this->post(route('enquiries.report', ['id' => $enquiry->id]), ['reason' => 'x'])->assertNotFound();
        $this->get(route('enquiries.index'))->assertDontSee('Secret deal');

        // an administrator cannot open it until it is reported
        $this->systemAdmin();
        $this->get(route('admin.enquiries.show', ['id' => $enquiry->id]))->assertNotFound();
    }

    public function test_a_blocked_company_cannot_write_and_a_report_opens_the_thread_to_administrators(): void
    {
        $buyer = $this->business('Spammer Co', 'spam@co.test');
        $supplier = $this->business('Herat Cement', 'c@herat.test');
        $enquiry = Enquiry::create(['from_company_id' => $buyer->id, 'to_company_id' => $supplier->id, 'subject' => 'Buy now!!!', 'last_message_at' => now()]);
        $enquiry->messages()->create(['company_id' => $buyer->id, 'sender_name' => 'S', 'body' => 'Cheap offer']);

        $this->signIn($supplier);
        $this->post(route('enquiries.block', ['id' => $enquiry->id]))->assertSessionHas('success');
        $this->post(route('enquiries.report', ['id' => $enquiry->id]), ['reason' => 'Spam'])->assertSessionHas('success');

        $this->signIn($buyer);
        $this->post(route('enquiries.reply', ['id' => $enquiry->id]), ['body' => 'Again!'])->assertSessionHas('danger');
        $this->post(route('enquiries.store', ['slug' => $supplier->slug]), ['subject' => 'Hello again', 'body' => 'Another long message here'])->assertSessionHas('danger');
        $this->assertSame(1, Enquiry::count());

        // a focal point (messages permission) reads the reported thread; the reading is logged
        $this->actingAs(Admin::create(['name' => 'Focal', 'email' => 'f@t.test', 'password' => 'password123', 'role' => 'focal']), 'admin');
        $this->get(route('admin.enquiries.index'))->assertOk()->assertSee('Buy now!!!');
        $this->get(route('admin.enquiries.show', ['id' => $enquiry->id]))->assertOk()->assertSee('Cheap offer');
        $this->assertDatabaseHas('admin_logs', ['action' => 'read a reported enquiry']);
        $this->post(route('admin.enquiries.close', ['id' => $enquiry->id]))->assertRedirect();
        $this->assertSame('closed', $enquiry->fresh()->status);
    }

    public function test_guests_are_asked_to_sign_in_and_nobody_writes_to_themselves(): void
    {
        $supplier = $this->business('Herat Cement', 'c@herat.test');
        $this->get('/companies/'.$supplier->slug)->assertSee('Sign in to send an enquiry')->assertDontSee('name="subject"', false);
        $this->post(route('enquiries.store', ['slug' => $supplier->slug]), ['subject' => 'x', 'body' => 'xxxxxxxxxxxx'])->assertRedirect(route('login'));

        $this->signIn($supplier);
        $this->get('/companies/'.$supplier->slug)->assertDontSee('name="subject"', false);
        $this->post(route('enquiries.store', ['slug' => $supplier->slug]), ['subject' => 'Me', 'body' => 'Writing to myself'])->assertSessionHas('danger');
    }

    public function test_the_daily_limit_and_a_switched_off_module(): void
    {
        $buyer = $this->business('Buyer Co', 'buyer@co.test');
        $supplier = $this->business('Herat Cement', 'c@herat.test');
        for ($i = 0; $i < Enquiry::DAILY_LIMIT; $i++) {
            Enquiry::create(['from_company_id' => $buyer->id, 'to_company_id' => $supplier->id, 'subject' => 'n'.$i, 'last_message_at' => now()]);
        }
        $this->signIn($buyer);
        $this->post(route('enquiries.store', ['slug' => $supplier->slug]), ['subject' => 'One more', 'body' => 'Over the daily limit'])->assertSessionHas('danger');

        Platform::set(['module_enquiries' => '0']);
        $this->get('/companies/'.$supplier->slug)->assertDontSee('Send an enquiry');
        $this->get(route('enquiries.index'))->assertRedirect(route('dashboard'));
        $this->assertSame(Enquiry::DAILY_LIMIT, Enquiry::count()); // kept
    }
}

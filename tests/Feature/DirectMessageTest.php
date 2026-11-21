<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DirectMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DirectMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_focal_point_and_bidder_can_exchange_private_messages_and_attachments(): void
    {
        Storage::fake('local');
        $focal = Admin::create([
            'name' => 'Tender focal point', 'email' => 'focal@example.test', 'password' => 'password123', 'role' => 'focal',
        ]);
        $bidder = $this->business('Herat Bidder')->owner();
        $other = $this->business('Another Company', 'other@example.test')->owner();

        $this->post(route('admin.login.store'), ['email' => $focal->email, 'password' => 'password123'])
            ->assertRedirect(route('admin.messages.index'));
        $this->get(route('admin.companies.index'))->assertForbidden();
        $this->get(route('admin.notices.index'))->assertForbidden();
        $this->get(route('admin.messages.index', ['q' => 'Herat Bidder']))->assertOk()
            ->assertSee('Herat Bidder')->assertDontSee('Another Company');

        $thread = route('admin.messages.show', $bidder);
        $this->get($thread)->assertOk()->assertSee('Record voice');
        $this->post(route('admin.messages.store', $bidder), [
            'body' => 'Please review this tender.',
            'files' => [UploadedFile::fake()->create('tender.pdf', 12, 'application/pdf')],
            'voice' => UploadedFile::fake()->create('voice-message.webm', 20, 'video/webm'),
        ])->assertRedirect($thread);

        $message = DirectMessage::sole();
        $this->assertNull($message->read_at);
        Storage::disk('local')->assertExists(array_column($message->attachments, 'path'));

        auth('admin')->logout();
        $this->actingAs($other, 'web');
        $this->get(route('messages.show'))->assertOk()->assertDontSee('Please review this tender.');
        $this->get(route('messages.attachment', [$message->id, 0]))->assertNotFound();

        $this->actingAs($bidder, 'web');
        $this->get(route('messages.updates', ['after' => 0]))->assertOk()->assertJsonPath('last', $message->id);
        $this->assertNotNull($message->fresh()->read_at);
        $this->get(route('messages.show'))->assertOk()->assertSee('Please review this tender.')->assertSee('tender.pdf');
        $this->get(route('messages.attachment', [$message->id, 0]))->assertOk()
            ->assertDownload('tender.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('messages.attachment', [$message->id, 1]))->assertOk()->assertHeader('Content-Type', 'audio/webm');

        $this->post(route('messages.store'), ['body' => '1,250'])->assertRedirect(route('messages.show'));
        $reply = DirectMessage::latest('id')->firstOrFail();
        $this->assertSame('1,250', $reply->body);
        $this->assertNull($reply->read_at);

        $this->actingAs($focal->fresh(), 'admin');
        $this->get(route('admin.messages.updates', ['recipient' => $bidder->id, 'after' => $message->id]))
            ->assertOk()->assertJsonPath('last', $reply->id);
        $this->assertNotNull($reply->fresh()->read_at);
        $this->get(route('admin.messages.attachment', [$other->id, $message->id, 0]))->assertNotFound();
    }

    public function test_messages_reject_empty_posts_and_unsafe_file_types(): void
    {
        $this->signIn($this->business());
        $url = route('messages.store');

        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson($url, ['files' => [UploadedFile::fake()->create('page.html', 10, 'text/html')]])
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->assertDatabaseCount('direct_messages', 0);
    }
}

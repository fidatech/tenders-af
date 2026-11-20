<?php

namespace Tests\Feature;

use App\Models\Industry;
use App\Models\Product;
use App\Models\ProductPromotion;
use App\Support\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Products for wholesale: off until an administrator switches the module on; listings are approved; sponsoring is paid. */
class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $more = []): array
    {
        $industry = Industry::firstOrCreate(['slug' => 'construction'], ['name' => 'Construction', 'icon' => 'helmet-safety']);

        return $more + [
            'name' => 'Portland cement 50 kg', 'industry_id' => $industry->id, 'summary' => 'Grey cement from our plant in Herat.',
            'unit' => 'bag', 'min_order' => '200 bags', 'price_min' => '420', 'price_currency' => 'AFN', 'made_in_afghanistan' => '1',
            'images' => [UploadedFile::fake()->image('cement.jpg', 800, 600)], 'action' => 'send',
        ];
    }

    public function test_the_module_is_off_until_an_administrator_switches_it_on(): void
    {
        $this->get('/products')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('href="'.route('site.products').'"', false);
        $this->signIn($this->business());
        $this->get('/my/products')->assertNotFound();
        $this->get('/dashboard')->assertOk()->assertDontSee('My products');

        Platform::set(['products_enabled' => '1']);
        $this->get('/products')->assertOk()->assertSee('No products here yet');
        $this->get('/my/products')->assertOk()->assertSee('Add a product');
    }

    public function test_a_product_with_a_picture_and_a_video_is_shown_after_approval(): void
    {
        Storage::fake('local');
        Mail::fake();
        Platform::set(['products_enabled' => '1']);
        $company = $this->business();
        $company->update(['phone' => '0700 123 456']);
        $this->signIn($company);

        $this->post('/my/products', $this->form(['video' => UploadedFile::fake()->create('plant.mp4', 2000, 'video/mp4')]))->assertRedirect();
        $product = Product::firstOrFail();
        $this->assertSame(Product::PENDING, $product->status);
        $this->assertSame(1, $product->images()->count());
        $this->assertNotNull($product->video_path);
        // not on the site yet, but the company sees its own picture
        $this->get('/products/'.$product->slug)->assertNotFound();
        $this->get('/products/image/'.$product->images()->first()->id)->assertOk();

        $this->systemAdmin();
        $this->get('/admin/products')->assertOk()->assertSee('Portland cement 50 kg');
        $this->post('/admin/products/'.$product->id.'/approve')->assertRedirect();
        auth('admin')->logout();
        auth('web')->logout();

        $this->get('/products')->assertOk()->assertSee('Portland cement 50 kg')->assertSee('Smallest order: 200 bags');
        $this->get('/products/'.$product->slug)->assertOk()->assertSee('420 AFN per bag')->assertSee('WhatsApp the supplier');
        $this->get('/products/'.$product->slug.'/whatsapp')->assertRedirectContains('https://wa.me/93700123456');
        $this->assertSame(1, $product->refresh()->contacts);
        $this->assertSame(1, $product->views);
    }

    public function test_a_product_needs_a_smallest_order_and_a_company_lists_a_limited_number(): void
    {
        Storage::fake('local');
        Platform::set(['products_enabled' => '1', 'products_free_limit' => '1']);
        $this->signIn($this->business());

        $this->post('/my/products', $this->form(['min_order' => '']))->assertSessionHasErrors('min_order');
        $this->post('/my/products', $this->form())->assertRedirect();
        $this->post('/my/products', $this->form(['name' => 'Second product']));
        $this->assertSame(1, Product::count());
    }

    public function test_a_company_pays_to_sponsor_a_product_and_it_is_shown_first(): void
    {
        Storage::fake('local');
        Mail::fake();
        Platform::set(['products_enabled' => '1']);
        $company = $this->business();
        $this->signIn($company);
        $this->post('/my/products', $this->form(['name' => 'Older product']))->assertRedirect();
        $this->post('/my/products', $this->form(['name' => 'Sponsored product']))->assertRedirect();
        Product::query()->update(['status' => Product::PUBLISHED, 'published_at' => now()]);
        $product = Product::where('name', 'Sponsored product')->firstOrFail();

        $this->get('/my/products/'.$product->id.'/sponsor')->assertOk()->assertSee('1,500 AFN');
        $this->post('/my/products/'.$product->id.'/sponsor', ['period' => 'week', 'receipt' => UploadedFile::fake()->image('proof.jpg')])->assertRedirect('/my/products');
        $payment = ProductPromotion::firstOrFail();
        $this->assertSame('1500.00', $payment->amount);
        $this->assertFalse($product->refresh()->isSponsored());

        $this->systemAdmin();
        $this->post('/admin/products/payments/'.$payment->id.'/approve')->assertRedirect();
        $product->refresh();
        $this->assertTrue($product->isSponsored());
        // seven days, counting today (Kabul time)
        $this->assertSame('2026-11-20', $product->sponsored_until->toDateString());
        $this->get('/products')->assertOk()->assertSeeInOrder(['Sponsored product', 'Older product'])->assertSee('Sponsored');
        $this->get('/')->assertOk()->assertSee('Sponsored product');
    }

    public function test_the_statistics_page_hides_small_numbers_and_shows_real_ones(): void
    {
        $this->tender();
        $this->get('/statistics')->assertOk()->assertSee('The numbers are still growing')->assertDontSee('Open tenders by province');

        Platform::set(['stats_min' => '1']);
        $this->get('/statistics')->assertOk()->assertSee('Tenders published')->assertSee('Open tenders by province')->assertSee('Herat')->assertSee('How we count');
    }
}

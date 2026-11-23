<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminLog;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Branding::DISK);
    }

    public function test_the_text_brand_is_shown_while_no_logo_is_uploaded(): void
    {
        $page = $this->get('/')->assertOk();

        $page->assertSee('Tenders<b>.af</b>', false);
        $page->assertDontSee('class="brand-img"', false);
        $this->get('/login')->assertOk()->assertSee('Tenders<b>.af</b>', false);
        $this->assertNull(Branding::logoFor('dark'));
    }

    public function test_a_super_administrator_uploads_a_logo_and_it_is_shown_everywhere(): void
    {
        $this->systemAdmin();

        $this->get(route('admin.branding.edit'))->assertOk()->assertSee('Branding &amp; Logo', false);
        $this->put(route('admin.branding.update'), $this->form([
            'brand_logo_alt' => 'Tenders.af logo',
            'file_logo' => UploadedFile::fake()->image('logo.png', 600, 150),
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $url = Branding::url('logo');
        $this->assertNotNull($url);
        Storage::disk(Branding::DISK)->assertExists(Branding::path('logo'));

        $home = $this->get('/')->assertOk();
        $home->assertSee('class="brand-img"', false);
        $home->assertSee(e($url), false);
        $home->assertSee('alt="Tenders.af logo"', false);
        $home->assertDontSee('Tenders<b>.af</b></span></a>', false);

        // the picture is served as a PNG with a long cache
        $asset = $this->get(route('brand.asset', ['asset' => 'logo']))->assertOk();
        $this->assertSame('image/png', $asset->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $asset->headers->get('Cache-Control'));

        $this->assertDatabaseHas('admin_logs', ['action' => 'changed the branding']);
    }

    public function test_removing_the_logo_brings_back_the_text_brand(): void
    {
        $this->systemAdmin();
        $this->put(route('admin.branding.update'), $this->form(['file_logo' => UploadedFile::fake()->image('logo.png', 600, 150)]));
        $path = Branding::path('logo');

        $this->delete(route('admin.branding.destroy', ['asset' => 'logo']))->assertRedirect();

        Storage::disk(Branding::DISK)->assertMissing($path);
        $this->assertNull(Branding::url('logo'));
        $this->get('/')->assertSee('Tenders<b>.af</b>', false);
        $this->get(route('brand.asset', ['asset' => 'logo']))->assertNotFound();
    }

    public function test_the_dark_and_mobile_versions_are_used_where_they_belong(): void
    {
        $this->systemAdmin();
        $this->put(route('admin.branding.update'), $this->form([
            'file_logo' => UploadedFile::fake()->image('logo.png', 600, 150),
            'file_logo_dark' => UploadedFile::fake()->image('dark.png', 600, 150),
            'file_logo_mobile' => UploadedFile::fake()->image('mobile.png', 300, 150),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(Branding::url('logo_dark'), Branding::logoFor('dark'));
        $this->assertSame(Branding::url('logo'), Branding::logoFor('light'));
        $this->assertSame(Branding::url('logo_mobile'), Branding::logoFor('mobile'));

        $home = $this->get('/');
        $home->assertSee(e(Branding::url('logo_dark')), false); // the dark header of the website
        $home->assertSee('<source media="(max-width: 640px)"', false);
    }

    public function test_svg_and_fake_pictures_are_refused(): void
    {
        $this->systemAdmin();
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->put(route('admin.branding.update'), $this->form(['file_logo' => $svg]))->assertSessionHasErrors('file_logo');

        // a script with a .png name is not a picture
        $fake = UploadedFile::fake()->createWithContent('logo.png', "<?php echo 'x'; ?>");
        $this->put(route('admin.branding.update'), $this->form(['file_logo' => $fake]))->assertSessionHasErrors('file_logo');

        $this->assertNull(Branding::url('logo'));
    }

    public function test_the_icon_makes_app_icons_and_the_favicon(): void
    {
        $this->systemAdmin();
        $this->put(route('admin.branding.update'), $this->form(['file_icon' => UploadedFile::fake()->image('icon.png', 700, 700)]))->assertSessionHasNoErrors();

        $manifest = $this->get(route('site.manifest'))->assertOk()->json();
        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->get(route('brand.asset', ['asset' => 'app-icon-512']))->assertOk();

        [$width, $height] = getimagesizefromstring(Storage::disk(Branding::DISK)->get(Branding::variantPath(Branding::path('icon'), 512)));
        $this->assertSame([512, 512], [$width, $height]);
        $this->assertStringContainsString('/brand/icon', Branding::faviconUrl());
    }

    public function test_name_and_tagline_change_the_site(): void
    {
        $this->systemAdmin();
        $this->put(route('admin.branding.update'), $this->form(['brand_name' => 'Bids.example', 'brand_tagline' => 'Every bid in one place.']))->assertSessionHasNoErrors();

        $home = $this->get('/');
        $home->assertSee('Bids<b>.example</b>', false);
        $home->assertSee('Every bid in one place.');
        $this->assertSame('Bids.example', $this->get(route('site.manifest'))->json('name'));
    }

    public function test_only_a_super_administrator_may_change_the_branding(): void
    {
        $manager = Admin::create(['name' => 'Manager', 'email' => 'manager@tenders.test', 'password' => 'password123', 'role' => 'manager']);
        $this->actingAs($manager, 'admin');

        $this->get(route('admin.branding.edit'))->assertForbidden();
        $this->put(route('admin.branding.update'), $this->form(['file_logo' => UploadedFile::fake()->image('logo.png', 600, 150)]))->assertForbidden();
        $this->assertNull(Branding::url('logo'));
        $this->assertSame(0, AdminLog::count());
    }

    public function test_unknown_assets_are_not_served(): void
    {
        $this->get('/brand/..%2F.env')->assertNotFound();
        $this->get(route('brand.asset', ['asset' => 'app-icon-999']))->assertNotFound();
        $this->get(route('brand.asset', ['asset' => 'logo']))->assertNotFound();
    }

    public function test_the_offline_page_and_service_worker_never_cache_live_pages(): void
    {
        $this->get(route('site.offline'))->assertOk()->assertSee('You are offline');
        $worker = $this->get(route('site.sw'))->assertOk();
        $this->assertStringContainsString("mode !== 'navigate'", $worker->getContent());
        $this->assertStringNotContainsString('/tenders', $worker->getContent());
    }

    private function form(array $values): array
    {
        return $values + ['brand_logo_height' => 40, 'brand_logo_height_mobile' => 30, 'brand_display' => 'logo'];
    }
}

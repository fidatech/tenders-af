<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Visit;
use App\Support\GeoIp;
use App\Support\Statistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        GeoIp::reset();
        Cache::flush();
    }

    private function visit(string $path, array $headers = [], string $ip = '203.0.113.7'): void
    {
        $this->withHeaders($headers + ['User-Agent' => self::BROWSER])->withServerVariables(['REMOTE_ADDR' => $ip])->get($path)->assertOk();
    }

    public function test_a_public_page_view_is_counted_without_keeping_the_address(): void
    {
        $this->visit('/tenders');
        $this->visit('/companies');

        $this->assertSame(2, Visit::count());
        $visit = Visit::first();
        $this->assertSame('/tenders', $visit->page);
        $this->assertSame(16, strlen($visit->visitor));
        $this->assertSame('desktop', $visit->device);
        // the same person on the same day is one visitor
        $this->assertSame(1, Visit::distinct()->count('visitor'));
        // no IP address is stored anywhere in the table
        $this->assertNotContains('ip', Schema::getColumnListing('visits'));
        $this->assertStringNotContainsString('203.0.113.7', json_encode(Visit::all()->toArray()));
    }

    public function test_robots_private_browsers_admins_and_other_pages_are_not_counted(): void
    {
        $this->visit('/', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);
        $this->visit('/', ['User-Agent' => 'Mozilla/5.0 (X11) Chrome-Lighthouse']);
        $this->visit('/', ['DNT' => '1']);
        $this->visit('/', ['Sec-GPC' => '1']);
        $this->visit('/', ['Sec-Purpose' => 'prefetch']);
        $this->withHeaders(['User-Agent' => self::BROWSER])->get('/sitemap.xml')->assertOk();
        $this->withHeaders(['User-Agent' => self::BROWSER])->get('/no-such-page')->assertNotFound();

        $this->systemAdmin();
        $this->visit('/');
        auth('admin')->logout();

        // a company's own panel is not part of the public figures
        $this->signIn($this->business());
        $this->withHeaders(['User-Agent' => self::BROWSER])->get('/dashboard')->assertOk();

        $this->assertSame(0, Visit::count());
    }

    public function test_without_the_location_database_visits_are_counted_with_an_unknown_place(): void
    {
        config(['geoip.database' => '/nonexistent/file.mmdb']);
        $this->assertFalse(GeoIp::available());

        $this->visit('/', ['CF-IPCountry' => 'AF']);
        $this->assertNull(Visit::sole()->country); // the header is not trusted unless the site says it is behind Cloudflare
    }

    public function test_the_cloudflare_country_is_used_when_the_site_trusts_it(): void
    {
        config(['geoip.trust_cloudflare' => true, 'geoip.database' => '/nonexistent/file.mmdb']);
        $this->visit('/', ['CF-IPCountry' => 'af']);
        $this->visit('/', ['CF-IPCountry' => 'XX'], '198.51.100.9');

        $this->assertSame(['AF', null], Visit::orderBy('id')->pluck('country')->all());
    }

    public function test_country_names_and_flags_never_show_an_empty_or_unknown_name_for_a_real_code(): void
    {
        $this->assertSame('Afghanistan', GeoIp::countryName('AF'));
        $this->assertSame('Pakistan', GeoIp::countryName('pk'));
        $this->assertSame('United States', GeoIp::countryName('US'));
        $this->assertSame('Unknown', GeoIp::countryName(null));
        $this->assertSame('🇦🇫', GeoIp::flag('AF'));
        $this->assertSame('🌐', GeoIp::flag(null));
    }

    public function test_the_page_shows_visitors_by_country_and_city_but_never_a_lonely_city(): void
    {
        $today = now('Asia/Kabul')->toDateString();
        $make = fn (string $visitor, ?string $country, ?string $city, string $device = 'desktop') => Visit::create([
            'visited_on' => $today, 'visitor' => $visitor, 'country' => $country, 'region' => $city ? 'Kabul' : null, 'city' => $city,
            'page' => '/', 'device' => $device, 'created_at' => now(),
        ]);
        foreach (['a', 'b', 'c'] as $v) {
            $make(str_pad($v, 16, 'x'), 'AF', 'Kabul', 'mobile');
        }
        $make(str_pad('d', 16, 'x'), 'DE', 'Hamburg');
        $make(str_pad('e', 16, 'x'), null, null);

        $visitors = Statistics::visitors(30);
        $this->assertSame(5, $visitors['visitors']);
        $this->assertSame('Afghanistan', $visitors['countries'][0]['name']);
        $this->assertSame(['Kabul'], array_column($visitors['cities'], 'city')); // Hamburg has only one visitor

        Cache::flush();
        $page = $this->get('/statistics')->assertOk();
        $page->assertSee('Visitors and pages opened, day by day')->assertSee('Afghanistan')->assertSee('Germany')->assertSee('Unknown');
        $page->assertSee('Kabul')->assertDontSee('Hamburg');
        $page->assertSee('60%'); // three of five on phones
    }

    public function test_an_empty_site_explains_itself_instead_of_showing_nothing(): void
    {
        $page = $this->get('/statistics')->assertOk();
        $page->assertSee('Tender figures start with the first tenders')->assertSee('provinces covered')->assertSee('Counting has started.');
    }

    public function test_visitors_come_first_with_periods_sources_and_pages(): void
    {
        $day = fn (int $ago) => now('Asia/Kabul')->subDays($ago)->toDateString();
        $add = fn (string $who, int $ago, ?string $referrer, string $page) => Visit::create(['visited_on' => $day($ago), 'visitor' => str_pad($who, 16, 'x'), 'country' => 'AF', 'page' => $page, 'referrer' => $referrer, 'device' => 'mobile']);
        $add('a', 0, 'google.com', '/');
        $add('b', 1, null, '/tenders');
        $add('c', 2, 'facebook.com', '/tender/road-in-herat');
        $add('d', 20, 'news.example.org', '/');
        $add('a', 0, null, '/tenders'); // a click inside the site: still the visitor who came from Google
        $this->assertSame(['Direct or bookmarks' => 1, 'Search engines' => 1, 'Social media' => 1, 'Other websites' => 1], Statistics::visitors(30)['sources']);
        $this->assertSame(4, array_sum(Statistics::visitors(30)['sources'])); // the sources add up to the visitors
        Cache::flush();
        $this->assertSame(3, Statistics::visitors(7)['visitors']);
        $this->assertSame(4, Statistics::visitors(30)['visitors']);
        Cache::flush();

        $page = $this->get('/statistics')->assertOk();
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'Tenders and companies'), strpos($html, 'How visitors found the site'));
        $page->assertSee('Search engines')->assertSee('Social media')->assertSee('Direct or bookmarks')->assertSee('Other websites');
        $page->assertDontSee('news.example.org'); // other websites are named only in the admin panel
        $page->assertSee('Tender: road in herat')->assertSee('Home page');

        $week = $this->get('/statistics?days=7')->assertOk();
        $week->assertSee('noindex', false);
        $this->get('/statistics?days=999')->assertOk(); // an unknown period falls back to 30 days
    }

    public function test_tenders_by_month_sector_and_buyer_and_the_csv_download(): void
    {
        $tender = $this->tender();
        $page = $this->get('/statistics')->assertOk();
        $page->assertSee('Tenders published each month')->assertSee('By sector')->assertSee('Buyers that publish the most')->assertSee($tender->buyer_name);
        $page->assertDontSee('Tender figures start with the first tenders');

        $csv = $this->get(route('site.statistics.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Tenders published by month', $csv);
        $this->assertStringContainsString($tender->buyer_name, $csv);
    }

    public function test_old_visits_are_pruned(): void
    {
        Visit::create(['visited_on' => now()->subMonths(14)->toDateString(), 'visitor' => str_repeat('a', 16), 'page' => '/', 'device' => 'desktop']);
        Visit::create(['visited_on' => now()->toDateString(), 'visitor' => str_repeat('b', 16), 'page' => '/', 'device' => 'desktop']);
        $this->artisan('visits:prune')->assertSuccessful();
        $this->assertSame(1, Visit::count());
    }

    public function test_administrators_see_the_full_visitor_figures_and_others_do_not(): void
    {
        Visit::create(['visited_on' => now('Asia/Kabul')->toDateString(), 'visitor' => str_repeat('a', 16), 'country' => 'DE', 'city' => 'Hamburg', 'page' => '/tenders', 'referrer' => 'google.com', 'device' => 'desktop']);

        $this->get(route('admin.visitors.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(Admin::create(['name' => 'Focal', 'email' => 'f@t.test', 'password' => 'password123', 'role' => 'focal']), 'admin');
        $this->get(route('admin.visitors.index'))->assertForbidden();

        $this->systemAdmin();
        $this->get(route('admin.visitors.index', ['days' => 7]))->assertOk()->assertSee('Germany')->assertSee('Hamburg')->assertSee('/tenders')->assertSee('google.com');
    }
}

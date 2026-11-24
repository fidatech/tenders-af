<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Visit;
use App\Support\GeoIp;
use App\Support\Robots;
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

    public function test_robots_are_kept_apart_and_private_browsers_admins_and_other_pages_are_not_counted(): void
    {
        $this->visit('/', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);
        $this->visit('/', ['User-Agent' => 'Mozilla/5.0 (X11) Chrome-Lighthouse']);
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)'])->get('/sitemap.xml')->assertOk();
        // people who ask not to be tracked, prefetches, other files, missing pages
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

        $this->assertSame(0, Visit::where('kind', '!=', 'bot')->count());
        $this->assertSame(
            [['search', 'Google', '/'], ['monitor', 'Lighthouse', '/'], ['ai', 'OpenAI', '/sitemap.xml']],
            Visit::orderBy('id')->get()->map(fn ($v) => [$v->bot_type, $v->bot_name, $v->page])->all(),
        );
    }

    public function test_a_view_counts_as_a_person_only_after_the_page_confirms_a_real_browser(): void
    {
        $this->visit('/tenders');
        $this->assertSame('pending', Visit::sole()->kind);
        $this->assertSame(0, Statistics::visitors(30)['views']);

        // the page's script reports in: this view and the next pages are a person
        $this->withHeaders(['User-Agent' => self::BROWSER])->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->post(route('site.beacon'))->assertNoContent();
        $this->assertSame('human', Visit::sole()->kind);
        $this->visit('/companies');
        $this->assertSame(['human', 'human'], Visit::orderBy('id')->pluck('kind')->all());
        Cache::forget('stats.visitors.30');
        $this->assertSame(1, Statistics::visitors(30)['visitors']);

        // a different person is not confirmed by it
        $this->visit('/', [], '198.51.100.20');
        $this->assertSame('pending', Visit::latest('id')->first()->kind);
    }

    public function test_a_robot_cannot_confirm_itself_and_the_beacon_needs_no_token(): void
    {
        $google = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
        $this->visit('/', ['User-Agent' => $google]);
        $this->withHeaders(['User-Agent' => $google])->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->post(route('site.beacon'))->assertNoContent();
        $this->assertSame('bot', Visit::sole()->kind);
    }

    public function test_robot_names_and_kinds_are_recognised_and_phones_are_not_robots(): void
    {
        $cases = [
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)' => ['search', 'Bing'],
            'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)' => ['ai', 'Anthropic'],
            'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)' => ['seo', 'Ahrefs'],
            'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)' => ['social', 'Facebook'],
            'WhatsApp/2.23.20.0' => ['social', 'WhatsApp'],
            'python-requests/2.31.0' => ['script', 'Python'],
            'curl/8.4.0' => ['script', 'curl'],
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0 Safari/537.36' => ['script', 'Headless browser'],
            'Mozilla/5.0 (compatible; SomeNewBot/0.1; +https://example.org/bot)' => ['other', 'Other crawler'],
            'SomethingUnknown/1.0' => ['script', 'Other program'],
            '' => ['script', 'No browser name'],
        ];
        foreach ($cases as $agent => [$type, $name]) {
            $this->assertSame(['type' => $type, 'name' => $name], Robots::detect($agent), $agent);
        }
        foreach ([
            self::BROWSER,
            'Mozilla/5.0 (Linux; Android 10; CUBOT X30) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Mobile Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
        ] as $browser) {
            $this->assertNull(Robots::detect($browser), $browser);
        }
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

    public function test_the_growth_page_shows_people_robots_and_unconfirmed_traffic_apart(): void
    {
        $day = now('Asia/Kabul')->toDateString();
        $row = fn (array $v) => Visit::create($v + ['visited_on' => $day, 'page' => '/tenders', 'device' => 'desktop', 'created_at' => now()]);
        $row(['visitor' => str_repeat('a', 16), 'kind' => 'human', 'country' => 'DE', 'city' => 'Hamburg', 'referrer' => 'google.com']);
        $row(['visitor' => str_repeat('b', 16), 'kind' => 'bot', 'bot_type' => 'search', 'bot_name' => 'Google', 'page' => '/sitemap.xml']);
        $row(['visitor' => str_repeat('b', 16), 'kind' => 'bot', 'bot_type' => 'ai', 'bot_name' => 'OpenAI']);
        $row(['visitor' => str_repeat('c', 16), 'kind' => 'pending', 'country' => 'NL']);

        $this->get(route('admin.growth.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(Admin::create(['name' => 'Focal', 'email' => 'f@t.test', 'password' => 'password123', 'role' => 'focal']), 'admin');
        $this->get(route('admin.growth.index'))->assertForbidden();

        $this->systemAdmin();
        $page = $this->get(route('admin.growth.index', ['days' => 7]))->assertOk();
        $page->assertSee('Real visitors')->assertSee('Germany')->assertSee('Hamburg')->assertSee('google.com');
        $page->assertSee('Robots by name')->assertSee('Google')->assertSee('OpenAI')->assertSee('Search engines')->assertSee('AI crawlers and assistants')->assertSee('Sitemap');
        $page->assertSee('Unconfirmed traffic')->assertSee('Netherlands');
        $page->assertSee('New each month');
        foreach ([30, 90, 365] as $days) {
            $this->get(route('admin.growth.index', ['days' => $days]))->assertOk();
        }
        // the old address leads here
        $this->get('/admin/visitors')->assertRedirect('/admin/growth');

        // the public page shows the person only
        Cache::flush();
        $public = $this->get('/statistics')->assertOk();
        $public->assertSee('Germany')->assertDontSee('Netherlands')->assertDontSee('OpenAI');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Visit;
use App\Support\GeoIp;
use App\Support\MmdbReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\MmdbWriter;
use Tests\TestCase;

class GeoIpTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    /** Records shaped like the DB-IP City Lite database (the same layout as GeoIP2 City). */
    private function networks(): array
    {
        $place = fn (string $code, string $country, string $region, string $city) => [
            'city' => ['names' => ['en' => $city]],
            'continent' => ['code' => 'AS', 'names' => ['en' => 'Asia']],
            'country' => ['iso_code' => $code, 'names' => ['en' => $country]],
            'location' => ['latitude' => 34.5, 'longitude' => 69.2],
            'subdivisions' => ['array', [['names' => ['en' => $region]]]],
        ];

        return [
            '180.94.64.0/19' => $place('AF', 'Afghanistan', 'Kabul', 'Kabul'),
            '103.13.64.0/22' => $place('AF', 'Afghanistan', 'Herat', 'Herat'),
            '5.146.0.0/16' => $place('DE', 'Germany', 'Hamburg', 'Hamburg'),
            '8.8.8.0/24' => $place('US', 'United States', 'California', 'Mountain View'),
            '2a00:1450::/32' => $place('IE', 'Ireland', 'Leinster', 'Dublin'),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'mmdb');
        GeoIp::reset();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        GeoIp::reset();
        parent::tearDown();
    }

    public function test_the_built_in_reader_finds_countries_and_cities_with_every_record_size(): void
    {
        foreach ([24, 28, 32] as $size) {
            MmdbWriter::write($this->file, $this->networks(), $size);
            $reader = new MmdbReader($this->file);

            $this->assertSame($size, $reader->metadata()['record_size']);
            $kabul = $reader->get('180.94.70.12');
            $this->assertSame('AF', $kabul['country']['iso_code'], "record size $size");
            $this->assertSame('Kabul', $kabul['city']['names']['en']);
            $this->assertSame('Kabul', $kabul['subdivisions'][0]['names']['en']);
            $this->assertEqualsWithDelta(34.5, $kabul['location']['latitude'], 0.0001);
            $this->assertSame('Herat', $reader->get('103.13.67.255')['city']['names']['en']);
            $this->assertSame('DE', $reader->get('5.146.200.1')['country']['iso_code']);
            $this->assertSame('Mountain View', $reader->get('8.8.8.8')['city']['names']['en']);
            $this->assertSame('IE', $reader->get('2a00:1450:4001::1')['country']['iso_code']);
            // outside every network
            $this->assertNull($reader->get('1.1.1.1'));
            $this->assertNull($reader->get('180.94.96.1'));
            $this->assertNull($reader->get('2001:db8::1'));
        }
    }

    public function test_a_file_that_is_not_a_location_database_is_refused(): void
    {
        file_put_contents($this->file, str_repeat('not a database ', 100));
        $this->expectException(\RuntimeException::class);
        new MmdbReader($this->file);
    }

    public function test_visits_get_real_country_names_and_cities(): void
    {
        MmdbWriter::write($this->file, $this->networks());
        config(['geoip.database' => $this->file]);
        $this->assertTrue(GeoIp::available());

        $browser = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36';
        foreach (['180.94.70.12', '5.146.1.1', '1.1.1.1'] as $ip) {
            $this->withHeaders(['User-Agent' => $browser])->withServerVariables(['REMOTE_ADDR' => $ip])->get('/tenders')->assertOk();
            // the page's script confirms a real browser
            $this->withHeaders(['User-Agent' => $browser])->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('site.beacon'))->assertNoContent();
        }

        $visits = Visit::orderBy('id')->get();
        $this->assertSame(['AF', 'DE', null], $visits->pluck('country')->all());
        $this->assertSame(['Kabul', 'Hamburg', null], $visits->pluck('city')->all());

        Cache::flush();
        $page = $this->get('/statistics')->assertOk();
        $page->assertSee('Afghanistan')->assertSee('Germany');
    }

    public function test_a_damaged_file_never_breaks_a_page(): void
    {
        MmdbWriter::write($this->file, $this->networks());
        // cut the data section away but keep the metadata
        $bytes = file_get_contents($this->file);
        $marker = strrpos($bytes, "\xAB\xCD\xEFMaxMind.com");
        file_put_contents($this->file, substr($bytes, 0, 40).substr($bytes, $marker));
        config(['geoip.database' => $this->file]);

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/131.0'])->withServerVariables(['REMOTE_ADDR' => '180.94.70.12'])->get('/tenders')->assertOk();
        $this->assertNull(Visit::sole()->country);
    }

    public function test_the_update_command_downloads_unpacks_and_checks_the_database(): void
    {
        MmdbWriter::write($this->file, $this->networks());
        // pad it so it looks like a real download
        $gz = gzencode(file_get_contents($this->file).str_repeat("\0", 0), 9);
        $target = sys_get_temp_dir().'/geoip-test-'.uniqid().'/city.mmdb';
        config(['geoip.database' => $target, 'geoip.download_url' => 'https://download.example/db-{month}.mmdb.gz']);

        // this month's file is not out yet; last month's is
        Http::fake([
            'download.example/db-'.now()->format('Y-m').'*' => Http::response('', 404),
            'download.example/*' => Http::response(str_pad($gz, 200 * 1024, "\0")),
        ]);
        $this->artisan('geoip:update', ['--force' => true])->assertSuccessful();

        $this->assertFileExists($target);
        GeoIp::reset();
        $this->assertTrue(GeoIp::available());
        $this->assertSame('AF', (new MmdbReader($target))->get('180.94.70.12')['country']['iso_code']);

        // not a database: refused, and the good file stays
        Http::fake(['*' => Http::response(str_pad(gzencode(str_repeat('x', 300000)), 200 * 1024, "\0"))]);
        $this->artisan('geoip:update', ['--force' => true])->assertFailed();
        $this->assertSame('AF', (new MmdbReader($target))->get('180.94.70.12')['country']['iso_code']);
        @unlink($target);
        @rmdir(dirname($target));
    }
}

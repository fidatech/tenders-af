<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_are_gzipped_when_the_browser_accepts_it(): void
    {
        $response = $this->get('/companies', ['Accept-Encoding' => 'gzip, br']);
        $response->assertOk()->assertHeader('Content-Encoding', 'gzip');
        $this->assertStringContainsString('</html>', gzdecode($response->getContent()));
    }

    public function test_pages_are_plain_without_gzip(): void
    {
        $this->get('/companies')->assertOk()->assertHeaderMissing('Content-Encoding');
    }
}

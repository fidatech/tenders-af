<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_has_google_verification_tag(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="google-site-verification" content="J8acjyDxEnvrlerggEuM0d1dpHi1GSlmrVjdZ4YoNU4">', false)
            ->assertDontSee('msvalidate.01', false);
    }
}

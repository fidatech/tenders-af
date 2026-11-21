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

    public function test_public_pages_have_complete_website_share_metadata(): void
    {
        $url = route('site.tenders');
        $image = asset('img/og.png');
        $response = $this->get($url.'?q=construction')->assertOk();
        $properties = [
            'og:type' => 'website',
            'og:site_name' => config('app.name'),
            'og:locale' => 'en_US',
            'og:determiner' => '',
            'og:url' => $url,
            'og:image' => $image,
            'og:image:url' => $image,
            'og:image:secure_url' => secure_asset('img/og.png'),
            'og:image:type' => 'image/png',
            'og:image:width' => '1200',
            'og:image:height' => '630',
        ];

        foreach ($properties as $property => $content) {
            $response->assertSee('<meta property="'.$property.'" content="'.e($content).'">', false);
        }

        $response->assertSee('property="og:title"', false)
            ->assertSee('property="og:description"', false)
            ->assertSee('property="og:image:alt"', false)
            ->assertSee('name="twitter:image:alt"', false);
    }
}

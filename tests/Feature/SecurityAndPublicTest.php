<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSurveillanceData;
use Tests\TestCase;

class SecurityAndPublicTest extends TestCase
{
    use BuildsSurveillanceData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld(10);
    }

    public function test_pages_send_a_strict_csp_with_a_nonce_and_clickjacking_protection(): void
    {
        $res = $this->get('/login')->assertOk();
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString('script-src \'self\' \'unsafe-inline\'', $csp);
        $res->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy');
    }

    public function test_signed_in_pages_are_never_cached_or_indexed_but_public_pages_are_indexable(): void
    {
        $user = $this->userFor('national_manager', $this->country);
        $private = $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertStringContainsString('no-store', $private->headers->get('Cache-Control'));
        $private->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $public = $this->get('/situation')->assertOk();
        $this->assertNull($public->headers->get('X-Robots-Tag'));
    }

    public function test_hsts_is_sent_only_on_secure_requests(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_http_is_redirected_to_https_when_forced(): void
    {
        config(['surveillance.force_https' => true]);
        $this->get('http://localhost/login')->assertStatus(301)->assertRedirect('https://localhost/login');
    }

    public function test_inline_scripts_carry_the_request_nonce(): void
    {
        $user = $this->userFor('national_manager', $this->country);
        $res = $this->actingAs($user)->get('/dashboard');
        preg_match("/nonce-([A-Za-z0-9+\/=]+)/", $res->headers->get('Content-Security-Policy'), $m);
        $res->assertSee('nonce="'.$m[1].'"', false);
        $this->assertDoesNotMatchRegularExpression('/<script>(?!\s*<\/script>)/', $res->getContent());
        $this->assertStringNotContainsString('onclick=', $res->getContent());
    }

    public function test_public_pages_show_national_totals_only(): void
    {
        foreach (['/', '/situation', '/about-the-data', '/share'] as $url) {
            $body = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('Alpha District', $body, "$url leaks a district name");
            $this->assertStringNotContainsString('Facility facA1', $body, "$url leaks a facility name");
        }
        $this->get('/situation')->assertSee('Malaria')->assertSee('Measles');
        $this->get('/share/summary.png')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_robots_and_sitemap_keep_private_areas_out_of_search(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        foreach (['/cases', '/alerts', '/api', '/admin', '/fhir', '/dashboard'] as $path) {
            $this->assertStringContainsString("Disallow: $path", $robots);
        }
        $this->assertStringContainsString('Sitemap:', $robots);
        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/about-the-data', $sitemap);
        $this->assertStringNotContainsString('/cases', $sitemap);
        $this->assertStringContainsString('application/ld+json', $this->get('/about-the-data')->getContent());
    }

    public function test_guests_cannot_reach_any_private_page_or_api(): void
    {
        foreach (['/dashboard', '/trends', '/map', '/cases', '/alerts', '/exports', '/admin/users', '/fhir/MeasureReport', '/data/districts.geojson', '/live/summary'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->postJson('/api/v1/notifications', [])->assertStatus(401);
    }

    public function test_synthetic_data_notice_follows_the_setting(): void
    {
        $user = $this->userFor('national_manager', $this->country);
        config(['surveillance.synthetic_data' => true]);
        $this->actingAs($user)->get('/dashboard')->assertSee('generated sample data');
        config(['surveillance.synthetic_data' => false]);
        $this->actingAs($user)->get('/dashboard')->assertDontSee('generated sample data');
    }
}

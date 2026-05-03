<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the `$middleware->trustProxies(at: '*', ...)` configuration in
 * bootstrap/app.php — necessary in production because Azure App Service
 * terminates TLS at the edge and forwards plain HTTP to the worker. Without
 * this, Laravel sees the loopback request as HTTP and emits `http://...`
 * URLs in pagination payloads even though the client used HTTPS.
 */
class TrustProxiesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function honors_x_forwarded_proto_https_when_set_by_load_balancer(): void
    {
        $response = $this->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'api.whartonai.studio',
            'REMOTE_ADDR' => '169.254.131.5',
        ])->getJson('/api/public/events');

        $response->assertOk();

        $firstPageUrl = $response->json('first_page_url');

        $this->assertNotNull($firstPageUrl);
        $this->assertStringStartsWith('https://', $firstPageUrl);
        $this->assertStringContainsString('api.whartonai.studio', $firstPageUrl);
    }

    #[Test]
    public function defaults_to_request_scheme_when_no_proxy_header_present(): void
    {
        // Local dev / direct curl with no LB in front should keep emitting http
        // (or whatever scheme the test harness uses) — the middleware should
        // only override when X-Forwarded-Proto is explicitly set.
        $response = $this->getJson('/api/public/events');

        $response->assertOk();

        $firstPageUrl = $response->json('first_page_url');

        $this->assertNotNull($firstPageUrl);
        $this->assertStringStartsWith('http://', $firstPageUrl);
    }
}

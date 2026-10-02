<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Tests;

use Asignua\FilamentCspNonce\Http\Middleware\CspNonce;
use Asignua\FilamentCspNonce\Models\CspViolation;

class CustomReportPathTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('csp-nonce.report.path', '/security/csp-reports');
    }

    public function test_a_custom_path_reaches_the_route_and_both_headers(): void
    {
        $response = $this->get('/admin/users');

        $this->assertStringContainsString('report-uri /security/csp-reports', (string) $response->headers->get(CspNonce::ENFORCE));
        $this->assertSame('csp-endpoint="/security/csp-reports"', $response->headers->get('Reporting-Endpoints'));

        $this->postJson('/security/csp-reports', ['csp-report' => [
            'document-uri' => 'http://localhost/admin', 'effective-directive' => 'img-src', 'blocked-uri' => 'https://x.test/a.png',
        ]])->assertNoContent();

        $this->assertSame(1, CspViolation::query()->count());
        $this->postJson('/csp/report', [])->assertNotFound();
    }
}

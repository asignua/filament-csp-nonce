<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Tests;

use Asignua\FilamentCspNonce\Http\Middleware\CspNonce;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

class MiddlewareTest extends TestCase
{
    public function test_report_only_mode_swaps_the_header(): void
    {
        config(['csp-nonce.report_only' => true]);

        $response = $this->get('/admin/users');

        $this->assertTrue($response->headers->has(CspNonce::REPORT_ONLY));
        $this->assertFalse($response->headers->has(CspNonce::ENFORCE));
    }

    public function test_the_header_points_at_the_report_endpoint(): void
    {
        $response = $this->get('/admin/users');

        $header = (string) $response->headers->get(CspNonce::ENFORCE);

        $this->assertStringContainsString('report-uri /csp/report', $header);
        $this->assertStringContainsString('report-to csp-endpoint', $header);
        $this->assertSame('csp-endpoint="/csp/report"', $response->headers->get('Reporting-Endpoints'));
    }

    public function test_config_directives_are_merged_over_the_preset(): void
    {
        config(['csp-nonce.directives' => ['connect-src' => ["'self'", 'wss://ws.example.com']]]);

        $header = (string) $this->get('/admin/users')->headers->get(CspNonce::ENFORCE);

        $this->assertStringContainsString("connect-src 'self' wss://ws.example.com", $header);
    }

    public function test_disabled_means_no_header(): void
    {
        config(['csp-nonce.enabled' => false]);

        $this->assertFalse($this->get('/admin/users')->headers->has(CspNonce::ENFORCE));
    }

    public function test_the_alias_works_outside_panels_with_the_directive_and_helper(): void
    {
        Route::middleware('csp.nonce')->get('/plain', fn (): string => Blade::render('<script @cspNonce>x()</script>|{{ csp_nonce() }}'));

        $response = $this->get('/plain')->assertOk();

        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get(CspNonce::ENFORCE), $m);

        $this->assertSame('<script nonce="'.$m[1].'">x()</script>|'.e($m[1]), $response->getContent());
    }
}

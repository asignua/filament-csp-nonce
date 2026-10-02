<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Tests;

use Asignua\FilamentCspNonce\Models\CspViolation;

class ReportEndpointTest extends TestCase
{
    private const LEGACY = [
        'csp-report' => [
            'document-uri' => 'https://app.test/admin/users?token=secret#frag',
            'violated-directive' => 'script-src-elem',
            'effective-directive' => 'script-src-elem',
            'blocked-uri' => 'inline',
            'source-file' => 'https://app.test/admin/users',
            'line-number' => 12,
            'column-number' => 3,
            'script-sample' => 'alert(1)',
            'disposition' => 'report',
        ],
    ];

    public function test_legacy_report_uri_body_is_stored_without_query_strings(): void
    {
        $this->postJson('/csp/report', self::LEGACY)->assertNoContent();

        $row = CspViolation::query()->sole();

        $this->assertSame('script-src-elem', $row->directive);
        $this->assertSame('https://app.test/admin/users', $row->document);
        $this->assertSame('inline', $row->blocked);
        $this->assertSame(12, $row->line);
        $this->assertSame('report', $row->disposition);
        $this->assertSame(1, $row->hits);
    }

    public function test_reporting_api_body_is_understood(): void
    {
        $this->postJson('/csp/report', [[
            'type' => 'csp-violation',
            'url' => 'https://app.test/admin',
            'body' => ['effectiveDirective' => 'style-src-elem', 'blockedURL' => 'inline', 'documentURL' => 'https://app.test/admin', 'lineNumber' => 4],
        ], ['type' => 'deprecation', 'body' => []]])->assertNoContent();

        $this->assertSame('style-src-elem', CspViolation::query()->sole()->directive);
    }

    public function test_identical_violations_are_folded_into_one_row(): void
    {
        $this->postJson('/csp/report', self::LEGACY);
        $this->postJson('/csp/report', self::LEGACY);
        $this->postJson('/csp/report', self::LEGACY);

        $this->assertSame(3, CspViolation::query()->sole()->hits);
    }

    public function test_garbage_and_oversized_bodies_are_ignored(): void
    {
        $this->call('POST', '/csp/report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], 'not json')->assertNoContent();
        $this->call('POST', '/csp/report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], str_repeat('a', 20000))->assertNoContent();
        $this->postJson('/csp/report', ['hello' => 'world'])->assertNoContent();

        $this->assertSame(0, CspViolation::query()->count());
    }

    public function test_endpoint_needs_neither_session_nor_csrf(): void
    {
        $this->app['auth']->logout();

        $this->call('POST', '/csp/report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], (string) json_encode(self::LEGACY))->assertNoContent();

        $this->assertSame(1, CspViolation::query()->count());
    }

    public function test_log_storage(): void
    {
        $file = sys_get_temp_dir().'/csp-test-'.uniqid().'.log';
        config([
            'csp-nonce.report.storage' => 'log',
            'csp-nonce.report.log_channel' => 'csp-test',
            'logging.channels.csp-test' => ['driver' => 'single', 'path' => $file],
        ]);

        $this->postJson('/csp/report', self::LEGACY)->assertNoContent();

        $this->assertStringContainsString('CSP violation', (string) file_get_contents($file));
        $this->assertSame(0, CspViolation::query()->count());
        unlink($file);
    }

    public function test_prune_removes_old_rows(): void
    {
        $this->postJson('/csp/report', self::LEGACY);
        $this->travel(40)->days();

        $this->artisan('csp:prune')->assertSuccessful();

        $this->assertSame(0, CspViolation::query()->count());
    }
}

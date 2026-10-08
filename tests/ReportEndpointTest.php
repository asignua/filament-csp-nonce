<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Tests;

use Asignua\FilamentCspNonce\Models\CspViolation;
use Asignua\FilamentCspNonce\Support\ViolationReport;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportEndpointTest extends TestCase
{
    private const LEGACY = [
        'csp-report' => [
            'document-uri' => 'http://localhost/admin/users?token=secret#frag',
            'violated-directive' => 'script-src-elem',
            'effective-directive' => 'script-src-elem',
            'blocked-uri' => 'inline',
            'source-file' => 'http://localhost/admin/users',
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
        $this->assertSame('http://localhost/admin/users', $row->document);
        $this->assertSame('inline', $row->blocked);
        $this->assertSame(12, $row->line);
        $this->assertSame('report', $row->disposition);
        $this->assertSame(1, $row->hits);
    }

    public function test_reporting_api_body_is_understood(): void
    {
        $this->postJson('/csp/report', [[
            'type' => 'csp-violation',
            'url' => 'http://localhost/admin',
            'body' => ['effectiveDirective' => 'style-src-elem', 'blockedURL' => 'inline', 'documentURL' => 'http://localhost/admin', 'lineNumber' => 4],
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

    /**
     * @return array{csp-report: array<string, mixed>}
     */
    private static function report(string $blocked, string $document = 'http://localhost/admin'): array
    {
        return ['csp-report' => ['document-uri' => $document, 'effective-directive' => 'img-src', 'blocked-uri' => $blocked]];
    }

    public function test_reports_about_documents_on_foreign_hosts_are_dropped(): void
    {
        $this->postJson('/csp/report', self::report('https://x.test/a.png', 'https://evil.test/page'))->assertNoContent();
        $this->postJson('/csp/report', self::report('https://x.test/a.png', ''))->assertNoContent();

        $this->assertSame(0, CspViolation::query()->count());

        config(['csp-nonce.report.allowed_hosts' => ['www.example.com']]);
        $this->postJson('/csp/report', self::report('https://x.test/a.png', 'https://WWW.example.com/page'))->assertNoContent();

        $this->assertSame(1, CspViolation::query()->count());
    }

    public function test_new_violations_are_capped_per_minute_but_repeats_still_count(): void
    {
        config(['csp-nonce.report.max_new_per_minute' => 2]);

        foreach (['a', 'b', 'c', 'd'] as $blocked) {
            $this->postJson('/csp/report', self::report("https://x.test/{$blocked}.png"))->assertNoContent();
        }
        $this->postJson('/csp/report', self::report('https://x.test/a.png'));

        $this->assertSame(2, CspViolation::query()->count());
        $this->assertSame(2, CspViolation::query()->where('blocked', 'https://x.test/a.png')->sole()->hits);

        $this->travel(2)->minutes();
        $this->postJson('/csp/report', self::report('https://x.test/c.png'));

        $this->assertSame(3, CspViolation::query()->count());
    }

    public function test_the_table_has_a_hard_row_cap(): void
    {
        config(['csp-nonce.report.max_rows' => 2, 'csp-nonce.report.max_new_per_minute' => 0]);

        foreach (['a', 'b', 'c'] as $blocked) {
            $this->postJson('/csp/report', self::report("https://x.test/{$blocked}.png"));
        }
        $this->postJson('/csp/report', self::report('https://x.test/b.png'));

        $this->assertSame(2, CspViolation::query()->count());
        $this->assertSame(2, CspViolation::query()->where('blocked', 'https://x.test/b.png')->sole()->hits);
    }

    public function test_a_concurrent_insert_of_the_same_violation_is_folded_not_a_500(): void
    {
        $report = ViolationReport::fromPayload(self::LEGACY)[0];
        $raced = false;

        // Another request inserts the same violation right after our first query.
        DB::listen(function (QueryExecuted $query) use (&$raced, $report): void {
            if ($raced || !str_contains($query->sql, 'csp_violations')) {
                return;
            }

            $raced = true;
            DB::table('csp_violations')->insert([
                'fingerprint' => $report->fingerprint(), 'directive' => $report->directive, 'blocked' => $report->blocked,
                'document' => $report->document, 'disposition' => 'report', 'hits' => 1,
                'first_seen_at' => now(), 'last_seen_at' => now(),
            ]);
        });

        $row = CspViolation::record($report, 'UA');

        $this->assertTrue($raced);
        $this->assertSame(2, $row->hits);
        $this->assertSame(1, CspViolation::query()->count());
    }

    public function test_null_storage_keeps_nothing(): void
    {
        config(['csp-nonce.report.storage' => null]);

        $this->postJson('/csp/report', self::LEGACY)->assertNoContent();

        $this->assertSame(0, CspViolation::query()->count());
    }

    public function test_log_storage_logs_a_violation_once_per_window(): void
    {
        $file = sys_get_temp_dir().'/csp-test-'.uniqid().'.log';
        config([
            'csp-nonce.report.storage' => 'log',
            'csp-nonce.report.log_channel' => 'csp-test',
            'logging.channels.csp-test' => ['driver' => 'single', 'path' => $file],
        ]);

        $this->postJson('/csp/report', self::LEGACY);
        $this->postJson('/csp/report', self::LEGACY);

        $this->assertSame(1, substr_count((string) file_get_contents($file), 'CSP violation'));
        unlink($file);
    }

    public function test_prune_rejects_a_non_numeric_days_option(): void
    {
        $this->postJson('/csp/report', self::LEGACY);

        $this->artisan('csp:prune', ['--days' => 'abc'])->assertFailed();
        $this->artisan('csp:prune', ['--days' => '0'])->assertFailed();

        $this->assertSame(1, CspViolation::query()->count());
    }

    public function test_prune_without_the_table_is_a_no_op(): void
    {
        Schema::drop('csp_violations');

        $this->artisan('csp:prune')->assertSuccessful();
    }

    public function test_prune_is_scheduled_only_for_database_storage(): void
    {
        $events = fn (): int => collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'csp:prune'))
            ->count();

        $this->assertSame(1, $events());

        config(['csp-nonce.report.storage' => 'log']);
        $this->app->forgetInstance(Schedule::class);

        $this->assertSame(0, $events());
    }

    public function test_log_storage_rejected_reports_leave_no_marker_and_are_logged_later(): void
    {
        $file = sys_get_temp_dir().'/csp-test-'.uniqid().'.log';
        config([
            'csp-nonce.report.storage' => 'log',
            'csp-nonce.report.max_new_per_minute' => 1,
            'csp-nonce.report.log_channel' => 'csp-test',
            'logging.channels.csp-test' => ['driver' => 'single', 'path' => $file],
        ]);

        $this->postJson('/csp/report', self::report('https://x.test/a.png'));
        $this->postJson('/csp/report', self::report('https://x.test/b.png'));

        $rejected = ViolationReport::fromPayload(self::report('https://x.test/b.png'))[0];
        $this->assertFalse(Cache::has('csp-nonce:logged:'.$rejected->fingerprint()));
        $this->assertSame(1, substr_count((string) file_get_contents($file), 'CSP violation'));

        $this->travel(2)->minutes();
        $this->postJson('/csp/report', self::report('https://x.test/b.png'));

        $this->assertSame(2, substr_count((string) file_get_contents($file), 'CSP violation'));
        unlink($file);
    }

    public function test_out_of_range_line_and_column_are_dropped(): void
    {
        $body = self::LEGACY;
        $body['csp-report']['line-number'] = 1e12;
        $body['csp-report']['column-number'] = -5;

        $report = ViolationReport::fromPayload($body)[0];
        $this->assertNull($report->line);
        $this->assertNull($report->column);

        $body['csp-report']['line-number'] = '4294967295';
        $this->assertSame(4294967295, ViolationReport::fromPayload($body)[0]->line);

        $this->postJson('/csp/report', $body)->assertNoContent();
        $this->assertNull(CspViolation::query()->sole()->column);
    }

    public function test_prune_schedule_accepts_cron_and_ignores_invalid_values(): void
    {
        $prune = fn (): ?Event => collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'csp:prune'));

        foreach (['dayly', 'dailyAt', 'cron', '__construct', 'run'] as $invalid) {
            config(['csp-nonce.report.prune_schedule' => $invalid]);
            $this->app->forgetInstance(Schedule::class);

            $this->assertNull($prune(), $invalid);
        }

        config(['csp-nonce.report.prune_schedule' => '15 3 * * *']);
        $this->app->forgetInstance(Schedule::class);
        $this->assertSame('15 3 * * *', $prune()?->expression);

        config(['csp-nonce.report.prune_schedule' => 'hourly']);
        $this->app->forgetInstance(Schedule::class);
        $this->assertSame('0 * * * *', $prune()?->expression);
    }

    public function test_a_full_reporting_api_batch_is_recorded(): void
    {
        $policy = str_repeat("script-src 'self' 'nonce-abc' 'strict-dynamic'; ", 10);
        $batch = [];

        for ($i = 0; $i < 20; $i++) {
            $batch[] = [
                'type' => 'csp-violation',
                'url' => 'http://localhost/admin/page-'.$i,
                'user_agent' => str_repeat('Mozilla/5.0 ', 20),
                'body' => [
                    'effectiveDirective' => 'style-src-elem',
                    'blockedURL' => 'inline',
                    'documentURL' => 'http://localhost/admin/page-'.$i,
                    'originalPolicy' => $policy,
                    'sample' => str_repeat('x', 200),
                    'referrer' => 'http://localhost/admin',
                ],
            ];
        }

        $this->assertGreaterThan(16384, strlen((string) json_encode($batch)));

        $this->postJson('/csp/report', $batch)->assertNoContent();

        $this->assertSame(20, CspViolation::query()->count());
    }
}

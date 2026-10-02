<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Support;

use Asignua\FilamentCspNonce\Models\CspViolation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The report endpoint is public, so every field of a report is attacker-controlled.
 * The limits below protect STORAGE, not report completeness: NEW violations (rows
 * or log lines) are capped per minute across all clients and the table has a hard
 * row cap, so a flood of forged reports can crowd out genuine ones but cannot grow
 * the table or the log without bound. Repeat hits of a known violation only bump a
 * counter and are never limited. Reports about documents on other hosts are
 * dropped as a cheap filter against misrouted reports, not as authentication.
 */
final class ViolationRecorder
{
    private const LIMITER_KEY = 'csp-nonce:new-violations';

    /**
     * @param list<ViolationReport> $reports
     */
    public function record(array $reports, ?string $userAgent, ?string $requestHost = null): void
    {
        $storage = config('csp-nonce.report.storage', 'log');

        foreach ($reports as $report) {
            if (!$this->isOwnDocument($report, $requestHost)) {
                continue;
            }

            match ($storage) {
                'database' => $this->store($report, $userAgent),
                'log' => $this->log($report),
                default => null,
            };
        }
    }

    private function store(ViolationReport $report, ?string $userAgent): void
    {
        if (!CspViolation::query()->where('fingerprint', $report->fingerprint())->exists()) {
            $maxRows = (int) config('csp-nonce.report.max_rows', 10000);

            if ($maxRows > 0 && CspViolation::query()->count() >= $maxRows) {
                return;
            }

            if (!$this->allowNew()) {
                return;
            }
        }

        CspViolation::record($report, $userAgent);
    }

    private function log(ViolationReport $report): void
    {
        // The same violation is logged once per window, like a folded database row.
        // The limiter is checked BEFORE the marker is written: a rejected report must
        // neither create a cache entry (the database cache store never collects
        // expired rows, so attacker-chosen fingerprints would pile up there) nor
        // silence the same violation for an hour once the limiter frees up.
        $key = 'csp-nonce:logged:'.$report->fingerprint();

        if (Cache::has($key) || !$this->allowNew()) {
            return;
        }

        Cache::put($key, true, now()->addHour());

        $channel = config('csp-nonce.report.log_channel');

        Log::channel(is_string($channel) && $channel !== '' ? $channel : null)
            ->warning('CSP violation', $report->toArray());
    }

    private function allowNew(): bool
    {
        $max = (int) config('csp-nonce.report.max_new_per_minute', 100);

        if ($max <= 0) {
            return true;
        }

        if (RateLimiter::tooManyAttempts(self::LIMITER_KEY, $max)) {
            return false;
        }

        RateLimiter::hit(self::LIMITER_KEY, 60);

        return true;
    }

    /**
     * A browser only reports violations of documents it got from this app, so a
     * report about another host is misrouted (or forged) and is not kept. This is a
     * filter, not a defence: the request host comes from the Host header, which is
     * attacker-controlled unless the app trusts only known hosts (TrustHosts), and
     * random paths on the real host pass anyway.
     */
    private function isOwnDocument(ViolationReport $report, ?string $requestHost): bool
    {
        $host = parse_url($report->document, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return false;
        }

        $allowed = array_map('strtolower', array_filter([
            $requestHost,
            parse_url((string) config('app.url'), PHP_URL_HOST),
            ...array_values((array) config('csp-nonce.report.allowed_hosts', [])),
        ], is_string(...)));

        return in_array(strtolower($host), $allowed, true);
    }
}

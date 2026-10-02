<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Console;

use Asignua\FilamentCspNonce\Models\CspViolation;
use Illuminate\Console\Command;

final class PruneViolationsCommand extends Command
{
    protected $signature = 'csp:prune {--days= : Keep this many days (default: csp-nonce.report.retention_days)}';

    protected $description = 'Delete CSP violation rows not seen for a while';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('csp-nonce.report.retention_days', 30));

        $deleted = CspViolation::query()->where('last_seen_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} CSP violation row(s).");

        return self::SUCCESS;
    }
}

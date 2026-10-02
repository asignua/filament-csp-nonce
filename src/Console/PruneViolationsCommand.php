<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Console;

use Asignua\FilamentCspNonce\Models\CspViolation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class PruneViolationsCommand extends Command
{
    protected $signature = 'csp:prune {--days= : Keep this many days (default: csp-nonce.report.retention_days)}';

    protected $description = 'Delete CSP violation rows not seen for a while';

    public function handle(): int
    {
        $raw = $this->option('days') ?? config('csp-nonce.report.retention_days', 30);
        $days = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($days === false) {
            $this->error('--days (or csp-nonce.report.retention_days) must be a positive integer.');

            return self::FAILURE;
        }

        $table = (new CspViolation)->getTable();

        if (!Schema::hasTable($table)) {
            $this->info("Table {$table} does not exist (report.storage is not 'database'?): nothing to prune.");

            return self::SUCCESS;
        }

        $deleted = CspViolation::query()->where('last_seen_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} CSP violation row(s).");

        return self::SUCCESS;
    }
}

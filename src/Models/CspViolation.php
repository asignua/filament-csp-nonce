<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Models;

use Asignua\FilamentCspNonce\Support\ViolationReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $fingerprint
 * @property string $directive
 * @property string $blocked
 * @property string $document
 * @property string|null $source
 * @property int|null $line
 * @property int|null $column
 * @property string|null $sample
 * @property string $disposition
 * @property string|null $user_agent
 * @property int $hits
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 */
class CspViolation extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    public function getTable(): string
    {
        return (string) config('csp-nonce.report.table', 'csp_violations');
    }

    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'column' => 'integer',
            'hits' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Identical violations are folded into one row with a hit counter. The write is
     * race-safe: concurrent reports of the same new violation do not collide on the
     * unique fingerprint, and hits are incremented atomically in SQL. How many
     * DISTINCT violations get stored is limited by ViolationRecorder.
     */
    public static function record(ViolationReport $report, ?string $userAgent): self
    {
        $fingerprint = $report->fingerprint();
        $now = now();

        if (self::bump($fingerprint, $now) === 0) {
            $inserted = self::query()->insertOrIgnore([
                'fingerprint' => $fingerprint,
                'directive' => $report->directive,
                'blocked' => $report->blocked,
                'document' => $report->document,
                'source' => $report->source,
                'line' => $report->line,
                'column' => $report->column,
                'sample' => $report->sample,
                'disposition' => $report->disposition,
                'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 250, ''),
                'hits' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);

            // Another request inserted the same violation in between.
            if ($inserted === 0) {
                self::bump($fingerprint, $now);
            }
        }

        return self::query()->where('fingerprint', $fingerprint)->firstOrFail();
    }

    private static function bump(string $fingerprint, Carbon $now): int
    {
        return self::query()->where('fingerprint', $fingerprint)->increment('hits', 1, ['last_seen_at' => $now]);
    }
}

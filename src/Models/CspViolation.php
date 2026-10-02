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
     * Identical violations are folded into one row with a hit counter, so a
     * page that violates on every load cannot grow the table without bound.
     */
    public static function record(ViolationReport $report, ?string $userAgent): self
    {
        $violation = self::query()->where('fingerprint', $report->fingerprint())->first();

        if ($violation === null) {
            $violation = new self;
            $violation->fingerprint = $report->fingerprint();
            $violation->directive = $report->directive;
            $violation->blocked = $report->blocked;
            $violation->document = $report->document;
            $violation->source = $report->source;
            $violation->line = $report->line;
            $violation->column = $report->column;
            $violation->sample = $report->sample;
            $violation->disposition = $report->disposition;
            $violation->user_agent = $userAgent === null ? null : Str::limit($userAgent, 250, '');
            $violation->hits = 0;
            $violation->first_seen_at = now();
        }

        $violation->hits++;
        $violation->last_seen_at = now();
        $violation->save();

        return $violation;
    }
}

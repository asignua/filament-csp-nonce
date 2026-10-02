<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Support;

use Asignua\FilamentCspNonce\Models\CspViolation;
use Illuminate\Support\Facades\Log;

final class ViolationRecorder
{
    /**
     * @param list<ViolationReport> $reports
     */
    public function record(array $reports, ?string $userAgent): void
    {
        $storage = config('csp-nonce.report.storage', 'log');

        foreach ($reports as $report) {
            match ($storage) {
                'database' => CspViolation::record($report, $userAgent),
                'log' => $this->log($report),
                default => null,
            };
        }
    }

    private function log(ViolationReport $report): void
    {
        $channel = config('csp-nonce.report.log_channel');

        Log::channel(is_string($channel) && $channel !== '' ? $channel : null)
            ->warning('CSP violation', $report->toArray());
    }
}

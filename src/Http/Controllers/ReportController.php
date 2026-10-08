<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Http\Controllers;

use Asignua\FilamentCspNonce\Support\ViolationRecorder;
use Asignua\FilamentCspNonce\Support\ViolationReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ReportController
{
    /**
     * Sized for a full Reporting API batch (ViolationReport keeps 20 entries, each about 1-2 KB
     * with the originalPolicy): a smaller cap would drop the WHOLE batch with a 204 the browser
     * takes as delivered, and nothing would be logged.
     */
    private const MAX_BYTES = 131072;

    public function __invoke(Request $request, ViolationRecorder $recorder): Response
    {
        $raw = $request->getContent();

        if (strlen($raw) <= self::MAX_BYTES) {
            $payload = json_decode($raw, true);

            if (is_array($payload)) {
                $recorder->record(ViolationReport::fromPayload($payload), $request->userAgent(), $request->getHost());
            }
        }

        return response()->noContent();
    }
}

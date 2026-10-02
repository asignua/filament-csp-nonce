<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Http\Controllers;

use Asignua\FilamentCspNonce\Support\ViolationRecorder;
use Asignua\FilamentCspNonce\Support\ViolationReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ReportController
{
    private const MAX_BYTES = 16384;

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

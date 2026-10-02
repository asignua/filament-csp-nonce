<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Http\Middleware;

use Asignua\FilamentCspNonce\Nonce;
use Asignua\FilamentCspNonce\PolicyRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: `csp.nonce` (default policy) or `csp.nonce:admin` (policy of panel "admin").
 */
final class CspNonce
{
    public const ENFORCE = 'Content-Security-Policy';

    public const REPORT_ONLY = 'Content-Security-Policy-Report-Only';

    public function __construct(private readonly PolicyRegistry $registry) {}

    public function handle(Request $request, Closure $next, ?string $panelId = null): Response
    {
        if (!config('csp-nonce.enabled', true)) {
            return $next($request);
        }

        $nonce = Nonce::generate();

        /** @var Response $response */
        $response = $next($request);

        $header = $this->registry->isReportOnly($panelId) ? self::REPORT_ONLY : self::ENFORCE;

        if (!$response->headers->has($header)) {
            $response->headers->set($header, $this->registry->policyFor($panelId)->render($nonce));
        }

        if (config('csp-nonce.report.enabled', true) && !$response->headers->has('Reporting-Endpoints')) {
            $path = '/'.ltrim((string) config('csp-nonce.report.path', 'csp/report'), '/');
            $response->headers->set('Reporting-Endpoints', 'csp-endpoint="'.$path.'"');
        }

        return $response;
    }
}

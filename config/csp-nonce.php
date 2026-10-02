<?php

declare(strict_types=1);

use Asignua\FilamentCspNonce\Enums\Preset;

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When false the middleware does nothing and the Blade rewriter is not
    | registered. Views compiled while it was on keep working: the nonce
    | attribute they print is simply empty.
    |
    */
    'enabled' => env('CSP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Report-only
    |--------------------------------------------------------------------------
    |
    | Send Content-Security-Policy-Report-Only instead of the enforcing header.
    | Start here: browse the panel for a while, read the violation reports, then
    | switch it off.
    |
    */
    'report_only' => env('CSP_REPORT_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Policy
    |--------------------------------------------------------------------------
    |
    | "preset" is the base policy (see Asignua\FilamentCspNonce\Enums\Preset).
    | "directives" is merged over it: a list replaces that directive, null
    | removes it. The token '{nonce}' becomes 'nonce-<per-request value>'.
    |
    | 'directives' => [
    |     'connect-src' => ["'self'", 'wss://ws.example.com'],
    |     'img-src' => ["'self'", 'data:', 'https://cdn.example.com'],
    |     'upgrade-insecure-requests' => [],
    |     'frame-ancestors' => null,
    | ],
    |
    */
    'preset' => Preset::StrictDynamic,

    'directives' => [],

    /*
    |--------------------------------------------------------------------------
    | Violation reports
    |--------------------------------------------------------------------------
    |
    | The package exposes one POST endpoint (no session, no CSRF) that accepts
    | both the legacy "report-uri" body and the Reporting API "report-to" body.
    |
    | storage: 'log' (default), 'database' (publish the migration) or null.
    |
    */
    'report' => [
        'enabled' => env('CSP_REPORT_ENABLED', true),
        'path' => 'csp/report',
        'throttle' => '60,1',
        'storage' => env('CSP_REPORT_STORAGE', 'log'),
        'log_channel' => env('CSP_REPORT_LOG_CHANNEL'),
        'table' => 'csp_violations',
        'retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Blade rewriter
    |--------------------------------------------------------------------------
    |
    | Filament prints a handful of inline <script> and <style> tags without a
    | nonce. At Blade COMPILE time (so only on trusted template source, never on
    | rendered output) the package adds the nonce attribute to every such tag in
    | the template files of the Composer packages matching "packages" (fnmatch
    | patterns) and of the extra directories in "paths". Run
    | `php artisan view:clear` once after enabling it.
    |
    | Add third-party Filament plugins that print bare tags here, e.g.
    | 'packages' => ['filament/*', 'awcodes/*'].
    |
    */
    'blade' => [
        'rewrite' => true,
        'packages' => ['filament/*'],
        'paths' => [],
    ],
];

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
    |     'style-src' => ["'self'", '{nonce}', 'https://fonts.bunny.net'], // Filament ->font() uses Bunny Fonts
    |     'font-src' => ["'self'", 'data:', 'https://fonts.bunny.net'],
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
    | The endpoint is public, so every report field is attacker-controlled. The
    | limits protect storage, not report completeness: forged reports can crowd
    | out genuine ones.
    | - reports about a document on another host are dropped (the request host
    |   and the host of app.url are allowed, plus "allowed_hosts"). A filter, not
    |   a defence: the request host is the Host header unless TrustHosts is on;
    | - at most "max_new_per_minute" NEW violations (rows or log lines) are kept
    |   per minute across all clients; repeats of a known one only bump its
    |   counter (database) or are logged once per hour (log);
    | - the table never holds more than "max_rows" rows (0 = no cap);
    | - "throttle" limits requests per IP. Firefox POSTs once per violation, so
    |   a busy page under report-only sends many; behind a proxy without
    |   TrustProxies every user shares one IP. Excess requests get 429 and
    |   their reports are lost. A Reporting API batch is cut at 20 entries and the
    |   body at 128 KB.
    | - "prune_schedule" registers `csp:prune` in the scheduler when storage is
    |   'database': a parameterless frequency method such as 'daily' or a cron
    |   expression such as '15 3 * * *'; an invalid value is logged and skipped;
    |   null = off.
    |   The scheduler itself (`schedule:run` in cron) is up to you.
    |
    */
    'report' => [
        'enabled' => env('CSP_REPORT_ENABLED', true),
        'path' => 'csp/report',
        'throttle' => '300,1',
        'storage' => env('CSP_REPORT_STORAGE', 'log'),
        'log_channel' => env('CSP_REPORT_LOG_CHANNEL'),
        'table' => 'csp_violations',
        'retention_days' => 30,
        'allowed_hosts' => [],
        'max_new_per_minute' => 100,
        'max_rows' => 10000,
        'prune_schedule' => 'daily',
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
    | Published overrides of Filament/plugin views (resources/views/vendor/...)
    | are not inside the package install paths: add their directory to "paths",
    | e.g. [resource_path('views/vendor')]. The framework's error views and
    | resources/views/errors are always included.
    |
    | Tags inside @verbatim, @php ... @endphp and <?php ... ?> are left alone.
    |
    */
    'blade' => [
        'rewrite' => true,
        'packages' => ['filament/*'],
        'paths' => [],
    ],
];

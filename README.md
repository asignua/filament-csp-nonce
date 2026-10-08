# Filament CSP Nonce

[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://stand-with-ukraine.pp.ua)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/asignua/filament-csp-nonce.svg?style=flat-square)](https://packagist.org/packages/asignua/filament-csp-nonce)
[![Tests](https://img.shields.io/github/actions/workflow/status/asignua/filament-csp-nonce/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/asignua/filament-csp-nonce/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/asignua/filament-csp-nonce.svg?style=flat-square)](https://packagist.org/packages/asignua/filament-csp-nonce)
[![License](https://img.shields.io/packagist/l/asignua/filament-csp-nonce.svg?style=flat-square)](https://github.com/asignua/filament-csp-nonce/blob/main/LICENSE.md)
[![Plumb score](https://plumbphp.dev/badges/asignua/filament-csp-nonce/composite.svg)](https://plumbphp.dev/asignua/filament-csp-nonce)

<img class="filament-hidden" src="https://raw.githubusercontent.com/asignua/filament-csp-nonce/v1.0.0/art/cover.jpg" alt="Filament CSP Nonce">

A per-request CSP nonce, a ready `Content-Security-Policy` header and a violation report endpoint for
[Filament](https://filamentphp.com) panels, **without overriding a single Filament view**.

Without nonces, a Content-Security-Policy for Filament needs `'unsafe-inline'` for scripts, which defeats the point
([filamentphp/filament#7032](https://github.com/filamentphp/filament/discussions/7032),
[#8329](https://github.com/filamentphp/filament/discussions/8329)). Filament and Livewire already print the nonce on
their asset tags when Laravel's `Vite::useCspNonce()` is set, but a handful of Filament's own inline `<script>` and
`<style>` tags have none. This package sets the nonce, closes those gaps and sends the header.

> **Read [What this does and does not protect](#what-this-does-and-does-not-protect) before relying on it.**
> Filament needs `'unsafe-eval'`. That is a property of Alpine, not of this package.

- [Screenshots](#screenshots)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [What this does and does not protect](#what-this-does-and-does-not-protect)
- [Configuration](#configuration)
- [Gotchas](#gotchas)
- [Uninstalling](#uninstalling)
- [Translations](#translations)
- [AI agents](#ai-agents)
- [Testing](#testing)

## Screenshots

The `Content-Security-Policy` header the plugin sends for a panel page (default `strict-dynamic` preset, captured from the Testbench workbench; the nonce is different on every request). All 18 `<script>`/`<style>` tags of the list page carry it.

![Response headers with the strict-dynamic policy](https://raw.githubusercontent.com/asignua/filament-csp-nonce/v1.0.0/art/headers.jpg)

## Requirements

- PHP 8.3+, Laravel 12 or 13, Filament 5 (tested on 5.9, Livewire 4.4).

## Installation

```bash
composer require asignua/filament-csp-nonce
php artisan vendor:publish --tag=csp-nonce-config            # optional
php artisan vendor:publish --tag=csp-nonce-migrations         # only for database report storage
php artisan view:clear                                        # once: the Blade rewriter works at compile time
```

No assets to publish (`filament:assets` is not needed: the package ships no CSS or JS).

## Usage

```php
use Asignua\FilamentCspNonce\CspNoncePlugin;

$panel->plugin(
    CspNoncePlugin::make()
        ->reportOnly(),            // start here, look at the reports, then remove it
);
```

Everything is optional:

```php
CspNoncePlugin::make()
    ->preset(Preset::Compatible)                       // or Preset::StrictDynamic (default)
    ->directives([                                     // merged over the preset; null removes a directive
        'connect-src' => ["'self'", 'wss://ws.example.com'],
        'img-src' => ["'self'", 'data:', 'https://cdn.example.com'],
    ])
    ->allowInlineStyles()                              // ColorPicker, CodeEditor (see Gotchas)
    ->policy(fn (CspPolicy $preset) => $preset->directive('frame-src', ["'self'", 'https://www.youtube.com']));
```

Outside panels (your public pages), use the middleware alias and the helpers:

```php
Route::middleware('csp.nonce')->group(...);   // csp.nonce:admin uses the policy of panel "admin"
```

```blade
<script @cspNonce>...</script>                {{-- nonce="..." --}}
{{ csp_nonce() }}                             {{-- the raw value, or null --}}
```

### Presets

| Preset | `script-src` | `style-src` |
| --- | --- | --- |
| `filament-strict-dynamic` (default) | `'nonce-…' 'strict-dynamic' 'unsafe-eval'` | `'self' 'nonce-…'` + `style-src-attr 'unsafe-inline'` |
| `filament-compatible` | `'self' 'nonce-…' 'unsafe-eval'` (same-origin scripts keep working, e.g. third-party plugin assets) | `'self' 'unsafe-inline'` |

Both add `default-src 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`,
`frame-ancestors 'self'`, `img-src 'self' data: blob: https:`, `font-src 'self' data:`, `connect-src 'self'`,
`media-src 'self' blob: data:`, `worker-src 'self' blob:`, `report-uri` and `report-to`.

`->directives()` and `->allowInlineStyles()` accumulate in any order; a later value for the same directive wins. A
`CspPolicy` instance passed to `->policy()` is cloned per request, so it can be shared between panels.

### Violation reports

`POST /csp/report` (no session, no CSRF, throttled) accepts both `report-uri` and Reporting API bodies, strips query
strings, caps the payload at 128 KB (a full Reporting API batch of 20 entries fits) and stores per `report.storage`: `log` (default), `database` (publish the migration;
identical violations fold into one row with a hit counter; prune with `php artisan csp:prune`) or `null`.

The endpoint is public and unauthenticated, so every report field is attacker-controlled. The limits below protect
**storage** (the table, the log, the cache), not report completeness: a flood of forged reports can use up the
per-minute budget or fill the row cap and crowd out genuine violations, so treat a report-only rollout as a hint, not
as proof that nothing breaks.

- reports about a document on another host are dropped (allowed: the request host, the host of `app.url`,
  `report.allowed_hosts`). This filters misrouted reports, it is not a defence: the request host comes from the `Host`
  header unless the app trusts only known hosts (`TrustHosts`), and random paths on the real host pass anyway;
- at most `report.max_new_per_minute` (100) new violations are stored or logged per minute across all clients;
  repeats of a known violation only bump its counter (database) or are logged once per hour (log);
- the table holds at most `report.max_rows` (10 000) rows;
- `report.throttle` (`300,1`) limits requests per IP. Firefox POSTs once per violation, so a busy page under
  report-only sends many, and behind a proxy without `TrustProxies` all users share one IP: raise it if reports go
  missing (429). A Reporting API batch is cut at 20 entries.
- with `database` storage `csp:prune` is registered in the scheduler (`report.prune_schedule`, `daily`: a
  parameterless frequency method such as `hourly`/`weekly`, or a cron expression such as `15 3 * * *`; anything else
  is logged as a warning and not scheduled; `null` turns it off). You still need `schedule:run` in cron.

There is no UI for stored violations: query the table (or build a Filament resource on
`Asignua\FilamentCspNonce\Models\CspViolation`).

## What this does and does not protect

What you get with the default preset (measured in a real browser against Filament 5.9, see
[FEASIBILITY.md](FEASIBILITY.md)):

- No inline script runs unless it carries this request's nonce: injected `<script>` tags, `onerror=`-style handlers
  and `javascript:` URLs are blocked. No host allow-list to bypass (`strict-dynamic`).
- `object-src`, `base-uri`, `form-action` and `frame-ancestors` locked down.
- Style elements are nonce-only; violation reports tell you what else a page needs.

What you do **not** get:

- **`'unsafe-eval'` stays.** Alpine compiles every `x-*` expression with `new Function`, and Filament's templates use
  full JavaScript in them. Livewire 4's CSP-safe build (`livewire.csp_safe`) was tried: Alpine's CSP parser rejects
  Filament's expressions and the panel stops working. Consequence: an attacker who can inject **HTML** into a region
  Alpine initialises can still run JavaScript via an `x-init`/`x-on:*` attribute. Keep escaping output; treat this as
  defence in depth, not as a replacement for it.
- **`style="..."` attributes stay allowed** (`style-src-attr 'unsafe-inline'`): Filament renders up to ~20 per page and
  CSS cannot be nonced on attributes.

## Configuration

`config/csp-nonce.php`: `enabled` (env `CSP_ENABLED`), `report_only` (`CSP_REPORT_ONLY`), `preset`, `directives`,
`report.*` (`enabled`, `path`, `throttle`, `storage`, `log_channel`, `table`, `retention_days`, `allowed_hosts`,
`max_new_per_minute`, `max_rows`, `prune_schedule`) and `blade.*`
(`rewrite`, `packages` fnmatch patterns of Composer packages whose templates get nonces, `paths`).

Third-party Filament plugins that print bare `<script>`/`<style>` tags: add their package to `blade.packages`
(`['filament/*', 'awcodes/*']`) and run `php artisan view:clear`.

## Gotchas

- **`php artisan view:clear` after installing or changing `blade.*`.** The rewriter runs when a view is compiled;
  already-compiled views keep their old tags.
- **The rewriter only touches template source**, never rendered output, so HTML injected by a user does not receive a
  nonce. Tags inside `@verbatim`, `@php ... @endphp` and `<?php ... ?>` are skipped (Blade does not compile them).
  A tag that already prints a nonce is left alone: a `nonce` / `:nonce` / `x-bind:nonce` attribute, `@cspNonce`, or a
  standalone Blade echo mentioning a nonce. The word anywhere else (a `src` path, `data-nonce-*`, an `x-data`
  expression, a Blade echo inside another attribute's value) does not count.
- **Error pages** (403/404/419/500) rendered inside a panel carry the policy too, so the framework's error views and
  the host's `resources/views/errors` are rewritten automatically. Laravel's debug exception page (`APP_DEBUG=true`)
  prints inline `<style>`/`<script>` from PHP and cannot be rewritten: inside a panel it is blocked by the policy. Use
  `->reportOnly()` locally or read the log.
- **Published overrides of Filament/plugin views** (`resources/views/vendor/filament*`) are outside the package
  install paths and are NOT rewritten: add their directory to `blade.paths` (`[resource_path('views/vendor')]`) and run
  `php artisan view:clear`, or the dark-mode bootstrap and `x-cloak`/colour `<style>` blocks ship without a nonce.
- **Another CSP package next to this one** (spatie/laravel-csp, a hand-written middleware): run only one nonce
  producer. The header follows whatever nonce `Vite::useCspNonce()` holds when the response leaves this middleware, so
  a producer *inside* it is fine; one *outside* it gets its Vite nonce overwritten, and its own header no longer
  matches the tags. An existing header of the same name is never replaced.
- **ColorPicker and CodeEditor inject a nonce-less `<style>` at runtime** (CodeMirror, Pickr). They look broken under
  the strict preset until you call `->allowInlineStyles()` (or whitelist the style hashes from your reports). The rich
  editor works: its Tiptap CSS is printed server-side (`->tiptapStyle(false)` disables it).
- **Websockets (Echo/Reverb), CDNs, maps, Google Fonts** need entries in `connect-src` / `img-src` / `font-src` /
  `style-src`; run `->reportOnly()` first and read the reports. Filament's own `->font('Poppins')` loads **Bunny
  Fonts** unless you pass `provider: LocalFontProvider::class`; add `https://fonts.bunny.net` to `style-src` and
  `font-src`, otherwise the panel falls back to system fonts.
- **Reporting URL and base path.** `report-uri` and `Reporting-Endpoints` include the app's base path (an install in a
  subdirectory). Your own `report-uri`/`report-to` replaces the defaults entirely (`null` removes it); a custom
  `report-to` group needs your own `Reporting-Endpoints` header.
- **Nonces are per request.** Do not cache full HTML responses (CDN page cache, `Cache::remember` of rendered views)
  together with the header.
- **Do not re-add `'unsafe-inline'` to `script-src` next to a nonce:** browsers then ignore `'unsafe-inline'`, but it
  signals the policy was copied from somewhere that needed it.
- After a Filament upgrade, `TiptapStyleTest` fails if Tiptap's bundled CSS changed: copy it into
  `resources/css/tiptap-core.css`.

## Uninstalling

Compiled views reference `\Asignua\FilamentCspNonce\Nonce`. Run `php artisan view:clear` right after removing the
package, or every panel page fails with "class not found".

## Translations

The package has no UI strings, so no language files.

## AI agents

Laravel Boost guidelines ship in `resources/boost/guidelines/core.blade.php`.

## Testing

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

`browser/audit.mjs` is a manual, real-browser audit (puppeteer-core + Edge/Chrome) against the workbench; it is how
the claims above were measured.

## Changelog

See [CHANGELOG](https://github.com/asignua/filament-csp-nonce/blob/main/CHANGELOG.md).

## License

MIT. See [LICENSE](https://github.com/asignua/filament-csp-nonce/blob/main/LICENSE.md).

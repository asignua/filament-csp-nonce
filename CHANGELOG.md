# Changelog

## Unreleased

- Fix: the report body cap is 128 KB (was 16 KB), so a full 20-entry Reporting API batch is recorded instead of being dropped whole with a 204.
- Fix: error pages (framework views and `resources/views/errors`) are rewritten by default, so panel 403/404/419/500 pages keep their inline styles under the nonce policy.
- Fix: a Blade echo inside another attribute's value (`src="{{ asset('nonce.js') }}"`) no longer counts as an existing nonce.
- Fix: a user-defined `report-to` group is no longer overwritten, and `report-uri`/`report-to` set to `null` stay removed.
- Fix: `report-uri` and `Reporting-Endpoints` include the app's base path (subdirectory installs).
- Docs: published Filament view overrides need `blade.paths`; Filament's `->font()` loads Bunny Fonts; the debug exception page is blocked inside a panel.

## v1.0.0 - 2026-10-05

- Per-request nonce middleware (`CspNonce`, alias `csp.nonce`) feeding `Vite::useCspNonce()`. The header nonce is
  read after the inner stack, so it matches a nonce set further down.
- `CspPolicy` builder, presets `filament-strict-dynamic` and `filament-compatible`, per-panel `CspNoncePlugin`.
  `directives()` merges into the preset (keeping `allowInlineStyles()` and earlier calls); a `CspPolicy` passed to
  `->policy()` is cloned, never mutated.
- Blade compile-time rewriter adding the nonce to Filament's bare `<script>`/`<style>` tags. It skips `@verbatim`,
  `@php ... @endphp` and raw PHP regions, and a tag that already prints a nonce (a `nonce` attribute, `@cspNonce`, a
  Blade echo mentioning a nonce); the word elsewhere (a `src` path, `data-nonce-*`, an `x-data` value) does not count.
- Server-side Tiptap core CSS so the rich editor works under a nonce-based `style-src`.
- Violation report endpoint (legacy `report-uri` and Reporting API), log or database storage, `csp:prune`.
- `@cspNonce` directive and `csp_nonce()` helper.
- Report endpoint hardening (security): reports about documents on foreign hosts are dropped (the host check relies on
  `TrustHosts`); new violations are capped per minute (`report.max_new_per_minute`) and the table by
  `report.max_rows`; log storage logs a violation once per hour and checks the limiter before writing its cache
  marker; default throttle `300,1`. The caps protect storage, not report completeness.
- Identical violations are folded race-safely (`insertOrIgnore` + atomic increment), so concurrent reports are counted
  once each. Line/column numbers outside the unsigned 32-bit range are stored as `null`.
- `csp:prune` is scheduled for database storage (`report.prune_schedule`: a parameterless frequency method or a cron
  expression; an invalid value is logged and skipped). It rejects a non-positive or non-numeric `--days` and is a no-op
  without the table.

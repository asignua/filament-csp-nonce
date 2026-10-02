# Changelog

## v1.0.0 - unreleased

- Per-request nonce middleware (`CspNonce`, alias `csp.nonce`) feeding `Vite::useCspNonce()`.
- `CspPolicy` builder, presets `filament-strict-dynamic` and `filament-compatible`, per-panel `CspNoncePlugin`.
- Blade compile-time rewriter adding the nonce to Filament's bare `<script>`/`<style>` tags.
- Server-side Tiptap core CSS so the rich editor works under a nonce-based `style-src`.
- Violation report endpoint (legacy `report-uri` and Reporting API), log or database storage, `csp:prune`.
- `@cspNonce` directive and `csp_nonce()` helper.
- Report endpoint hardening (security): reports about documents on foreign hosts are dropped; new violations are
  capped per minute (`report.max_new_per_minute`) and the table by `report.max_rows`; log storage logs a violation
  once per hour; `csp:prune` is scheduled for database storage (`report.prune_schedule`); default throttle `300,1`.
  `ViolationRecorder::record()` takes the request host as an optional third argument.
- Race-safe violation folding (`insertOrIgnore` + atomic increment): concurrent identical reports no longer 500.
- `directives()` merges instead of replacing, so it no longer discards `allowInlineStyles()` or an earlier call.
- A `CspPolicy` passed to `->policy()` is cloned, never mutated.
- The header nonce is read after the inner stack, so it matches a nonce set further down.
- The Blade rewriter skips `@verbatim`, `@php ... @endphp` and raw PHP regions.
- Log storage checks the new-violation limiter before writing its once-per-hour cache marker, so rejected reports
  create no cache entries and a violation rejected during a flood is logged once the limiter frees up.
- Line/column numbers outside the unsigned 32-bit range are dropped instead of failing the insert.
- `report.prune_schedule` accepts a parameterless frequency method or a cron expression; an invalid value is logged
  and skipped instead of breaking `schedule:run`.
- The README and config state that the report caps protect storage, not report completeness, and that the host check
  relies on `TrustHosts`.
- `csp:prune` rejects a non-positive or non-numeric `--days` and is a no-op without the table.

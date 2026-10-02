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
- `csp:prune` rejects a non-positive or non-numeric `--days` and is a no-op without the table.

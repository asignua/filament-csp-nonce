# Changelog

## v1.0.0 - unreleased

- Per-request nonce middleware (`CspNonce`, alias `csp.nonce`) feeding `Vite::useCspNonce()`.
- `CspPolicy` builder, presets `filament-strict-dynamic` and `filament-compatible`, per-panel `CspNoncePlugin`.
- Blade compile-time rewriter adding the nonce to Filament's bare `<script>`/`<style>` tags.
- Server-side Tiptap core CSS so the rich editor works under a nonce-based `style-src`.
- Violation report endpoint (legacy `report-uri` and Reporting API), log or database storage, `csp:prune`.
- `@cspNonce` directive and `csp_nonce()` helper.

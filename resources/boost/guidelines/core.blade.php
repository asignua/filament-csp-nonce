## Filament CSP Nonce (asignua/filament-csp-nonce)

- Register `Asignua\FilamentCspNonce\CspNoncePlugin::make()` on a panel: it adds the `CspNonce` middleware (per-request nonce -> `Vite::useCspNonce()`, which Filament and Livewire already read) and sends a `Content-Security-Policy` header built from a preset (`Preset::StrictDynamic` default, `Preset::Compatible`).
- Honest limits: `'unsafe-eval'` is REQUIRED (Filament's Alpine expressions are arbitrary JS; Livewire's `csp_safe` build breaks the panel) and `style=""` attributes need `style-src-attr 'unsafe-inline'`. Do not "fix" this by removing them.
- Nonce attributes for Filament's own bare `<script>`/`<style>` tags come from a Blade COMPILE-time rewriter over `vendor/filament/*` templates (config `blade.packages`); run `php artisan view:clear` after enabling. Never add a nonce to rendered/user output.
- Components that inject nonce-less `<style>` at runtime (ColorPicker, CodeEditor) need `->allowInlineStyles()` on the plugin; check the violation log first.
- In app views use `@cspNonce` (prints `nonce="..."`) or `csp_nonce()`. Start with `->reportOnly()`; reports go to `POST /csp/report` (log or `csp_violations` table, config `report.storage`).
- Tiptap's core CSS is printed server-side (`TiptapStyle`); a test compares it with Filament's bundle, so refresh `resources/css/tiptap-core.css` when that test fails after a Filament upgrade.

# Feasibility: strict CSP for Filament 5

Tested against Filament **v5.9.0**, Livewire **v4.4.7**, Laravel **13.34** (Testbench workbench with a resource that
uses TextInput, Email, searchable Select, Toggle, DatePicker, RichEditor, MarkdownEditor, FileUpload, ColorPicker,
TagsInput, KeyValue, CodeEditor, a table with search and actions, a ChartWidget and a StatsOverview), driven in
headless Microsoft Edge (puppeteer-core, `securitypolicyviolation` listener + console capture). The script is
`browser/audit.mjs`. Everything below was measured, not assumed.

## Verdict

Build it, with honest claims. A meaningful CSP is achievable **without forking any Filament view**:

| Level | Achievable? | How / why not |
| --- | --- | --- |
| (a) Nonce on every `<script>`/`<style>` tag + `'strict-dynamic'`, no `'unsafe-inline'` for scripts | **Yes** | Filament and Livewire already honour `Vite::cspNonce()` for their asset tags. 15 bare tags in Filament's own views do not; a Blade compile-time rewriter covers them. 0 violations in the browser audit. |
| (b) No `'unsafe-eval'` | **No** | Standard Livewire/Alpine evaluates every `x-*` expression with `new Function`/AsyncFunction (confirmed: `livewire.js` contains it). Livewire 4 ships a CSP build (`livewire.csp.js`, `config('livewire.csp_safe')`), but Alpine's CSP build cannot parse Filament's templates: see below. |
| (c) `style-src` without `'unsafe-inline'` | **Partly** | Style *elements* can be nonced. Style *attributes* cannot: 7 to 19 `style="..."` per rendered page. Solution: `style-src-attr 'unsafe-inline'` (low risk: CSS cannot execute code) plus a nonce on `style-src`. Two components still inject nonce-less `<style>` at runtime (ColorPicker, CodeEditor): they need `style-src 'unsafe-inline'` (plugin option `allowInlineStyles()`). |

## Inventory (rendered pages: login, list, create, edit)

| Item | Count / finding | Covered by Laravel / Livewire / Filament? |
| --- | --- | --- |
| `<script>` tags | 11-12 per page | Filament `Js` assets, `window.filamentData` and Livewire scripts: **yes** (`Vite::cspNonce()`). Dark-mode bootstrap, `collapsedGroups`, `loadDarkMode()` calls: **no** |
| `<style>` tags | 5 per page | Livewire styles: **yes**. x-cloak rules, colour variables, font variables, theme block: **no** |
| `<link rel=stylesheet>` | 2 | Same-origin: `style-src 'self'` |
| `style="..."` attributes | 7 (list) to 19 (edit) | No, and cannot be nonced |
| Inline `on*=` handlers | 0 | n/a |
| `javascript:` URLs | 0 | n/a |
| Alpine expression attributes | 52 (login) to 211 (create) | Need `unsafe-eval` (see below) |
| Un-nonced `<script>`/`<style>` literals in `vendor/filament/*/resources/views` | 15 sites: layout/base (7), page/index (3), sidebar, notifications (2), unsaved-action-changes, support/assets | **No**: this is what the rewriter fixes |
| Runtime-injected `<style>` elements | Tiptap (rich editor), CodeMirror (code editor), colour picker | Tiptap: fixed server-side. The other two: not fixable without a fork, need `'unsafe-inline'` styles |

Livewire's `@livewireScripts`/`@livewireStyles` and `livewireScriptConfig` read `Vite::cspNonce()`. Livewire's JS even
rewrites the nonce inside HTML returned by update requests to the page nonce, so per-request nonces are safe with
`wire:navigate` and updates. `Vite::useCspNonce()` is therefore the single source of truth: the middleware only has
to set it.

## Why `'unsafe-eval'` cannot go (measured)

- With the strict policy minus `'unsafe-eval'` and standard Livewire: `EvalError` on every page, the login form never
  initialises (`input[type=password]` not even rendered), the panel is dead.
- With `livewire.csp_safe = true` (Alpine CSP build) **and** no `'unsafe-eval'`: 0 CSP violations but ~20 distinct
  `CSP Parser Error` / `Undefined variable` page errors (`(theme = 'light') && close()`, template literals, arrow
  functions, `JSON.parse(...)`, `new CustomEvent(...)`, inline `async` functions in `x-data`); selects and the rich
  editor do not render; the theme switcher is broken. Filament's templates are written in full JavaScript, the CSP
  build only supports property/method references.
- Consequence worth stating loudly: with `'unsafe-eval'` allowed, an attacker who can inject **HTML** (not a script
  tag) into a region Alpine initialises can run JS through `x-init`/`x-on:click` attributes. The policy still blocks
  injected `<script>` tags and inline event handlers (`onerror=`), `javascript:` URLs, third-party script origins,
  `<base>`/`<object>`/form-action hijacking, framing: but it is **not** a full XSS mitigation for HTML injection in
  the panel. README says so.

## Decision against "placebo"

The result is not a placebo: scripts need a nonce (no `unsafe-inline`), `strict-dynamic` drops host allow-lists,
and `object-src`/`base-uri`/`form-action`/`frame-ancestors` are real gains, plus violation reports. It is also not
"CSP solved": the limits above are documented in the README and in the policy presets' docblocks.

## Mechanism chosen (no view overrides)

1. `CspNonce` middleware: random nonce -> `Vite::useCspNonce()` -> header.
2. Blade `prepareStringsForCompilationUsing` hook, restricted to templates of Composer packages matching
   `filament/*`: adds ` {!! Nonce::attribute() !!}` to literal `<script`/`<style` tags of the template **source**.
   Rendered/user output is never touched, so an injected `<script>` does not get a nonce. Needs `view:clear` once.
   Compared with forking 6 Filament views: nothing to re-merge on Filament upgrades; it survives layout changes because
   it works on tags, not files.
3. Tiptap skips its runtime `<style>` injection when `style[data-tiptap-style]` already exists, so the same CSS (10
   lines of ProseMirror core CSS) is printed server-side with the nonce. A test compares it to Filament's bundle.

## Not verified

- The report endpoint receiving a real browser report: headless Edge did not POST `report-uri` reports during the
  audit (covered by feature tests with both wire formats instead).
- Third-party plugins, Echo/Reverb websockets (`connect-src` must list the ws host), maps, charts from CDN, Google
  Fonts (needs `style-src`/`font-src` entries): configure through `directives`.
- Components not in the workbench (e.g. Spatie media library, custom Alpine plugins) may inject runtime styles or
  scripts. Run in report-only first.

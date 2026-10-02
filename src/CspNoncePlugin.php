<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce;

use Asignua\FilamentCspNonce\Enums\Preset;
use Asignua\FilamentCspNonce\Http\Middleware\CspNonce;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

final class CspNoncePlugin implements Plugin
{
    private ?Preset $preset = null;

    private ?bool $reportOnly = null;

    /** @var array<string, list<string>|null> */
    private array $directives = [];

    private CspPolicy|Closure|null $policy = null;

    private bool $tiptapStyle = true;

    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'csp-nonce';
    }

    public function preset(Preset $preset): self
    {
        $this->preset = $preset;

        return $this;
    }

    public function reportOnly(bool $condition = true): self
    {
        $this->reportOnly = $condition;

        return $this;
    }

    /**
     * Overrides merged over the preset: a list replaces a directive, null removes it.
     * Repeated calls (and allowInlineStyles()) accumulate; a later value for the
     * same directive wins.
     *
     * @param array<string, list<string>|null> $directives
     */
    public function directives(array $directives): self
    {
        $this->directives = array_merge($this->directives, $directives);

        return $this;
    }

    /**
     * Allow 'unsafe-inline' for <style> elements. Needed when the panel uses
     * components whose JavaScript injects a nonce-less <style> at runtime: Filament's
     * ColorPicker and CodeEditor do (CodeMirror's style-mod, Pickr). Inline STYLES are
     * a much smaller risk than inline scripts; script-src stays untouched.
     */
    public function allowInlineStyles(): self
    {
        $this->directives['style-src'] = ["'self'", "'unsafe-inline'"];

        return $this;
    }

    /**
     * Replace the preset with your own policy, or a closure that receives the
     * preset policy and returns the one to use.
     */
    public function policy(CspPolicy|Closure $policy): self
    {
        $this->policy = $policy;

        return $this;
    }

    public function register(Panel $panel): void
    {
        app(PolicyRegistry::class)->registerPanel($panel->getId(), [
            'preset' => $this->preset,
            'reportOnly' => $this->reportOnly,
            'directives' => $this->directives,
            'policy' => $this->policy,
        ]);

        $panel->middleware([CspNonce::class.':'.$panel->getId()]);
    }

    /**
     * Tiptap (Filament's rich editor) injects its core CSS as a nonce-less <style>
     * at runtime, which a nonce-based style-src blocks. Tiptap skips the injection
     * when a `style[data-tiptap-style]` element already exists, so the same CSS is
     * printed once, server-side, with the nonce. Disable it if you do not use the
     * rich editor or you allow 'unsafe-inline' styles.
     */
    public function tiptapStyle(bool $condition = true): self
    {
        $this->tiptapStyle = $condition;

        return $this;
    }

    public function boot(Panel $panel): void
    {
        if (!$this->tiptapStyle || !config('csp-nonce.enabled', true)) {
            return;
        }

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            static fn (): string => Filament::getCurrentPanel()?->getId() === $panel->getId()
                ? TiptapStyle::html()
                : '',
        );
    }
}

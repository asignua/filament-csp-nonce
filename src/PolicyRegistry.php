<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce;

use Asignua\FilamentCspNonce\Enums\Preset;
use Closure;

/**
 * Resolves the policy for a panel (or for none): panel plugin settings first,
 * then the config file.
 */
final class PolicyRegistry
{
    /** @var array<string, array{preset: ?Preset, reportOnly: ?bool, directives: array<string, list<string>|null>, policy: Closure|CspPolicy|null}> */
    private array $panels = [];

    /**
     * @param array{preset: ?Preset, reportOnly: ?bool, directives: array<string, list<string>|null>, policy: Closure|CspPolicy|null} $settings
     */
    public function registerPanel(string $panelId, array $settings): void
    {
        $this->panels[$panelId] = $settings;
    }

    public function policyFor(?string $panelId = null): CspPolicy
    {
        $settings = $panelId !== null ? ($this->panels[$panelId] ?? null) : null;

        $policy = $settings['policy'] ?? null;

        if ($policy instanceof Closure) {
            $policy = $policy($this->base($settings['preset'] ?? null));
        }

        if (!$policy instanceof CspPolicy) {
            $policy = $this->base($settings['preset'] ?? null);
        }

        $policy->merge($this->configDirectives());
        $policy->merge($settings['directives'] ?? []);

        return $this->withReporting($policy);
    }

    public function isReportOnly(?string $panelId = null): bool
    {
        $settings = $panelId !== null ? ($this->panels[$panelId] ?? null) : null;

        return $settings['reportOnly'] ?? (bool) config('csp-nonce.report_only', false);
    }

    private function base(?Preset $preset): CspPolicy
    {
        if ($preset === null) {
            $configured = config('csp-nonce.preset', Preset::StrictDynamic);
            $preset = $configured instanceof Preset ? $configured : Preset::from((string) $configured);
        }

        return $preset->policy();
    }

    /**
     * @return array<string, list<string>|null>
     */
    private function configDirectives(): array
    {
        /** @var array<string, list<string>|null> $directives */
        $directives = (array) config('csp-nonce.directives', []);

        return $directives;
    }

    private function withReporting(CspPolicy $policy): CspPolicy
    {
        if (!config('csp-nonce.report.enabled', true) || $policy->has('report-uri')) {
            return $policy;
        }

        $uri = '/'.ltrim((string) config('csp-nonce.report.path', 'csp/report'), '/');

        return $policy->reportUri($uri)->reportTo('csp-endpoint');
    }
}

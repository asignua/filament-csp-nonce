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

    /**
     * @param string $baseUrl base path of the app (Request::getBaseUrl()), prefixed to the report URL
     */
    public function policyFor(?string $panelId = null, string $baseUrl = ''): CspPolicy
    {
        $settings = $panelId !== null ? ($this->panels[$panelId] ?? null) : null;

        $policy = $settings['policy'] ?? null;

        if ($policy instanceof Closure) {
            $policy = $policy($this->base($settings['preset'] ?? null));
        }

        // A user-supplied instance may be shared between panels and lives for the
        // whole worker under Octane: never mutate it.
        $policy = $policy instanceof CspPolicy ? clone $policy : $this->base($settings['preset'] ?? null);

        $overrides = [...$this->configDirectives(), ...($settings['directives'] ?? [])];

        $policy->merge($overrides);

        return $this->withReporting($policy, $overrides, $baseUrl);
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

    /**
     * The URL browsers POST reports to, under the app's base path (a subdirectory install).
     */
    public function reportUrl(string $baseUrl = ''): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim((string) config('csp-nonce.report.path', 'csp/report'), '/');
    }

    /**
     * @param array<string, list<string>|null> $overrides user directives; an explicit null stays removed
     */
    private function withReporting(CspPolicy $policy, array $overrides, string $baseUrl): CspPolicy
    {
        // A user-chosen reporting setup (own report-uri or report-to group) is never mixed with ours:
        // naming a group that no Reporting-Endpoints header defines would silence the reports.
        if (!config('csp-nonce.report.enabled', true) || $policy->has('report-uri') || $policy->has('report-to')) {
            return $policy;
        }

        $removed = array_map('strtolower', array_keys(array_filter($overrides, static fn (?array $values): bool => $values === null)));

        if (!in_array('report-uri', $removed, true)) {
            $policy->reportUri($this->reportUrl($baseUrl));
        }

        if (!in_array('report-to', $removed, true)) {
            $policy->reportTo('csp-endpoint');
        }

        return $policy;
    }
}

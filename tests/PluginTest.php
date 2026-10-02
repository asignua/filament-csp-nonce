<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Tests;

use Asignua\FilamentCspNonce\CspNoncePlugin;
use Asignua\FilamentCspNonce\CspPolicy;
use Asignua\FilamentCspNonce\Enums\Preset;
use Asignua\FilamentCspNonce\PolicyRegistry;
use Filament\Panel;

class PluginTest extends TestCase
{
    private function registry(): PolicyRegistry
    {
        return $this->app->make(PolicyRegistry::class);
    }

    public function test_the_plugin_registers_the_middleware_with_the_panel_id(): void
    {
        $panel = Panel::make()->id('shop')->plugin(CspNoncePlugin::make());

        $this->assertContains('Asignua\FilamentCspNonce\Http\Middleware\CspNonce:shop', $panel->getMiddleware());
    }

    public function test_allow_inline_styles_relaxes_style_src_only(): void
    {
        Panel::make()->id('shop')->plugin(CspNoncePlugin::make()->allowInlineStyles());

        $policy = $this->registry()->policyFor('shop');

        $this->assertContains("'unsafe-inline'", $policy->get('style-src'));
        $this->assertNotContains("'unsafe-inline'", $policy->get('script-src'));
    }

    public function test_per_panel_preset_report_only_and_directives(): void
    {
        Panel::make()->id('shop')->plugin(
            CspNoncePlugin::make()->preset(Preset::Compatible)->reportOnly()->directives(['img-src' => ["'self'"]]),
        );

        $this->assertTrue($this->registry()->isReportOnly('shop'));
        $this->assertFalse($this->registry()->isReportOnly('admin'));
        $this->assertContains("'self'", $this->registry()->policyFor('shop')->get('script-src'));
        $this->assertSame(["'self'"], $this->registry()->policyFor('shop')->get('img-src'));
        $this->assertNotContains("'strict-dynamic'", $this->registry()->policyFor('shop')->get('script-src'));
    }

    public function test_a_closure_receives_the_preset_policy(): void
    {
        Panel::make()->id('shop')->plugin(CspNoncePlugin::make()->policy(
            fn (CspPolicy $policy): CspPolicy => $policy->directive('frame-src', ["'self'", 'https://www.youtube.com']),
        ));

        $policy = $this->registry()->policyFor('shop');

        $this->assertSame(["'self'", 'https://www.youtube.com'], $policy->get('frame-src'));
        $this->assertContains("'strict-dynamic'", $policy->get('script-src'));
    }

    public function test_unknown_panel_falls_back_to_the_config(): void
    {
        $this->assertContains("'strict-dynamic'", $this->registry()->policyFor(null)->get('script-src'));
    }
}

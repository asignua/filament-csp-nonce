<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Tests;

use Asignua\FilamentCspNonce\CspPolicy;
use Asignua\FilamentCspNonce\Enums\Preset;
use PHPUnit\Framework\TestCase as Base;

class PolicyTest extends Base
{
    public function test_it_renders_directives_and_replaces_the_nonce_token(): void
    {
        $policy = CspPolicy::make()
            ->directive('script-src', [CspPolicy::NONCE, "'strict-dynamic'"])
            ->directive('object-src', ["'none'"]);

        $this->assertSame("script-src 'nonce-abc' 'strict-dynamic'; object-src 'none'", $policy->render('abc'));
        $this->assertSame("script-src 'strict-dynamic'; object-src 'none'", $policy->render(null));
    }

    public function test_merge_replaces_and_removes(): void
    {
        $policy = Preset::StrictDynamic->policy()->merge([
            'connect-src' => ["'self'", 'wss://ws.example.com'],
            'frame-ancestors' => null,
            'upgrade-insecure-requests' => [],
        ]);

        $this->assertSame(["'self'", 'wss://ws.example.com'], $policy->get('connect-src'));
        $this->assertFalse($policy->has('frame-ancestors'));
        $this->assertStringContainsString('upgrade-insecure-requests', $policy->render('n'));
    }

    public function test_strict_dynamic_preset_has_no_unsafe_inline_for_scripts(): void
    {
        $policy = Preset::StrictDynamic->policy();

        $this->assertNotContains("'unsafe-inline'", $policy->get('script-src'));
        $this->assertContains("'strict-dynamic'", $policy->get('script-src'));
        $this->assertSame(["'unsafe-inline'"], $policy->get('style-src-attr'));
        $this->assertNotContains("'unsafe-inline'", $policy->get('style-src'));
    }

    public function test_compatible_preset_allows_inline_styles_and_same_origin_scripts(): void
    {
        $policy = Preset::Compatible->policy();

        $this->assertContains("'self'", $policy->get('script-src'));
        $this->assertNotContains("'strict-dynamic'", $policy->get('script-src'));
        $this->assertContains("'unsafe-inline'", $policy->get('style-src'));
    }
}

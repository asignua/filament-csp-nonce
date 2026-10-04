<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Tests;

use Asignua\FilamentCspNonce\BladeNonceRewriter;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Vite;

class RewriterTest extends TestCase
{
    private function rewriter(): BladeNonceRewriter
    {
        return new BladeNonceRewriter(fn (): array => [__DIR__.'/../vendor/filament']);
    }

    public function test_it_adds_a_nonce_to_bare_tags_only(): void
    {
        $out = $this->rewriter()->rewrite('<script>a()</script><style>b{}</style><script src="x.js" defer></script><script nonce="x">c</script><scripty></scripty>');

        $this->assertSame(3, substr_count($out, 'Nonce::attribute()'));
        $this->assertStringContainsString('<scripty>', $out);
    }

    public function test_it_only_touches_template_source_never_injected_output(): void
    {
        Vite::useCspNonce('abc123');

        $html = Blade::render(
            $this->rewriter()->rewrite('<script>trusted()</script>{!! $evil !!}'),
            ['evil' => '<script>evil()</script>'],
        );

        $this->assertStringContainsString('<script nonce="abc123">trusted()</script>', $html);
        $this->assertStringContainsString('<script>evil()</script>', $html);
    }

    public function test_it_emits_nothing_when_no_nonce_is_active(): void
    {
        $this->app->forgetInstance(\Illuminate\Foundation\Vite::class);
        Facade::clearResolvedInstances();

        $html = Blade::render($this->rewriter()->rewrite('<script>a()</script>'));

        $this->assertStringStartsWith('<script', $html);
        $this->assertStringNotContainsString('nonce=', $html);
    }

    public function test_paths_are_matched_by_prefix(): void
    {
        $rewriter = $this->rewriter();

        $this->assertTrue($rewriter->shouldRewrite(__DIR__.'/../vendor/filament/support/resources/views/assets.blade.php'));
        $this->assertFalse($rewriter->shouldRewrite(__DIR__.'/../vendor/filamentish/x.blade.php'));
        $this->assertFalse($rewriter->shouldRewrite(null));
    }

    public function test_tags_blade_does_not_compile_are_left_alone(): void
    {
        $source = "@verbatim<script>v()</script>@endverbatim\n@php \$x = '<style>p{}</style>'; @endphp\n<?php echo '<script>r()</script>'; ?>\n<script>outside()</script>";

        $out = $this->rewriter()->rewrite($source);

        $this->assertSame(1, substr_count($out, 'Nonce::attribute()'));
        $this->assertStringContainsString('@verbatim<script>v()</script>@endverbatim', $out);
        $this->assertStringContainsString("'<style>p{}</style>'", $out);
        $this->assertStringContainsString("'<script>r()</script>'", $out);
    }

    public function test_only_a_nonce_attribute_skips_a_tag(): void
    {
        $tagged = [
            '<script src="/js/vendor/nonce-polyfill.js"></script>',
            '<script data-nonce-key="x"></script>',
            '<script x-data="{ nonce: null }"></script>',
            '<style data-x="nonces"></style>',
        ];

        foreach ($tagged as $source) {
            $this->assertSame(1, substr_count($this->rewriter()->rewrite($source), 'Nonce::attribute()'), $source);
        }

        $skipped = [
            '<script nonce="x"></script>',
            '<script NONCE=\'x\'></script>',
            '<script defer nonce></script>',
            '<script :nonce="n"></script>',
            '<script x-bind:nonce="n"></script>',
            '<script @cspNonce></script>',
            '<script {{ $cspNonce }}></script>',
            '<script nonce="{{ csp_nonce() }}"></script>',
            '<style {!! \\Illuminate\\Support\\Facades\\Vite::cspNonce() !!}></style>',
        ];

        foreach ($skipped as $source) {
            $this->assertSame($source, $this->rewriter()->rewrite($source), $source);
        }
    }
}

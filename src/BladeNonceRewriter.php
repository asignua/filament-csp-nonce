<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce;

use Closure;
use Illuminate\Support\Str;

/**
 * Adds a nonce attribute to literal <script>/<style> tags of Blade TEMPLATE
 * SOURCE. It runs at compile time on files the developer ships, so markup that
 * a user manages to inject into rendered output never receives a nonce.
 */
final class BladeNonceRewriter
{
    /** @var list<string>|null */
    private ?array $paths = null;

    /**
     * @param Closure(): list<string> $pathResolver resolved lazily: only a view compile needs it
     */
    public function __construct(private readonly Closure $pathResolver) {}

    public function shouldRewrite(?string $templatePath): bool
    {
        if ($templatePath === null || $templatePath === '') {
            return false;
        }

        $templatePath = $this->normalise($templatePath);

        foreach ($this->paths ??= ($this->pathResolver)() as $path) {
            if (Str::startsWith($templatePath, rtrim($this->normalise($path), '/').'/')) {
                return true;
            }
        }

        return false;
    }

    public function rewrite(string $source): string
    {
        $protected = $this->protectedRegions($source);

        $result = preg_replace_callback(
            '~<(script|style)(?![\w:-])([^>]*)>~i',
            static function (array $match) use ($protected): string {
                [$tag, $offset] = $match[0];

                foreach ($protected as [$start, $end]) {
                    if ($offset >= $start && $offset < $end) {
                        return $tag;
                    }
                }

                if (self::hasNonce($match[2][0])) {
                    return $tag;
                }

                return '<'.$match[1][0].' {!! \\'.Nonce::class.'::attribute() !!}'.$match[2][0].'>';
            },
            $source,
            flags: PREG_OFFSET_CAPTURE,
        );

        return $result ?? $source;
    }

    /**
     * Whether the tag already prints a nonce: a `nonce` attribute (`nonce="…"`, a bare
     * `nonce`, Alpine's `:nonce` / `x-bind:nonce`), the `@cspNonce` directive, or a
     * standalone Blade echo that mentions a nonce. The word elsewhere (a `src` path, an echo
     * inside another attribute's value, `data-nonce-key`, an `x-data` expression) does not
     * count: such a tag still needs one.
     */
    private static function hasNonce(string $attributes): bool
    {
        if (str_contains($attributes, '@cspNonce')) {
            return true;
        }

        // Values of other attributes are dropped first: an echo inside `src="{{ asset('nonce.js') }}"`
        // or `data-key="{{ $nonceKey }}"` prints no nonce attribute.
        $names = (string) preg_replace('/=\s*(?:"[^"]*"|\'[^\']*\')/', '=""', $attributes);

        if (preg_match('/\{\{.*?nonce.*?\}\}|\{!!.*?nonce.*?!!\}/is', $names) === 1) {
            return true;
        }

        return preg_match('/(?:^|\s)(?:x-bind:|:)?nonce(?![\w-])/i', $names) === 1;
    }

    /**
     * Byte ranges Blade does not compile: @verbatim, @php ... @endphp and raw PHP.
     * A `{!! !!}` inserted there would be printed literally. The patterns mirror
     * the ones BladeCompiler uses to extract these blocks.
     *
     * @return list<array{int, int}>
     */
    private function protectedRegions(string $source): array
    {
        preg_match_all(
            '/(?<!@)@verbatim(.*?)@endverbatim|(?<!@)@php(.*?)@endphp|<\?(?:php|=)(.*?)(?:\?>|$)/si',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER,
        );

        $regions = [];

        foreach ($matches as $match) {
            $regions[] = [$match[0][1], $match[0][1] + strlen($match[0][0])];
        }

        return $regions;
    }

    private function normalise(string $path): string
    {
        return str_replace('\\', '/', realpath($path) ?: $path);
    }
}

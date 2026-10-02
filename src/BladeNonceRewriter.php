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
        $result = preg_replace_callback(
            '~<(script|style)(?![\w:-])([^>]*)>~i',
            static function (array $match): string {
                if (stripos($match[2], 'nonce') !== false) {
                    return $match[0];
                }

                return '<'.$match[1].' {!! \\'.Nonce::class.'::attribute() !!}'.$match[2].'>';
            },
            $source,
        );

        return $result ?? $source;
    }

    private function normalise(string $path): string
    {
        return str_replace('\\', '/', realpath($path) ?: $path);
    }
}

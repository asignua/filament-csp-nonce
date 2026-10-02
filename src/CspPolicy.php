<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A mutable builder for a Content-Security-Policy header value. PolicyRegistry
 * clones instances handed to it, so a policy passed to a plugin is never changed.
 *
 * @implements Arrayable<string, list<string>>
 */
final class CspPolicy implements Arrayable
{
    /** Replaced by 'nonce-<value>' when the header is rendered. */
    public const NONCE = '{nonce}';

    /** @var array<string, list<string>> */
    private array $directives = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param list<string> $values
     */
    public function directive(string $name, array $values): self
    {
        $this->directives[strtolower($name)] = array_values(array_unique($values));

        return $this;
    }

    public function remove(string $name): self
    {
        unset($this->directives[strtolower($name)]);

        return $this;
    }

    /**
     * Merge overrides: a list replaces the directive, null removes it.
     *
     * @param array<string, list<string>|null> $overrides
     */
    public function merge(array $overrides): self
    {
        foreach ($overrides as $name => $values) {
            if ($values === null) {
                $this->remove($name);

                continue;
            }

            $this->directive($name, $values);
        }

        return $this;
    }

    public function reportUri(string $uri): self
    {
        return $this->directive('report-uri', [$uri]);
    }

    public function reportTo(string $group): self
    {
        return $this->directive('report-to', [$group]);
    }

    public function has(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->directives);
    }

    /**
     * @return list<string>
     */
    public function get(string $name): array
    {
        return $this->directives[strtolower($name)] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function toArray(): array
    {
        return $this->directives;
    }

    public function render(?string $nonce = null): string
    {
        $parts = [];

        foreach ($this->directives as $name => $values) {
            $values = array_map(
                fn (string $value): string => $value === self::NONCE ? $this->nonceSource($nonce) : $value,
                $values,
            );

            $values = array_values(array_filter($values, fn (string $value): bool => $value !== ''));

            $parts[] = trim($name.' '.implode(' ', $values));
        }

        return implode('; ', $parts);
    }

    private function nonceSource(?string $nonce): string
    {
        return filled($nonce) ? "'nonce-{$nonce}'" : '';
    }
}

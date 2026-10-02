<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce;

use Illuminate\Support\Facades\Vite;

/**
 * The per-request nonce. Laravel's Vite owns the value because Filament,
 * Livewire and Vite all read it from there: one source, nothing to keep in sync.
 */
final class Nonce
{
    public static function generate(): string
    {
        return Vite::useCspNonce(base64_encode(random_bytes(16)));
    }

    public static function get(): ?string
    {
        $nonce = Vite::cspNonce();

        return filled($nonce) ? (string) $nonce : null;
    }

    /**
     * `nonce="..."`, or an empty string when no nonce is active.
     */
    public static function attribute(): string
    {
        $nonce = self::get();

        return $nonce === null ? '' : 'nonce="'.e($nonce).'"';
    }
}

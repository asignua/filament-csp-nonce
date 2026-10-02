<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Enums;

use Asignua\FilamentCspNonce\CspPolicy;

enum Preset: string
{
    case StrictDynamic = 'filament-strict-dynamic';
    case Compatible = 'filament-compatible';

    public function policy(): CspPolicy
    {
        return match ($this) {
            self::StrictDynamic => self::strictDynamic(),
            self::Compatible => self::compatible(),
        };
    }

    /**
     * Scripts: nonce + strict-dynamic. No 'unsafe-inline', no host allow-list.
     * 'unsafe-eval' stays: Filament's Alpine expressions are compiled with
     * `new Function`, and Livewire's CSP-safe Alpine build cannot run them.
     * Styles: elements need the nonce; style="" attributes are allowed (Filament
     * renders hundreds of them).
     */
    private static function strictDynamic(): CspPolicy
    {
        return self::base()
            ->directive('script-src', [CspPolicy::NONCE, "'strict-dynamic'", "'unsafe-eval'"])
            ->directive('style-src', ["'self'", CspPolicy::NONCE])
            ->directive('style-src-attr', ["'unsafe-inline'"]);
    }

    /**
     * Same-origin scripts keep working without a nonce (third-party Filament
     * plugins that print their own tags), styles are fully permissive.
     */
    private static function compatible(): CspPolicy
    {
        return self::base()
            ->directive('script-src', ["'self'", CspPolicy::NONCE, "'unsafe-eval'"])
            ->directive('style-src', ["'self'", "'unsafe-inline'"]);
    }

    private static function base(): CspPolicy
    {
        return CspPolicy::make()
            ->directive('default-src', ["'self'"])
            ->directive('img-src', ["'self'", 'data:', 'blob:', 'https:'])
            ->directive('font-src', ["'self'", 'data:'])
            ->directive('connect-src', ["'self'"])
            ->directive('media-src', ["'self'", 'blob:', 'data:'])
            ->directive('worker-src', ["'self'", 'blob:'])
            ->directive('object-src', ["'none'"])
            ->directive('base-uri', ["'self'"])
            ->directive('form-action', ["'self'"])
            ->directive('frame-ancestors', ["'self'"]);
    }
}

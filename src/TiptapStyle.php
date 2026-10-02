<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce;

/**
 * Tiptap's core stylesheet, byte-for-byte as bundled in filament/forms. A test
 * compares it with the bundle, so a Filament upgrade that changes it fails loudly.
 */
final class TiptapStyle
{
    public static function css(): string
    {
        return rtrim((string) file_get_contents(__DIR__.'/../resources/css/tiptap-core.css'));
    }

    public static function html(): string
    {
        $nonce = Nonce::attribute();

        if ($nonce === '') {
            return '';
        }

        return '<style '.$nonce.' data-tiptap-style>'.self::css().'</style>';
    }
}

<?php

declare(strict_types=1);

use Asignua\FilamentCspNonce\Nonce;

if (!function_exists('csp_nonce')) {
    /**
     * The current request's CSP nonce, or null when the middleware did not run.
     */
    function csp_nonce(): ?string
    {
        return Nonce::get();
    }
}

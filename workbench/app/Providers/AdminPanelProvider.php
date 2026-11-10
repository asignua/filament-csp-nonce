<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Asignua\FilamentCspNonce\CspNoncePlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Workbench\App\Filament\Resources\Users\UserResource;
use Workbench\App\Filament\Widgets\DemoChart;
use Workbench\App\Filament\Widgets\DemoStats;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // Workbench-only switch used by the manual browser audit (see FEASIBILITY.md).
        if (filter_var(env('CSP_NO_EVAL', false), FILTER_VALIDATE_BOOL)) {
            config(['csp-nonce.directives.script-src' => ['{nonce}', "'strict-dynamic'"]]);
        }

        config(['livewire.csp_safe' => filter_var(env('LIVEWIRE_CSP_SAFE', false), FILTER_VALIDATE_BOOL)]);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->pages([Dashboard::class])
            ->login()
            ->resources([UserResource::class])
            ->widgets([DemoStats::class, DemoChart::class])
            ->plugin(CspNoncePlugin::make())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                // Laravel 13 renamed VerifyCsrfToken to PreventRequestForgery; the string is the Laravel 12 fallback.
                class_exists(PreventRequestForgery::class) ? PreventRequestForgery::class : 'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}

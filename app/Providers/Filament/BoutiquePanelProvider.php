<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RedirectToAccessiblePanel;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class BoutiquePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('boutique')
            ->path('tienda')
            ->login()
            ->brandName('BOUTIQUE VENTAS')
            ->brandLogo(asset('images/brand/logo-light.svg'))
            ->darkModeBrandLogo(asset('images/brand/logo-dark.svg'))
            ->brandLogoHeight('2.6rem')
            ->favicon(asset('images/brand/favicon.svg'))
            ->font('Poppins')
            ->colors([
                'primary' => Color::hex('#F43F78'),
            ])
            ->discoverResources(in: app_path('Filament/Boutique/Resources'), for: 'App\Filament\Boutique\Resources')
            ->discoverPages(in: app_path('Filament/Boutique/Pages'), for: 'App\Filament\Boutique\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Boutique/Widgets'), for: 'App\Filament\Boutique\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                RedirectToAccessiblePanel::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

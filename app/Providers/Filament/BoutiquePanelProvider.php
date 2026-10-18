<?php

namespace App\Providers\Filament;

use App\Filament\Boutique\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
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
                'gray' => Color::Stone,
                'danger' => Color::Red,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'info' => Color::Sky,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Operación diaria')
                    ->icon(Heroicon::OutlinedShoppingCart),
                NavigationGroup::make()
                    ->label('Tu catálogo')
                    ->icon(Heroicon::OutlinedArchiveBox),
                NavigationGroup::make()
                    ->label('Finanzas')
                    ->icon(Heroicon::OutlinedBanknotes),
                NavigationGroup::make()
                    ->label('Reportes')
                    ->icon(Heroicon::OutlinedChartPie),
                NavigationGroup::make()
                    ->label('Administración')
                    ->icon(Heroicon::OutlinedCog6Tooth),
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

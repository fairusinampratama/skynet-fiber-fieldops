<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AssetStatsWidget;
use App\Filament\Widgets\AutoAlertWidget;
use App\Filament\Widgets\OltMonitoringWidget;
use App\Filament\Widgets\PortAvailabilityWidget;
use App\Filament\Widgets\TopCriticalOdpWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Skynet Fiber FieldOps')
            ->colors(['primary' => Color::Sky])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <link rel="stylesheet" href="/vendor/leaflet/leaflet.css" data-navigate-track />
                    <link rel="stylesheet" href="/css/fieldops-coordinate-map.css" data-navigate-track />
                    HTML),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_BEFORE,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <script src="/vendor/leaflet/leaflet.js" data-navigate-once></script>
                    <script src="/js/fieldops-coordinate-map.js" data-navigate-once></script>
                    HTML),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Pages\Dashboard::class])
            ->widgets([
                AssetStatsWidget::class,
                AutoAlertWidget::class,
                TopCriticalOdpWidget::class,
                OltMonitoringWidget::class,
                PortAvailabilityWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;

class TopCriticalOdpWidget extends Widget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = [
        'xl' => 2,
    ];

    protected string $view = 'filament.widgets.top-critical-odp';

    protected function getViewData(): array
    {
        return [
            'rows' => app(DashboardMetricsService::class)->criticalOdps(),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}

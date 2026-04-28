<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;

class OltMonitoringWidget extends Widget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = [
        'xl' => 2,
    ];

    protected string $view = 'filament.widgets.pon-pressure';

    protected function getViewData(): array
    {
        return [
            'rows' => app(DashboardMetricsService::class)->pressuredPons(),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}

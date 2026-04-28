<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;

class ProjectProgressWidget extends Widget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = [
        'xl' => 2,
    ];

    protected string $view = 'filament.widgets.project-area-progress';

    protected function getViewData(): array
    {
        return [
            'rows' => app(DashboardMetricsService::class)->areaProgress(),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}

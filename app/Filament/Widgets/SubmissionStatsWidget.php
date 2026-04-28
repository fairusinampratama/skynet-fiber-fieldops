<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;

class SubmissionStatsWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected string $view = 'filament.widgets.odp-utilization-summary';

    protected function getViewData(): array
    {
        return [
            'rows' => app(DashboardMetricsService::class)->utilizationSummary(),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}

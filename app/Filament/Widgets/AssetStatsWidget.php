<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AssetStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | array | null $columns = [
        'default' => 1,
        'md' => 2,
        'xl' => 4,
    ];

    protected function getStats(): array
    {
        return collect(app(DashboardMetricsService::class)->kpiCards())
            ->map(fn (array $card) => Stat::make($card['label'], $card['value'])
                ->description($card['description'])
                ->color($card['color'])
                ->icon($card['icon'])
                ->url($card['url']))
            ->all();
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}

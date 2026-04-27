<?php

namespace App\Filament\Widgets;

use App\Models\OdcAsset;
use App\Models\OdpAsset;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AssetStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total projects', Project::query()->count())->color('gray'),
            Stat::make('Total ODC assets', OdcAsset::query()->count())->color('success'),
            Stat::make('Total ODP assets', OdpAsset::query()->count())->color('success'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}

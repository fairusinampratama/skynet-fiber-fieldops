<?php

namespace App\Filament\Widgets;

use App\Enums\PortStatus;
use App\Models\OdcPort;
use App\Models\OdpPort;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PortAvailabilityWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Available ODC ports', OdcPort::query()->where('status', PortStatus::Available)->count())->color('success'),
            Stat::make('Used ODC ports', OdcPort::query()->where('status', PortStatus::Used)->count())->color('info'),
            Stat::make('Available ODP ports', OdpPort::query()->where('status', PortStatus::Available)->count())->color('success'),
            Stat::make('Used ODP ports', OdpPort::query()->where('status', PortStatus::Used)->count())->color('info'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}

<?php

namespace App\Filament\Widgets;

use App\Enums\PortStatus;
use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;

class PortAvailabilityWidget extends ChartWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected ?string $heading = 'Distribusi Status Port ODP';

    protected function getData(): array
    {
        $distribution = app(DashboardMetricsService::class)->portStatusDistribution();
        $usedPorts = $distribution[PortStatus::Used->value] ?? 0;
        $unusedPorts = array_sum($distribution) - $usedPorts;

        return [
            'datasets' => [
                [
                    'label' => 'Port',
                    'data' => [$unusedPorts, $usedPorts],
                    'backgroundColor' => ['#22c55e', '#38bdf8'],
                ],
            ],
            'labels' => array_values(PortStatus::simpleOptions()),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}

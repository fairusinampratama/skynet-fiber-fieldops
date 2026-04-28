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

        return [
            'datasets' => [
                [
                    'label' => 'Port',
                    'data' => array_values($distribution),
                    'backgroundColor' => ['#22c55e', '#38bdf8', '#f59e0b', '#ef4444', '#94a3b8'],
                ],
            ],
            'labels' => collect(PortStatus::cases())->map(fn (PortStatus $status) => $status->getLabel())->all(),
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

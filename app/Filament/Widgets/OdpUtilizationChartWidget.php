<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;

class OdpUtilizationChartWidget extends ChartWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected ?string $heading = 'Utilisasi ODP';

    protected function getData(): array
    {
        $summary = app(DashboardMetricsService::class)->utilizationSummary();

        return [
            'datasets' => [
                [
                    'label' => 'ODP',
                    'data' => collect($summary)->pluck('count')->all(),
                    'backgroundColor' => ['#ef4444', '#f59e0b', '#22c55e'],
                ],
            ],
            'labels' => collect($summary)->pluck('category')->all(),
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

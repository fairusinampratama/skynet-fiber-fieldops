<?php

namespace App\Services;

use App\Enums\PortStatus;
use App\Models\OdcAsset;
use App\Models\OdpAsset;
use App\Models\OdpPort;
use App\Models\OltAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public const FULL_THRESHOLD = 90;
    public const WARNING_THRESHOLD = 75;

    private ?array $overview = null;

    public function overview(): array
    {
        return $this->overview ??= $this->buildOverview();
    }

    public function kpis(): array
    {
        return $this->overview()['kpis'];
    }

    public function overviewKpis(): array
    {
        return $this->kpiCards();
    }

    public function kpiCards(): array
    {
        $kpis = $this->kpis();

        return [
            ['label' => 'Total OLT', 'value' => $kpis['total_olt'], 'description' => 'Unit terdaftar', 'color' => 'info', 'icon' => 'heroicon-o-signal', 'url' => url('/admin/olt-assets')],
            ['label' => 'Total ODC', 'value' => $kpis['total_odc'], 'description' => 'Termasuk belum mapping', 'color' => 'gray', 'icon' => 'heroicon-o-server-stack', 'url' => url('/admin/odc-assets')],
            ['label' => 'Total ODP', 'value' => $kpis['total_odp'], 'description' => 'Unit distribusi', 'color' => 'info', 'icon' => 'heroicon-o-square-3-stack-3d', 'url' => url('/admin/odp-assets')],
            ['label' => 'Total Kapasitas', 'value' => $kpis['total_capacity'], 'description' => 'Port ODP tercatat', 'color' => 'success', 'icon' => 'heroicon-o-circle-stack', 'url' => url('/admin/odp-assets')],
            ['label' => 'Pelanggan Aktif', 'value' => $kpis['active_customers'], 'description' => 'Port ODP terpakai', 'color' => 'warning', 'icon' => 'heroicon-o-users', 'url' => url('/admin/odp-assets')],
            ['label' => 'Port Kosong', 'value' => $kpis['empty_ports'], 'description' => 'Port ODP tersedia', 'color' => 'primary', 'icon' => 'heroicon-o-arrow-down-tray', 'url' => url('/admin/odp-assets')],
            ['label' => 'ODP Kritis', 'value' => $kpis['critical_odp'], 'description' => 'Utilisasi >= 90%', 'color' => 'danger', 'icon' => 'heroicon-o-exclamation-circle', 'url' => url('/admin/odp-assets')],
            ['label' => 'PON Bermasalah', 'value' => $kpis['pressured_pon'], 'description' => 'Utilisasi >= 75%', 'color' => 'danger', 'icon' => 'heroicon-o-exclamation-triangle', 'url' => url('/admin/olt-pon-ports')],
        ];
    }

    public function operationalAlerts(): array
    {
        return $this->overview()['alerts'];
    }

    public function alerts(): array
    {
        return $this->operationalAlerts();
    }

    public function criticalOdps(int $limit = 10): Collection
    {
        return $this->overview()['critical_odps']->take($limit)->values();
    }

    public function topCriticalOdps(int $limit = 10): Collection
    {
        return $this->criticalOdps($limit);
    }

    public function pressuredPons(int $limit = 10): Collection
    {
        return $this->overview()['pressured_pons']->take($limit)->values();
    }

    public function ponMonitoring(): Collection
    {
        return $this->pressuredPons();
    }

    public function ponMonitoringRows(): Collection
    {
        return $this->overview()['pon_rows'];
    }

    public function portStatusDistribution(): array
    {
        return $this->overview()['port_status_distribution'];
    }

    public function utilizationSummary(): array
    {
        $summary = $this->overview()['utilization_summary'];

        return [
            [
                'category' => 'Penuh',
                'count' => $summary['full'],
                'threshold' => '>= 90%',
                'description' => 'ODP penuh / kritis',
                'priority' => 'Penuh',
                'color' => 'danger',
            ],
            [
                'category' => 'Hampir Penuh',
                'count' => $summary['warning'],
                'threshold' => '75% - 90%',
                'description' => 'ODP hampir penuh',
                'priority' => 'Hampir Penuh',
                'color' => 'warning',
            ],
            [
                'category' => 'Aman',
                'count' => $summary['safe'],
                'threshold' => '< 75%',
                'description' => 'ODP aman',
                'priority' => 'Aman',
                'color' => 'success',
            ],
        ];
    }

    public function areaProgress(int $limit = 10): Collection
    {
        return collect();
    }

    public function projectAreaProgress(int $limit = 10): Collection
    {
        return $this->areaProgress($limit);
    }

    public function odpUtilizationRows(): Collection
    {
        return $this->overview()['odp_rows'];
    }

    public function calculateUtilization(int $used, int $reserved, int $capacity): float
    {
        if ($capacity <= 0) {
            return 0.0;
        }

        return round((($used + $reserved) / $capacity) * 100, 2);
    }

    private function buildOverview(): array
    {
        $odpRows = $this->buildOdpRows();
        $ponRows = $this->buildPonRows();
        $portDistribution = $this->buildPortStatusDistribution();
        $utilizationSummary = [
            'full' => $odpRows->where('category', 'full')->count(),
            'warning' => $odpRows->where('category', 'warning')->count(),
            'safe' => $odpRows->where('category', 'safe')->count(),
        ];

        $criticalOdps = $odpRows
            ->filter(fn (array $row) => $row['utilization'] >= self::WARNING_THRESHOLD)
            ->sortByDesc('utilization')
            ->values();
        $pressuredPons = $ponRows
            ->filter(fn (array $row) => in_array($row['category'], ['warning', 'full', 'overload'], true))
            ->sortByDesc('utilization')
            ->values();
        $unmappedOdcs = OdcAsset::query()->whereNull('olt_pon_port_id')->count();
        $unlinkedOdps = OdpAsset::query()->whereNull('odc_asset_id')->count();

        $kpis = [
            'total_olt' => OltAsset::query()->count(),
            'total_odc' => OdcAsset::query()->count(),
            'total_odp' => $odpRows->count(),
            'total_capacity' => array_sum($portDistribution),
            'active_customers' => $portDistribution[PortStatus::Used->value] ?? 0,
            'empty_ports' => $portDistribution[PortStatus::Available->value] ?? 0,
            'critical_odp' => $utilizationSummary['full'],
            'near_full_odp' => $utilizationSummary['warning'],
            'pressured_pon' => $pressuredPons->count(),
            'full_odp' => $utilizationSummary['full'],
            'pon_overload' => $ponRows->where('category', 'overload')->count(),
        ];

        return [
            'kpis' => $kpis,
            'alerts' => $this->buildAlerts($kpis, $pressuredPons->count(), $unmappedOdcs, $unlinkedOdps),
            'critical_odps' => $criticalOdps,
            'pressured_pons' => $pressuredPons,
            'pon_rows' => $ponRows,
            'odp_rows' => $odpRows,
            'port_status_distribution' => $portDistribution,
            'utilization_summary' => $utilizationSummary,
        ];
    }

    private function buildOdpRows(): Collection
    {
        return DB::table('odp_assets')
            ->leftJoin('projects', 'projects.id', '=', 'odp_assets.project_id')
            ->leftJoin('areas', 'areas.id', '=', 'odp_assets.area_id')
            ->leftJoin('odc_assets', 'odc_assets.id', '=', 'odp_assets.odc_asset_id')
            ->leftJoin('olt_pon_ports', 'olt_pon_ports.id', '=', 'odc_assets.olt_pon_port_id')
            ->leftJoin('olt_assets', 'olt_assets.id', '=', 'olt_pon_ports.olt_asset_id')
            ->leftJoin('odp_ports', 'odp_ports.odp_asset_id', '=', 'odp_assets.id')
            ->select([
                'odp_assets.id',
                'odp_assets.odc_asset_id',
                'odc_assets.olt_pon_port_id',
                'olt_pon_ports.olt_asset_id',
                'olt_assets.code as olt_code',
                'olt_pon_ports.pon_number',
                'odc_assets.box_id as odc_box_id',
                'odp_assets.box_id',
                'projects.name as project_name',
                'areas.name as area_name',
            ])
            ->selectRaw('COUNT(odp_ports.id) as total_ports')
            ->selectRaw("SUM(CASE WHEN odp_ports.status = ? THEN 1 ELSE 0 END) as used_ports", [PortStatus::Used->value])
            ->selectRaw("SUM(CASE WHEN odp_ports.status = ? THEN 1 ELSE 0 END) as reserved_ports", [PortStatus::Reserved->value])
            ->selectRaw("SUM(CASE WHEN odp_ports.status = ? THEN 1 ELSE 0 END) as available_ports", [PortStatus::Available->value])
            ->groupBy([
                'odp_assets.id',
                'odp_assets.odc_asset_id',
                'odc_assets.olt_pon_port_id',
                'olt_pon_ports.olt_asset_id',
                'olt_assets.code',
                'olt_pon_ports.pon_number',
                'odc_assets.box_id',
                'odp_assets.box_id',
                'projects.name',
                'areas.name',
            ])
            ->get()
            ->map(function (object $odp): array {
                $capacity = (int) $odp->total_ports;
                $used = (int) $odp->used_ports;
                $reserved = (int) $odp->reserved_ports;
                $available = (int) $odp->available_ports;
                $utilization = $this->calculateUtilization($used, $reserved, $capacity);
                $category = $this->odpCategoryFor($utilization);

                return [
                    'id' => (int) $odp->id,
                    'odc_id' => $odp->odc_asset_id ? (int) $odp->odc_asset_id : null,
                    'olt_id' => $odp->olt_asset_id ? (int) $odp->olt_asset_id : null,
                    'pon_id' => $odp->olt_pon_port_id ? (int) $odp->olt_pon_port_id : null,
                    'olt' => $odp->olt_code ?? 'Belum Mapping',
                    'pon' => $odp->pon_number ? (int) $odp->pon_number : null,
                    'odc' => $odp->odc_box_id ?? 'Belum Mapping',
                    'box_id' => $odp->box_id,
                    'project' => $odp->project_name,
                    'area' => $odp->area_name,
                    'capacity' => $capacity,
                    'used' => $used,
                    'reserved' => $reserved,
                    'available' => $available,
                    'utilization' => $utilization,
                    'category' => $category,
                    'status' => $this->odpStatusLabelFor($category),
                    'color' => $this->colorFor($category),
                    'recommendation' => $this->recommendationFor($utilization),
                    'url' => url("/admin/odp-assets/{$odp->id}/edit"),
                    'odc_url' => $odp->odc_asset_id ? url("/admin/odc-assets/{$odp->odc_asset_id}/edit") : null,
                    'pon_url' => $odp->olt_pon_port_id ? url("/admin/olt-pon-ports/{$odp->olt_pon_port_id}/edit") : null,
                    'olt_url' => $odp->olt_asset_id ? url("/admin/olt-assets/{$odp->olt_asset_id}/edit") : null,
                ];
            });
    }

    private function buildPonRows(): Collection
    {
        return DB::table('olt_pon_ports')
            ->join('olt_assets', 'olt_assets.id', '=', 'olt_pon_ports.olt_asset_id')
            ->leftJoin('odc_assets', 'odc_assets.olt_pon_port_id', '=', 'olt_pon_ports.id')
            ->leftJoin('odp_assets', 'odp_assets.odc_asset_id', '=', 'odc_assets.id')
            ->leftJoin('odp_ports', 'odp_ports.odp_asset_id', '=', 'odp_assets.id')
            ->select([
                'olt_pon_ports.id',
                'olt_pon_ports.olt_asset_id',
                'olt_assets.code as olt_code',
                'olt_pon_ports.pon_number',
                'olt_pon_ports.label',
                'olt_pon_ports.capacity',
            ])
            ->selectRaw('COUNT(DISTINCT odc_assets.id) as odc_count')
            ->selectRaw('COUNT(DISTINCT odp_assets.id) as odp_count')
            ->selectRaw("SUM(CASE WHEN odp_ports.status = ? THEN 1 ELSE 0 END) as active_customers", [PortStatus::Used->value])
            ->groupBy([
                'olt_pon_ports.id',
                'olt_pon_ports.olt_asset_id',
                'olt_assets.code',
                'olt_pon_ports.pon_number',
                'olt_pon_ports.label',
                'olt_pon_ports.capacity',
            ])
            ->get()
            ->map(function (object $pon): array {
                $activeCustomers = (int) $pon->active_customers;
                $capacity = (int) $pon->capacity;
                $utilization = $this->calculateUtilization($activeCustomers, 0, $capacity);
                $category = $this->ponCategoryFor($utilization);

                return [
                    'id' => (int) $pon->id,
                    'olt_id' => (int) $pon->olt_asset_id,
                    'olt' => $pon->olt_code,
                    'pon' => (int) $pon->pon_number,
                    'label' => $pon->label ?: 'PON ' . $pon->pon_number,
                    'odc_count' => (int) $pon->odc_count,
                    'odp_count' => (int) $pon->odp_count,
                    'active_customers' => $activeCustomers,
                    'capacity' => $capacity,
                    'utilization' => $utilization,
                    'status' => $this->ponStatusLabelFor($category),
                    'category' => $category,
                    'color' => $this->colorFor($category),
                    'url' => url("/admin/olt-pon-ports/{$pon->id}/edit"),
                    'olt_url' => url("/admin/olt-assets/{$pon->olt_asset_id}/edit"),
                ];
            })
            ->sortByDesc('utilization')
            ->values();
    }

    private function buildPortStatusDistribution(): array
    {
        $counts = OdpPort::query()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        return collect(PortStatus::cases())
            ->mapWithKeys(fn (PortStatus $status): array => [$status->value => $counts[$status->value] ?? 0])
            ->all();
    }

    private function buildAlerts(array $kpis, int $pressuredPons, int $unmappedOdcs, int $unlinkedOdps): array
    {
        return collect([
            [
                'level' => 'Penuh',
                'type' => 'ODP Kritis',
                'object' => 'Utilisasi ODP >= 90%',
                'value' => $kpis['critical_odp'],
                'action' => 'Review ODP',
                'color' => 'danger',
                'url' => url('/admin/odp-assets'),
            ],
            [
                'level' => 'Hampir Penuh',
                'type' => 'ODP Hampir Penuh',
                'object' => 'Utilisasi ODP 75-90%',
                'value' => $kpis['near_full_odp'],
                'action' => 'Siapkan kapasitas',
                'color' => 'warning',
                'url' => url('/admin/odp-assets'),
            ],
            [
                'level' => 'Overload',
                'type' => 'PON Bermasalah',
                'object' => 'Utilisasi PON >= 75%',
                'value' => $pressuredPons,
                'action' => 'Review PON',
                'color' => 'danger',
                'url' => url('/admin/olt-pon-ports'),
            ],
            [
                'level' => 'Belum Mapping',
                'type' => 'ODC Belum Mapping',
                'object' => 'ODC belum terhubung ke OLT/PON',
                'value' => $unmappedOdcs,
                'action' => 'Hubungkan OLT/PON',
                'color' => 'info',
                'url' => $this->assetFilterUrl('/admin/odc-assets', 'mapping_state', false),
            ],
            [
                'level' => 'Belum Mapping',
                'type' => 'ODP Belum Mapping',
                'object' => 'ODP belum terhubung ke ODC',
                'value' => $unlinkedOdps,
                'action' => 'Hubungkan ODC',
                'color' => 'info',
                'url' => $this->assetFilterUrl('/admin/odp-assets', 'mapping_state', false),
            ],
        ])->filter(fn (array $alert): bool => $alert['value'] > 0)->values()->all();
    }

    private function assetFilterUrl(string $path, string $filter, bool|string $value): string
    {
        return url($path . '?' . http_build_query([
            'filters' => [
                $filter => [
                    'value' => is_bool($value) ? (int) $value : $value,
                ],
            ],
        ]));
    }

    private function odpCategoryFor(float $utilization): string
    {
        if ($utilization >= self::FULL_THRESHOLD) {
            return 'full';
        }

        if ($utilization >= self::WARNING_THRESHOLD) {
            return 'warning';
        }

        return 'safe';
    }

    private function recommendationFor(float $utilization): string
    {
        if ($utilization >= self::FULL_THRESHOLD) {
            return 'Upgrade splitter';
        }

        if ($utilization >= self::WARNING_THRESHOLD) {
            return 'Siapkan kapasitas';
        }

        return 'Normal';
    }

    private function ponCategoryFor(float $utilization): string
    {
        if ($utilization > 100) {
            return 'overload';
        }

        if ($utilization >= self::FULL_THRESHOLD) {
            return 'full';
        }

        if ($utilization >= self::WARNING_THRESHOLD) {
            return 'warning';
        }

        return 'safe';
    }

    private function ponStatusLabelFor(string $category): string
    {
        return match ($category) {
            'overload' => 'Overload',
            'full' => 'Penuh',
            'warning' => 'Hampir Penuh',
            default => 'Aman',
        };
    }

    private function odpStatusLabelFor(string $category): string
    {
        return match ($category) {
            'full' => 'Penuh',
            'warning' => 'Hampir Penuh',
            default => 'Aman',
        };
    }

    private function colorFor(string $category): string
    {
        return match ($category) {
            'overload', 'full' => 'danger',
            'warning' => 'warning',
            default => 'success',
        };
    }
}

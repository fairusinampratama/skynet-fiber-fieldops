<?php

namespace App\Services;

use App\Enums\PortStatus;
use App\Models\OdcAsset;
use App\Models\OdpAsset;
use App\Models\OltAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AssetMapDataService
{
    public const DEFAULT_CENTER = [-7.966620, 112.632632];

    /**
     * @param  array{project_id?: int|string|null, area_id?: int|string|null, status?: string|null, mapping_state?: string|null}  $filters
     * @return array{assets: array<int, array<string, mixed>>, links: array<int, array<string, mixed>>, center: array<int, float>}
     */
    public function payload(array $filters = []): array
    {
        $assets = [
            ...$this->oltAssets($filters),
            ...$this->odcAssets($filters),
            ...$this->odpAssets($filters),
        ];

        return [
            'assets' => $assets,
            'links' => [],
            'center' => self::DEFAULT_CENTER,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function oltAssets(array $filters): array
    {
        if ($this->mappingState($filters) !== 'all') {
            return [];
        }

        return OltAsset::query()
            ->with(['project', 'area'])
            ->tap(fn (Builder $query) => $this->applyCommonFilters($query, $filters))
            ->orderBy('code')
            ->get()
            ->filter(fn (OltAsset $asset): bool => $this->hasValidCoordinate($asset->latitude, $asset->longitude))
            ->map(fn (OltAsset $asset): array => [
                'type' => 'olt',
                'id' => $asset->id,
                'label' => $asset->code,
                'lat' => (float) $asset->latitude,
                'lng' => (float) $asset->longitude,
                'status' => $asset->status,
                'project' => $asset->project?->name,
                'area' => $asset->area?->name,
                'url' => url("/admin/olt-assets/{$asset->id}/edit"),
                'metadata' => [
                    'name' => $asset->name,
                    'location' => $asset->location,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function odcAssets(array $filters): array
    {
        return OdcAsset::query()
            ->with(['project', 'area', 'oltPonPort.oltAsset'])
            ->tap(fn (Builder $query) => $this->applyCommonFilters($query, $filters))
            ->tap(fn (Builder $query) => $this->applyMappingFilter($query, $filters, 'olt_pon_port_id'))
            ->orderBy('box_id')
            ->get()
            ->filter(fn (OdcAsset $asset): bool => $this->hasValidCoordinate($asset->latitude, $asset->longitude))
            ->map(fn (OdcAsset $asset): array => [
                'type' => 'odc',
                'id' => $asset->id,
                'label' => $asset->box_id,
                'lat' => (float) $asset->latitude,
                'lng' => (float) $asset->longitude,
                'status' => $asset->status,
                'project' => $asset->project?->name,
                'area' => $asset->area?->name,
                'url' => url("/admin/odc-assets/{$asset->id}/edit"),
                'metadata' => [
                    'olt_id' => $asset->oltPonPort?->olt_asset_id,
                    'olt' => $asset->oltPonPort?->oltAsset?->code,
                    'pon' => $asset->oltPonPort?->pon_number,
                    'mapping' => $asset->olt_pon_port_id ? 'Sudah Mapping' : 'Belum Mapping',
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function odpAssets(array $filters): array
    {
        return OdpAsset::query()
            ->with(['project', 'area', 'odcAsset.oltPonPort.oltAsset'])
            ->withCount('ports')
            ->withCount([
                'ports as used_ports_count' => fn (Builder $query) => $query->where('status', PortStatus::Used->value),
                'ports as reserved_ports_count' => fn (Builder $query) => $query->where('status', PortStatus::Reserved->value),
                'ports as available_ports_count' => fn (Builder $query) => $query->where('status', PortStatus::Available->value),
            ])
            ->tap(fn (Builder $query) => $this->applyCommonFilters($query, $filters))
            ->tap(fn (Builder $query) => $this->applyMappingFilter($query, $filters, 'odc_asset_id'))
            ->orderBy('box_id')
            ->get()
            ->filter(fn (OdpAsset $asset): bool => $this->hasValidCoordinate($asset->latitude, $asset->longitude))
            ->map(function (OdpAsset $asset): array {
                $capacity = (int) $asset->ports_count;
                $used = (int) $asset->used_ports_count;
                $reserved = (int) $asset->reserved_ports_count;
                $utilization = $capacity > 0 ? round((($used + $reserved) / $capacity) * 100, 2) : 0.0;

                return [
                    'type' => 'odp',
                    'id' => $asset->id,
                    'label' => $asset->box_id,
                    'lat' => (float) $asset->latitude,
                    'lng' => (float) $asset->longitude,
                    'status' => $asset->status,
                    'project' => $asset->project?->name,
                    'area' => $asset->area?->name,
                    'url' => url("/admin/odp-assets/{$asset->id}/edit"),
                    'metadata' => [
                        'odc_id' => $asset->odc_asset_id,
                        'odc' => $asset->odcAsset?->box_id,
                        'olt_id' => $asset->odcAsset?->oltPonPort?->olt_asset_id,
                        'olt' => $asset->odcAsset?->oltPonPort?->oltAsset?->code,
                        'pon' => $asset->odcAsset?->oltPonPort?->pon_number,
                        'core_color' => $asset->core_color?->getLabel() ?? (string) $asset->core_color,
                        'capacity' => $capacity,
                        'used' => $used,
                        'reserved' => $reserved,
                        'available' => (int) $asset->available_ports_count,
                        'utilization' => $utilization,
                        'mapping' => $asset->odc_asset_id ? 'Sudah Mapping' : 'Belum Mapping',
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCommonFilters(Builder $query, array $filters): void
    {
        if (filled($filters['project_id'] ?? null)) {
            $query->where('project_id', $filters['project_id']);
        }

        if (filled($filters['area_id'] ?? null)) {
            $query->where('area_id', $filters['area_id']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyMappingFilter(Builder $query, array $filters, string $column): void
    {
        match ($this->mappingState($filters)) {
            'mapped' => $query->whereNotNull($column),
            'unmapped' => $query->whereNull($column),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function mappingState(array $filters): string
    {
        return in_array($filters['mapping_state'] ?? 'all', ['mapped', 'unmapped'], true)
            ? $filters['mapping_state']
            : 'all';
    }

    private function hasValidCoordinate(mixed $latitude, mixed $longitude): bool
    {
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        return $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180
            && ($latitude !== 0.0 || $longitude !== 0.0);
    }
}

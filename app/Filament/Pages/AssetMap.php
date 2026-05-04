<?php

namespace App\Filament\Pages;

use App\Models\Area;
use App\Models\Project;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use UnitEnum;

class AssetMap extends Page
{
    protected static ?string $title = 'Peta Aset';

    protected static ?string $slug = 'asset-map';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|UnitEnum|null $navigationGroup = 'Aset Resmi';

    protected static ?string $navigationLabel = 'Peta Aset';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.asset-map';

    protected Width|string|null $maxContentWidth = Width::Full;

    public ?string $projectId = null;

    public ?string $areaId = null;

    public ?string $status = null;

    public string $mappingState = 'all';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function updated(string $property): void
    {
        if ($property === 'projectId') {
            $this->areaId = null;
        }

        if (in_array($property, ['projectId', 'areaId', 'status', 'mappingState'], true)) {
            $this->dispatch('asset-map-filters-updated', filters: $this->filters());
        }
    }

    /**
     * @return array{project_id: ?string, area_id: ?string, status: ?string, mapping_state: string}
     */
    public function filters(): array
    {
        return [
            'project_id' => $this->projectId,
            'area_id' => $this->areaId,
            'status' => $this->status,
            'mapping_state' => $this->mappingState,
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public function projectOptions(): array
    {
        return Project::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function areaOptions(): array
    {
        return Area::query()
            ->when($this->projectId, fn ($query) => $query->where('project_id', $this->projectId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        return [
            'active' => 'Aktif',
            'unmapped' => 'Belum Mapping',
            'inactive' => 'Tidak Aktif',
            'maintenance' => 'Maintenance',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function mappingStateOptions(): array
    {
        return [
            'all' => 'Semua Mapping',
            'mapped' => 'Sudah Mapping',
            'unmapped' => 'Belum Mapping',
        ];
    }
}

<?php

namespace App\Filament\Resources\OdpAssetResource\Pages;

use App\Filament\Resources\OdpAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOdpAssets extends ListRecords
{
    protected static string $resource = OdpAssetResource::class;

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'aktif' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'active')),
            'terhubung' => Tab::make('Terhubung')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('odc_asset_id')),
            'belum_mapping' => Tab::make('Belum Mapping')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('odc_asset_id')),
            'maintenance' => Tab::make('Maintenance')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'maintenance')),
            'tidak_aktif' => Tab::make('Tidak Aktif')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'inactive')),
        ];
    }

    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}

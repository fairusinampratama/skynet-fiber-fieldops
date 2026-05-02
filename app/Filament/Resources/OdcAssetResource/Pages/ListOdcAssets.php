<?php

namespace App\Filament\Resources\OdcAssetResource\Pages;

use App\Filament\Resources\OdcAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOdcAssets extends ListRecords
{
    protected static string $resource = OdcAssetResource::class;

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'aktif' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'active')),
            'belum_mapping' => Tab::make('Belum Mapping')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('olt_pon_port_id')),
            'maintenance' => Tab::make('Maintenance')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'maintenance')),
            'tidak_aktif' => Tab::make('Tidak Aktif')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'inactive')),
        ];
    }

    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}

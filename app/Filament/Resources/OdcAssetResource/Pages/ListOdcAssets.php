<?php

namespace App\Filament\Resources\OdcAssetResource\Pages;

use App\Filament\Resources\OdcAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOdcAssets extends ListRecords
{
    protected static string $resource = OdcAssetResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}

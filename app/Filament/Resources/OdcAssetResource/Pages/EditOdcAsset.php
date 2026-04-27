<?php

namespace App\Filament\Resources\OdcAssetResource\Pages;

use App\Filament\Resources\OdcAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOdcAsset extends EditRecord
{
    protected static string $resource = OdcAssetResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}

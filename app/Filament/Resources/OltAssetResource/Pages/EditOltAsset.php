<?php

namespace App\Filament\Resources\OltAssetResource\Pages;

use App\Filament\Resources\OltAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOltAsset extends EditRecord
{
    protected static string $resource = OltAssetResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}

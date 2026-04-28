<?php

namespace App\Filament\Resources\OltPonPortResource\Pages;

use App\Filament\Resources\OltPonPortResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOltPonPort extends EditRecord
{
    protected static string $resource = OltPonPortResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}

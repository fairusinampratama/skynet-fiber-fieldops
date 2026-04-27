<?php

namespace App\Filament\Resources\SubmissionResource\Pages;

use App\Enums\AssetType;
use App\Enums\PortStatus;
use App\Filament\Resources\SubmissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubmission extends CreateRecord
{
    protected static string $resource = SubmissionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['technician_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->ports()->count() > 0) {
            return;
        }

        foreach ([AssetType::Odc, AssetType::Odp] as $assetType) {
            foreach (range(1, 8) as $portNumber) {
                $this->record->ports()->create([
                    'asset_type' => $assetType,
                    'port_number' => $portNumber,
                    'status' => PortStatus::Unknown,
                ]);
            }
        }
    }
}

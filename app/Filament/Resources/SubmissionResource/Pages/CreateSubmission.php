<?php

namespace App\Filament\Resources\SubmissionResource\Pages;

use App\Enums\PortStatus;
use App\Filament\Resources\SubmissionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateSubmission extends CreateRecord
{
    protected static string $resource = SubmissionResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string|Htmlable
    {
        return 'Buat Penugasan Lapangan';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Buat Penugasan');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Penugasan dibuat dan siap dikerjakan teknisi.';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SubmissionResource::mutateFormDataBeforeCreate($data);
    }

    protected function afterCreate(): void
    {
        if ($this->record->ports()->count() > 0) {
            return;
        }

        foreach (range(1, 8) as $portNumber) {
            $this->record->ports()->create([
                'asset_type' => $this->record->asset_type,
                'port_number' => $portNumber,
                'status' => PortStatus::Available,
            ]);
        }
    }
}

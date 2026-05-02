<?php

namespace App\Filament\Resources\SubmissionResource\Pages;

use App\Enums\SubmissionStatus;
use App\Filament\Resources\SubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSubmission extends ViewRecord
{
    protected static string $resource = SubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SubmissionResource::approveAction(),
            SubmissionResource::requestCorrectionAction(),
            SubmissionResource::rejectAction(),
            SubmissionResource::submitAction(),
            Actions\EditAction::make()
                ->label(fn () => auth()->user()->isAdmin()
                    ? 'Edit'
                    : ($this->getRecord()->status === SubmissionStatus::CorrectionNeeded ? 'Perbaiki' : 'Kerjakan'))
                ->visible(fn () => SubmissionResource::canEdit($this->getRecord())),
        ];
    }
}

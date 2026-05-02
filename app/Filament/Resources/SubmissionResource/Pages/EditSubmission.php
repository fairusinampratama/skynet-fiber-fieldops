<?php

namespace App\Filament\Resources\SubmissionResource\Pages;

use App\Enums\SubmissionStatus;
use App\Filament\Resources\SubmissionResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditSubmission extends EditRecord
{
    protected static string $resource = SubmissionResource::class;

    public function getTitle(): string|Htmlable
    {
        if (auth()->user()->isAdmin()) {
            return 'Edit Penugasan Lapangan';
        }

        return $this->getRecord()->status === SubmissionStatus::CorrectionNeeded
            ? 'Perbaiki Penugasan'
            : 'Kerjakan Penugasan';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getSubmitAssignmentAction(),
            Actions\ViewAction::make()
                ->label('Lihat Review')
                ->visible(fn () => auth()->user()->isAdmin()),
            Actions\DeleteAction::make()
                ->visible(fn () => auth()->user()->isAdmin()),
        ];
    }

    protected function getSaveFormAction(): Actions\Action
    {
        return parent::getSaveFormAction()
            ->label(auth()->user()->isAdmin() ? 'Simpan Perubahan' : 'Simpan Data Lapangan');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getSubmitAssignmentAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getSubmitAssignmentAction(): Actions\Action
    {
        return Actions\Action::make('submitAssignment')
            ->label(fn (): string => $this->getRecord()->status === SubmissionStatus::CorrectionNeeded ? 'Kirim Ulang Laporan' : 'Kirim Laporan')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->visible(fn (): bool => ! auth()->user()->isAdmin()
                && in_array($this->getRecord()->status, [SubmissionStatus::Assigned, SubmissionStatus::CorrectionNeeded], true))
            ->requiresConfirmation()
            ->action(function (): void {
                $this->saveAndSubmit();
            });
    }

    public function saveAndSubmit(): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        $record = $this->getRecord()->refresh();

        if (! SubmissionResource::isReadyForSubmission($record)) {
            Notification::make()
                ->danger()
                ->title('Lengkapi data aset sebelum diajukan.')
                ->body(implode(' ', SubmissionResource::missingSubmissionRequirements($record)))
                ->send();

            return;
        }

        $record->forceFill([
            'status' => $record->status === SubmissionStatus::CorrectionNeeded ? SubmissionStatus::Resubmitted : SubmissionStatus::Submitted,
            'submitted_at' => now(),
        ])->save();

        Notification::make()
            ->success()
            ->title('Laporan dikirim ke admin untuk review.')
            ->send();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return auth()->user()->isAdmin()
            ? 'Perubahan penugasan disimpan.'
            : 'Data lapangan disimpan.';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()->isAdmin()) {
            return $data;
        }

        foreach ([
            'project_id',
            'technician_id',
            'area_id',
            'asset_type',
            'parent_odc_asset_id',
            'work_date',
            'planned_latitude',
            'planned_longitude',
            'assignment_notes',
            'review_notes',
        ] as $planningField) {
            unset($data[$planningField]);
        }

        return $data;
    }
}

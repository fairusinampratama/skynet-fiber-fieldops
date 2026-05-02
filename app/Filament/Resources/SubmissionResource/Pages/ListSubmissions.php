<?php

namespace App\Filament\Resources\SubmissionResource\Pages;

use App\Enums\SubmissionStatus;
use App\Filament\Resources\SubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSubmissions extends ListRecords
{
    protected static string $resource = SubmissionResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return auth()->user()->isAdmin() ? 'perlu_review' : 'aktif';
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'perlu_review' => Tab::make('Perlu Review')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    SubmissionStatus::Submitted->value,
                    SubmissionStatus::Resubmitted->value,
                ])),
            'aktif' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    SubmissionStatus::Assigned->value,
                    SubmissionStatus::CorrectionNeeded->value,
                ])),
            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    SubmissionStatus::Approved->value,
                    SubmissionStatus::Rejected->value,
                ])),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Penugasan Baru')
                ->visible(fn () => auth()->user()->isAdmin()),
            Actions\Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(route('exports.submissions'))
                ->openUrlInNewTab()
                ->visible(fn () => auth()->user()->isAdmin()),
        ];
    }
}

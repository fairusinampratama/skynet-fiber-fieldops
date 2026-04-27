<?php

namespace App\Filament\Widgets;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubmissionStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pending submissions', Submission::query()->whereIn('status', [SubmissionStatus::Submitted, SubmissionStatus::Resubmitted])->count())->color('info'),
            Stat::make('Approved submissions', Submission::query()->where('status', SubmissionStatus::Approved)->count())->color('success'),
            Stat::make('Rejected submissions', Submission::query()->where('status', SubmissionStatus::Rejected)->count())->color('danger'),
            Stat::make('Correction needed', Submission::query()->where('status', SubmissionStatus::CorrectionNeeded)->count())->color('warning'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}

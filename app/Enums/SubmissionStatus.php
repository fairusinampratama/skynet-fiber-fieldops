<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubmissionStatus: string implements HasColor, HasLabel
{
    case Assigned = 'assigned';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case CorrectionNeeded = 'correction_needed';
    case Resubmitted = 'resubmitted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Assigned => 'Ditugaskan',
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::CorrectionNeeded => 'Perlu Koreksi',
            self::Resubmitted => 'Diajukan Ulang',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Assigned => 'gray',
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::CorrectionNeeded => 'warning',
            self::Resubmitted => 'primary',
        };
    }
}

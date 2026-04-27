<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PortStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Used = 'used';
    case Reserved = 'reserved';
    case Broken = 'broken';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Used => 'info',
            self::Reserved => 'warning',
            self::Broken => 'danger',
            self::Unknown => 'gray',
        };
    }
}

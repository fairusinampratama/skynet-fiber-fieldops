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

    public static function simpleOptions(): array
    {
        return [
            self::Available->value => self::Available->getLabel(),
            self::Used->value => self::Used->getLabel(),
        ];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Belum Terpakai',
            self::Used => 'Terpakai',
            self::Reserved => 'Dicadangkan',
            self::Broken => 'Rusak',
            self::Unknown => 'Belum Dicek',
        };
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

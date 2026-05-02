<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssetType: string implements HasLabel
{
    case Odc = 'odc';
    case Odp = 'odp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Odc => 'ODC',
            self::Odp => 'ODP',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OdpCoreColor: string implements HasColor, HasLabel
{
    case Biru = 'biru';
    case Orange = 'orange';
    case Hijau = 'hijau';
    case Coklat = 'coklat';
    case AbuAbu = 'abu_abu';
    case Putih = 'putih';
    case Merah = 'merah';
    case Hitam = 'hitam';
    case Kuning = 'kuning';
    case Ungu = 'ungu';
    case Pink = 'pink';
    case Tosca = 'tosca';

    public function getLabel(): string
    {
        return match ($this) {
            self::AbuAbu => 'Abu-abu',
            default => ucfirst($this->value),
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Biru => 'info',
            self::Orange => 'warning',
            self::Hijau => 'success',
            self::Coklat => 'gray',
            self::AbuAbu => 'gray',
            self::Putih => 'secondary',
            self::Merah => 'danger',
            self::Hitam => 'secondary',
            self::Kuning => 'warning',
            self::Ungu => 'primary',
            self::Pink => 'primary',
            self::Tosca => 'info',
        };
    }
}

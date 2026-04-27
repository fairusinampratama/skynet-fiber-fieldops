<?php

namespace App\Enums;

enum OdpCoreColor: string
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

    public function label(): string
    {
        return match ($this) {
            self::AbuAbu => 'Abu-abu',
            default => ucfirst($this->value),
        };
    }
}

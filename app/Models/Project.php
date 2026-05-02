<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'description', 'status', 'start_date', 'target_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'target_date' => 'date'];
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function odcAssets(): HasMany
    {
        return $this->hasMany(OdcAsset::class);
    }

    public function oltAssets(): HasMany
    {
        return $this->hasMany(OltAsset::class);
    }

    public function odpAssets(): HasMany
    {
        return $this->hasMany(OdpAsset::class);
    }
}

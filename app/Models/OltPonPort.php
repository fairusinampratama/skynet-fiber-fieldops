<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OltPonPort extends Model
{
    use HasFactory;

    protected $fillable = ['olt_asset_id', 'pon_number', 'label', 'capacity', 'status'];

    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    public function oltAsset(): BelongsTo
    {
        return $this->belongsTo(OltAsset::class);
    }

    public function odcAssets(): HasMany
    {
        return $this->hasMany(OdcAsset::class);
    }
}

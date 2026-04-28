<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class OltAsset extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'area_id', 'name', 'code', 'location', 'latitude', 'longitude', 'status', 'notes'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:8', 'longitude' => 'decimal:8'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function ponPorts(): HasMany
    {
        return $this->hasMany(OltPonPort::class);
    }

    public function odcAssets(): HasManyThrough
    {
        return $this->hasManyThrough(OdcAsset::class, OltPonPort::class);
    }
}

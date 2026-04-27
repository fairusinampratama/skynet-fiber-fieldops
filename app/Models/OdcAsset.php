<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdcAsset extends Model
{
    protected $fillable = ['project_id', 'area_id', 'box_id', 'photo_path', 'latitude', 'longitude', 'source_submission_id', 'approved_by', 'approved_at', 'status'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:8', 'longitude' => 'decimal:8', 'approved_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function ports(): HasMany
    {
        return $this->hasMany(OdcPort::class);
    }
}

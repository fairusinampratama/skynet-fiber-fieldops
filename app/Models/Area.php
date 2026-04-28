<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'name', 'code', 'description'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function odpAssets(): HasMany
    {
        return $this->hasMany(OdpAsset::class);
    }

    public function oltAssets(): HasMany
    {
        return $this->hasMany(OltAsset::class);
    }
}

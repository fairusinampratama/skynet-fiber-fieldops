<?php

namespace App\Models;

use App\Enums\OdpCoreColor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdpAsset extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'area_id', 'odc_asset_id', 'box_id', 'photo_path', 'latitude', 'longitude', 'core_color', 'source_submission_id', 'approved_by', 'approved_at', 'status'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:8', 'longitude' => 'decimal:8', 'core_color' => OdpCoreColor::class, 'approved_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function odcAsset(): BelongsTo
    {
        return $this->belongsTo(OdcAsset::class);
    }

    public function ports(): HasMany
    {
        return $this->hasMany(OdpPort::class);
    }

    public function sourceSubmission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'source_submission_id');
    }
}

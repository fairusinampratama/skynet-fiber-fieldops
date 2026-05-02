<?php

namespace App\Models;

use App\Enums\AssetType;
use App\Enums\OdpCoreColor;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'technician_id',
        'area_id',
        'planned_latitude',
        'planned_longitude',
        'work_date',
        'asset_type',
        'box_id',
        'photo_path',
        'latitude',
        'longitude',
        'core_color',
        'parent_odc_asset_id',
        'notes',
        'status',
        'review_notes',
        'reviewed_by',
        'assigned_by',
        'assigned_at',
        'assignment_notes',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'asset_type' => AssetType::class,
            'planned_latitude' => 'decimal:8',
            'planned_longitude' => 'decimal:8',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'core_color' => OdpCoreColor::class,
            'status' => SubmissionStatus::class,
            'assigned_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('technician_id', $user->id);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function parentOdcAsset(): BelongsTo
    {
        return $this->belongsTo(OdcAsset::class, 'parent_odc_asset_id');
    }

    public function ports(): HasMany
    {
        return $this->hasMany(SubmissionPort::class);
    }
}

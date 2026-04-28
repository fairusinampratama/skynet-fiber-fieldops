<?php

namespace App\Models;

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
        'team_id',
        'area_id',
        'work_date',
        'odc_box_id',
        'odc_photo_path',
        'odc_latitude',
        'odc_longitude',
        'odp_box_id',
        'odp_photo_path',
        'odp_latitude',
        'odp_longitude',
        'odp_core_color',
        'notes',
        'status',
        'review_notes',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'odc_latitude' => 'decimal:8',
            'odc_longitude' => 'decimal:8',
            'odp_latitude' => 'decimal:8',
            'odp_longitude' => 'decimal:8',
            'odp_core_color' => OdpCoreColor::class,
            'status' => SubmissionStatus::class,
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function ports(): HasMany
    {
        return $this->hasMany(SubmissionPort::class);
    }
}

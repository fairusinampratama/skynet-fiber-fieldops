<?php

namespace App\Models;

use App\Enums\AssetType;
use App\Enums\PortStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionPort extends Model
{
    public $timestamps = false;

    protected $fillable = ['submission_id', 'asset_type', 'port_number', 'status'];

    protected function casts(): array
    {
        return ['asset_type' => AssetType::class, 'status' => PortStatus::class];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
